<?php

/**
 * Simple test script for Redis cache performance optimization features
 * 
 * This script validates the core optimization functionality without requiring
 * full WHMCS environment setup.
 */

// Simulated test environment
define("WHMCS", true);

// Mock the required functions that would normally come from WHMCS
if (!function_exists('logActivity')) {
    function logActivity($message, $level) {
        echo "[LOG] $message\n";
    }
}

// Include the Redis optimization functions
require_once __DIR__ . '/v2raysocks_traffic/lib/Monitor_Redis.php';

/**
 * Test Redis connection and basic operations
 */
function testRedisConnection() {
    echo "\n=== Testing Redis Connection ===\n";
    
    $redis = v2raysocks_traffic_getRedisInstance();
    if ($redis instanceof Redis) {
        echo "✓ Redis connection successful\n";
        
        // Test ping
        $ping = v2raysocks_traffic_redisOperate('ping', []);
        echo $ping ? "✓ Redis ping successful\n" : "✗ Redis ping failed\n";
        
        return true;
    } else {
        echo "✗ Redis connection failed\n";
        return false;
    }
}

/**
 * Test enhanced TTL strategy
 */
function testEnhancedTTL() {
    echo "\n=== Testing Enhanced TTL Strategy ===\n";
    
    // Test different cache key types and contexts
    $testCases = [
        [
            'key' => 'live_stats',
            'context' => ['data_type' => 'live_stats', 'access_frequency' => 'high'],
            'expected_range' => [30, 70]
        ],
        [
            'key' => 'language_config',
            'context' => ['data_type' => 'config', 'priority' => 'critical'],
            'expected_range' => [600, 900]
        ],
        [
            'key' => 'traffic_data_today',
            'context' => ['time_range' => 'today', 'access_frequency' => 'high'],
            'expected_range' => [60, 120]
        ],
        [
            'key' => 'user_rankings',
            'context' => ['data_type' => 'rankings', 'priority' => 'normal'],
            'expected_range' => [120, 300]
        ]
    ];
    
    foreach ($testCases as $test) {
        $ttl = v2raysocks_traffic_getDefaultTTL($test['key'], $test['context']);
        $inRange = $ttl >= $test['expected_range'][0] && $ttl <= $test['expected_range'][1];
        
        echo ($inRange ? "✓" : "✗") . " TTL for '{$test['key']}': {$ttl}s (expected: {$test['expected_range'][0]}-{$test['expected_range'][1]}s)\n";
    }
}

/**
 * Test cache statistics functionality
 */
function testCacheStatistics() {
    echo "\n=== Testing Cache Statistics ===\n";
    
    // Test basic cache stats
    $stats = v2raysocks_traffic_redisOperate('stats', []);
    if (is_array($stats)) {
        $requiredKeys = ['hits', 'misses', 'sets', 'errors'];
        $hasAllKeys = true;
        
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $stats)) {
                $hasAllKeys = false;
                break;
            }
        }
        
        echo ($hasAllKeys ? "✓" : "✗") . " Basic cache statistics structure\n";
        echo "  Hits: {$stats['hits']}, Misses: {$stats['misses']}, Sets: {$stats['sets']}, Errors: {$stats['errors']}\n";
    } else {
        echo "✗ Failed to get cache statistics\n";
    }
}

/**
 * Test memory monitoring functionality
 */
function testMemoryMonitoring() {
    echo "\n=== Testing Memory Monitoring ===\n";
    
    $memInfo = v2raysocks_traffic_redisOperate('memory_info', []);
    if (is_array($memInfo)) {
        $requiredKeys = ['used_memory', 'used_memory_rss', 'mem_fragmentation_ratio'];
        $hasAllKeys = true;
        
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $memInfo)) {
                $hasAllKeys = false;
                break;
            }
        }
        
        echo ($hasAllKeys ? "✓" : "✗") . " Memory monitoring structure\n";
        
        if (isset($memInfo['mem_fragmentation_ratio'])) {
            $ratio = $memInfo['mem_fragmentation_ratio'];
            $status = v2raysocks_traffic_assessFragmentation($ratio);
            echo "  Fragmentation ratio: {$ratio} ({$status['status']})\n";
        }
    } else {
        echo "✗ Memory monitoring not available (Redis version may not support INFO memory)\n";
    }
}

