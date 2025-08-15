<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function v2raysocks_traffic_getRedisInstance()
{
    static $redisInstance = null;
    static $isConnected = false;
    static $lastFailTime = 0;
    static $connectionRetryDelay = 30; // Retry connection every 30 seconds after failure

    if (!extension_loaded('redis')) {
        // Log this condition only once per session
        if ($lastFailTime === 0) {
            logActivity("V2RaySocks Traffic Monitor: Redis extension not loaded", 0);
            $lastFailTime = time();
        }
        return false;
    }

    // If we have a connected instance, test it with ping
    if ($redisInstance instanceof Redis && $isConnected) {
        try {
            $redisInstance->ping();
            return $redisInstance;
        } catch (\Throwable $e) {
            // Connection is stale, reset and reconnect
            logActivity("V2RaySocks Traffic Monitor: Redis connection lost, attempting reconnect: " . $e->getMessage(), 0);
            $isConnected = false;
            $redisInstance = null;
        }
    }

    // If recently failed, don't retry immediately
    if ($lastFailTime > 0 && (time() - $lastFailTime) < $connectionRetryDelay) {
        return false;
    }

    try {
        $redis = new Redis();

        // Try default connection first
        $connected = false;
        try {
            if ($redis->connect('127.0.0.1', 6379, 2.0)) {
                // Test the connection
                $redis->ping();
                $connected = true;
                logActivity("V2RaySocks Traffic Monitor: Connected to Redis on default localhost:6379", 0);
            }
        } catch (\Throwable $e) {
            // Default connection failed, continue trying from database config
            logActivity("V2RaySocks Traffic Monitor: Default Redis connection failed: " . $e->getMessage(), 0);
        }

        if (!$connected) {
            try {
                $settings = Capsule::table('tbladdonmodules')
                    ->where('module', 'v2raysocks_traffic')
                    ->whereIn('setting', ['redis_ip', 'redis_port', 'redis_password'])
                    ->pluck('value', 'setting');

                $redisIP = $settings['redis_ip'] ?? null;
                $redisPort = $settings['redis_port'] ?? null;
                $redisPassword = $settings['redis_password'] ?? null;

                if (!$redisIP || !$redisPort) {
                    logActivity("V2RaySocks Traffic Monitor: Redis configuration incomplete - missing IP or port", 0);
                    $lastFailTime = time();
                    return false;
                }

                if (!$redis->connect($redisIP, (int)$redisPort, 2.0)) {
                    logActivity("V2RaySocks Traffic Monitor: Failed to connect to Redis at {$redisIP}:{$redisPort}", 0);
                    $lastFailTime = time();
                    return false;
                }

                if (!empty($redisPassword)) {
                    if (!$redis->auth($redisPassword)) {
                        logActivity("V2RaySocks Traffic Monitor: Redis authentication failed", 0);
                        $lastFailTime = time();
                        return false;
                    }
                }

                // Test the connection
                $redis->ping();
                $connected = true;
                logActivity("V2RaySocks Traffic Monitor: Connected to Redis on {$redisIP}:{$redisPort}", 0);
            } catch (\Throwable $e) {
                logActivity("V2RaySocks Traffic Monitor: Custom Redis connection failed: " . $e->getMessage(), 0);
                $lastFailTime = time();
                return false;
            }
        }

        if (!$connected) {
            $lastFailTime = time();
            return false;
        }

        $redisInstance = $redis;
        $isConnected = true;
        $lastFailTime = 0; // Reset fail time on successful connection
        return $redisInstance;
    } catch (\Throwable $e) {
        // Catch all unexpected fatal errors
        logActivity("V2RaySocks Traffic Monitor: Unexpected Redis error: " . $e->getMessage(), 0);
        $lastFailTime = time();
        return false;
    }
}

