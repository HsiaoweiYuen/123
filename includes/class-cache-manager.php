<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Intelligent Cache Manager for V2RaySocks Traffic Analysis
 * 
 * Provides advanced caching strategies for large datasets:
 * - Layered caching (hot/warm/cold data)
 * - Async cache updates and background processing
 * - Smart cache invalidation and refresh
 * - Memory-efficient cache operations
 * - Cache analytics and monitoring
 * 
 * @version 1.0.0
 * @author V2RaySocks Traffic Optimization Team
 */
class V2RaySocks_CacheManager
{
    private $redisAvailable;
    private $cachePrefix;
    private $defaultTTL;
    private $layeredTTLs;
    private $asyncQueue;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->redisAvailable = function_exists('v2raysocks_traffic_redisOperate');
        $this->cachePrefix = 'v2rs_optimized_';
        $this->defaultTTL = 300; // 5 minutes
        
        // Layered TTL configuration
        $this->layeredTTLs = [
            'hot' => 60,      // 1 minute - frequently accessed data
            'warm' => 300,    // 5 minutes - regularly accessed data  
            'cold' => 1800,   // 30 minutes - infrequently accessed data
            'frozen' => 7200, // 2 hours - rarely accessed historical data
            'permanent' => 86400 // 24 hours - static configuration data
        ];
        
