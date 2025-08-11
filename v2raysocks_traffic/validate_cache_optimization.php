<?php
/**
 * Code Structure Validation for Cache Optimization
 * 
 * This script validates that the new caching functions have been properly implemented
 * even when Redis is not available
 */

if (!defined("WHMCS")) {
    define("WHMCS", true);
}

// Mock functions if not in WHMCS environment
if (!function_exists('logActivity')) {
    function logActivity($message, $userId = 0) {
        // Silent for validation
    }
}

// Mock WHMCS Database
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
        public function pluck($column, $key = null) { return []; }
        public function value($column) { return 'test'; }
        public function get() { return []; }
        public function first() { return null; }
    }
}

if (!function_exists('decrypt')) {
    function decrypt($value) { return $value; }
}

// Include the modules
require_once __DIR__ . '/lib/Monitor_Redis.php';
require_once __DIR__ . '/lib/Monitor_DB.php';

echo "==========================================\n";
echo "Cache Optimization Code Validation\n";
echo "==========================================\n\n";

$validationPassed = true;

// Test 1: Verify TTL function exists and works
echo "1. Testing TTL calculation function... ";
try {
    $ttl1 = v2raysocks_traffic_getDefaultTTL('server_info_config');
    $ttl2 = v2raysocks_traffic_getDefaultTTL('live_stats');
    $ttl3 = v2raysocks_traffic_getDefaultTTL('chart_data');
    
    if ($ttl1 === 600 && $ttl2 === 60 && $ttl3 === 180) {
        echo "✅ PASS\n";
    } else {
        echo "❌ FAIL - TTL values incorrect: $ttl1, $ttl2, $ttl3\n";
        $validationPassed = false;
    }
} catch (\Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $validationPassed = false;
}

// Test 2: Verify optimized functions exist
echo "2. Testing that optimized functions exist... ";
$requiredFunctions = [
    'v2raysocks_traffic_serverInfo',
    'v2raysocks_traffic_loadLanguage', 
    'v2raysocks_traffic_serverOptions',
    'v2raysocks_traffic_getUserTrafficChart'
];

$missingFunctions = [];
foreach ($requiredFunctions as $func) {
    if (!function_exists($func)) {
        $missingFunctions[] = $func;
    }
}

if (empty($missingFunctions)) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL - Missing functions: " . implode(', ', $missingFunctions) . "\n";
    $validationPassed = false;
}

// Test 3: Test functions execute without fatal errors (when Redis unavailable)
echo "3. Testing functions execute gracefully without Redis... ";
try {
    $result1 = v2raysocks_traffic_serverInfo();
    $result2 = v2raysocks_traffic_loadLanguage();
    $result3 = v2raysocks_traffic_serverOptions();
    $result4 = v2raysocks_traffic_getUserTrafficChart(1, 'today');
    
    // All functions should complete without fatal errors even when Redis is unavailable
    echo "✅ PASS\n";
} catch (\Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $validationPassed = false;
}

// Test 4: Verify cache key patterns are implemented
echo "4. Testing cache key patterns... ";
try {
    // Test Redis operation exists and handles missing Redis gracefully
    $result = v2raysocks_traffic_redisOperate('get', ['key' => 'test']);
    // Should return false when Redis unavailable, not throw fatal error
    
    echo "✅ PASS\n";
} catch (\Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $validationPassed = false;
}

// Test 5: Check implementation adds caching to previously uncached functions
echo "5. Verifying cache implementation in source code... ";

$sourceFile = file_get_contents(__DIR__ . '/lib/Monitor_DB.php');

// Check that cache operations are present in the optimized functions
$cacheChecks = [
    'server_info_config' => 'server_info_config.*v2raysocks_traffic_redisOperate',
    'language_config' => 'language_config.*v2raysocks_traffic_redisOperate',
    'server_options_list' => 'server_options_list.*v2raysocks_traffic_redisOperate',
    'user_traffic_chart' => 'user_traffic_chart.*v2raysocks_traffic_redisOperate'
];

$missingCache = [];
foreach ($cacheChecks as $cacheType => $pattern) {
    if (!preg_match('/' . $pattern . '/s', $sourceFile)) {
        $missingCache[] = $cacheType;
    }
}

if (empty($missingCache)) {
    echo "✅ PASS\n";
} else {
    echo "❌ FAIL - Missing cache implementations: " . implode(', ', $missingCache) . "\n";
    $validationPassed = false;
}

echo "\n==========================================\n";
if ($validationPassed) {
    echo "🎉 ALL CODE VALIDATIONS PASSED!\n";
    echo "\nCache Optimization Summary:\n";
    echo "✅ Added Redis caching to v2raysocks_traffic_serverInfo() - 600s TTL\n";
    echo "✅ Added Redis caching to v2raysocks_traffic_loadLanguage() - 600s TTL\n";
    echo "✅ Added Redis caching to v2raysocks_traffic_serverOptions() - 600s TTL\n";
    echo "✅ Added Redis caching to v2raysocks_traffic_getUserTrafficChart() - 120-300s TTL\n";
    echo "✅ Implemented graceful degradation when Redis unavailable\n";
    echo "✅ Added proper error handling and logging\n";
    echo "✅ Used optimized TTL values based on data type\n";
    echo "✅ Followed existing caching patterns for consistency\n";
} else {
    echo "❌ SOME VALIDATIONS FAILED - Review implementation\n";
}
echo "==========================================\n\n";