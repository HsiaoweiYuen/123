<?php
/**
 * Cache Optimization Test for V2RaySocks Traffic Monitor
 * 
 * This script validates the new Redis caching functions added for optimization
 * Run: php test_cache_optimization.php
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
        public function value($column) { 
            if ($column === 'value') {
                return 'english';
            }
            return '1'; 
        }
        public function get() {
            return [
                (object)['id' => 1, 'name' => 'Test Server 1'],
                (object)['id' => 2, 'name' => 'Test Server 2']
            ];
        }
        public function first() {
            return (object)[
                'id' => 1,
                'name' => 'Test V2RaySocks Server',
                'ipaddress' => '127.0.0.1',
                'username' => 'test_user',
                'password' => 'test_pass',
                'type' => 'V2RaySocks'
            ];
        }
    }
}

// Mock functions if needed
if (!function_exists('decrypt')) {
    function decrypt($value) {
        return $value;
    }
}

// Include the Redis module only
require_once __DIR__ . '/lib/Monitor_Redis.php';

class CacheOptimizationTest {
    
    private $testResults = [];
    
    public function runTests() {
        echo "==========================================\n";
        echo "Cache Optimization Test\n";
        echo "==========================================\n\n";
        
        $allPassed = true;
        
        // Test Redis connection first
        echo "0. Checking Redis connection... ";
        $redis = v2raysocks_traffic_getRedisInstance();
        if ($redis === false) {
            echo "❌ FAIL - Redis not available, skipping cache tests\n";
            return false;
        } else {
            echo "✅ PASS\n";
        }
        
        // Clear any existing cache before testing
        v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => '*']);
        
        // Test 1: Cache basic operations
        echo "1. Testing basic cache operations... ";
        $allPassed = $this->testBasicCacheOperations() && $allPassed;
        
        // Test 2: Cache TTL functionality
        echo "2. Testing cache TTL for new function types... ";
        $allPassed = $this->testCacheTTLFunctionality() && $allPassed;
        
        // Test 3: Cache key patterns
        echo "3. Testing cache key patterns for new functions... ";
        $allPassed = $this->testCacheKeyPatterns() && $allPassed;
        
        // Test 4: Cache performance with multiple operations
        echo "4. Testing cache performance improvements... ";
        $allPassed = $this->testCachePerformance() && $allPassed;
        
        // Test 5: Cache statistics
        echo "5. Testing cache statistics tracking... ";
        $allPassed = $this->testCacheStatistics() && $allPassed;
        
        echo "\n==========================================\n";
        if ($allPassed) {
            echo "🎉 ALL CACHE OPTIMIZATION TESTS PASSED!\n";
            $this->showCacheStats();
        } else {
            echo "❌ SOME TESTS FAILED - Review cache implementation\n";
            $this->showFailures();
        }
        echo "==========================================\n\n";
        
        return $allPassed;
    }
    
    private function testBasicCacheOperations() {
        try {
            // Test cache set and get for new function types
            $testKeys = [
                'server_info_config',
                'language_config', 
                'server_options_list',
                'user_traffic_chart_test'
            ];
            
            foreach ($testKeys as $key) {
                $testData = ['test' => 'data_' . $key, 'timestamp' => time()];
                
                // Set cache
                $setResult = v2raysocks_traffic_redisOperate('set', [
                    'key' => $key,
                    'value' => json_encode($testData),
                    'ttl' => 300
                ]);
                
                if (!$setResult) {
                    echo "❌ FAIL - Could not set cache for $key\n";
                    return false;
                }
                
                // Get cache
                $getData = v2raysocks_traffic_redisOperate('get', ['key' => $key]);
                $decodedData = json_decode($getData, true);
                
                if ($decodedData !== $testData) {
                    echo "❌ FAIL - Cache data mismatch for $key\n";
                    return false;
                }
                
                // Clean up
                v2raysocks_traffic_redisOperate('del', ['key' => $key]);
            }
            
            echo "✅ PASS\n";
            return true;
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function testCacheTTLFunctionality() {
        try {
            // Test default TTL calculation for different function types
            $expectedTTLs = [
                'server_info_config' => 600,  // Configuration data
                'language_config' => 600,     // Language data
                'server_options_list' => 600, // Server options
                'user_traffic_chart_today' => 180,  // Chart data
                'live_stats' => 60            // Real-time data
            ];
            
            foreach ($expectedTTLs as $keyType => $expectedTTL) {
                $actualTTL = v2raysocks_traffic_getDefaultTTL($keyType);
                if ($actualTTL !== $expectedTTL) {
                    echo "❌ FAIL - TTL mismatch for $keyType: expected $expectedTTL, got $actualTTL\n";
                    return false;
                }
            }
            
            echo "✅ PASS\n";
            return true;
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function testCacheKeyPatterns() {
        try {
            // Test cache key existence and pattern clearing
            $testKeys = [
                'test_server_info_1',
                'test_server_info_2',
                'test_language_data',
                'test_chart_data'
            ];
            
            // Set multiple keys
            foreach ($testKeys as $key) {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $key,
                    'value' => 'test_data',
                    'ttl' => 60
                ]);
            }
            
            // Test pattern clearing
            $clearResult = v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'test_server_info_*']);
            if (!$clearResult) {
                echo "❌ FAIL - Pattern clearing failed\n";
                return false;
            }
            
            // Verify specific keys were cleared
            $data1 = v2raysocks_traffic_redisOperate('get', ['key' => 'test_server_info_1']);
            $data2 = v2raysocks_traffic_redisOperate('get', ['key' => 'test_language_data']);
            
            if ($data1 !== false) {
                echo "❌ FAIL - Pattern clearing didn't work correctly\n";
                return false;
            }
            
            if ($data2 === false) {
                echo "❌ FAIL - Pattern clearing was too broad\n";
                return false;
            }
            
            // Clean up remaining keys
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'test_*']);
            
            echo "✅ PASS\n";
            return true;
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function testCachePerformance() {
        try {
            $testKey = 'performance_test_' . time();
            $testData = str_repeat('test_data_', 100); // Larger data set
            
            // Measure cache write performance
            $start = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $testKey . '_' . $i,
                    'value' => $testData,
                    'ttl' => 300
                ]);
            }
            $writeTime = microtime(true) - $start;
            
            // Measure cache read performance
            $start = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                v2raysocks_traffic_redisOperate('get', ['key' => $testKey . '_' . $i]);
            }
            $readTime = microtime(true) - $start;
            
            // Clean up
            for ($i = 0; $i < 10; $i++) {
                v2raysocks_traffic_redisOperate('del', ['key' => $testKey . '_' . $i]);
            }
            
            // Performance should be reasonable (less than 1 second for 10 operations)
            if ($writeTime > 1.0 || $readTime > 1.0) {
                echo "❌ FAIL - Cache operations too slow (write: {$writeTime}s, read: {$readTime}s)\n";
                return false;
            }
            
            echo "✅ PASS (write: " . round($writeTime * 1000, 2) . "ms, read: " . round($readTime * 1000, 2) . "ms)\n";
            return true;
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function testCacheStatistics() {
        try {
            // Reset stats by running some operations
            v2raysocks_traffic_redisOperate('set', ['key' => 'stats_test', 'value' => 'test', 'ttl' => 60]);
            v2raysocks_traffic_redisOperate('get', ['key' => 'stats_test']);
            v2raysocks_traffic_redisOperate('get', ['key' => 'nonexistent_key']);
            
            $stats = v2raysocks_traffic_redisOperate('stats', []);
            
            if (!is_array($stats) || !isset($stats['hits']) || !isset($stats['misses']) || !isset($stats['sets'])) {
                echo "❌ FAIL - Cache statistics not available\n";
                return false;
            }
            
            if ($stats['sets'] < 1 || $stats['hits'] < 1 || $stats['misses'] < 1) {
                echo "❌ FAIL - Cache statistics not tracking correctly\n";
                return false;
            }
            
            // Clean up
            v2raysocks_traffic_redisOperate('del', ['key' => 'stats_test']);
            
            echo "✅ PASS (hits: {$stats['hits']}, misses: {$stats['misses']}, sets: {$stats['sets']})\n";
            return true;
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function showCacheStats() {
        $stats = v2raysocks_traffic_redisOperate('stats', []);
        if ($stats) {
            echo "\nFinal Cache Performance Stats:\n";
            echo "  Hits: {$stats['hits']}\n";
            echo "  Misses: {$stats['misses']}\n";
            echo "  Sets: {$stats['sets']}\n";
            echo "  Errors: {$stats['errors']}\n";
            $totalRequests = $stats['hits'] + $stats['misses'];
            if ($totalRequests > 0) {
                $hitRate = ($stats['hits'] / $totalRequests) * 100;
                echo "  Hit Rate: " . round($hitRate, 2) . "%\n";
            }
        }
    }
    
    private function showFailures() {
        if (!empty($this->testResults)) {
            echo "\nDetailed Failure Information:\n";
            foreach ($this->testResults as $test => $result) {
                if (!$result['passed']) {
                    echo "  $test: {$result['message']}\n";
                }
            }
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CacheOptimizationTest();
    $test->runTests();
}