        $this->asyncQueue = [];
    }
    
    /**
     * Get data with intelligent cache management
     * 
     * @param string $key Cache key
     * @param callable $dataProvider Function to get fresh data
     * @param string $cacheLayer Cache layer (hot/warm/cold/frozen/permanent)
     * @param array $metadata Additional cache metadata
     * @return mixed Cached or fresh data
     */
    public function getWithCache($key, $dataProvider, $cacheLayer = 'warm', $metadata = [])
    {
        try {
            $fullKey = $this->buildCacheKey($key, $cacheLayer);
            
            // Try to get from cache first
            $cachedData = $this->getCachedData($fullKey);
            
            if ($cachedData !== null) {
                $this->logActivity("Cache HIT for key: $key (layer: $cacheLayer)");
                $this->updateCacheAccessStats($fullKey, 'hit');
                
                // Schedule async refresh for warm/cold data if approaching expiry
                if (in_array($cacheLayer, ['warm', 'cold'])) {
                    $this->scheduleAsyncRefresh($fullKey, $dataProvider, $cacheLayer, $metadata);
                }
                
                return $cachedData;
            }
            
            $this->logActivity("Cache MISS for key: $key (layer: $cacheLayer)");
            $this->updateCacheAccessStats($fullKey, 'miss');
            
            // Get fresh data
            $freshData = call_user_func($dataProvider);
            
            if ($freshData !== null) {
                // Cache the fresh data
                $this->setCachedData($fullKey, $freshData, $cacheLayer, $metadata);
            }
            
            return $freshData;
            
        } catch (Exception $e) {
            $this->logActivity("Cache operation failed for key $key: " . $e->getMessage());
            // Fallback to direct data provider call
            return call_user_func($dataProvider);
        }
    }
    
    /**
     * Set data in cache with intelligent layering
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param string $cacheLayer Cache layer
     * @param array $metadata Cache metadata
     * @return bool Success status
     */
    public function set($key, $data, $cacheLayer = 'warm', $metadata = [])
    {
        try {
            $fullKey = $this->buildCacheKey($key, $cacheLayer);
            return $this->setCachedData($fullKey, $data, $cacheLayer, $metadata);
        } catch (Exception $e) {
            $this->logActivity("Cache set failed for key $key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get data from cache
     * 
     * @param string $key Cache key
     * @param string $cacheLayer Cache layer
     * @return mixed Cached data or null
     */
    public function get($key, $cacheLayer = 'warm')
    {
        try {
            $fullKey = $this->buildCacheKey($key, $cacheLayer);
            return $this->getCachedData($fullKey);
        } catch (Exception $e) {
            $this->logActivity("Cache get failed for key $key: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Invalidate cache entries with smart patterns
     * 
     * @param string $pattern Cache key pattern
     * @param array $layers Specific cache layers to invalidate
     * @return int Number of keys invalidated
     */
    public function invalidate($pattern, $layers = ['hot', 'warm', 'cold'])
    {
        $invalidatedCount = 0;
        
        try {
            foreach ($layers as $layer) {
                $fullPattern = $this->buildCacheKey($pattern, $layer);
                
                if ($this->redisAvailable) {
                    $result = v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => $fullPattern]);
                    if ($result) {
                        $invalidatedCount++;
                        $this->logActivity("Invalidated cache pattern: $fullPattern");
                    }
                }
            }
            
            $this->logActivity("Cache invalidation completed. Patterns cleared: $invalidatedCount");
            
        } catch (Exception $e) {
            $this->logActivity("Cache invalidation failed for pattern $pattern: " . $e->getMessage());
        }
        
        return $invalidatedCount;
    }
    
    /**
     * Batch cache operations for efficiency
     * 
     * @param array $operations Array of cache operations
     * @return array Results of operations
     */
    public function batch($operations)
    {
        $results = [];
        
        try {
            $this->logActivity("Starting batch cache operations: " . count($operations) . " operations");
            
            foreach ($operations as $index => $operation) {
                $type = $operation['type'];
                $key = $operation['key'];
                $layer = $operation['layer'] ?? 'warm';
                
                switch ($type) {
                    case 'get':
                        $results[$index] = $this->get($key, $layer);
                        break;
                        
                    case 'set':
                        $data = $operation['data'];
                        $metadata = $operation['metadata'] ?? [];
                        $results[$index] = $this->set($key, $data, $layer, $metadata);
                        break;
                        
                    case 'invalidate':
                        $layers = $operation['layers'] ?? ['hot', 'warm', 'cold'];
                        $results[$index] = $this->invalidate($key, $layers);
                        break;
                        
                    default:
                        $results[$index] = false;
                        $this->logActivity("Unknown batch operation type: $type");
                }
            }
            
            $this->logActivity("Batch cache operations completed");
            
        } catch (Exception $e) {
            $this->logActivity("Batch cache operations failed: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Background cache warming for large datasets
     * 
     * @param array $warmingTasks Array of warming tasks
     * @return bool Success status
     */
    public function warmCache($warmingTasks)
    {
        try {
            $this->logActivity("Starting cache warming for " . count($warmingTasks) . " tasks");
            
            foreach ($warmingTasks as $task) {
                $key = $task['key'];
                $dataProvider = $task['data_provider'];
                $layer = $task['layer'] ?? 'warm';
                $metadata = $task['metadata'] ?? [];
                
                // Check if cache already exists
                if ($this->get($key, $layer) === null) {
                    // Get fresh data and cache it
                    $data = call_user_func($dataProvider);
                    if ($data !== null) {
                        $this->set($key, $data, $layer, $metadata);
                        $this->logActivity("Cache warmed for key: $key");
                    }
                }
            }
            
            $this->logActivity("Cache warming completed");
            return true;
            
        } catch (Exception $e) {
            $this->logActivity("Cache warming failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache statistics and health information
     * 
     * @return array Cache statistics
     */
    public function getStats()
    {
        $stats = [
            'redis_available' => $this->redisAvailable,
            'cache_prefix' => $this->cachePrefix,
            'layer_ttls' => $this->layeredTTLs,
            'async_queue_size' => count($this->asyncQueue)
        ];
        
        if ($this->redisAvailable) {
            try {
                // Get basic Redis info if available
                $redisInfo = v2raysocks_traffic_redisOperate('info', []);
                if ($redisInfo) {
                    $stats['redis_info'] = $redisInfo;
                }
                
                // Get cache access statistics
                $accessStats = $this->getCacheAccessStats();
                $stats['access_stats'] = $accessStats;
                
            } catch (Exception $e) {
                $stats['redis_error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean up expired cache entries and optimize storage
     * 
     * @return array Cleanup results
     */
    public function cleanup()
    {
        $results = [
            'expired_keys' => 0,
            'freed_memory' => 0,
            'optimization_applied' => false
        ];
        
        try {
            $this->logActivity("Starting cache cleanup and optimization");
            
            if ($this->redisAvailable) {
                // Clean up expired keys by pattern
                foreach ($this->layeredTTLs as $layer => $ttl) {
                    $pattern = $this->buildCacheKey('*', $layer);
                    $cleanupResult = v2raysocks_traffic_redisOperate('cleanup_expired', ['pattern' => $pattern]);
                    
                    if ($cleanupResult) {
                        $results['expired_keys'] += $cleanupResult;
                    }
                }
                
                $results['optimization_applied'] = true;
            }
            
            // Clear async queue
            $this->asyncQueue = [];
            
            $this->logActivity("Cache cleanup completed. Expired keys: " . $results['expired_keys']);
            
        } catch (Exception $e) {
            $this->logActivity("Cache cleanup failed: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Auto-refresh cache for data approaching expiry
     * 
     * @param string $key Cache key
     * @param mixed $newData New data to cache
     * @param string $cacheLayer Cache layer
     * @return bool Success status
     */
    public function autoRefresh($key, $newData, $cacheLayer = 'warm')
    {
        try {
            $fullKey = $this->buildCacheKey($key, $cacheLayer);
            
            // Check if the key exists and is approaching expiry
            $currentData = $this->getCachedData($fullKey);
            if ($currentData !== null) {
                $ttl = $this->getCacheTTL($fullKey);
                $layerTTL = $this->layeredTTLs[$cacheLayer];
                
                // Refresh if TTL is less than 25% of original
                if ($ttl < ($layerTTL * 0.25)) {
                    $this->setCachedData($fullKey, $newData, $cacheLayer);
                    $this->logActivity("Auto-refreshed cache for key: $key");
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logActivity("Auto-refresh failed for key $key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Schedule async cache refresh
     */
    private function scheduleAsyncRefresh($key, $dataProvider, $cacheLayer, $metadata)
    {
        $this->asyncQueue[] = [
            'key' => $key,
            'data_provider' => $dataProvider,
            'cache_layer' => $cacheLayer,
            'metadata' => $metadata,
            'scheduled_at' => time()
        ];
        
        // Process queue if it gets too large
        if (count($this->asyncQueue) > 10) {
            $this->processAsyncQueue();
        }
    }
    
    /**
     * Process async cache refresh queue
     */
    private function processAsyncQueue()
    {
        $processed = 0;
        
        foreach ($this->asyncQueue as $index => $task) {
            try {
                // Check if refresh is needed
                $currentData = $this->getCachedData($task['key']);
                if ($currentData === null) {
                    // Data expired, refresh it
                    $freshData = call_user_func($task['data_provider']);
                    if ($freshData !== null) {
                        $this->setCachedData($task['key'], $freshData, $task['cache_layer'], $task['metadata']);
                        $processed++;
                    }
                }
                
                unset($this->asyncQueue[$index]);
                
            } catch (Exception $e) {
                $this->logActivity("Async refresh failed: " . $e->getMessage());
                unset($this->asyncQueue[$index]);
            }
        }
        
        // Reindex array
        $this->asyncQueue = array_values($this->asyncQueue);
        
        if ($processed > 0) {
            $this->logActivity("Processed $processed async cache refreshes");
        }
    }
    
    /**
     * Build full cache key with layer prefix
     */
    private function buildCacheKey($key, $layer)
    {
        return $this->cachePrefix . $layer . '_' . $key;
    }
    
    /**
     * Get data from cache
     */
    private function getCachedData($fullKey)
    {
        if (!$this->redisAvailable) {
            return null;
        }
        
        try {
            $cached = v2raysocks_traffic_redisOperate('get', ['key' => $fullKey]);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (isset($decoded['data'])) {
                    return $decoded['data'];
                }
            }
        } catch (Exception $e) {
            $this->logActivity("Cache read failed for key $fullKey: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Set data in cache
     */
    private function setCachedData($fullKey, $data, $cacheLayer, $metadata = [])
    {
        if (!$this->redisAvailable) {
            return false;
        }
        
        try {
            $cacheData = [
                'data' => $data,
                'metadata' => array_merge($metadata, [
                    'layer' => $cacheLayer,
                    'created_at' => time(),
                    'version' => '1.0.0'
                ])
            ];
            
            $ttl = $this->layeredTTLs[$cacheLayer] ?? $this->defaultTTL;
            
            return v2raysocks_traffic_redisOperate('set', [
                'key' => $fullKey,
                'value' => json_encode($cacheData),
                'ttl' => $ttl
            ]);
            
        } catch (Exception $e) {
            $this->logActivity("Cache write failed for key $fullKey: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache TTL
     */
    private function getCacheTTL($fullKey)
    {
        if (!$this->redisAvailable) {
            return 0;
        }
        
        try {
            return v2raysocks_traffic_redisOperate('ttl', ['key' => $fullKey]);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Update cache access statistics
     */
    private function updateCacheAccessStats($key, $type)
    {
        if (!$this->redisAvailable) {
            return;
        }
        
        try {
            $statsKey = $this->cachePrefix . 'stats_' . date('Y-m-d');
            $currentStats = v2raysocks_traffic_redisOperate('get', ['key' => $statsKey]);
            
            $stats = $currentStats ? json_decode($currentStats, true) : [];
            
            if (!isset($stats[$type])) {
                $stats[$type] = 0;
            }
            $stats[$type]++;
            
            $stats['last_updated'] = time();
            
            v2raysocks_traffic_redisOperate('set', [
                'key' => $statsKey,
                'value' => json_encode($stats),
                'ttl' => 86400 // 24 hours
            ]);
            
        } catch (Exception $e) {
            // Silent fail for stats
        }
    }
    
    /**
     * Get cache access statistics
     */
    private function getCacheAccessStats()
    {
        if (!$this->redisAvailable) {
            return [];
        }
        
        try {
            $statsKey = $this->cachePrefix . 'stats_' . date('Y-m-d');
            $currentStats = v2raysocks_traffic_redisOperate('get', ['key' => $statsKey]);
            
            if ($currentStats) {
                $stats = json_decode($currentStats, true);
                
                // Calculate hit ratio
                $hits = $stats['hit'] ?? 0;
                $misses = $stats['miss'] ?? 0;
                $total = $hits + $misses;
                
                $stats['hit_ratio'] = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
                
                return $stats;
            }
            
        } catch (Exception $e) {
            // Silent fail for stats
        }
        
        return [];
    }
    
    /**
     * Log activity for monitoring
     */
    private function logActivity($message)
    {
        if (function_exists('logActivity')) {
            logActivity("V2RaySocks Cache Manager: $message", 0);
        }
    }
}