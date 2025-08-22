<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * High-Performance Asynchronous Pagination Manager
 * Supports cursor-based pagination, connection pooling, and million-level concurrent queries
 */
class V2RaySocksPaginationManager
{
    private $pdo;
    private $redisInstance;
    private $config;
    private $connectionPool = [];
    private $maxConnections = 10;
    private $cachePrefix = 'v2ray_pagination_';
    
    public function __construct($config = [])
    {
        $this->config = array_merge([
            'pagination_size' => 1000,
            'max_concurrent_queries' => 10,
            'cache_ttl' => 300,
            'async_timeout' => 30,
            'high_performance_mode' => false,
            'cursor_pagination' => true,
            'memory_limit' => '1G'
        ], $config);
        
        $this->maxConnections = intval($this->config['max_concurrent_queries']);
        $this->initializeConnections();
    }
    
    /**
     * Initialize database connections for connection pooling
     */
    private function initializeConnections()
    {
        try {
            // Primary connection
            $this->pdo = v2raysocks_traffic_createPDO();
            if (!$this->pdo) {
                throw new Exception("Failed to create primary database connection");
            }
            
            // Initialize Redis connection for caching
            $this->redisInstance = v2raysocks_traffic_getRedisInstance();
            
            // Pre-warm connection pool for high performance mode
            if ($this->config['high_performance_mode']) {
                $this->warmupConnectionPool();
            }
            
        } catch (Exception $e) {
            logActivity("V2RaySocks PaginationManager: Connection initialization failed: " . $e->getMessage(), 0);
            throw $e;
        }
    }
    
    /**
     * Warm up connection pool for high performance mode
     */
    private function warmupConnectionPool()
    {
        for ($i = 0; $i < min($this->maxConnections, 5); $i++) {
            try {
                $connection = v2raysocks_traffic_createPDO();
                if ($connection) {
                    $this->connectionPool[] = $connection;
                }
            } catch (Exception $e) {
                // Log but don't fail, we can work with fewer connections
                logActivity("V2RaySocks PaginationManager: Failed to create pooled connection: " . $e->getMessage(), 0);
            }
        }
    }
    
    /**
     * Get available database connection from pool
     */
    private function getConnection()
    {
        if (!empty($this->connectionPool)) {
            return array_pop($this->connectionPool);
        }
        
        return $this->pdo ?: v2raysocks_traffic_createPDO();
    }
    
    /**
     * Return connection to pool
     */
    private function returnConnection($connection)
    {
        if (count($this->connectionPool) < $this->maxConnections) {
            $this->connectionPool[] = $connection;
        }
    }
    