function v2raysocks_traffic_redisOperate($act, $data)
{
    static $cacheStats = ['hits' => 0, 'misses' => 0, 'sets' => 0, 'errors' => 0];
    static $cacheVersion = 'v1'; // Version for cache invalidation
    
    $redis = v2raysocks_traffic_getRedisInstance();
    if (!$redis instanceof Redis) {
        $cacheStats['errors']++;
        return false;
    }

    // Default TTL values based on data type
    $defaultTTL = v2raysocks_traffic_getDefaultTTL($data['key'] ?? '', $data['context'] ?? []);

    try {
        $fullKey = 'v2raysocks_traffic:' . $cacheVersion . ':' . ($data['key'] ?? '');
        
        switch (strtolower($act)) {
            case 'set':
                if (!isset($data['key']) || !isset($data['value'])) {
                    logActivity("V2RaySocks Traffic Monitor: Redis SET operation missing key or value", 0);
                    return false;
                }
                $ttl = isset($data['ttl']) && is_numeric($data['ttl']) && (int)$data['ttl'] > 0
                    ? (int)$data['ttl']
                    : $defaultTTL;
                
                $result = $redis->setex($fullKey, $ttl, $data['value']);
                if ($result) {
                    $cacheStats['sets']++;
                } else {
                    $cacheStats['errors']++;
                    logActivity("V2RaySocks Traffic Monitor: Redis SET failed for key: " . $data['key'], 0);
                }
                return $result;

            case 'get':
                if (!isset($data['key'])) {
                    logActivity("V2RaySocks Traffic Monitor: Redis GET operation missing key", 0);
                    return false;
                }
                $result = $redis->get($fullKey);
                if ($result !== false) {
                    $cacheStats['hits']++;
                } else {
                    $cacheStats['misses']++;
                }
                return $result;

            case 'del':
                if (!isset($data['key'])) {
                    logActivity("V2RaySocks Traffic Monitor: Redis DEL operation missing key", 0);
                    return false;
                }
                return $redis->del($fullKey);

            case 'ping':
                return $redis->ping();

            case 'exists':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->exists($fullKey);

            case 'stats':
                // Return cache statistics
                return $cacheStats;

            case 'clear_pattern':
                // Clear keys matching a pattern
                if (!isset($data['pattern'])) {
                    return false;
                }
                $pattern = 'v2raysocks_traffic:' . $cacheVersion . ':' . $data['pattern'];
                $keys = $redis->keys($pattern);
                if (!empty($keys)) {
                    return $redis->del($keys);
                }
                return true;

            case 'pipeline_set':
                // Batch set operations using Redis Pipeline to reduce memory fragmentation
                if (!isset($data['operations']) || !is_array($data['operations'])) {
                    return false;
                }
                
                $pipe = $redis->pipeline();
                $successCount = 0;
                
                foreach ($data['operations'] as $operation) {
                    if (!isset($operation['key']) || !isset($operation['value'])) {
                        continue;
                    }
                    
                    $fullKey = 'v2raysocks_traffic:' . $cacheVersion . ':' . $operation['key'];
                    $ttl = isset($operation['ttl']) && is_numeric($operation['ttl']) && (int)$operation['ttl'] > 0
                        ? (int)$operation['ttl']
                        : v2raysocks_traffic_getDefaultTTL($operation['key'], $operation['context'] ?? []);
                    
                    $pipe->setex($fullKey, $ttl, $operation['value']);
                    $successCount++;
                }
                
                if ($successCount > 0) {
                    $results = $pipe->exec();
                    $cacheStats['sets'] += $successCount;
                    return $results;
                }
                return false;

            case 'memory_info':
                // Get Redis memory information for fragmentation monitoring
                try {
                    $info = $redis->info('memory');
                    if ($info && isset($info['used_memory']) && isset($info['used_memory_rss'])) {
                        $fragmentation = $info['used_memory_rss'] > 0 
                            ? round($info['used_memory_rss'] / $info['used_memory'], 2)
                            : 1.0;
                        
                        return [
                            'used_memory' => $info['used_memory'],
                            'used_memory_rss' => $info['used_memory_rss'],
                            'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                            'mem_fragmentation_ratio' => $fragmentation,
                            'maxmemory' => $info['maxmemory'] ?? 0,
                            'used_memory_human' => $info['used_memory_human'] ?? '',
                            'used_memory_peak_human' => $info['used_memory_peak_human'] ?? ''
                        ];
                    }
                    return false;
                } catch (\Exception $e) {
                    logActivity("V2RaySocks Traffic Monitor: Memory info failed: " . $e->getMessage(), 0);
                    return false;
                }

            case 'key_analysis':
                // Analyze cache key patterns for optimization insights
                try {
                    $pattern = 'v2raysocks_traffic:' . $cacheVersion . ':*';
                    $keys = $redis->keys($pattern);
                    
                    if (empty($keys)) {
                        return ['total_keys' => 0, 'patterns' => []];
                    }
                    
                    $patterns = [];
                    foreach ($keys as $key) {
                        // Extract pattern from key
                        $baseKey = str_replace('v2raysocks_traffic:' . $cacheVersion . ':', '', $key);
                        $keyParts = explode('_', $baseKey);
                        $pattern = $keyParts[0] ?? 'unknown';
                        
                        if (!isset($patterns[$pattern])) {
                            $patterns[$pattern] = ['count' => 0, 'total_size' => 0];
                        }
                        $patterns[$pattern]['count']++;
                        
                        // Get memory usage for this key
                        try {
                            $memUsage = $redis->memory('USAGE', $key);
                            $patterns[$pattern]['total_size'] += $memUsage ?: 0;
                        } catch (\Exception $e) {
                            // Memory usage command not available in older Redis versions
                        }
                    }
                    
                    return [
                        'total_keys' => count($keys),
                        'patterns' => $patterns
                    ];
                } catch (\Exception $e) {
                    logActivity("V2RaySocks Traffic Monitor: Key analysis failed: " . $e->getMessage(), 0);
                    return false;
                }

            default:
                logActivity("V2RaySocks Traffic Monitor: Unknown Redis operation: " . $act, 0);
                return false;
        }
    } catch (RedisException $e) {
        $cacheStats['errors']++;
        logActivity("V2RaySocks Traffic Monitor: Redis operation failed - " . $act . ": " . $e->getMessage(), 0);
        return false;
    } catch (\Throwable $e) {
        $cacheStats['errors']++;
        logActivity("V2RaySocks Traffic Monitor: Unexpected Redis error - " . $act . ": " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Unified cache operation with automatic TTL strategy application
 * Reduces hardcoded TTL usage and provides consistent caching interface
 */
function v2raysocks_traffic_setCacheWithTTL($key, $value, $context = [])
{
    try {
        // Get dynamic TTL based on key and context
        $ttl = v2raysocks_traffic_getDefaultTTL($key, $context);
        
        // Log TTL decision for debugging (can be removed in production)
        if (isset($context['debug']) && $context['debug']) {
            logActivity("V2RaySocks Traffic Monitor: Using TTL {$ttl}s for key '{$key}' with context: " . json_encode($context), 0);
        }
        
        // Use the existing redis operation function
        return v2raysocks_traffic_redisOperate('set', [
            'key' => $key,
            'value' => $value,
            'ttl' => $ttl
        ]);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: setCacheWithTTL failed for key '{$key}': " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Enhanced cache getter with consistent error handling
 */
function v2raysocks_traffic_getCacheWithFallback($key, $defaultValue = null)
{
    try {
        $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $key]);
        if ($cachedData !== false) {
            $decodedData = json_decode($cachedData, true);
            if ($decodedData !== null) {
                return $decodedData;
            }
        }
        return $defaultValue;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: getCacheWithFallback failed for key '{$key}': " . $e->getMessage(), 0);
        return $defaultValue;
    }
}

/**
 * Get dynamic TTL based on cache key type and context
 * Enhanced to support time range dynamic adjustment, access frequency, and better data type identification
 */
function v2raysocks_traffic_getDefaultTTL($key, $context = [])
{
    // Extract time range from context for dynamic adjustment
    $timeRange = $context['time_range'] ?? '';
    $dataType = $context['data_type'] ?? '';
    $isHistorical = $context['is_historical'] ?? false;
    $accessFrequency = $context['access_frequency'] ?? 'normal'; // high, normal, low
    $priority = $context['priority'] ?? 'normal'; // critical, high, normal, low
    
    // Apply access frequency modifier
    $frequencyMultiplier = 1.0;
    switch ($accessFrequency) {
        case 'high':
            $frequencyMultiplier = 0.7; // Shorter TTL for frequently accessed data
            break;
        case 'low':
            $frequencyMultiplier = 1.5; // Longer TTL for rarely accessed data
            break;
    }
    
    // Configuration data - longest TTL as it changes rarely
    if (strpos($key, 'language_config') !== false || 
        strpos($key, 'server_info_config') !== false ||
        strpos($key, 'server_options') !== false) {
        $baseTTL = ($priority === 'critical') ? 900 : 600; // 15 or 10 minutes for configuration data
        return max(300, intval($baseTTL * $frequencyMultiplier));
    }
    
    // Real-time/live data - shortest TTL for immediate updates
    if (strpos($key, 'live_stats') !== false || 
        strpos($key, 'traffic_5min') !== false ||
        strpos($key, 'real_time') !== false) {
        $baseTTL = ($accessFrequency === 'high') ? 45 : 60; // 45s-1min for real-time data
        return max(30, intval($baseTTL * $frequencyMultiplier));
    }
    
    // Traffic data - dynamic TTL based on time range and access patterns
    if (strpos($key, 'traffic_data') !== false || 
        strpos($key, 'day_traffic') !== false ||
        strpos($key, 'enhanced_traffic') !== false) {
        // Shorter TTL for today's data, longer for historical
        if ($timeRange === 'today' || (!$isHistorical && empty($timeRange))) {
            $baseTTL = ($accessFrequency === 'high') ? 90 : 120; // 1.5-2 minutes for current/today data
        } else {
            $baseTTL = 300; // 5 minutes for historical data
        }
        return max(60, intval($baseTTL * $frequencyMultiplier));
    }
    
    // Chart data - dynamic TTL based on time range and access patterns
    if (strpos($key, 'chart') !== false || 
        strpos($key, 'node_traffic_chart') !== false || 
        strpos($key, 'user_traffic_chart') !== false) {
        if ($timeRange === 'today') {
            $baseTTL = ($accessFrequency === 'high') ? 90 : 120; // 1.5-2 minutes for today's charts
        } else {
            $baseTTL = 180; // 3 minutes for historical charts
        }
        return max(60, intval($baseTTL * $frequencyMultiplier));
    }
    
    // Rankings data - dynamic TTL based on time range and priority
    if (strpos($key, 'rankings') !== false || 
        strpos($key, 'node_rankings') !== false || 
        strpos($key, 'user_rankings') !== false) {
        if ($timeRange === 'today' || $timeRange === 'custom') {
            $baseTTL = ($priority === 'high') ? 150 : 180; // 2.5-3 minutes for current rankings
        } else {
            $baseTTL = 300; // 5 minutes for historical rankings
        }
        return max(90, intval($baseTTL * $frequencyMultiplier));
    }
    
    // User/Node details - dynamic TTL with priority consideration
    if (strpos($key, 'user_details') !== false || 
        strpos($key, 'node_details') !== false ||
        strpos($key, 'usage_records') !== false) {
        $baseTTL = ($priority === 'high') ? 240 : 300; // 4-5 minutes for details and usage records
        return max(120, intval($baseTTL * $frequencyMultiplier));
    }
    
    // Static/slow-changing data - longer TTL with smart adjustment
    if (strpos($key, 'all_nodes') !== false) {
        $baseTTL = ($accessFrequency === 'high') ? 240 : 300; // 4-5 minutes for node lists
        return max(180, intval($baseTTL * $frequencyMultiplier));
    }
    
    // Default TTL - medium duration with frequency adjustment
    $baseTTL = 300; // 5 minutes default
    return max(120, intval($baseTTL * $frequencyMultiplier));
}

/**
 * Clear cache entries based on data type for active invalidation
 */
function v2raysocks_traffic_clearRelatedCache($dataType)
{
    switch ($dataType) {
        case 'user_traffic':
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'user_*']);
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'traffic_*']);
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'live_stats*']);
            break;
            
        case 'node_traffic':
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'node_*']);
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'traffic_*']);
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'live_stats*']);
            break;
            
        case 'live_stats':
            v2raysocks_traffic_redisOperate('del', ['key' => 'live_stats']);
            break;
            
        case 'all':
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => '*']);
            break;
    }
    
    return true;
}

