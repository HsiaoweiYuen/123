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
    static $connectionPool = [];
    static $poolSize = 5;
    static $performanceStats = ['connect_time' => 0, 'reconnects' => 0, 'failures' => 0];

    if (!extension_loaded('redis')) {
        // Log this condition only once per session
        if ($lastFailTime === 0) {
            logActivity("V2RaySocks Traffic Monitor: Redis extension not loaded", 0);
            $lastFailTime = time();
        }
        return false;
    }

    // Check for healthy connection in pool first
    if (!empty($connectionPool)) {
        foreach ($connectionPool as $key => $conn) {
            try {
                if ($conn instanceof Redis && $conn->ping()) {
                    $redisInstance = $conn;
                    $isConnected = true;
                    return $redisInstance;
                }
            } catch (\Throwable $e) {
                // Remove stale connection from pool
                unset($connectionPool[$key]);
            }
        }
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
            $performanceStats['reconnects']++;
        }
    }

    // If recently failed, don't retry immediately
    if ($lastFailTime > 0 && (time() - $lastFailTime) < $connectionRetryDelay) {
        return false;
    }

    $startTime = microtime(true);
    
    try {
        // Get configuration settings
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'v2raysocks_traffic')
            ->whereIn('setting', ['redis_ip', 'redis_port', 'redis_password', 'redis_ssl', 'redis_cluster', 'redis_sentinel'])
            ->pluck('value', 'setting');

        $redisIP = $settings['redis_ip'] ?? '127.0.0.1';
        $redisPort = $settings['redis_port'] ?? 6379;
        $redisPassword = $settings['redis_password'] ?? null;
        $useSSL = ($settings['redis_ssl'] ?? '0') === '1';
        $useCluster = ($settings['redis_cluster'] ?? '0') === '1';
        $useSentinel = ($settings['redis_sentinel'] ?? '0') === '1';

        // Try cluster mode if enabled
        if ($useCluster && class_exists('RedisCluster')) {
            try {
                $clusterNodes = explode(',', $redisIP);
                $redis = new RedisCluster(null, $clusterNodes, 2.0, 2.0, true, $redisPassword);
                $redis->ping();
                $connected = true;
                logActivity("V2RaySocks Traffic Monitor: Connected to Redis Cluster", 0);
            } catch (\Throwable $e) {
                logActivity("V2RaySocks Traffic Monitor: Redis Cluster connection failed: " . $e->getMessage(), 0);
                $connected = false;
            }
        } 
        // Try Sentinel mode if enabled
        elseif ($useSentinel && class_exists('RedisSentinel')) {
            try {
                $sentinelNodes = explode(',', $redisIP);
                $sentinels = [];
                foreach ($sentinelNodes as $node) {
                    $parts = explode(':', $node);
                    $sentinels[] = ['host' => $parts[0], 'port' => $parts[1] ?? 26379];
                }
                
                $sentinel = new RedisSentinel($sentinels[0]['host'], $sentinels[0]['port'], 2.0);
                $masterInfo = $sentinel->master('mymaster');
                
                $redis = new Redis();
                if ($redis->connect($masterInfo[3], $masterInfo[5], 2.0)) {
                    if (!empty($redisPassword)) {
                        $redis->auth($redisPassword);
                    }
                    $redis->ping();
                    $connected = true;
                    logActivity("V2RaySocks Traffic Monitor: Connected to Redis via Sentinel", 0);
                } else {
                    $connected = false;
                }
            } catch (\Throwable $e) {
                logActivity("V2RaySocks Traffic Monitor: Redis Sentinel connection failed: " . $e->getMessage(), 0);
                $connected = false;
            }
        } 
        // Standard Redis connection
        else {
            $redis = new Redis();
            $connected = false;
            
            // Try persistent connection first for connection pooling
            try {
                $connectionKey = "v2ray_redis_" . md5($redisIP . $redisPort);
                $connectMethod = $useSSL ? 'pconnect' : 'pconnect';
                
                if ($redis->$connectMethod($redisIP, (int)$redisPort, 2.0, $connectionKey)) {
                    if (!empty($redisPassword)) {
                        if (!$redis->auth($redisPassword)) {
                            logActivity("V2RaySocks Traffic Monitor: Redis authentication failed", 0);
                            $lastFailTime = time();
                            return false;
                        }
                    }
                    
                    // Configure for Redis 6.0+ if available
                    if (method_exists($redis, 'hello')) {
                        try {
                            $redis->hello(['ver' => 3]);
                        } catch (\Throwable $e) {
                            // Redis version < 6.0, continue with normal operation
                        }
                    }
                    
                    // Test the connection
                    $redis->ping();
                    $connected = true;
                    logActivity("V2RaySocks Traffic Monitor: Connected to Redis on {$redisIP}:{$redisPort}" . ($useSSL ? ' (SSL)' : ''), 0);
                }
            } catch (\Throwable $e) {
                logActivity("V2RaySocks Traffic Monitor: Redis persistent connection failed: " . $e->getMessage(), 0);
                
                // Fallback to regular connection
                try {
                    $connectMethod = $useSSL ? 'connect' : 'connect';
                    if ($redis->$connectMethod($redisIP, (int)$redisPort, 2.0)) {
                        if (!empty($redisPassword)) {
                            if (!$redis->auth($redisPassword)) {
                                logActivity("V2RaySocks Traffic Monitor: Redis authentication failed", 0);
                                $lastFailTime = time();
                                return false;
                            }
                        }
                        $redis->ping();
                        $connected = true;
                        logActivity("V2RaySocks Traffic Monitor: Connected to Redis on {$redisIP}:{$redisPort} (fallback)", 0);
                    }
                } catch (\Throwable $e) {
                    logActivity("V2RaySocks Traffic Monitor: Redis fallback connection failed: " . $e->getMessage(), 0);
                    $connected = false;
                }
            }
        }

        if (!$connected) {
            $lastFailTime = time();
            $performanceStats['failures']++;
            return false;
        }

        // Add to connection pool if not cluster/sentinel
        if (!$useCluster && !$useSentinel && count($connectionPool) < $poolSize) {
            $connectionPool[] = $redis;
        }

        $redisInstance = $redis;
        $isConnected = true;
        $lastFailTime = 0; // Reset fail time on successful connection
        $performanceStats['connect_time'] = microtime(true) - $startTime;
        
        return $redisInstance;
    } catch (\Throwable $e) {
        // Catch all unexpected fatal errors
        logActivity("V2RaySocks Traffic Monitor: Unexpected Redis error: " . $e->getMessage(), 0);
        $lastFailTime = time();
        $performanceStats['failures']++;
        return false;
    }
}