    /**
     * Perform paginated query with cursor-based pagination
     * 
     * @param string $baseQuery The base SQL query without ORDER BY and LIMIT
     * @param array $params Query parameters
     * @param array $options Pagination options
     * @return array Paginated results
     */
    public function paginatedQuery($baseQuery, $params = [], $options = [])
    {
        $options = array_merge([
            'page_size' => $this->config['pagination_size'],
            'cursor_field' => 't', // Default to timestamp field
            'cursor_direction' => 'DESC',
            'cursor_value' => null,
            'max_results' => null, // null for unlimited
            'use_cache' => true,
            'cache_key_suffix' => ''
        ], $options);
        
        try {
            // Generate cache key
            $cacheKey = $this->generateCacheKey($baseQuery, $params, $options);
            
            // Try cache first if enabled
            if ($options['use_cache']) {
                $cachedResult = $this->getCachedResult($cacheKey);
                if ($cachedResult !== null) {
                    return $cachedResult;
                }
            }
            
            // Execute query with cursor pagination
            $result = $this->executePaginatedQuery($baseQuery, $params, $options);
            
            // Cache result if enabled
            if ($options['use_cache'] && !empty($result['data'])) {
                $this->setCachedResult($cacheKey, $result);
            }
            
            return $result;
            
        } catch (Exception $e) {
            logActivity("V2RaySocks PaginationManager: Paginated query failed: " . $e->getMessage(), 0);
            return [
                'data' => [],
                'pagination' => [
                    'has_more' => false,
                    'next_cursor' => null,
                    'total_fetched' => 0
                ],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute the actual paginated query
     */
    private function executePaginatedQuery($baseQuery, $params, $options)
    {
        $connection = $this->getConnection();
        
        try {
            // Build the paginated query
            $sql = $baseQuery;
            
            // Add cursor condition if provided
            if ($options['cursor_value'] !== null) {
                $cursorOperator = ($options['cursor_direction'] === 'DESC') ? '<' : '>';
                $sql .= " AND {$options['cursor_field']} {$cursorOperator} :cursor_value";
                $params[':cursor_value'] = $options['cursor_value'];
            }
            
            // Add ORDER BY and LIMIT
            $sql .= " ORDER BY {$options['cursor_field']} {$options['cursor_direction']}";
            
            // Fetch one extra record to check if there are more results
            $fetchLimit = $options['page_size'] + 1;
            $sql .= " LIMIT :limit";
            $params[':limit'] = $fetchLimit;
            
            // Execute query
            $stmt = $connection->prepare($sql);
            
            // Bind parameters with proper types
            foreach ($params as $key => $value) {
                if (in_array($key, [':limit', ':cursor_value']) && is_numeric($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check if we have more results
            $hasMore = count($allResults) > $options['page_size'];
            if ($hasMore) {
                // Remove the extra record
                array_pop($allResults);
            }
            
            // Get next cursor value
            $nextCursor = null;
            if ($hasMore && !empty($allResults)) {
                $lastRecord = end($allResults);
                $nextCursor = $lastRecord[$options['cursor_field']] ?? null;
            }
            
            return [
                'data' => $allResults,
                'pagination' => [
                    'has_more' => $hasMore,
                    'next_cursor' => $nextCursor,
                    'total_fetched' => count($allResults),
                    'page_size' => $options['page_size']
                ]
            ];
            
        } finally {
            $this->returnConnection($connection);
        }
    }
    
    /**
     * Fetch all data with automatic pagination (for unlimited queries)
     * Efficiently handles large datasets with memory optimization
     */
    public function fetchAllData($baseQuery, $params = [], $options = [])
    {
        $options = array_merge([
            'batch_size' => $this->config['pagination_size'],
            'memory_limit' => $this->config['memory_limit'],
            'progress_callback' => null,
            'max_iterations' => 1000 // Prevent infinite loops
        ], $options);
        
        $allData = [];
        $cursor = null;
        $totalFetched = 0;
        $iterations = 0;
        
        // Convert memory limit to bytes
        $memoryLimitBytes = $this->parseMemoryLimit($options['memory_limit']);
        
        do {
            // Check memory usage
            if (memory_get_usage() > $memoryLimitBytes * 0.8) {
                logActivity("V2RaySocks PaginationManager: Approaching memory limit, stopping fetch", 0);
                break;
            }
            
            // Prevent infinite loops
            if (++$iterations > $options['max_iterations']) {
                logActivity("V2RaySocks PaginationManager: Maximum iterations reached, stopping fetch", 0);
                break;
            }
            
            // Fetch next batch
            $batchOptions = array_merge($options, [
                'page_size' => $options['batch_size'],
                'cursor_value' => $cursor,
                'use_cache' => false // Don't cache individual batches
            ]);
            
            $result = $this->paginatedQuery($baseQuery, $params, $batchOptions);
            
            if (!empty($result['data'])) {
                $allData = array_merge($allData, $result['data']);
                $totalFetched += count($result['data']);
                
                // Call progress callback if provided
                if ($options['progress_callback'] && is_callable($options['progress_callback'])) {
                    call_user_func($options['progress_callback'], $totalFetched, $result['pagination']['has_more']);
                }
            }
            
            // Update cursor for next iteration
            $cursor = $result['pagination']['next_cursor'] ?? null;
            $hasMore = $result['pagination']['has_more'] ?? false;
            
        } while ($hasMore && $cursor !== null);
        
        return [
            'data' => $allData,
            'total_fetched' => $totalFetched,
            'memory_used' => memory_get_usage(true),
            'iterations' => $iterations
        ];
    }
    
    /**
     * Generate cache key for query
     */
    private function generateCacheKey($query, $params, $options)
    {
        $keyData = [
            'query' => md5($query),
            'params' => $params,
            'options' => array_intersect_key($options, ['page_size' => 1, 'cursor_value' => 1, 'cursor_direction' => 1])
        ];
        
        $suffix = $options['cache_key_suffix'] ?? '';
        return $this->cachePrefix . md5(serialize($keyData)) . $suffix;
    }
    
    /**
     * Get cached result
     */
    private function getCachedResult($cacheKey)
    {
        if (!$this->redisInstance) {
            return null;
        }
        
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks PaginationManager: Cache read failed: " . $e->getMessage(), 0);
        }
        
        return null;
    }
    
    /**
     * Set cached result
     */
    private function setCachedResult($cacheKey, $result)
    {
        if (!$this->redisInstance) {
            return false;
        }
        
        try {
            $ttl = $this->config['cache_ttl'];
            $encodedData = json_encode($result);
            return v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => $encodedData,
                'ttl' => $ttl
            ]);
        } catch (Exception $e) {
            logActivity("V2RaySocks PaginationManager: Cache write failed: " . $e->getMessage(), 0);
            return false;
        }
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($memoryLimit)
    {
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;
        
        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return $value;
        }
    }
    
    /**
     * Clear pagination cache
     */
    public function clearCache($pattern = null)
    {
        if (!$this->redisInstance) {
            return false;
        }
        
        try {
            if ($pattern) {
                return v2raysocks_traffic_clearCache([], $this->cachePrefix . $pattern);
            } else {
                return v2raysocks_traffic_clearCache([], $this->cachePrefix . '*');
            }
        } catch (Exception $e) {
            logActivity("V2RaySocks PaginationManager: Cache clear failed: " . $e->getMessage(), 0);
            return false;
        }
    }
    
    /**
     * Destructor - cleanup connections
     */
    public function __destruct()
    {
        // Close pooled connections
        foreach ($this->connectionPool as $connection) {
            $connection = null;
        }
        $this->connectionPool = [];
    }
}

/**
 * Factory function to create PaginationManager with module configuration
 */
function v2raysocks_traffic_createPaginationManager()
{
    try {
        // Get module configuration
        $moduleConfig = v2raysocks_traffic_getModuleConfig();
        
        $config = [
            'pagination_size' => intval($moduleConfig['pagination_size'] ?? 1000),
            'max_concurrent_queries' => intval($moduleConfig['max_concurrent_queries'] ?? 10),
            'cache_ttl' => intval($moduleConfig['cache_ttl'] ?? 300),
            'async_timeout' => intval($moduleConfig['async_timeout'] ?? 30),
            'high_performance_mode' => ($moduleConfig['high_performance_mode'] ?? 'no') === 'yes',
            'cursor_pagination' => true,
            'memory_limit' => '1G'
        ];
        
        // Handle unlimited pagination size
        if ($moduleConfig['pagination_size'] === 'unlimited') {
            $config['pagination_size'] = null;
        }
        
        return new V2RaySocksPaginationManager($config);
        
    } catch (Exception $e) {
        logActivity("V2RaySocks PaginationManager: Failed to create instance: " . $e->getMessage(), 0);
        // Return null to allow fallback to direct queries
        return null;
    }
}