/**
 * Enhanced cache statistics with performance metrics and memory monitoring
 */
function v2raysocks_traffic_getEnhancedCacheStats()
{
    try {
        // Get basic cache stats
        $basicStats = v2raysocks_traffic_redisOperate('stats', []);
        if (!$basicStats) {
            return [
                'redis_available' => false,
                'error' => 'Redis not available'
            ];
        }
        
        // Get memory information
        $memoryInfo = v2raysocks_traffic_redisOperate('memory_info', []);
        
        // Get key analysis
        $keyAnalysis = v2raysocks_traffic_redisOperate('key_analysis', []);
        
        // Calculate performance metrics
        $totalRequests = $basicStats['hits'] + $basicStats['misses'];
        $hitRate = $totalRequests > 0 ? ($basicStats['hits'] / $totalRequests) * 100 : 0;
        
        $stats = array_merge($basicStats, [
            'redis_available' => true,
            'hit_rate' => round($hitRate, 2),
            'total_requests' => $totalRequests,
            'memory_info' => $memoryInfo ?: [],
            'key_analysis' => $keyAnalysis ?: [],
            'timestamp' => time()
        ]);
        
        // Add fragmentation assessment
        if ($memoryInfo && isset($memoryInfo['mem_fragmentation_ratio'])) {
            $fragRatio = $memoryInfo['mem_fragmentation_ratio'];
            $stats['fragmentation_status'] = v2raysocks_traffic_assessFragmentation($fragRatio);
        }
        
        return $stats;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Enhanced cache stats failed: " . $e->getMessage(), 0);
        return [
            'redis_available' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Assess memory fragmentation status
 */
function v2raysocks_traffic_assessFragmentation($ratio)
{
    if ($ratio <= 1.3) {
        return ['status' => 'excellent', 'message' => 'Low fragmentation'];
    } elseif ($ratio <= 1.5) {
        return ['status' => 'good', 'message' => 'Acceptable fragmentation'];
    } elseif ($ratio <= 2.0) {
        return ['status' => 'warning', 'message' => 'High fragmentation - consider optimization'];
    } else {
        return ['status' => 'critical', 'message' => 'Very high fragmentation - immediate optimization needed'];
    }
}

/**
 * Cache prewarming mechanism for frequently accessed data
 */
function v2raysocks_traffic_prewarmCache($dataTypes = [])
{
    static $prewarmInProgress = false;
    
    // Prevent concurrent prewarming operations
    if ($prewarmInProgress) {
        return false;
    }
    
    $prewarmInProgress = true;
    $prewarmed = [];
    
    try {
        // Default data types to prewarm if none specified
        if (empty($dataTypes)) {
            $dataTypes = ['live_stats', 'all_nodes', 'server_config'];
        }
        
        foreach ($dataTypes as $dataType) {
            switch ($dataType) {
                case 'live_stats':
                    // Prewarm live statistics
                    try {
                        require_once __DIR__ . '/Monitor_DB.php';
                        $liveStats = v2raysocks_traffic_getLiveStats();
                        if ($liveStats) {
                            $prewarmed[] = 'live_stats';
                        }
                    } catch (\Exception $e) {
                        logActivity("V2RaySocks Traffic Monitor: Prewarm live_stats failed: " . $e->getMessage(), 0);
                    }
                    break;
                    
                case 'all_nodes':
                    // Prewarm node list
                    try {
                        require_once __DIR__ . '/Monitor_DB.php';
                        $nodes = v2raysocks_traffic_getAllNodes();
                        if ($nodes) {
                            $prewarmed[] = 'all_nodes';
                        }
                    } catch (\Exception $e) {
                        logActivity("V2RaySocks Traffic Monitor: Prewarm all_nodes failed: " . $e->getMessage(), 0);
                    }
                    break;
                    
                case 'server_config':
                    // Prewarm frequently accessed configuration
                    try {
                        $configKey = 'server_info_config';
                        $cachedConfig = v2raysocks_traffic_redisOperate('get', ['key' => $configKey]);
                        if ($cachedConfig === false) {
                            // Generate and cache configuration data
                            $configData = [
                                'timestamp' => time(),
                                'php_version' => PHP_VERSION,
                                'redis_available' => true
                            ];
                            v2raysocks_traffic_setCacheWithTTL($configKey, json_encode($configData), [
                                'data_type' => 'config'
                            ]);
                            $prewarmed[] = 'server_config';
                        }
                    } catch (\Exception $e) {
                        logActivity("V2RaySocks Traffic Monitor: Prewarm server_config failed: " . $e->getMessage(), 0);
                    }
                    break;
            }
        }
        
        logActivity("V2RaySocks Traffic Monitor: Cache prewarming completed for: " . implode(', ', $prewarmed), 0);
        return $prewarmed;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Cache prewarming failed: " . $e->getMessage(), 0);
        return false;
    } finally {
        $prewarmInProgress = false;
    }
}

/**
 * Batch cache operations using Pipeline for better performance
 */
function v2raysocks_traffic_batchCacheOperations($operations)
{
    if (empty($operations) || !is_array($operations)) {
        return false;
    }
    
    try {
        return v2raysocks_traffic_redisOperate('pipeline_set', [
            'operations' => $operations
        ]);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Batch cache operations failed: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Smart cache clearing with protection for important configuration data
 */
function v2raysocks_traffic_smartCacheClear($clearType = 'selective', $protectedPatterns = [])
{
    // Default protected patterns for important configuration data
    $defaultProtected = [
        'language_config*',
        'server_info_config*',
        'server_options*'
    ];
    
    $protectedPatterns = array_merge($defaultProtected, $protectedPatterns);
    
    try {
        switch ($clearType) {
            case 'selective':
                // Clear only volatile data, protect configuration
                $volatilePatterns = [
                    'live_stats*',
                    'traffic_5min*',
                    'real_time*',
                    'user_traffic_*',
                    'node_traffic_*'
                ];
                
                foreach ($volatilePatterns as $pattern) {
                    v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => $pattern]);
                }
                break;
                
            case 'aggressive':
                // Clear most data but still protect critical configuration
                $aggressivePatterns = [
                    'traffic_*',
                    'user_*',
                    'node_*',
                    'chart_*',
                    'rankings_*'
                ];
                
                foreach ($aggressivePatterns as $pattern) {
                    // Check if pattern conflicts with protected patterns
                    $isProtected = false;
                    foreach ($protectedPatterns as $protectedPattern) {
                        if (fnmatch($protectedPattern, $pattern)) {
                            $isProtected = true;
                            break;
                        }
                    }
                    
                    if (!$isProtected) {
                        v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => $pattern]);
                    }
                }
                break;
                
            case 'all':
                // Clear everything except explicitly protected patterns
                $allKeys = v2raysocks_traffic_redisOperate('key_analysis', []);
                if ($allKeys && isset($allKeys['patterns'])) {
                    foreach (array_keys($allKeys['patterns']) as $pattern) {
                        $isProtected = false;
                        foreach ($protectedPatterns as $protectedPattern) {
                            if (fnmatch($protectedPattern, $pattern . '*')) {
                                $isProtected = true;
                                break;
                            }
                        }
                        
                        if (!$isProtected) {
                            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => $pattern . '*']);
                        }
                    }
                }
                break;
        }
        
        return true;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Smart cache clear failed: " . $e->getMessage(), 0);
        return false;
    }
}