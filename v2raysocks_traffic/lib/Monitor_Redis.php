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