/**
 * Test key analysis functionality
 */
function testKeyAnalysis() {
    echo "\n=== Testing Key Analysis ===\n";
    
    $analysis = v2raysocks_traffic_redisOperate('key_analysis', []);
    if (is_array($analysis)) {
        echo "✓ Key analysis completed\n";
        echo "  Total keys: {$analysis['total_keys']}\n";
        
        if (isset($analysis['patterns']) && is_array($analysis['patterns'])) {
            echo "  Patterns found: " . count($analysis['patterns']) . "\n";
            foreach ($analysis['patterns'] as $pattern => $info) {
                echo "    $pattern: {$info['count']} keys\n";
            }
        }
    } else {
        echo "✗ Key analysis failed\n";
    }
}

/**
 * Test cache operations with some sample data
 */
function testCacheOperations() {
    echo "\n=== Testing Cache Operations ===\n";
    
    // Test individual cache operations
    $testKey = 'test_optimization_' . time();
    $testValue = json_encode(['test' => true, 'timestamp' => time()]);
    
    // Test SET operation
    $setResult = v2raysocks_traffic_redisOperate('set', [
        'key' => $testKey,
        'value' => $testValue,
        'ttl' => 60
    ]);
    echo ($setResult ? "✓" : "✗") . " SET operation\n";
    
    // Test GET operation
    $getValue = v2raysocks_traffic_redisOperate('get', ['key' => $testKey]);
    $getSuccess = $getValue === $testValue;
    echo ($getSuccess ? "✓" : "✗") . " GET operation\n";
    
    // Test EXISTS operation
    $exists = v2raysocks_traffic_redisOperate('exists', ['key' => $testKey]);
    echo ($exists ? "✓" : "✗") . " EXISTS operation\n";
    
    // Test DELETE operation
    $delResult = v2raysocks_traffic_redisOperate('del', ['key' => $testKey]);
    echo ($delResult ? "✓" : "✗") . " DEL operation\n";
}

/**
 * Test batch operations for pipeline optimization
 */
function testBatchOperations() {
    echo "\n=== Testing Batch Operations ===\n";
    
    $operations = [];
    for ($i = 1; $i <= 3; $i++) {
        $operations[] = [
            'key' => "batch_test_$i",
            'value' => json_encode(['batch' => true, 'id' => $i]),
            'ttl' => 60,
            'context' => ['access_frequency' => 'normal']
        ];
    }
    
    $batchResult = v2raysocks_traffic_redisOperate('pipeline_set', [
        'operations' => $operations
    ]);
    
    $success = is_array($batchResult) && count($batchResult) === 3;
    echo ($success ? "✓" : "✗") . " Batch pipeline operations\n";
    
    if ($success) {
        // Clean up test keys
        for ($i = 1; $i <= 3; $i++) {
            v2raysocks_traffic_redisOperate('del', ['key' => "batch_test_$i"]);
        }
    }
}

/**
 * Main test runner
 */
function runOptimizationTests() {
    echo "Redis Cache Performance Optimization Tests\n";
    echo "==========================================\n";
    
    if (!testRedisConnection()) {
        echo "\nRedis connection failed. Please ensure Redis is running and accessible.\n";
        echo "Default connection: localhost:6379 (no password)\n";
        return false;
    }
    
    testEnhancedTTL();
    testCacheStatistics();
    testMemoryMonitoring();
    testKeyAnalysis();
    testCacheOperations();
    testBatchOperations();
    
    echo "\n=== Test Summary ===\n";
    echo "All core optimization features tested.\n";
    echo "Check the output above for any failed tests (marked with ✗).\n";
    echo "\nFor full functionality testing, access the Performance Dashboard\n";
    echo "through WHMCS: addonmodules.php?module=v2raysocks_traffic&action=performance_dashboard\n";
    
    return true;
}

// Run tests if script is executed directly
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    runOptimizationTests();
}