<?php
/**
 * Redis Integration Test for V2RaySocks Traffic Monitor
 * 
 * This script provides a quick verification of Redis functionality
 * Run: php redis_integration_test.php
 */

if (!defined("WHMCS")) {
    define("WHMCS", true);
}

// Mock functions if not in WHMCS environment
if (!function_exists('logActivity')) {
    function logActivity($message, $userId = 0) {
        echo "[LOG] " . $message . "\n";
    }
}

// Mock WHMCS Database if not available
if (!class_exists('WHMCS\Database\Capsule')) {
    eval('
    namespace WHMCS\Database {
        class Capsule {
            public static function table($tableName) {
                return new \MockQueryBuilder();
            }
        }
    }
    ');
    
    class MockQueryBuilder {
        public function where($column, $value) { return $this; }
        public function whereIn($column, $values) { return $this; }
        public function pluck($column, $key = null) { 
            return [
                'redis_ip' => '127.0.0.1',
                'redis_port' => '6379',
                'redis_password' => ''
            ];
        }
        public function value($column) { return 'english'; }
    }
}

// Include Redis integration
require_once __DIR__ . '/lib/Monitor_Redis.php';

class QuickRedisTest {
    
    public function runQuickTest() {
        echo "==========================================\n";
        echo "Redis Integration Quick Test\n";
        echo "==========================================\n\n";
        
        $allPassed = true;
        
        // Test 1: Extension check
        echo "1. Checking Redis PHP extension... ";
        if (extension_loaded('redis')) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL - Redis extension not loaded\n";
            $allPassed = false;
        }
        
        // Test 2: Connection
        echo "2. Testing Redis connection... ";
        $redis = v2raysocks_traffic_getRedisInstance();
        if ($redis !== false) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL - Cannot connect to Redis\n";
            $allPassed = false;
            return $allPassed;
        }
        
        // Test 3: Basic operations
        echo "3. Testing basic operations... ";
        $testKey = 'quick_test_' . time();
        $testValue = 'test_value_' . rand(1000, 9999);
        
        $setResult = v2raysocks_traffic_redisOperate('set', [
            'key' => $testKey,
            'value' => $testValue,
            'ttl' => 60
        ]);
        
        $getResult = v2raysocks_traffic_redisOperate('get', ['key' => $testKey]);
        
        if ($setResult && $getResult === $testValue) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL - Basic operations failed\n";
            $allPassed = false;
        }
        
        // Test 4: TTL functionality
        echo "4. Testing TTL functionality... ";
        $ttl = v2raysocks_traffic_getDefaultTTL('live_stats_test');
        if ($ttl === 60) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL - TTL calculation incorrect\n";
            $allPassed = false;
        }
        
        // Test 5: Cache stats
        echo "5. Testing cache statistics... ";
        $stats = v2raysocks_traffic_redisOperate('stats', []);
        if (is_array($stats) && isset($stats['hits'])) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL - Cache stats unavailable\n";
            $allPassed = false;
        }
        
        // Test 6: Pattern clearing
        echo "6. Testing pattern clearing... ";
        v2raysocks_traffic_redisOperate('set', ['key' => 'pattern_test_1', 'value' => 'test', 'ttl' => 60]);
        v2raysocks_traffic_redisOperate('set', ['key' => 'pattern_test_2', 'value' => 'test', 'ttl' => 60]);
        
        $clearResult = v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'pattern_test_*']);
        if ($clearResult) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL - Pattern clearing failed\n";
            $allPassed = false;
        }
        
        // Clean up
        v2raysocks_traffic_redisOperate('del', ['key' => $testKey]);
        
        echo "\n==========================================\n";
        if ($allPassed) {
            echo "🎉 ALL TESTS PASSED - Redis integration is working!\n";
            
            // Show performance info
            if ($redis instanceof Redis) {
                $info = $redis->info();
                echo "\nRedis Info:\n";
                echo "  Version: {$info['redis_version']}\n";
                echo "  Memory: {$info['used_memory_human']}\n";
                echo "  Commands: {$info['total_commands_processed']}\n";
            }
            
        } else {
            echo "❌ SOME TESTS FAILED - Check Redis configuration\n";
            echo "\nTroubleshooting:\n";
            echo "  1. Ensure Redis server is running: redis-cli ping\n";
            echo "  2. Check PHP Redis extension: php -m | grep redis\n";
            echo "  3. Verify Redis configuration in WHMCS addon settings\n";
        }
        echo "==========================================\n\n";
        
        return $allPassed;
    }
}

// Run the quick test if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new QuickRedisTest();
    $test->runQuickTest();
}