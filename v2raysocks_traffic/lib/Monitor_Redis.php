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
    $defaultTTL = v2raysocks_traffic_getDefaultTTL($data['key'] ?? '');

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
 * Get default TTL based on cache key type
 */
function v2raysocks_traffic_getDefaultTTL($key)
{
    // Real-time data - short TTL
    if (strpos($key, 'live_stats') !== false || 
        strpos($key, 'traffic_5min') !== false ||
        strpos($key, 'real_time') !== false) {
        return 60; // 1 minute
    }
    
    // Traffic data - medium TTL
    if (strpos($key, 'traffic_data') !== false || 
        strpos($key, 'day_traffic') !== false ||
        strpos($key, 'enhanced_traffic') !== false) {
        return 120; // 2 minutes
    }
    
    // Chart data - shorter TTL for recent data
    if (strpos($key, 'chart') !== false || 
        strpos($key, 'rankings') !== false) {
        return 180; // 3 minutes
    }
    
    // User/Node details - medium TTL
    if (strpos($key, 'user_details') !== false || 
        strpos($key, 'node_details') !== false ||
        strpos($key, 'usage_records') !== false) {
        return 300; // 5 minutes
    }
    
    // Static data - longer TTL
    if (strpos($key, 'all_nodes') !== false || 
        strpos($key, 'server_info') !== false) {
        return 600; // 10 minutes
    }
    
    // Default TTL
    return 300; // 5 minutes
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