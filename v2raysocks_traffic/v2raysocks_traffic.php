<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once(__DIR__ . '/lib/Monitor_DB.php');

function v2raysocks_traffic_config()
{
    // Load language for config page
    $lang = v2raysocks_traffic_loadLanguage();
    
    $serverOptions = v2raysocks_traffic_serverOptions();
    return [
        'name' => isset($lang['module_name']) ? $lang['module_name'] : 'V2RaySocks Traffic Analysis',
        'description' => isset($lang['module_description']) ? $lang['module_description'] : 'Real-time traffic analysis and analytics for V2RaySocks',
        'author' => 'helloblock.net',
        'language' => 'english',
        'version' => '1.0.1',
        'fields' => [
            'language' => [
                'FriendlyName' => isset($lang['interface_language']) ? $lang['interface_language'] : 'Interface Language',
                'Type' => 'dropdown',
                'Options' => [
                    'english' => 'English',
                    'chinese-cn' => '简体中文 (Simplified Chinese)',
                    'chinese-tw' => '繁體中文 (Traditional Chinese)'
                ],
                'Default' => 'english',
                'Description' => isset($lang['language_description']) ? $lang['language_description'] : 'Select the interface language for the monitoring dashboard',
            ],
            'v2raysocks_server' => [
                'FriendlyName' => isset($lang['v2raysocks_database_server']) ? $lang['v2raysocks_database_server'] : 'V2RaySocks Database(server)',
                'Type' => 'dropdown',
                'Options' => $serverOptions,
                'Description' => isset($lang['server_description']) ? $lang['server_description'] : 'Select a V2RaySocks database server you want to monitor',
            ],
            'redis_ip' => [
                'FriendlyName' => isset($lang['redis_ip']) ? $lang['redis_ip'] : 'Redis IP',
                'Type' => 'text',
                'Default' => '127.0.0.1',
                'Description' => isset($lang['redis_ip_description']) ? $lang['redis_ip_description'] : 'Redis in-memory database IP',
            ],
            'redis_port' => [
                'FriendlyName' => isset($lang['redis_port']) ? $lang['redis_port'] : 'Redis port',
                'Type' => 'text',
                'Default' => '6379',
                'Description' => isset($lang['redis_port_description']) ? $lang['redis_port_description'] : 'Redis in-memory database port',
            ],
            'redis_password' => [
                'FriendlyName' => isset($lang['redis_password']) ? $lang['redis_password'] : 'Redis password',
                'Type' => 'text',
                'Default' => '',
                'Description' => isset($lang['redis_password_description']) ? $lang['redis_password_description'] : 'Redis in-memory database password, leave blank for no password',
            ],
            'refresh_interval' => [
                'FriendlyName' => isset($lang['refresh_interval']) ? $lang['refresh_interval'] : 'Refresh Interval (seconds)',
                'Type' => 'dropdown',
                'Options' => [
                    '60' => '60 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '90' => '90 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '120' => '120 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '180' => '180 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '300' => '300 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                ],
                'Default' => '300',
                'Description' => isset($lang['refresh_interval_description']) ? $lang['refresh_interval_description'] : 'Auto-refresh interval for monitoring dashboard (recommended: 300 for 5-minute WHMCS sync)',
            ],
            'realtime_refresh_interval' => [
                'FriendlyName' => isset($lang['realtime_refresh_interval']) ? $lang['realtime_refresh_interval'] : 'Real-time Monitor Refresh Interval (seconds)',
                'Type' => 'dropdown',
                'Options' => [
                    '30' => '30 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '60' => '60 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '90' => '90 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '120' => '120 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                    '150' => '150 ' . (isset($lang['seconds']) ? $lang['seconds'] : 'seconds'),
                ],
                'Default' => '30',
                'Description' => isset($lang['realtime_refresh_interval_description']) ? $lang['realtime_refresh_interval_description'] : 'Update interval for real-time statistics page (lower values increase server load)',
            ],
            'default_unit' => [
                'FriendlyName' => isset($lang['default_display_unit']) ? $lang['default_display_unit'] : 'Default Display Unit',
                'Type' => 'dropdown',
                'Options' => [
                    'auto' => isset($lang['auto_best_fit']) ? $lang['auto_best_fit'] : 'Auto (best fit)',
                    'MB' => 'Megabytes',
                    'GB' => 'Gigabytes',
                    'TB' => 'Terabytes'
                ],
                'Default' => 'auto',
                'Description' => isset($lang['default_unit_description']) ? $lang['default_unit_description'] : 'Default unit for displaying traffic data',
            ],
            'chart_unit' => [
                'FriendlyName' => isset($lang['chart_display_unit']) ? $lang['chart_display_unit'] : 'Chart Display Unit',
                'Type' => 'dropdown',
                'Options' => [
                    'auto' => isset($lang['auto_best_fit']) ? $lang['auto_best_fit'] : 'Auto (best fit)',
                    'MB' => 'Megabytes',
                    'GB' => 'Gigabytes',
                    'TB' => 'Terabytes'
                ],
                'Default' => 'auto',
                'Description' => isset($lang['chart_unit_description']) ? $lang['chart_unit_description'] : 'Unit used in charts and graphs',
            ],
        ]
    ];
}

function v2raysocks_traffic_output($vars)
{
    require(__DIR__ . '/templates/templates_functions.php');
    $LANG = $vars['_lang'];
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $userID = isset($_REQUEST['user_id']) ? $_REQUEST['user_id'] : '';
    $nodeID = isset($_REQUEST['node_id']) ? $_REQUEST['node_id'] : '';

    switch ($action) {
        case '':
            echo v2raysocks_traffic_displayTrafficDashboard($LANG);
            break;
        case 'real_time':
            echo v2raysocks_traffic_displayRealTimeMonitor($LANG);
            break;
        case 'user_stats':
            // User statistics functionality has been removed
            header('Location: addonmodules.php?module=v2raysocks_traffic&action=service_search');
            exit();
            break;
        case 'node_stats':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $formData = $_POST;
                echo v2raysocks_traffic_displayNodeStats($LANG, $formData);
            } else {
                echo v2raysocks_traffic_displayNodeStats($LANG, null);
            }
            break;
        case 'get_traffic_data':
            try {
                $filters = [
                    'user_id' => $_GET['user_id'] ?? null,
                    'service_id' => $_GET['service_id'] ?? null,
                    'node_id' => $_GET['node_id'] ?? null,
                    'start_date' => $_GET['start_date'] ?? null,
                    'end_date' => $_GET['end_date'] ?? null,
                    'time_range' => $_GET['time_range'] ?? 'month_including_today',
                    'uuid' => $_GET['uuid'] ?? null,
                    'start_timestamp' => !empty($_GET['start_timestamp']) ? intval($_GET['start_timestamp']) : null,
                    'end_timestamp' => !empty($_GET['end_timestamp']) ? intval($_GET['end_timestamp']) : null,
                ];
                
                // Use enhanced traffic data function for better node name resolution
                $useEnhanced = $_GET['enhanced'] ?? 'true';
                if ($useEnhanced === 'true') {
                    $trafficData = v2raysocks_traffic_getEnhancedTrafficData($filters);
                } else {
                    $trafficData = v2raysocks_traffic_getTrafficData($filters);
                }
                
                // Apply PR#37 time grouping if requested
                $grouped = $_GET['grouped'] ?? 'false';
                $groupedData = null;
                if ($grouped === 'true') {
                    $timeRange = $filters['time_range'] ?? 'today';
                    $groupedData = v2raysocks_traffic_groupDataByTime($trafficData, $timeRange);
                }
                
                $result = [
                    'status' => 'success',
                    'data' => $trafficData,
                    'grouped_data' => $groupedData,
                    'count' => count($trafficData),
                    'filters_applied' => array_filter($filters),
                    'enhanced_mode' => $useEnhanced === 'true'
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_traffic_data error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve traffic data: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_live_stats':
            $liveStats = v2raysocks_traffic_getLiveStats();
            $result = [
                'status' => 'success',
                'data' => $liveStats,
                'timestamp' => time(),
            ];
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_user_details':
            $userDetails = v2raysocks_traffic_getUserDetails($userID);
            $result = [
                'status' => 'success',
                'data' => $userDetails,
            ];
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_node_details':
            $nodeDetails = v2raysocks_traffic_getNodeDetails($nodeID);
            $result = [
                'status' => 'success',
                'data' => $nodeDetails,
            ];
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'export_data':
            // Enhanced export functionality with options
            $exportFormat = $_GET['format'] ?? 'csv';
            $exportLimit = $_GET['limit'] ?? null; // null means all data
            $exportType = $_GET['export_type'] ?? 'all'; // all, limited, date_range
            
            $filters = [
                'user_id' => $_GET['user_id'] ?? null,
                'service_id' => $_GET['service_id'] ?? null,
                'node_id' => $_GET['node_id'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'time_range' => $_GET['time_range'] ?? null,
                'uuid' => $_GET['uuid'] ?? null,
                'node_search' => $_GET['node_search'] ?? null,
                'export_type' => $exportType,
                'sort_by' => $_GET['sort_by'] ?? 'traffic_desc',
                'only_today' => $_GET['only_today'] ?? 'true',
                'show_offline' => $_GET['show_offline'] ?? 'true',
                'limit' => $_GET['limit'] ?? PHP_INT_MAX,
                // Add timestamp parameters for time range filtering
                'start_timestamp' => !empty($_GET['export_start_timestamp']) ? intval($_GET['export_start_timestamp']) : null,
                'end_timestamp' => !empty($_GET['export_end_timestamp']) ? intval($_GET['export_end_timestamp']) : null
            ];
            
            // Apply export options for regular traffic data
            if (!in_array($exportType, ['node_rankings', 'user_rankings'])) {
                switch ($exportType) {
                    case 'limited':
                        // Export specific number of records - remove limit restriction
                        $exportLimit = intval($_GET['limit_count'] ?? PHP_INT_MAX);
                        break;
                    case 'date_range':
                        // Use custom date range from export dialog
                        if (!empty($_GET['export_start_date'])) {
                            $filters['start_date'] = $_GET['export_start_date'];
                        }
                        if (!empty($_GET['export_end_date'])) {
                            $filters['end_date'] = $_GET['export_end_date'];
                        }
                        
                        // Handle timestamp-based range (for real-time monitor time selection)
                        if (!empty($_GET['export_start_timestamp'])) {
                            $filters['start_timestamp'] = intval($_GET['export_start_timestamp']);
                        }
                        if (!empty($_GET['export_end_timestamp'])) {
                            $filters['end_timestamp'] = intval($_GET['export_end_timestamp']);
                        }
                        break;
                    case 'all':
                    default:
                        // Export all filtered data
                        break;
                }
            }
            
            v2raysocks_traffic_exportTrafficData($filters, $exportFormat, $exportLimit);
            die();
        case 'service_search':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $formData = $_POST;
                echo v2raysocks_traffic_displayServiceSearch($LANG, $formData);
            } else {
                echo v2raysocks_traffic_displayServiceSearch($LANG, null);
            }
            break;
        case 'user_rankings':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $formData = $_POST;
                echo v2raysocks_traffic_displayUserRankings($LANG, $formData);
            } else {
                echo v2raysocks_traffic_displayUserRankings($LANG, null);
            }
            break;
        case 'user_rankings_standalone':
            // Use the standalone user rankings page
            require_once(__DIR__ . '/user_rankings_page.php');
            echo v2raysocks_traffic_userRankingsStandalone();
            break;
        case 'today_traffic_chart':
            // Redirect to real-time monitoring since today traffic chart is now integrated there
            header('Location: addonmodules.php?module=v2raysocks_traffic&action=real_time');
            exit();
            break;
        case 'get_module_config':
            $config = v2raysocks_traffic_getModuleConfig();
            $result = [
                'status' => 'success',
                'data' => $config,
            ];
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_today_traffic_data':
            try {
                $todayTrafficData = v2raysocks_traffic_getTodayTrafficData();
                $result = [
                    'status' => 'success',
                    'data' => $todayTrafficData,
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_today_traffic_data error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve today traffic data: ' . $e->getMessage(),
                    'data' => null
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_total_traffic_since_launch':
            try {
                $totalTrafficData = v2raysocks_traffic_getTotalTrafficSinceLaunch();
                $result = [
                    'status' => 'success',
                    'data' => $totalTrafficData,
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_total_traffic_since_launch error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve total traffic data: ' . $e->getMessage(),
                    'data' => null
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_historical_peak_traffic':
            try {
                $historicalPeakData = v2raysocks_traffic_getHistoricalPeakTraffic();
                $result = [
                    'status' => 'success',
                    'data' => $historicalPeakData,
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_historical_peak_traffic error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve historical peak traffic data: ' . $e->getMessage(),
                    'data' => null
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_all_nodes':
            try {
                $nodesData = v2raysocks_traffic_getAllNodes();
                $result = [
                    'status' => 'success',
                    'data' => $nodesData,
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_all_nodes error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve nodes data: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'search_service_advanced':
            try {
                $serviceId = $_GET['service_id'] ?? '';
                $filters = [
                    'time_range' => $_GET['time_range'] ?? 'month_including_today',
                    'start_date' => $_GET['start_date'] ?? null,
                    'end_date' => $_GET['end_date'] ?? null,
                    'start_timestamp' => $_GET['start_timestamp'] ?? null,
                    'end_timestamp' => $_GET['end_timestamp'] ?? null,
                ];
                
                if (empty($serviceId)) {
                    $result = [
                        'status' => 'error',
                        'message' => 'Service ID is required',
                        'data' => []
                    ];
                } else {
                    $searchResults = v2raysocks_traffic_searchByServiceId($serviceId, $filters);
                    $result = [
                        'status' => 'success',
                        'data' => $searchResults,
                        'count' => count($searchResults),
                        'service_id_searched' => $serviceId,
                        'filters_applied' => array_filter($filters)
                    ];
                }
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis search_service_advanced error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to search service data: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'clear_cache':
            try {
                // Use improved cache clearing with pattern support
                $clearType = $_GET['type'] ?? 'all';
                
                switch ($clearType) {
                    case 'live':
                        v2raysocks_traffic_clearCache(['live_stats']);
                        $message = 'Live statistics cache cleared';
                        break;
                    case 'traffic':
                        v2raysocks_traffic_clearCache([], 'traffic_*');
                        $message = 'Traffic data cache cleared';
                        break;
                    case 'rankings':
                        v2raysocks_traffic_clearCache([], '*_rankings_*');
                        $message = 'Rankings cache cleared';
                        break;
                    case 'all':
                    default:
                        // Clear all cache with improved method
                        v2raysocks_traffic_clearCache();
                        $message = 'All cache cleared';
                        break;
                }
                
                $result = [
                    'status' => 'success',
                    'message' => $message,
                    'timestamp' => time()
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis clear_cache error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to clear cache: ' . $e->getMessage()
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
            
        case 'cache_stats':
            try {
                $cacheStats = v2raysocks_traffic_getCacheStats();
                
                $result = [
                    'status' => 'success',
                    'data' => $cacheStats,
                    'timestamp' => time()
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis cache_stats error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get cache stats: ' . $e->getMessage()
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'debug_info':
            try {
                $cacheStats = v2raysocks_traffic_getCacheStats();
                
                $debugInfo = [
                    'php_version' => PHP_VERSION,
                    'redis_available' => extension_loaded('redis'),
                    'database_connection' => v2raysocks_traffic_createPDO() ? 'OK' : 'FAILED',
                    'redis_connection' => v2raysocks_traffic_redisOperate('ping', []) ? 'OK' : 'FAILED',
                    'cache_performance' => $cacheStats,
                    'module_config' => v2raysocks_traffic_getModuleConfig(),
                    'server_info' => v2raysocks_traffic_serverInfo() ? 'OK' : 'FAILED',
                    'current_time' => time(),
                    'current_date_utc' => gmdate('Y-m-d H:i:s'),
                    'current_date_local' => date('Y-m-d H:i:s'),
                    'timezone' => date_default_timezone_get()
                ];
                
                $result = [
                    'status' => 'success',
                    'data' => $debugInfo
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis debug_info error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get debug info: ' . $e->getMessage()
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_node_traffic_rankings':
            try {
                $sortBy = $_GET['sort_by'] ?? 'traffic_desc';
                $timeRange = $_GET['time_range'] ?? 'today';
                $startTimestamp = $_GET['start_timestamp'] ?? null;
                $endTimestamp = $_GET['end_timestamp'] ?? null;
                
                // Legacy support for only_today parameter
                if (isset($_GET['only_today'])) {
                    $timeRange = ($_GET['only_today'] === 'true') ? 'today' : 'all';
                }
                
                $rankings = v2raysocks_traffic_getNodeTrafficRankings($sortBy, $timeRange, $startTimestamp, $endTimestamp);
                $result = [
                    'status' => 'success',
                    'data' => $rankings,
                    'sort_by' => $sortBy,
                    'time_range' => $timeRange
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_node_traffic_rankings error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get node rankings: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_user_traffic_rankings':
            try {
                $sortBy = $_GET['sort_by'] ?? 'traffic_desc';
                $timeRange = $_GET['time_range'] ?? 'today';
                $startDate = $_GET['start_date'] ?? null;
                $endDate = $_GET['end_date'] ?? null;
                $startTimestamp = $_GET['start_timestamp'] ?? null;
                $endTimestamp = $_GET['end_timestamp'] ?? null;
                $limitValue = $_GET['limit'] ?? 'all';
                
                // Handle "all" option properly - remove limit restriction
                $limit = ($limitValue === 'all') ? PHP_INT_MAX : intval($limitValue);
                if ($limit <= 0) $limit = PHP_INT_MAX; // Default fallback - no limit
                
                $rankings = v2raysocks_traffic_getUserTrafficRankings($sortBy, $timeRange, $limit, $startDate, $endDate, $startTimestamp, $endTimestamp);
                $result = [
                    'status' => 'success',
                    'data' => $rankings,
                    'sort_by' => $sortBy,
                    'time_range' => $timeRange,
                    'limit' => $limitValue, // Return original value for frontend
                    'actual_limit' => $limit // Return actual numeric limit used
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_user_traffic_rankings error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get user rankings: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_node_traffic_chart':
            try {
                $nodeId = $_GET['node_id'] ?? null;
                $timeRange = $_GET['time_range'] ?? 'today';
                $startTimestamp = $_GET['start_timestamp'] ?? null;
                $endTimestamp = $_GET['end_timestamp'] ?? null;
                if (!$nodeId) {
                    throw new Exception('Node ID is required');
                }
                $chartData = v2raysocks_traffic_getNodeTrafficChart($nodeId, $timeRange, $startTimestamp, $endTimestamp);
                $result = [
                    'status' => 'success',
                    'data' => $chartData
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_node_traffic_chart error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get node chart data: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_user_traffic_chart':
            try {
                $userId = $_GET['user_id'] ?? null;
                $timeRange = $_GET['time_range'] ?? 'today';
                $startDate = $_GET['start_date'] ?? null;
                $endDate = $_GET['end_date'] ?? null;
                $startTimestamp = $_GET['start_timestamp'] ?? null;
                $endTimestamp = $_GET['end_timestamp'] ?? null;
                
                if (!$userId) {
                    throw new Exception('User ID is required');
                }
                
                $chartData = v2raysocks_traffic_getUserTrafficChart($userId, $timeRange, $startDate, $endDate, $startTimestamp, $endTimestamp);
                $result = [
                    'status' => 'success',
                    'data' => $chartData
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_user_traffic_chart error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get user chart data: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'get_usage_records':
            try {
                $nodeId = $_GET['node_id'] ?? null;
                $userId = $_GET['user_id'] ?? null;
                $uuid = $_GET['uuid'] ?? null;
                $timeRange = $_GET['time_range'] ?? 'today';
                $startDate = $_GET['start_date'] ?? null;
                $endDate = $_GET['end_date'] ?? null;
                $startTimestamp = $_GET['start_timestamp'] ?? $_GET['export_start_timestamp'] ?? null;
                $endTimestamp = $_GET['end_timestamp'] ?? $_GET['export_end_timestamp'] ?? null;
                $limit = intval($_GET['limit'] ?? PHP_INT_MAX);
                
                $records = v2raysocks_traffic_getUsageRecords($nodeId, $userId, $timeRange, $limit, $startDate, $endDate, $uuid, $startTimestamp, $endTimestamp);
                $result = [
                    'status' => 'success',
                    'data' => $records,
                    'node_id' => $nodeId,
                    'user_id' => $userId,
                    'uuid' => $uuid,
                    'time_range' => $timeRange,
                    'start_timestamp' => $startTimestamp,
                    'end_timestamp' => $endTimestamp,
                    'limit' => $limit
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis get_usage_records error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get usage records: ' . $e->getMessage(),
                    'data' => []
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'debug':
            // Debug action for troubleshooting (should be removed in production)
            require_once(__DIR__ . '/debug.php');
            echo v2raysocks_traffic_debugPage();
            die();
        default:
            echo "Unknown action";
    }
}

// =============================================================================
// 大数据处理优化功能 - Big Data Processing Optimization Features
// =============================================================================

/**
 * 大数据批处理引擎类
 * Large Data Batch Processing Engine
 * 
 * 设计用于处理300k-500k记录，控制内存使用在100MB以下
 * Designed to handle 300k-500k records with memory usage under 100MB
 */
class V2RaySocksLargeDataProcessor
{
    private $batchSize;
    private $memoryLimit;
    private $pdo;
    
    public function __construct($batchSize = 5000, $memoryLimitMB = 80)
    {
        $this->batchSize = $batchSize;
        $this->memoryLimit = $memoryLimitMB * 1024 * 1024; // Convert to bytes
        $this->pdo = v2raysocks_traffic_createPDO();
    }
    
    /**
     * 批处理用户排名数据
     * Batch process user ranking data
     */
    public function processUserRankingsBatch($sortBy, $timeRange, $limit, $startTime, $endTime)
    {
        try {
            $results = [];
            $offset = 0;
            $processedCount = 0;
            
            // 预估计算总记录数以优化处理
            $countSql = "SELECT COUNT(DISTINCT u.id) as total_users FROM user u 
                        LEFT JOIN user_usage uu ON u.id = uu.user_id 
                        WHERE uu.t >= :start_time AND uu.t <= :end_time";
            
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
            $countStmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
            $countStmt->execute();
            $totalUsers = $countStmt->fetch(PDO::FETCH_ASSOC)['total_users'];
            
            logActivity("V2RaySocks Traffic Monitor: Large data processing started for {$totalUsers} users", 0);
            
            while ($processedCount < $totalUsers && $processedCount < $limit) {
                // 检查内存使用情况
                if (memory_get_usage() > $this->memoryLimit) {
                    logActivity("V2RaySocks Traffic Monitor: Memory limit approached, optimizing...", 0);
                    gc_collect_cycles(); // 强制垃圾回收
                }
                
                $currentBatchSize = min($this->batchSize, $limit - $processedCount);
                $batchResults = $this->processBatch($sortBy, $timeRange, $currentBatchSize, $offset, $startTime, $endTime);
                
                if (empty($batchResults)) {
                    break;
                }
                
                $results = array_merge($results, $batchResults);
                $processedCount += count($batchResults);
                $offset += $this->batchSize;
                
                // 记录进度
                if ($processedCount % 10000 == 0) {
                    logActivity("V2RaySocks Traffic Monitor: Processed {$processedCount}/{$totalUsers} users", 0);
                }
            }
            
            // 最终排序和限制结果
            $results = $this->finalSort($results, $sortBy, $limit);
            
            logActivity("V2RaySocks Traffic Monitor: Large data processing completed. Processed {$processedCount} users, returned " . count($results) . " results", 0);
            
            return $results;
            
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Large data processing error: " . $e->getMessage(), 0);
            throw $e;
        }
    }
    
    /**
     * 处理单个批次的数据
     * Process a single batch of data
     */
    private function processBatch($sortBy, $timeRange, $batchSize, $offset, $startTime, $endTime)
    {
        $currentTime = time();
        $time5min = $currentTime - 300;
        $time1hour = $currentTime - 3600;
        $time4hour = $currentTime - 14400;
        
        // 优化的SQL查询，使用分页和索引
        $sql = "
            SELECT 
                u.id as user_id,
                u.uuid,
                u.sid,
                u.u as total_upload_user,
                u.d as total_download_user,
                u.transfer_enable,
                u.enable,
                u.created_at,
                u.remark,
                COALESCE(u.speedlimitss, '') as speedlimitss,
                COALESCE(u.speedlimitother, '') as speedlimitother,
                COALESCE(SUM(uu.u), 0) as period_upload,
                COALESCE(SUM(uu.d), 0) as period_download,
                COALESCE(SUM(uu.u + uu.d), 0) as period_traffic,
                COALESCE(SUM(CASE WHEN uu.t >= :time_5min THEN uu.u + uu.d ELSE 0 END), 0) as traffic_5min,
                COALESCE(SUM(CASE WHEN uu.t >= :time_1hour THEN uu.u + uu.d ELSE 0 END), 0) as traffic_1hour,
                COALESCE(SUM(CASE WHEN uu.t >= :time_4hour THEN uu.u + uu.d ELSE 0 END), 0) as traffic_4hour,
                COUNT(DISTINCT uu.node) as nodes_used,
                COUNT(uu.id) as usage_records,
                MIN(uu.t) as first_usage,
                MAX(uu.t) as last_usage
            FROM user u
            LEFT JOIN user_usage uu ON u.id = uu.user_id AND uu.t >= :start_time AND uu.t <= :end_time
            GROUP BY u.id, u.uuid, u.sid, u.u, u.d, u.transfer_enable, u.enable, u.created_at, u.remark, u.speedlimitss, u.speedlimitother
            ORDER BY u.id 
            LIMIT :batch_size OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->bindValue(':time_5min', $time5min, PDO::PARAM_INT);
        $stmt->bindValue(':time_1hour', $time1hour, PDO::PARAM_INT);
        $stmt->bindValue(':time_4hour', $time4hour, PDO::PARAM_INT);
        $stmt->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 处理结果并添加计算字段
        foreach ($users as &$user) {
            $this->processUserRecord($user);
        }
        
        return $users;
    }
    
    /**
     * 处理单个用户记录
     * Process a single user record
     */
    private function processUserRecord(&$user)
    {
        $user['user_id'] = intval($user['user_id']);
        $user['period_upload'] = floatval($user['period_upload']);
        $user['period_download'] = floatval($user['period_download']);
        $user['period_traffic'] = floatval($user['period_traffic']);
        $user['traffic_5min'] = floatval($user['traffic_5min']);
        $user['traffic_1hour'] = floatval($user['traffic_1hour']);
        $user['traffic_4hour'] = floatval($user['traffic_4hour']);
        $user['total_upload_user'] = floatval($user['total_upload_user']);
        $user['total_download_user'] = floatval($user['total_download_user']);
        $user['transfer_enable'] = floatval($user['transfer_enable']);
        $user['nodes_used'] = intval($user['nodes_used']);
        $user['usage_records'] = intval($user['usage_records']);
        $user['first_usage'] = intval($user['first_usage']);
        $user['last_usage'] = intval($user['last_usage']);
        
        $user['speedlimitss'] = $user['speedlimitss'] ?? '';
        $user['speedlimitother'] = $user['speedlimitother'] ?? '';
        
        $totalUsed = $user['total_upload_user'] + $user['total_download_user'];
        $user['used_traffic'] = $totalUsed;
        $user['remaining_quota'] = max(0, $user['transfer_enable'] - $totalUsed);
        $user['quota_utilization'] = $user['transfer_enable'] > 0 ? ($totalUsed / $user['transfer_enable']) * 100 : 0;
        
        $user['has_activity'] = $user['period_traffic'] > 0;
        $user['avg_traffic_per_node'] = $user['nodes_used'] > 0 ? $user['period_traffic'] / $user['nodes_used'] : 0;
    }
    
    /**
     * 最终排序和限制结果
     * Final sorting and limiting of results
     */
    private function finalSort($results, $sortBy, $limit)
    {
        switch ($sortBy) {
            case 'traffic_desc':
                usort($results, function($a, $b) { return $b['period_traffic'] <=> $a['period_traffic']; });
                break;
            case 'traffic_asc':
                usort($results, function($a, $b) { return $a['period_traffic'] <=> $b['period_traffic']; });
                break;
            case 'remaining_desc':
                usort($results, function($a, $b) { return $b['remaining_quota'] <=> $a['remaining_quota']; });
                break;
            case 'remaining_asc':
                usort($results, function($a, $b) { return $a['remaining_quota'] <=> $b['remaining_quota']; });
                break;
            case 'nodes_desc':
                usort($results, function($a, $b) { return $b['nodes_used'] <=> $a['nodes_used']; });
                break;
            case 'recent_activity':
                usort($results, function($a, $b) { return $b['last_usage'] <=> $a['last_usage']; });
                break;
            default:
                usort($results, function($a, $b) { return $b['period_traffic'] <=> $a['period_traffic']; });
        }
        
        return array_slice($results, 0, $limit);
    }
}

/**
 * 流式数据聚合器类
 * Streaming Data Aggregator
 * 
 * 实现实时数据聚合和预处理，支持多维度数据分析
 * Implements real-time data aggregation and preprocessing with multi-dimensional analysis
 */
class V2RaySocksTrafficAggregator
{
    private $pdo;
    private $aggregationCache;
    
    public function __construct()
    {
        $this->pdo = v2raysocks_traffic_createPDO();
        $this->aggregationCache = [];
    }
    
    /**
     * 流式处理流量数据聚合
     * Stream process traffic data aggregation
     */
    public function streamProcessTrafficData($timeRange, $groupBy = 'user', $metrics = ['traffic', 'nodes', 'records'])
    {
        try {
            $startTime = $this->getTimeRangeStart($timeRange);
            $endTime = time();
            
            $cacheKey = "stream_aggregation_{$timeRange}_{$groupBy}_" . md5(implode(',', $metrics));
            
            // 尝试从缓存获取
            if (isset($this->aggregationCache[$cacheKey])) {
                return $this->aggregationCache[$cacheKey];
            }
            
            $results = [];
            
            // 根据分组类型选择不同的聚合策略
            switch ($groupBy) {
                case 'user':
                    $results = $this->aggregateByUser($startTime, $endTime, $metrics);
                    break;
                case 'node':
                    $results = $this->aggregateByNode($startTime, $endTime, $metrics);
                    break;
                case 'time':
                    $results = $this->aggregateByTime($startTime, $endTime, $metrics);
                    break;
                case 'hourly':
                    $results = $this->aggregateByHour($startTime, $endTime, $metrics);
                    break;
            }
            
            // 缓存结果
            $this->aggregationCache[$cacheKey] = $results;
            
            return $results;
            
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Stream aggregation error: " . $e->getMessage(), 0);
            return [];
        }
    }
    
    /**
     * 按用户聚合数据
     */
    private function aggregateByUser($startTime, $endTime, $metrics)
    {
        $sql = "
            SELECT 
                u.id as user_id,
                u.uuid,
                u.sid,
                SUM(uu.u + uu.d) as total_traffic,
                COUNT(DISTINCT uu.node) as unique_nodes,
                COUNT(uu.id) as total_records,
                AVG(uu.u + uu.d) as avg_traffic_per_record,
                MAX(uu.t) as last_activity
            FROM user u
            INNER JOIN user_usage uu ON u.id = uu.user_id
            WHERE uu.t >= :start_time AND uu.t <= :end_time
            GROUP BY u.id, u.uuid, u.sid
            ORDER BY total_traffic DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 按节点聚合数据
     */
    private function aggregateByNode($startTime, $endTime, $metrics)
    {
        $sql = "
            SELECT 
                uu.node,
                n.name as node_name,
                SUM(uu.u + uu.d) as total_traffic,
                COUNT(DISTINCT uu.user_id) as unique_users,
                COUNT(uu.id) as total_records,
                AVG(uu.u + uu.d) as avg_traffic_per_record
            FROM user_usage uu
            LEFT JOIN node n ON uu.node = n.id
            WHERE uu.t >= :start_time AND uu.t <= :end_time
            GROUP BY uu.node, n.name
            ORDER BY total_traffic DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 按时间聚合数据
     */
    private function aggregateByTime($startTime, $endTime, $metrics)
    {
        $sql = "
            SELECT 
                FROM_UNIXTIME(FLOOR(uu.t / 3600) * 3600, '%Y-%m-%d %H:00:00') as time_hour,
                FLOOR(uu.t / 3600) * 3600 as timestamp_hour,
                SUM(uu.u + uu.d) as total_traffic,
                COUNT(DISTINCT uu.user_id) as unique_users,
                COUNT(DISTINCT uu.node) as unique_nodes,
                COUNT(uu.id) as total_records
            FROM user_usage uu
            WHERE uu.t >= :start_time AND uu.t <= :end_time
            GROUP BY FLOOR(uu.t / 3600)
            ORDER BY timestamp_hour ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 按小时聚合数据（更细粒度）
     */
    private function aggregateByHour($startTime, $endTime, $metrics)
    {
        $sql = "
            SELECT 
                HOUR(FROM_UNIXTIME(uu.t)) as hour_of_day,
                SUM(uu.u + uu.d) as total_traffic,
                COUNT(DISTINCT uu.user_id) as unique_users,
                COUNT(DISTINCT uu.node) as unique_nodes,
                AVG(uu.u + uu.d) as avg_traffic_per_record
            FROM user_usage uu
            WHERE uu.t >= :start_time AND uu.t <= :end_time
            GROUP BY HOUR(FROM_UNIXTIME(uu.t))
            ORDER BY hour_of_day ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取时间范围开始时间
     */
    private function getTimeRangeStart($timeRange)
    {
        switch ($timeRange) {
            case 'today':
                return strtotime('today');
            case 'week':
                return strtotime('-6 days', strtotime('today'));
            case 'month':
                return strtotime('-29 days', strtotime('today'));
            case 'last_hour':
                return strtotime('-1 hour');
            case 'last_6_hours':
                return strtotime('-6 hours');
            case 'last_24_hours':
                return strtotime('-24 hours');
            default:
                return strtotime('today');
        }
    }
}

/**
 * 智能缓存管理器类
 * Intelligent Cache Manager
 * 
 * 实现多层缓存策略和自动缓存失效机制
 * Implements multi-layer caching strategy and automatic cache invalidation
 */
class V2RaySocksCacheManager
{
    private $redis;
    private $memoryCache;
    private $cacheStats;
    
    public function __construct()
    {
        $this->memoryCache = [];
        $this->cacheStats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'invalidations' => 0
        ];
    }
    
    /**
     * 多层缓存获取
     * Multi-layer cache get
     */
    public function get($key, $options = [])
    {
        try {
            // 第一层：内存缓存
            if (isset($this->memoryCache[$key])) {
                $this->cacheStats['hits']++;
                return $this->memoryCache[$key];
            }
            
            // 第二层：Redis缓存
            try {
                $redisData = v2raysocks_traffic_redisOperate('get', ['key' => $key]);
                if ($redisData !== false && $redisData !== null) {
                    $decodedData = json_decode($redisData, true);
                    if ($decodedData !== null) {
                        // 回填到内存缓存
                        $this->memoryCache[$key] = $decodedData;
                        $this->cacheStats['hits']++;
                        return $decodedData;
                    }
                }
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Monitor: Redis cache read failed for key {$key}: " . $e->getMessage(), 0);
            }
            
            $this->cacheStats['misses']++;
            return null;
            
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Cache get error for key {$key}: " . $e->getMessage(), 0);
            $this->cacheStats['misses']++;
            return null;
        }
    }
    
    /**
     * 多层缓存设置
     * Multi-layer cache set
     */
    public function set($key, $value, $options = [])
    {
        try {
            // 设置内存缓存
            $this->memoryCache[$key] = $value;
            
            // 设置Redis缓存
            $ttl = $this->calculateTTL($key, $options);
            try {
                v2raysocks_traffic_redisOperate('setex', [
                    'key' => $key,
                    'value' => json_encode($value),
                    'seconds' => $ttl
                ]);
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Monitor: Redis cache write failed for key {$key}: " . $e->getMessage(), 0);
            }
            
            $this->cacheStats['sets']++;
            
            // 内存使用优化：限制内存缓存大小
            if (count($this->memoryCache) > 1000) {
                $this->trimMemoryCache();
            }
            
            return true;
            
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Cache set error for key {$key}: " . $e->getMessage(), 0);
            return false;
        }
    }
    
    /**
     * 智能缓存失效
     * Intelligent cache invalidation
     */
    public function invalidate($pattern, $reason = 'manual')
    {
        try {
            // 清除内存缓存
            $removedFromMemory = 0;
            foreach (array_keys($this->memoryCache) as $key) {
                if (fnmatch($pattern, $key)) {
                    unset($this->memoryCache[$key]);
                    $removedFromMemory++;
                }
            }
            
            // 清除Redis缓存
            try {
                v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => $pattern]);
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Monitor: Redis pattern clear failed for pattern {$pattern}: " . $e->getMessage(), 0);
            }
            
            $this->cacheStats['invalidations']++;
            
            logActivity("V2RaySocks Traffic Monitor: Cache invalidated for pattern {$pattern} (reason: {$reason}), removed {$removedFromMemory} from memory", 0);
            
            return true;
            
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Cache invalidation error for pattern {$pattern}: " . $e->getMessage(), 0);
            return false;
        }
    }
    
    /**
     * 自动缓存失效规则
     * Automatic cache invalidation rules
     */
    public function autoInvalidate($trigger, $context = [])
    {
        $invalidationRules = [
            'user_update' => ['user_*', '*_rankings_*', 'enhanced_traffic_*'],
            'node_update' => ['node_*', '*_rankings_*'],
            'traffic_update' => ['traffic_*', 'live_stats*', '*_rankings_*'],
            'large_dataset_complete' => ['*_rankings_*', 'enhanced_traffic_*'],
            'database_optimize' => ['*'] // 清除所有缓存
        ];
        
        if (isset($invalidationRules[$trigger])) {
            foreach ($invalidationRules[$trigger] as $pattern) {
                $this->invalidate($pattern, "auto:{$trigger}");
            }
        }
    }
    
    /**
     * 计算TTL（生存时间）
     * Calculate TTL (Time To Live)
     */
    private function calculateTTL($key, $options)
    {
        // 默认TTL策略
        $defaultTTL = 120; // 2分钟
        
        if (isset($options['ttl'])) {
            return $options['ttl'];
        }
        
        // 根据数据类型智能调整TTL
        if (isset($options['data_type'])) {
            switch ($options['data_type']) {
                case 'rankings':
                    return 300; // 5分钟
                case 'live_stats':
                    return 30; // 30秒
                case 'aggregated':
                    return 600; // 10分钟
                case 'configuration':
                    return 3600; // 1小时
                case 'large_dataset':
                    return 900; // 15分钟
                default:
                    return $defaultTTL;
            }
        }
        
        // 根据时间范围调整TTL
        if (isset($options['time_range'])) {
            switch ($options['time_range']) {
                case 'today':
                    return 120; // 2分钟
                case 'week':
                case 'month':
                    return 600; // 10分钟
                case 'historical':
                    return 1800; // 30分钟
                default:
                    return $defaultTTL;
            }
        }
        
        return $defaultTTL;
    }
    
    /**
     * 修剪内存缓存
     * Trim memory cache
     */
    private function trimMemoryCache()
    {
        // 保留最近使用的500个项目
        $this->memoryCache = array_slice($this->memoryCache, -500, 500, true);
    }
    
    /**
     * 获取缓存统计信息
     * Get cache statistics
     */
    public function getStats()
    {
        $hitRate = $this->cacheStats['hits'] + $this->cacheStats['misses'] > 0 
                   ? ($this->cacheStats['hits'] / ($this->cacheStats['hits'] + $this->cacheStats['misses'])) * 100 
                   : 0;
        
        return array_merge($this->cacheStats, [
            'hit_rate' => round($hitRate, 2),
            'memory_cache_size' => count($this->memoryCache)
        ]);
    }
}

/**
 * 数据库优化功能
 * Database Optimization Functions
 */

/**
 * 自动创建性能索引
 * Automatically create performance indexes
 */
function v2raysocks_traffic_createPerformanceIndexes()
{
    try {
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return false;
        }
        
        $indexes = [
            // user_usage表的复合索引
            "CREATE INDEX IF NOT EXISTS idx_user_usage_user_time ON user_usage (user_id, t)",
            "CREATE INDEX IF NOT EXISTS idx_user_usage_node_time ON user_usage (node, t)",
            "CREATE INDEX IF NOT EXISTS idx_user_usage_time_traffic ON user_usage (t, u, d)",
            
            // user表的索引
            "CREATE INDEX IF NOT EXISTS idx_user_sid ON user (sid)",
            "CREATE INDEX IF NOT EXISTS idx_user_uuid ON user (uuid)",
            "CREATE INDEX IF NOT EXISTS idx_user_enable ON user (enable)",
            
            // node表的索引
            "CREATE INDEX IF NOT EXISTS idx_node_name ON node (name)",
            "CREATE INDEX IF NOT EXISTS idx_node_id_name ON node (id, name)",
            
            // 用于大数据查询的专用索引
            "CREATE INDEX IF NOT EXISTS idx_user_usage_large_data ON user_usage (t, user_id, node, u, d)",
            "CREATE INDEX IF NOT EXISTS idx_user_enable_transfer ON user (enable, transfer_enable, u, d)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
                logActivity("V2RaySocks Traffic Monitor: Created index: " . substr($sql, 0, 100) . "...", 0);
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Monitor: Index creation failed: " . $e->getMessage(), 0);
            }
        }
        
        return true;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Performance index creation error: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * 创建预聚合表
 * Create pre-aggregation tables
 */
function v2raysocks_traffic_createPreAggregationTables()
{
    try {
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return false;
        }
        
        // 每日用户流量聚合表
        $dailyUserTrafficSql = "
            CREATE TABLE IF NOT EXISTS daily_user_traffic (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                date DATE NOT NULL,
                total_upload BIGINT DEFAULT 0,
                total_download BIGINT DEFAULT 0,
                total_traffic BIGINT DEFAULT 0,
                unique_nodes INT DEFAULT 0,
                usage_records INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_date (user_id, date),
                KEY idx_date (date),
                KEY idx_user_id (user_id),
                KEY idx_traffic (total_traffic)
            ) ENGINE=InnoDB
        ";
        
        // 每日节点流量聚合表
        $dailyNodeTrafficSql = "
            CREATE TABLE IF NOT EXISTS daily_node_traffic (
                id INT AUTO_INCREMENT PRIMARY KEY,
                node_id INT NOT NULL,
                date DATE NOT NULL,
                total_upload BIGINT DEFAULT 0,
                total_download BIGINT DEFAULT 0,
                total_traffic BIGINT DEFAULT 0,
                unique_users INT DEFAULT 0,
                usage_records INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_node_date (node_id, date),
                KEY idx_date (date),
                KEY idx_node_id (node_id),
                KEY idx_traffic (total_traffic)
            ) ENGINE=InnoDB
        ";
        
        // 每小时统计表（用于实时监控）
        $hourlyStatsSql = "
            CREATE TABLE IF NOT EXISTS hourly_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                hour_timestamp INT NOT NULL,
                total_users INT DEFAULT 0,
                active_users INT DEFAULT 0,
                total_nodes INT DEFAULT 0,
                active_nodes INT DEFAULT 0,
                total_traffic BIGINT DEFAULT 0,
                peak_traffic BIGINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_hour (hour_timestamp),
                KEY idx_hour_timestamp (hour_timestamp)
            ) ENGINE=InnoDB
        ";
        
        $pdo->exec($dailyUserTrafficSql);
        $pdo->exec($dailyNodeTrafficSql);
        $pdo->exec($hourlyStatsSql);
        
        logActivity("V2RaySocks Traffic Monitor: Pre-aggregation tables created successfully", 0);
        
        return true;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Pre-aggregation table creation error: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * 更新预聚合数据
 * Update pre-aggregation data
 */
function v2raysocks_traffic_updatePreAggregationData($date = null)
{
    try {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return false;
        }
        
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');
        
        // 更新每日用户流量聚合
        $userAggregateSql = "
            INSERT INTO daily_user_traffic (user_id, date, total_upload, total_download, total_traffic, unique_nodes, usage_records)
            SELECT 
                uu.user_id,
                :date,
                SUM(uu.u) as total_upload,
                SUM(uu.d) as total_download,
                SUM(uu.u + uu.d) as total_traffic,
                COUNT(DISTINCT uu.node) as unique_nodes,
                COUNT(uu.id) as usage_records
            FROM user_usage uu
            WHERE uu.t >= :start_time AND uu.t <= :end_time
            GROUP BY uu.user_id
            ON DUPLICATE KEY UPDATE
                total_upload = VALUES(total_upload),
                total_download = VALUES(total_download),
                total_traffic = VALUES(total_traffic),
                unique_nodes = VALUES(unique_nodes),
                usage_records = VALUES(usage_records),
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $pdo->prepare($userAggregateSql);
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->execute();
        
        // 更新每日节点流量聚合
        $nodeAggregateSql = "
            INSERT INTO daily_node_traffic (node_id, date, total_upload, total_download, total_traffic, unique_users, usage_records)
            SELECT 
                uu.node,
                :date,
                SUM(uu.u) as total_upload,
                SUM(uu.d) as total_download,
                SUM(uu.u + uu.d) as total_traffic,
                COUNT(DISTINCT uu.user_id) as unique_users,
                COUNT(uu.id) as usage_records
            FROM user_usage uu
            WHERE uu.t >= :start_time AND uu.t <= :end_time
            GROUP BY uu.node
            ON DUPLICATE KEY UPDATE
                total_upload = VALUES(total_upload),
                total_download = VALUES(total_download),
                total_traffic = VALUES(total_traffic),
                unique_users = VALUES(unique_users),
                usage_records = VALUES(usage_records),
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $pdo->prepare($nodeAggregateSql);
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->execute();
        
        logActivity("V2RaySocks Traffic Monitor: Pre-aggregation data updated for date: {$date}", 0);
        
        return true;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Pre-aggregation data update error: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * 优化版的用户流量排名函数
 * Optimized user traffic rankings function
 */
function v2raysocks_traffic_getUserTrafficRankingsOptimized($sortBy = 'traffic_desc', $timeRange = 'today', $limit = PHP_INT_MAX, $startDate = null, $endDate = null, $startTimestamp = null, $endTimestamp = null)
{
    try {
        // 创建大数据处理器实例
        $processor = new V2RaySocksLargeDataProcessor();
        $aggregator = new V2RaySocksTrafficAggregator();
        $cacheManager = new V2RaySocksCacheManager();
        
        // 生成缓存键
        $cacheKey = 'optimized_user_rankings_' . md5($sortBy . '_' . $timeRange . '_' . $limit . '_' . ($startDate ?: '') . '_' . ($endDate ?: '') . '_' . ($startTimestamp ?: '') . '_' . ($endTimestamp ?: ''));
        
        // 尝试从智能缓存获取
        $cachedData = $cacheManager->get($cacheKey);
        if ($cachedData) {
            logActivity("V2RaySocks Traffic Monitor: Optimized rankings served from cache", 0);
            return $cachedData;
        }
        
        // 计算时间范围
        list($startTime, $endTime) = v2raysocks_traffic_calculateTimeRange($timeRange, $startDate, $endDate, $startTimestamp, $endTimestamp);
        
        // 使用大数据批处理引擎处理
        $results = $processor->processUserRankingsBatch($sortBy, $timeRange, $limit, $startTime, $endTime);
        
        // 缓存结果
        $cacheManager->set($cacheKey, $results, [
            'data_type' => 'large_dataset',
            'time_range' => $timeRange
        ]);
        
        // 自动缓存失效
        $cacheManager->autoInvalidate('large_dataset_complete', [
            'time_range' => $timeRange,
            'record_count' => count($results)
        ]);
        
        logActivity("V2RaySocks Traffic Monitor: Optimized rankings completed with " . count($results) . " results", 0);
        
        return $results;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Optimized rankings error: " . $e->getMessage(), 0);
        // 失败时回退到原始函数
        return v2raysocks_traffic_getUserTrafficRankings($sortBy, $timeRange, $limit, $startDate, $endDate, $startTimestamp, $endTimestamp);
    }
}

/**
 * 计算时间范围的辅助函数
 * Helper function to calculate time range
 */
function v2raysocks_traffic_calculateTimeRange($timeRange, $startDate = null, $endDate = null, $startTimestamp = null, $endTimestamp = null)
{
    switch ($timeRange) {
        case 'today':
            return [strtotime('today'), strtotime('tomorrow') - 1];
        case 'week':
        case '7days':
            return [strtotime('-6 days', strtotime('today')), strtotime('tomorrow') - 1];
        case '15days':
            return [strtotime('-14 days', strtotime('today')), strtotime('tomorrow') - 1];
        case 'month':
        case '30days':
            return [strtotime('-29 days', strtotime('today')), strtotime('tomorrow') - 1];
        case 'custom':
            if ($startDate && $endDate) {
                return [strtotime($startDate . ' 00:00:00'), strtotime($endDate . ' 23:59:59')];
            }
            return [strtotime('today'), strtotime('tomorrow') - 1];
        case 'time_range':
            if ($startTimestamp !== null && $endTimestamp !== null) {
                return [intval($startTimestamp), intval($endTimestamp)];
            }
            return [strtotime('today'), strtotime('tomorrow') - 1];
        default:
            return [0, time()];
    }
}

/**
 * 包装函数：自动检测大数据集并选择处理方式
 * Wrapper function: automatic large dataset detection and processing selection
 */
function v2raysocks_traffic_getUserTrafficRankingsWithAutoOptimization($sortBy = 'traffic_desc', $timeRange = 'today', $limit = PHP_INT_MAX, $startDate = null, $endDate = null, $startTimestamp = null, $endTimestamp = null)
{
    try {
        // 自动检测大数据集条件
        $isLargeDataset = false;
        
        // 条件1: 时间范围超过24小时
        if (in_array($timeRange, ['week', '7days', '15days', 'month', '30days'])) {
            $isLargeDataset = true;
        }
        
        // 条件2: 请求结果数量超过10k
        if ($limit > 10000) {
            $isLargeDataset = true;
        }
        
        // 条件3: 自定义时间范围超过24小时
        if ($timeRange === 'custom' && $startDate && $endDate) {
            $timeDiff = strtotime($endDate) - strtotime($startDate);
            if ($timeDiff > 86400) { // 24小时
                $isLargeDataset = true;
            }
        }
        
        // 条件4: 时间戳范围超过24小时
        if ($timeRange === 'time_range' && $startTimestamp && $endTimestamp) {
            $timeDiff = $endTimestamp - $startTimestamp;
            if ($timeDiff > 86400) { // 24小时
                $isLargeDataset = true;
            }
        }
        
        // 条件5: 数据库记录数量检查（采样检查）
        try {
            $pdo = v2raysocks_traffic_createPDO();
            if ($pdo) {
                list($startTime, $endTime) = v2raysocks_traffic_calculateTimeRange($timeRange, $startDate, $endDate, $startTimestamp, $endTimestamp);
                
                // 快速估算记录数量
                $countSql = "SELECT COUNT(*) as record_count FROM user_usage WHERE t >= :start_time AND t <= :end_time LIMIT 50000";
                $countStmt = $pdo->prepare($countSql);
                $countStmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
                $countStmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
                $countStmt->execute();
                $recordCount = $countStmt->fetch(PDO::FETCH_ASSOC)['record_count'];
                
                // 如果记录数超过50k，认为是大数据集
                if ($recordCount >= 50000) {
                    $isLargeDataset = true;
                }
            }
        } catch (\Exception $e) {
            // 检查失败时不影响主流程
            logActivity("V2RaySocks Traffic Monitor: Record count check failed: " . $e->getMessage(), 0);
        }
        
        // 根据检测结果选择处理方式
        if ($isLargeDataset) {
            logActivity("V2RaySocks Traffic Monitor: Large dataset detected, using optimized processing for timeRange: {$timeRange}, limit: {$limit}", 0);
            
            // 首先尝试创建性能索引（如果还没有）
            v2raysocks_traffic_createPerformanceIndexes();
            
            // 使用优化处理
            return v2raysocks_traffic_getUserTrafficRankingsOptimized($sortBy, $timeRange, $limit, $startDate, $endDate, $startTimestamp, $endTimestamp);
        } else {
            logActivity("V2RaySocks Traffic Monitor: Small dataset detected, using standard processing for timeRange: {$timeRange}, limit: {$limit}", 0);
            
            // 小数据集继续使用标准处理（调用原始的 Monitor_DB.php 中的函数）
            // 这里我们不重新定义函数，而是让Monitor_DB.php的原始函数处理
            // 原始函数在Monitor_DB.php中已经定义，会在require时加载
            
            // 直接返回，让原始函数处理
            return null; // 表示使用原始函数
        }
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Auto-detection error: " . $e->getMessage(), 0);
        return null; // 表示使用原始函数
    }
}

/**
 * 初始化大数据处理功能
 * Initialize big data processing features
 */
function v2raysocks_traffic_initializeBigDataProcessing()
{
    try {
        // 创建性能索引
        v2raysocks_traffic_createPerformanceIndexes();
        
        // 创建预聚合表
        v2raysocks_traffic_createPreAggregationTables();
        
        // 更新今天的预聚合数据
        v2raysocks_traffic_updatePreAggregationData();
        
        logActivity("V2RaySocks Traffic Monitor: Big data processing features initialized successfully", 0);
        
        return true;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Big data processing initialization error: " . $e->getMessage(), 0);
        return false;
    }
}

function v2raysocks_traffic_activate()
{
    // Module activation function
    // This is called when the module is first activated
    // 初始化大数据处理功能
    v2raysocks_traffic_initializeBigDataProcessing();
    
    // Return an array with any errors or empty array for success
    return [];
}

function v2raysocks_traffic_deactivate()
{
    // Module deactivation function
    // This is called when the module is deactivated
    // Return an array with any errors or empty array for success
    return [];
}