function v2raysocks_traffic_redisOperate($act, $data)
{
    static $cacheStats = ['hits' => 0, 'misses' => 0, 'sets' => 0, 'errors' => 0, 'operations' => 0];
    static $cacheVersion = 'v1'; // Version for cache invalidation
    static $slowQueries = [];
    static $compressionEnabled = true;
    
    $redis = v2raysocks_traffic_getRedisInstance();
    if (!$redis instanceof Redis && !$redis instanceof RedisCluster) {
        $cacheStats['errors']++;
        return false;
    }

    $startTime = microtime(true);
    $cacheStats['operations']++;

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
                
                $value = $data['value'];
                if ($compressionEnabled && strlen($value) > 1024) {
                    $value = 'COMPRESSED:' . gzcompress($value, 6);
                }
                
                $result = $redis->setex($fullKey, $ttl, $value);
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
                    // Handle compression
                    if (strpos($result, 'COMPRESSED:') === 0) {
                        $result = gzuncompress(substr($result, 11));
                    }
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

            // Hash operations
            case 'hset':
                if (!isset($data['key']) || !isset($data['field']) || !isset($data['value'])) {
                    return false;
                }
                return $redis->hset($fullKey, $data['field'], $data['value']);

            case 'hget':
                if (!isset($data['key']) || !isset($data['field'])) {
                    return false;
                }
                return $redis->hget($fullKey, $data['field']);

            case 'hmset':
                if (!isset($data['key']) || !isset($data['hash']) || !is_array($data['hash'])) {
                    return false;
                }
                return $redis->hmset($fullKey, $data['hash']);

            case 'hmget':
                if (!isset($data['key']) || !isset($data['fields']) || !is_array($data['fields'])) {
                    return false;
                }
                return $redis->hmget($fullKey, $data['fields']);

            case 'hdel':
                if (!isset($data['key']) || !isset($data['field'])) {
                    return false;
                }
                return $redis->hdel($fullKey, $data['field']);

            case 'hgetall':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->hgetall($fullKey);

            // List operations
            case 'lpush':
                if (!isset($data['key']) || !isset($data['value'])) {
                    return false;
                }
                return $redis->lpush($fullKey, $data['value']);

            case 'rpush':
                if (!isset($data['key']) || !isset($data['value'])) {
                    return false;
                }
                return $redis->rpush($fullKey, $data['value']);

            case 'lpop':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->lpop($fullKey);

            case 'rpop':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->rpop($fullKey);

            case 'lrange':
                if (!isset($data['key'])) {
                    return false;
                }
                $start = $data['start'] ?? 0;
                $end = $data['end'] ?? -1;
                return $redis->lrange($fullKey, $start, $end);

            case 'llen':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->llen($fullKey);

            // Set operations
            case 'sadd':
                if (!isset($data['key']) || !isset($data['value'])) {
                    return false;
                }
                return $redis->sadd($fullKey, $data['value']);

            case 'srem':
                if (!isset($data['key']) || !isset($data['value'])) {
                    return false;
                }
                return $redis->srem($fullKey, $data['value']);

            case 'smembers':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->smembers($fullKey);

            case 'sismember':
                if (!isset($data['key']) || !isset($data['value'])) {
                    return false;
                }
                return $redis->sismember($fullKey, $data['value']);

            case 'scard':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->scard($fullKey);

            // Batch operations
            case 'batch':
                if (!isset($data['operations']) || !is_array($data['operations'])) {
                    return false;
                }
                
                $results = [];
                $pipe = $redis->multi(Redis::PIPELINE);
                
                foreach ($data['operations'] as $op) {
                    if (!isset($op['action']) || !isset($op['data'])) {
                        continue;
                    }
                    
                    $batchKey = 'v2raysocks_traffic:' . $cacheVersion . ':' . ($op['data']['key'] ?? '');
                    
                    switch (strtolower($op['action'])) {
                        case 'set':
                            $pipe->setex($batchKey, $op['data']['ttl'] ?? $defaultTTL, $op['data']['value']);
                            break;
                        case 'get':
                            $pipe->get($batchKey);
                            break;
                        case 'del':
                            $pipe->del($batchKey);
                            break;
                        case 'hset':
                            $pipe->hset($batchKey, $op['data']['field'], $op['data']['value']);
                            break;
                        case 'hget':
                            $pipe->hget($batchKey, $op['data']['field']);
                            break;
                    }
                }
                
                return $pipe->exec();

            case 'ping':
                return $redis->ping();

            case 'exists':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->exists($fullKey);

            case 'ttl':
                if (!isset($data['key'])) {
                    return false;
                }
                return $redis->ttl($fullKey);

            case 'expire':
                if (!isset($data['key']) || !isset($data['ttl'])) {
                    return false;
                }
                return $redis->expire($fullKey, $data['ttl']);

            case 'stats':
                // Return enhanced cache statistics
                $info = [];
                try {
                    if (method_exists($redis, 'info')) {
                        $info = $redis->info();
                    }
                } catch (\Throwable $e) {
                    // Redis cluster doesn't support info command
                }
                
                return array_merge($cacheStats, [
                    'slow_queries' => $slowQueries,
                    'redis_info' => $info
                ]);

            case 'performance':
                // Return performance metrics
                try {
                    $info = method_exists($redis, 'info') ? $redis->info() : [];
                    return [
                        'memory_usage' => $info['used_memory'] ?? 0,
                        'memory_peak' => $info['used_memory_peak'] ?? 0,
                        'hit_rate' => $cacheStats['hits'] > 0 ? 
                            ($cacheStats['hits'] / ($cacheStats['hits'] + $cacheStats['misses'])) * 100 : 0,
                        'operations_total' => $cacheStats['operations'],
                        'errors_total' => $cacheStats['errors'],
                        'slow_queries' => count($slowQueries)
                    ];
                } catch (\Throwable $e) {
                    return ['error' => $e->getMessage()];
                }

            case 'clear_pattern':
                // Clear keys matching a pattern
                if (!isset($data['pattern'])) {
                    return false;
                }
                $pattern = 'v2raysocks_traffic:' . $cacheVersion . ':' . $data['pattern'];
                
                if ($redis instanceof RedisCluster) {
                    // For cluster, we need to iterate through all nodes
                    $deleted = 0;
                    $masters = $redis->_masters();
                    foreach ($masters as $master) {
                        $keys = $redis->keys($pattern);
                        if (!empty($keys)) {
                            $deleted += $redis->del($keys);
                        }
                    }
                    return $deleted;
                } else {
                    $keys = $redis->keys($pattern);
                    if (!empty($keys)) {
                        return $redis->del($keys);
                    }
                    return true;
                }

            default:
                logActivity("V2RaySocks Traffic Monitor: Unknown Redis operation: " . $act, 0);
                return false;
        }
    } catch (RedisException $e) {
        $cacheStats['errors']++;
        $executionTime = microtime(true) - $startTime;
        
        // Track slow queries (> 100ms)
        if ($executionTime > 0.1) {
            $slowQueries[] = [
                'operation' => $act,
                'key' => $data['key'] ?? 'unknown',
                'execution_time' => $executionTime,
                'timestamp' => time(),
                'error' => $e->getMessage()
            ];
            
            // Keep only last 50 slow queries
            if (count($slowQueries) > 50) {
                array_shift($slowQueries);
            }
        }
        
        logActivity("V2RaySocks Traffic Monitor: Redis operation failed - " . $act . ": " . $e->getMessage(), 0);
        return false;
    } catch (\Throwable $e) {
        $cacheStats['errors']++;
        $executionTime = microtime(true) - $startTime;
        
        if ($executionTime > 0.1) {
            $slowQueries[] = [
                'operation' => $act,
                'key' => $data['key'] ?? 'unknown',
                'execution_time' => $executionTime,
                'timestamp' => time(),
                'error' => $e->getMessage()
            ];
            
            if (count($slowQueries) > 50) {
                array_shift($slowQueries);
            }
        }
        
        logActivity("V2RaySocks Traffic Monitor: Unexpected Redis error - " . $act . ": " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Get default TTL based on cache key type with dynamic adjustment
 */
function v2raysocks_traffic_getDefaultTTL($key)
{
    $currentHour = (int)date('H');
    $isBusinessHours = ($currentHour >= 8 && $currentHour <= 18);
    $multiplier = $isBusinessHours ? 0.8 : 1.2; // Shorter TTL during business hours
    
    // Real-time data - very short TTL
    if (strpos($key, 'live_stats') !== false || 
        strpos($key, 'traffic_5min') !== false ||
        strpos($key, 'real_time') !== false ||
        strpos($key, 'current_') !== false) {
        return max(30, (int)(60 * $multiplier)); // 30s-72s
    }
    
    // Frequently updated traffic data - short TTL
    if (strpos($key, 'traffic_data') !== false || 
        strpos($key, 'day_traffic') !== false ||
        strpos($key, 'enhanced_traffic') !== false ||
        strpos($key, 'hourly_') !== false) {
        return max(60, (int)(120 * $multiplier)); // 1-2.4 minutes
    }
    
    // Chart data - dynamic based on time sensitivity
    if (strpos($key, 'chart') !== false || 
        strpos($key, 'rankings') !== false ||
        strpos($key, 'stats_') !== false) {
        return max(120, (int)(180 * $multiplier)); // 2-3.6 minutes
    }
    
    // User/Node details - medium TTL with business hours adjustment
    if (strpos($key, 'user_details') !== false || 
        strpos($key, 'node_details') !== false ||
        strpos($key, 'usage_records') !== false ||
        strpos($key, 'profile_') !== false) {
        return max(180, (int)(300 * $multiplier)); // 3-6 minutes
    }
    
    // Configuration and static data - longer TTL
    if (strpos($key, 'all_nodes') !== false || 
        strpos($key, 'server_info') !== false ||
        strpos($key, 'config_') !== false ||
        strpos($key, 'settings_') !== false) {
        return max(300, (int)(600 * $multiplier)); // 5-12 minutes
    }
    
    // Historical data - very long TTL
    if (strpos($key, 'monthly_') !== false ||
        strpos($key, 'yearly_') !== false ||
        strpos($key, 'history_') !== false) {
        return max(1800, (int)(3600 * $multiplier)); // 30m-1.2h
    }
    
    // Default TTL with time adjustment
    return max(180, (int)(300 * $multiplier)); // 3-6 minutes
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
 * Preload frequently accessed cache data
 */
function v2raysocks_traffic_prewarmCache($dataTypes = [])
{
    if (empty($dataTypes)) {
        $dataTypes = ['live_stats', 'server_info', 'all_nodes'];
    }
    
    $prewarmed = 0;
    
    foreach ($dataTypes as $type) {
        try {
            switch ($type) {
                case 'live_stats':
                    // Pre-generate live statistics
                    $liveData = v2raysocks_traffic_generateLiveStats();
                    if ($liveData) {
                        v2raysocks_traffic_redisOperate('set', [
                            'key' => 'live_stats',
                            'value' => json_encode($liveData),
                            'ttl' => 60
                        ]);
                        $prewarmed++;
                    }
                    break;
                    
                case 'server_info':
                    // Pre-load server information
                    $serverInfo = v2raysocks_traffic_getServerInfo();
                    if ($serverInfo) {
                        v2raysocks_traffic_redisOperate('set', [
                            'key' => 'server_info',
                            'value' => json_encode($serverInfo),
                            'ttl' => 600
                        ]);
                        $prewarmed++;
                    }
                    break;
                    
                case 'all_nodes':
                    // Pre-load node list
                    $nodes = v2raysocks_traffic_getAllNodes();
                    if ($nodes) {
                        v2raysocks_traffic_redisOperate('set', [
                            'key' => 'all_nodes',
                            'value' => json_encode($nodes),
                            'ttl' => 600
                        ]);
                        $prewarmed++;
                    }
                    break;
            }
        } catch (\Throwable $e) {
            logActivity("V2RaySocks Traffic Monitor: Cache prewarming failed for {$type}: " . $e->getMessage(), 0);
        }
    }
    
    logActivity("V2RaySocks Traffic Monitor: Cache prewarming completed, {$prewarmed} entries preloaded", 0);
    return $prewarmed;
}

/**
 * Smart cache cleanup based on usage patterns and memory pressure
 */
function v2raysocks_traffic_smartCacheCleanup()
{
    $redis = v2raysocks_traffic_getRedisInstance();
    if (!$redis) {
        return false;
    }
    
    try {
        $info = method_exists($redis, 'info') ? $redis->info() : [];
        $memoryUsage = $info['used_memory'] ?? 0;
        $memoryLimit = $info['maxmemory'] ?? 0;
        
        // Only cleanup if memory usage is high
        if ($memoryLimit > 0 && $memoryUsage > ($memoryLimit * 0.8)) {
            $cleaned = 0;
            
            // Clean expired keys first
            if (method_exists($redis, 'eval')) {
                $script = "
                local keys = redis.call('keys', ARGV[1])
                local cleaned = 0
                for i=1,#keys do
                    if redis.call('ttl', keys[i]) == -1 then
                        redis.call('del', keys[i])
                        cleaned = cleaned + 1
                    end
                end
                return cleaned
                ";
                $cleaned += $redis->eval($script, ['v2raysocks_traffic:*'], 1);
            }
            
            // Clean old temporary data
            $patterns = [
                'v2raysocks_traffic:*:temp_*',
                'v2raysocks_traffic:*:cache_*'
            ];
            
            foreach ($patterns as $pattern) {
                $keys = $redis->keys($pattern);
                if (!empty($keys)) {
                    $deleted = $redis->del($keys);
                    $cleaned += $deleted;
                }
            }
            
            logActivity("V2RaySocks Traffic Monitor: Smart cache cleanup completed, {$cleaned} keys removed", 0);
            return $cleaned;
        }
        
        return 0;
    } catch (\Throwable $e) {
        logActivity("V2RaySocks Traffic Monitor: Smart cache cleanup failed: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Get Redis performance and monitoring metrics
 */
function v2raysocks_traffic_getRedisPerformance()
{
    $redis = v2raysocks_traffic_getRedisInstance();
    if (!$redis) {
        return false;
    }
    
    try {
        $performance = [];
        
        // Get basic stats
        $stats = v2raysocks_traffic_redisOperate('stats', []);
        if ($stats) {
            $performance['cache_stats'] = $stats;
        }
        
        // Get Redis info if available
        if (method_exists($redis, 'info')) {
            $info = $redis->info();
            $performance['redis_info'] = [
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
            ];
            
            // Calculate hit rate
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            $performance['redis_info']['hit_rate'] = $total > 0 ? ($hits / $total) * 100 : 0;
        }
        
        // Get slow queries
        $slowQueries = v2raysocks_traffic_redisOperate('stats', [])['slow_queries'] ?? [];
        $performance['slow_queries'] = array_slice($slowQueries, -10); // Last 10 slow queries
        
        return $performance;
    } catch (\Throwable $e) {
        logActivity("V2RaySocks Traffic Monitor: Failed to get Redis performance metrics: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Placeholder functions for data generation (these would be implemented based on actual data sources)
 */
function v2raysocks_traffic_generateLiveStats()
{
    // This would be implemented to generate actual live statistics
    return [
        'timestamp' => time(),
        'active_users' => 0,
        'total_traffic' => 0,
        'nodes_online' => 0
    ];
}

function v2raysocks_traffic_getServerInfo()
{
    // This would be implemented to get actual server information
    return [
        'server_time' => time(),
        'version' => '2025-01-01',
        'status' => 'online'
    ];
}

function v2raysocks_traffic_getAllNodes()
{
    // This would be implemented to get actual node list
    return [];
}