<?php
/**
 * V2RaySocks Monitoring Module Debug Helper
 * 
 * This file can be temporarily placed in the module directory to help debug
 * connection and data retrieval issues. 
 * 
 * WARNING: Remove this file after debugging as it may expose sensitive information
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly - must be accessed through WHMCS");
}

require_once(__DIR__ . '/lib/Monitor_DB.php');

/**
 * Debug function to test the monitoring module functionality
 */
function v2raysocks_traffic_debugInfo()
{
    $debug = [
        'timestamp' => date('Y-m-d H:i:s'),
        'tests' => []
    ];
    
    // Test 1: Check module configuration
    $debug['tests']['module_config'] = [
        'name' => 'Module Configuration',
        'status' => 'unknown',
        'details' => []
    ];
    
    try {
        $serverInfo = v2raysocks_traffic_serverInfo();
        if ($serverInfo) {
            $debug['tests']['module_config']['status'] = 'pass';
            $debug['tests']['module_config']['details'] = [
                'server_ip' => $serverInfo->ipaddress,
                'database_name' => $serverInfo->name,
                'username' => $serverInfo->username,
                'has_password' => !empty($serverInfo->password)
            ];
        } else {
            $debug['tests']['module_config']['status'] = 'fail';
            $debug['tests']['module_config']['details'] = ['error' => 'No V2RaySocks server configured'];
        }
    } catch (Exception $e) {
        $debug['tests']['module_config']['status'] = 'error';
        $debug['tests']['module_config']['details'] = ['error' => $e->getMessage()];
    }
    
    // Test 2: Database connection
    $debug['tests']['database_connection'] = [
        'name' => 'Database Connection',
        'status' => 'unknown',
        'details' => []
    ];
    
    try {
        $pdo = v2raysocks_traffic_createPDO();
        if ($pdo) {
            $debug['tests']['database_connection']['status'] = 'pass';
            $debug['tests']['database_connection']['details'] = ['message' => 'Connection successful'];
        } else {
            $debug['tests']['database_connection']['status'] = 'fail';
            $debug['tests']['database_connection']['details'] = ['error' => 'Failed to create PDO connection'];
        }
    } catch (Exception $e) {
        $debug['tests']['database_connection']['status'] = 'error';
        $debug['tests']['database_connection']['details'] = ['error' => $e->getMessage()];
    }
    
    // Test 3: Database structure validation
    $debug['tests']['database_structure'] = [
        'name' => 'Database Structure',
        'status' => 'unknown',
        'details' => []
    ];
    
    if (isset($pdo) && $pdo) {
        try {
            if (v2raysocks_traffic_validateDatabaseStructure($pdo)) {
                $debug['tests']['database_structure']['status'] = 'pass';
                $debug['tests']['database_structure']['details'] = ['message' => 'All required tables and columns found'];
            } else {
                $debug['tests']['database_structure']['status'] = 'fail';
                $debug['tests']['database_structure']['details'] = ['error' => 'Missing required tables or columns'];
            }
        } catch (Exception $e) {
            $debug['tests']['database_structure']['status'] = 'error';
            $debug['tests']['database_structure']['details'] = ['error' => $e->getMessage()];
        }
    } else {
        $debug['tests']['database_structure']['status'] = 'skip';
        $debug['tests']['database_structure']['details'] = ['message' => 'Skipped due to database connection failure'];
    }
    
    // Test 4: Sample data queries
    $debug['tests']['sample_queries'] = [
        'name' => 'Sample Data Queries',
        'status' => 'unknown',
        'details' => []
    ];
    
    if (isset($pdo) && $pdo) {
        try {
            $queries = [
                'users' => 'SELECT COUNT(*) as count FROM user',
                'nodes' => 'SELECT COUNT(*) as count FROM node',
                'usage_records' => 'SELECT COUNT(*) as count FROM user_usage',
                'recent_usage' => 'SELECT COUNT(*) as count FROM user_usage WHERE t >= ' . (time() - 3600)
            ];
            
            $results = [];
            foreach ($queries as $name => $sql) {
                try {
                    $stmt = $pdo->query($sql);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $results[$name] = $result['count'];
                } catch (Exception $e) {
                    $results[$name] = 'Error: ' . $e->getMessage();
                }
            }
            
            $debug['tests']['sample_queries']['status'] = 'pass';
            $debug['tests']['sample_queries']['details'] = $results;
            
        } catch (Exception $e) {
            $debug['tests']['sample_queries']['status'] = 'error';
            $debug['tests']['sample_queries']['details'] = ['error' => $e->getMessage()];
        }
    } else {
        $debug['tests']['sample_queries']['status'] = 'skip';
        $debug['tests']['sample_queries']['details'] = ['message' => 'Skipped due to database connection failure'];
    }
    
    // Test 5: Live stats function
    $debug['tests']['live_stats'] = [
        'name' => 'Live Stats Function',
        'status' => 'unknown',
        'details' => []
    ];
    
    try {
        $liveStats = v2raysocks_traffic_getLiveStats();
        if (!empty($liveStats)) {
            $debug['tests']['live_stats']['status'] = 'pass';
            $debug['tests']['live_stats']['details'] = [
                'has_data' => true,
                'total_users' => $liveStats['total_users'] ?? 'N/A',
                'total_nodes' => $liveStats['total_nodes'] ?? 'N/A',
                'today_upload' => $liveStats['today_upload'] ?? 'N/A',
                'today_download' => $liveStats['today_download'] ?? 'N/A',
                'has_error' => isset($liveStats['error'])
            ];
            if (isset($liveStats['error'])) {
                $debug['tests']['live_stats']['details']['error'] = $liveStats['error'];
            }
        } else {
            $debug['tests']['live_stats']['status'] = 'fail';
            $debug['tests']['live_stats']['details'] = ['error' => 'No data returned'];
        }
    } catch (Exception $e) {
        $debug['tests']['live_stats']['status'] = 'error';
        $debug['tests']['live_stats']['details'] = ['error' => $e->getMessage()];
    }
    
    return $debug;
}

