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
    static $cacheStats = ['hits' => 0, 'misses' => 0, 'sets' => 0, 'errors' => 0, 'compression_saves' => 0];
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
                
                // Optimize data storage based on type and size
                $value = $data['value'];
                $useCompression = false;
                $useHash = false;
                
                // Check if we should use hash structure for complex objects
                if (isset($data['use_hash']) && $data['use_hash']) {
                    $useHash = true;
                } elseif (is_array(json_decode($value, true)) && strlen($value) > 500) {
                    // Auto-detect: use hash for large JSON objects
                    $useHash = true;
                }
                
                // Check if we should compress large values
                if (strlen($value) > 1024 && function_exists('gzcompress')) {
                    $compressed = gzcompress($value, 6); // Level 6 compression - good balance
                    if (strlen($compressed) < strlen($value) * 0.8) { // Only use if 20%+ savings
                        $value = $compressed;
                        $useCompression = true;
                        $cacheStats['compression_saves']++;
                    }
                }
                
                if ($useHash) {
                    // Store as hash structure
                    $hashKey = $fullKey . ':hash';
                    $decodedValue = json_decode($data['value'], true);
                    if (is_array($decodedValue)) {
                        $redis->del($hashKey); // Clear existing hash
                        foreach ($decodedValue as $field => $fieldValue) {
                            $redis->hSet($hashKey, $field, is_array($fieldValue) ? json_encode($fieldValue) : $fieldValue);
                        }
                        $redis->expire($hashKey, $ttl);
                        // Store metadata about hash structure
                        $redis->setex($fullKey . ':meta', $ttl, json_encode([
                            'type' => 'hash',
                            'compressed' => false,
                            'created' => time()
                        ]));
                        $result = true;
                    } else {
                        // Fallback to regular string storage
                        $result = $redis->setex($fullKey, $ttl, $value);
                        if ($useCompression) {
                            $redis->setex($fullKey . ':meta', $ttl, json_encode([
                                'type' => 'string',
                                'compressed' => true,
                                'created' => time()
                            ]));
                        }
                    }
                } else {
                    // Regular string storage with optional compression
                    $result = $redis->setex($fullKey, $ttl, $value);
                    if ($useCompression) {
                        $redis->setex($fullKey . ':meta', $ttl, json_encode([
                            'type' => 'string', 
                            'compressed' => true,
                            'created' => time()
                        ]));
                    }
                }
                
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
                
                // Check if data is stored as hash
                $metaKey = $fullKey . ':meta';
                $meta = $redis->get($metaKey);
                $metaData = $meta ? json_decode($meta, true) : null;
                
                if ($metaData && $metaData['type'] === 'hash') {
                    // Retrieve from hash structure
                    $hashKey = $fullKey . ':hash';
                    $hashData = $redis->hGetAll($hashKey);
                    if (!empty($hashData)) {
                        // Reconstruct JSON from hash
                        $reconstructed = [];
                        foreach ($hashData as $field => $fieldValue) {
                            $decoded = json_decode($fieldValue, true);
                            $reconstructed[$field] = $decoded !== null ? $decoded : $fieldValue;
                        }
                        $result = json_encode($reconstructed);
                        $cacheStats['hits']++;
                        return $result;
                    }
                }
                
                // Regular string retrieval
                $result = $redis->get($fullKey);
                if ($result !== false) {
                    // Check if data is compressed
                    if ($metaData && $metaData['compressed']) {
                        $decompressed = gzuncompress($result);
                        if ($decompressed !== false) {
                            $result = $decompressed;
                        }
                    }
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
                // Delete both main key and potential hash/meta keys
                $deleted = 0;
                $deleted += $redis->del($fullKey);
                $deleted += $redis->del($fullKey . ':hash');
                $deleted += $redis->del($fullKey . ':meta');
                return $deleted > 0;

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
                // Batch set operations using pipeline
                if (!isset($data['items']) || !is_array($data['items'])) {
                    return false;
                }
                $pipe = $redis->pipeline();
                $count = 0;
                foreach ($data['items'] as $item) {
                    if (isset($item['key']) && isset($item['value'])) {
                        $itemFullKey = 'v2raysocks_traffic:' . $cacheVersion . ':' . $item['key'];
                        $itemTTL = isset($item['ttl']) ? (int)$item['ttl'] : $defaultTTL;
                        $pipe->setex($itemFullKey, $itemTTL, $item['value']);
                        $count++;
                    }
                }
                $results = $pipe->exec();
                $cacheStats['sets'] += $count;
                return $results;
                
            case 'pipeline_get':
                // Batch get operations using pipeline
                if (!isset($data['keys']) || !is_array($data['keys'])) {
                    return false;
                }
                $pipe = $redis->pipeline();
                $fullKeys = [];
                foreach ($data['keys'] as $key) {
                    $fullKey = 'v2raysocks_traffic:' . $cacheVersion . ':' . $key;
                    $fullKeys[] = $fullKey;
                    $pipe->get($fullKey);
                }
                $results = $pipe->exec();
                $hitCount = 0;
                $finalResults = [];
                foreach ($results as $i => $result) {
                    if ($result !== false) {
                        $hitCount++;
                    }
                    $finalResults[$data['keys'][$i]] = $result;
                }
                $cacheStats['hits'] += $hitCount;
                $cacheStats['misses'] += (count($data['keys']) - $hitCount);
                return $finalResults;
                
            case 'memory_info':
                // Get memory usage and fragmentation info
                try {
                    $info = $redis->info('memory');
                    $memoryInfo = [];
                    foreach (explode("\r\n", $info) as $line) {
                        if (strpos($line, ':') !== false) {
                            list($key, $value) = explode(':', $line, 2);
                            $memoryInfo[trim($key)] = trim($value);
                        }
                    }
                    return $memoryInfo;
                } catch (\Exception $e) {
                    return ['error' => $e->getMessage()];
                }
                
            case 'defrag':
                // Trigger memory defragmentation if supported
                try {
                    $redis->config('SET', 'activedefrag', 'yes');
                    return true;
                } catch (\Exception $e) {
                    logActivity("V2RaySocks Traffic Monitor: Memory defrag not supported: " . $e->getMessage(), 0);
                    return false;
                }
                
            case 'cache_warm':
                // Cache warming functionality
                if (!isset($data['warm_keys']) || !is_array($data['warm_keys'])) {
                    return false;
                }
                $warmed = 0;
                foreach ($data['warm_keys'] as $warmKey => $warmData) {
                    if (isset($warmData['generator']) && is_callable($warmData['generator'])) {
                        try {
                            $value = call_user_func($warmData['generator']);
                            if ($value !== null) {
                                $warmTTL = $warmData['ttl'] ?? $defaultTTL;
                                $warmFullKey = 'v2raysocks_traffic:' . $cacheVersion . ':' . $warmKey;
                                if ($redis->setex($warmFullKey, $warmTTL, $value)) {
                                    $warmed++;
                                }
                            }
                        } catch (\Exception $e) {
                            logActivity("V2RaySocks Traffic Monitor: Cache warm failed for {$warmKey}: " . $e->getMessage(), 0);
                        }
                    }
                }
                return $warmed;

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
 * Enhanced cache getter with consistent error handling and penetration protection
 */
function v2raysocks_traffic_getCacheWithFallback($key, $defaultValue = null, $options = [])
{
    try {
        $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $key]);
        if ($cachedData !== false) {
            $decodedData = json_decode($cachedData, true);
            if ($decodedData !== null) {
                return $decodedData;
            }
        }
        
        // Cache penetration protection: if we have a data generator, use it and cache the result
        if (isset($options['generator']) && is_callable($options['generator'])) {
            try {
                $generatedData = call_user_func($options['generator']);
                if ($generatedData !== null) {
                    // Cache the generated data to prevent repeated calls
                    $cacheTTL = $options['ttl'] ?? v2raysocks_traffic_getDefaultTTL($key, $options['context'] ?? []);
                    v2raysocks_traffic_redisOperate('set', [
                        'key' => $key,
                        'value' => json_encode($generatedData),
                        'ttl' => $cacheTTL
                    ]);
                    return $generatedData;
                }
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Monitor: Cache generator failed for key '{$key}': " . $e->getMessage(), 0);
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
 * Enhanced to support time range dynamic adjustment and better data type identification
 */
function v2raysocks_traffic_getDefaultTTL($key, $context = [])
{
    // Extract time range from context for dynamic adjustment
    $timeRange = $context['time_range'] ?? '';
    $dataType = $context['data_type'] ?? '';
    $isHistorical = $context['is_historical'] ?? false;
    
    // Configuration data - longest TTL as it changes rarely
    if (strpos($key, 'language_config') !== false || 
        strpos($key, 'server_info_config') !== false ||
        strpos($key, 'server_options') !== false) {
        return 600; // 10 minutes for configuration data
    }
    
    // Real-time/live data - shortest TTL for immediate updates
    if (strpos($key, 'live_stats') !== false || 
        strpos($key, 'traffic_5min') !== false ||
        strpos($key, 'real_time') !== false) {
        return 60; // 1 minute for real-time data
    }
    
    // Traffic data - dynamic TTL based on time range
    if (strpos($key, 'traffic_data') !== false || 
        strpos($key, 'day_traffic') !== false ||
        strpos($key, 'enhanced_traffic') !== false) {
        // Shorter TTL for today's data, longer for historical
        if ($timeRange === 'today' || (!$isHistorical && empty($timeRange))) {
            return 120; // 2 minutes for current/today data
        }
        return 300; // 5 minutes for historical data
    }
    
    // Chart data - dynamic TTL based on time range
    if (strpos($key, 'chart') !== false || 
        strpos($key, 'node_traffic_chart') !== false || 
        strpos($key, 'user_traffic_chart') !== false) {
        // Use the standardized 180 seconds for chart data
        if ($timeRange === 'today') {
            return 120; // 2 minutes for today's charts (more dynamic)
        }
        return 180; // 3 minutes for historical charts (standard)
    }
    
    // Rankings data - dynamic TTL based on time range
    if (strpos($key, 'rankings') !== false || 
        strpos($key, 'node_rankings') !== false || 
        strpos($key, 'user_rankings') !== false) {
        // Use the standardized 180 seconds for rankings
        if ($timeRange === 'today' || $timeRange === 'custom') {
            return 180; // 3 minutes for current rankings
        }
        return 300; // 5 minutes for historical rankings
    }
    
    // User/Node details - dynamic TTL
    if (strpos($key, 'user_details') !== false || 
        strpos($key, 'node_details') !== false ||
        strpos($key, 'usage_records') !== false) {
        return 300; // 5 minutes for details and usage records
    }
    
    // Static/slow-changing data - longer TTL
    if (strpos($key, 'all_nodes') !== false) {
        return 300; // 5 minutes for node lists (may change when nodes are added/removed)
    }
    
    // Default TTL - medium duration
    return 300; // 5 minutes default
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
 * Batch set operations for improved performance
 * Reduces network round trips and improves cache efficiency
 */
function v2raysocks_traffic_batchSet($items)
{
    try {
        return v2raysocks_traffic_redisOperate('pipeline_set', ['items' => $items]);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: batchSet failed: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Batch get operations for improved performance
 * Reduces network round trips and improves cache efficiency
 */
function v2raysocks_traffic_batchGet($keys)
{
    try {
        $result = v2raysocks_traffic_redisOperate('pipeline_get', ['keys' => $keys]);
        return is_array($result) ? $result : [];
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: batchGet failed: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Enhanced cache setter with automatic optimization
 * Automatically chooses best storage method based on data characteristics
 */
function v2raysocks_traffic_setOptimizedCache($key, $value, $context = [])
{
    try {
        $ttl = v2raysocks_traffic_getDefaultTTL($key, $context);
        
        // Auto-optimize based on value characteristics
        $optimizedData = [
            'key' => $key,
            'value' => $value,
            'ttl' => $ttl,
            'context' => $context
        ];
        
        // Determine if we should use hash structure
        if (isset($context['prefer_hash']) && $context['prefer_hash']) {
            $optimizedData['use_hash'] = true;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && count($decoded) > 3 && strlen($value) > 500) {
                $optimizedData['use_hash'] = true;
            }
        }
        
        return v2raysocks_traffic_redisOperate('set', $optimizedData);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: setOptimizedCache failed for key '{$key}': " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Get Redis memory information and fragmentation statistics
 */
function v2raysocks_traffic_getMemoryInfo()
{
    try {
        $memInfo = v2raysocks_traffic_redisOperate('memory_info', []);
        if (!$memInfo || isset($memInfo['error'])) {
            return [
                'available' => false,
                'error' => $memInfo['error'] ?? 'Memory info not available'
            ];
        }
        
        $usedMemory = isset($memInfo['used_memory']) ? (int)$memInfo['used_memory'] : 0;
        $usedMemoryRss = isset($memInfo['used_memory_rss']) ? (int)$memInfo['used_memory_rss'] : 0;
        $maxMemory = isset($memInfo['maxmemory']) ? (int)$memInfo['maxmemory'] : 0;
        
        $fragmentation = 0;
        if ($usedMemory > 0 && $usedMemoryRss > 0) {
            $fragmentation = round(($usedMemoryRss / $usedMemory), 2);
        }
        
        return [
            'available' => true,
            'used_memory_human' => $memInfo['used_memory_human'] ?? 'N/A',
            'used_memory_rss_human' => $memInfo['used_memory_rss_human'] ?? 'N/A',
            'used_memory_peak_human' => $memInfo['used_memory_peak_human'] ?? 'N/A',
            'fragmentation_ratio' => $fragmentation,
            'fragmentation_status' => $fragmentation > 1.5 ? 'high' : ($fragmentation > 1.2 ? 'moderate' : 'low'),
            'max_memory_human' => $maxMemory > 0 ? number_format($maxMemory / 1024 / 1024, 2) . 'MB' : 'unlimited',
            'raw_info' => $memInfo
        ];
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: getMemoryInfo failed: " . $e->getMessage(), 0);
        return [
            'available' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Trigger memory defragmentation if Redis supports it
 */
function v2raysocks_traffic_defragMemory()
{
    try {
        return v2raysocks_traffic_redisOperate('defrag', []);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: defragMemory failed: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Cache warming functionality to preload frequently accessed data
 */
function v2raysocks_traffic_warmCache($warmConfig)
{
    try {
        return v2raysocks_traffic_redisOperate('cache_warm', ['warm_keys' => $warmConfig]);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: warmCache failed: " . $e->getMessage(), 0);
        return 0;
    }
}

/**
 * Enhanced cache statistics with memory optimization metrics
 */
function v2raysocks_traffic_getEnhancedCacheStats()
{
    try {
        $basicStats = v2raysocks_traffic_redisOperate('stats', []);
        if (!is_array($basicStats)) {
            $basicStats = ['hits' => 0, 'misses' => 0, 'sets' => 0, 'errors' => 0, 'compression_saves' => 0];
        }
        
        $memoryInfo = v2raysocks_traffic_getMemoryInfo();
        
        $totalRequests = ($basicStats['hits'] ?? 0) + ($basicStats['misses'] ?? 0);
        $hitRate = $totalRequests > 0 ? (($basicStats['hits'] ?? 0) / $totalRequests) * 100 : 0;
        
        return array_merge($basicStats, [
            'hit_rate' => round($hitRate, 2),
            'total_requests' => $totalRequests,
            'memory_info' => $memoryInfo,
            'optimization_enabled' => true,
            'compression_ratio' => ($basicStats['sets'] ?? 0) > 0 ? 
                round((($basicStats['compression_saves'] ?? 0) / ($basicStats['sets'] ?? 1)) * 100, 2) : 0
        ]);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: getEnhancedCacheStats failed: " . $e->getMessage(), 0);
        return [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'errors' => 0,
            'hit_rate' => 0,
            'optimization_enabled' => false,
            'error' => $e->getMessage()
        ];
    }
}