/**
 * HTML output for debug information
 */
function v2raysocks_traffic_debugPage()
{
    $debug = v2raysocks_traffic_debugInfo();
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>V2RaySocks Monitor Debug</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .debug-container { max-width: 800px; margin: 0 auto; }
            .test-result { margin: 10px 0; padding: 15px; border-radius: 5px; }
            .test-pass { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
            .test-fail { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
            .test-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
            .test-skip { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
            .test-unknown { background: #f8f9fa; border: 1px solid #dee2e6; color: #6c757d; }
            .details { margin-top: 10px; font-size: 0.9em; }
            .details pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
            h1 { color: #333; }
            h2 { color: #666; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class="debug-container">
            <h1>V2RaySocks Monitor Debug Information</h1>
            <p>Generated: ' . $debug['timestamp'] . '</p>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong>Warning:</strong> This debug page should only be used for troubleshooting. 
                Remove or disable it after debugging to prevent exposing sensitive information.
            </div>';
    
    foreach ($debug['tests'] as $testKey => $test) {
        $statusClass = 'test-' . $test['status'];
        $statusText = ucfirst($test['status']);
        
        $html .= '
            <div class="test-result ' . $statusClass . '">
                <h3>' . $test['name'] . ' - ' . $statusText . '</h3>
                <div class="details">
                    <pre>' . htmlspecialchars(json_encode($test['details'], JSON_PRETTY_PRINT)) . '</pre>
                </div>
            </div>';
    }
    
    $html .= '
            <h2>Next Steps</h2>
            <div class="test-result test-unknown">
                <h3>Troubleshooting Guide</h3>
                <div class="details">
                    <ul>
                        <li><strong>Module Configuration Failure:</strong> Check WHMCS admin -> Setup -> Addon Modules -> V2RaySocks Monitor settings</li>
                        <li><strong>Database Connection Failure:</strong> Verify server credentials, network connectivity, and database permissions</li>
                        <li><strong>Database Structure Issues:</strong> Ensure you\'re connecting to a valid V2RaySocks database with the required tables</li>
                        <li><strong>No Data:</strong> Check if there are users and traffic records in the V2RaySocks database</li>
                        <li><strong>Live Stats Errors:</strong> Review WHMCS activity log for detailed error messages</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>