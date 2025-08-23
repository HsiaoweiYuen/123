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
                    // New cursor pagination parameters
                    'cursor' => $_GET['cursor'] ?? null,
                    'page_size' => !empty($_GET['page_size']) ? intval($_GET['page_size']) : null,
                    'use_cursor_pagination' => $_GET['use_cursor_pagination'] ?? 'false',
                    'order_by' => $_GET['order_by'] ?? 't',
                    'direction' => $_GET['direction'] ?? 'DESC',
                    'return_pagination_info' => $_GET['return_pagination_info'] ?? 'false'
                ];
                
                // Use enhanced traffic data function for better node name resolution
                $useEnhanced = $_GET['enhanced'] ?? 'true';
                if ($useEnhanced === 'true') {
                    $trafficData = v2raysocks_traffic_getEnhancedTrafficData($filters);
                } else {
                    $trafficData = v2raysocks_traffic_getTrafficData($filters);
                }
                
                // Handle different response formats for cursor pagination
                $useCursorPagination = $filters['use_cursor_pagination'] === 'true';
                $returnPaginationInfo = $filters['return_pagination_info'] === 'true';
                
                $data = $trafficData;
                $pagination = null;
                
                if ($useCursorPagination || $returnPaginationInfo) {
                    // Extract data and pagination info
                    if (is_array($trafficData) && isset($trafficData['data'])) {
                        $data = $trafficData['data'];
                        $pagination = $trafficData['pagination'] ?? null;
                    }
                }
                
                // Apply PR#37 time grouping if requested
                $grouped = $_GET['grouped'] ?? 'false';
                $groupedData = null;
                if ($grouped === 'true') {
                    $timeRange = $filters['time_range'] ?? 'today';
                    $groupedData = v2raysocks_traffic_groupDataByTime($data, $timeRange);
                }
                
                $result = [
                    'status' => 'success',
                    'data' => $data,
                    'grouped_data' => $groupedData,
                    'count' => count($data),
                    'filters_applied' => array_filter($filters),
                    'enhanced_mode' => $useEnhanced === 'true'
                ];
                
                // Add pagination info if available
                if ($pagination) {
                    $result['pagination'] = $pagination;
                }
                
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
                
                // New cursor pagination parameters
                $cursor = $_GET['cursor'] ?? null;
                $useCursorPagination = $_GET['use_cursor_pagination'] ?? 'false';
                $returnPaginationInfo = $_GET['return_pagination_info'] ?? 'false';
                
                // Handle "all" option properly - remove limit restriction
                $limit = ($limitValue === 'all') ? PHP_INT_MAX : intval($limitValue);
                if ($limit <= 0) $limit = PHP_INT_MAX; // Default fallback - no limit
                
                // For cursor pagination, use smaller default page size
                if ($useCursorPagination === 'true' && $limit === PHP_INT_MAX) {
                    $limit = 1000; // Default page size for cursor pagination
                }
                
                $rankings = v2raysocks_traffic_getUserTrafficRankings($sortBy, $timeRange, $limit, $startDate, $endDate, $startTimestamp, $endTimestamp, $cursor);
                
                // Handle different response formats for cursor pagination
                $data = $rankings;
                $pagination = null;
                
                if ($useCursorPagination === 'true' || $returnPaginationInfo === 'true') {
                    // Extract data and pagination info
                    if (is_array($rankings) && isset($rankings['data'])) {
                        $data = $rankings['data'];
                        $pagination = $rankings['pagination'] ?? null;
                    }
                }
                
                $result = [
                    'status' => 'success',
                    'data' => $data,
                    'sort_by' => $sortBy,
                    'time_range' => $timeRange,
                    'limit' => $limitValue, // Return original value for frontend
                    'actual_limit' => $limit, // Return actual numeric limit used
                    'count' => count($data)
                ];
                
                // Add pagination info if available
                if ($pagination) {
                    $result['pagination'] = $pagination;
                }
                
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
                
                // New cursor pagination parameters
                $cursor = $_GET['cursor'] ?? null;
                $useCursorPagination = $_GET['use_cursor_pagination'] ?? 'false';
                $returnPaginationInfo = $_GET['return_pagination_info'] ?? 'false';
                
                // For cursor pagination, use smaller default page size
                if ($useCursorPagination === 'true' && $limit === PHP_INT_MAX) {
                    $limit = 1000; // Default page size for cursor pagination
                }
                
                $records = v2raysocks_traffic_getUsageRecords($nodeId, $userId, $timeRange, $limit, $startDate, $endDate, $uuid, $startTimestamp, $endTimestamp, $cursor);
                
                // Handle different response formats for cursor pagination
                $data = $records;
                $pagination = null;
                
                if ($useCursorPagination === 'true' || $returnPaginationInfo === 'true') {
                    // Extract data and pagination info
                    if (is_array($records) && isset($records['data'])) {
                        $data = $records['data'];
                        $pagination = $records['pagination'] ?? null;
                    }
                }
                
                $result = [
                    'status' => 'success',
                    'data' => $data,
                    'node_id' => $nodeId,
                    'user_id' => $userId,
                    'uuid' => $uuid,
                    'time_range' => $timeRange,
                    'start_timestamp' => $startTimestamp,
                    'end_timestamp' => $endTimestamp,
                    'limit' => $limit,
                    'count' => count($data)
                ];
                
                // Add pagination info if available
                if ($pagination) {
                    $result['pagination'] = $pagination;
                }
                
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
        case 'async_enqueue':
            // Enqueue a task for asynchronous processing
            try {
                $taskType = $_POST['task_type'] ?? $_GET['task_type'] ?? null;
                $taskData = $_POST['task_data'] ?? $_GET['task_data'] ?? [];
                $priority = intval($_POST['priority'] ?? $_GET['priority'] ?? 0);
                $delay = intval($_POST['delay'] ?? $_GET['delay'] ?? 0);
                
                if (!$taskType) {
                    throw new Exception('Task type is required');
                }
                
                // Decode JSON task data if it's a string
                if (is_string($taskData)) {
                    $taskData = json_decode($taskData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('Invalid JSON in task_data');
                    }
                }
                
                $processor = new AsyncProcessor();
                $taskId = $processor->enqueue($taskType, $taskData, $priority, $delay);
                
                $result = [
                    'status' => 'success',
                    'task_id' => $taskId,
                    'task_type' => $taskType,
                    'priority' => $priority,
                    'delay' => $delay
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis async_enqueue error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to enqueue task: ' . $e->getMessage()
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'async_status':
            // Get task status
            try {
                $taskId = $_GET['task_id'] ?? null;
                if (!$taskId) {
                    throw new Exception('Task ID is required');
                }
                
                $processor = new AsyncProcessor();
                $status = $processor->getTaskStatus($taskId);
                
                $result = [
                    'status' => 'success',
                    'task_id' => $taskId,
                    'task_status' => $status
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis async_status error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get task status: ' . $e->getMessage()
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'async_result':
            // Get task result
            try {
                $taskId = $_GET['task_id'] ?? null;
                if (!$taskId) {
                    throw new Exception('Task ID is required');
                }
                
                $processor = new AsyncProcessor();
                $taskResult = $processor->getResult($taskId);
                
                if ($taskResult === null) {
                    $result = [
                        'status' => 'not_ready',
                        'message' => 'Task result not available yet'
                    ];
                } else {
                    $result = [
                        'status' => 'success',
                        'task_id' => $taskId,
                        'result' => $taskResult
                    ];
                }
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis async_result error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get task result: ' . $e->getMessage()
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'async_process':
            // Process tasks in batch (for use by background workers)
            try {
                $taskType = $_GET['task_type'] ?? 'traffic_data';
                $batchSize = intval($_GET['batch_size'] ?? 10);
                $timeout = intval($_GET['timeout'] ?? 300);
                
                $processor = new AsyncProcessor();
                $results = $processor->processInBatches($taskType, $batchSize, $timeout);
                
                $result = [
                    'status' => 'success',
                    'task_type' => $taskType,
                    'batch_size' => $batchSize,
                    'processing_results' => $results
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis async_process error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to process tasks: ' . $e->getMessage()
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            die();
        case 'async_aggregate':
            // Get aggregated results
            try {
                $aggregateId = $_GET['aggregate_id'] ?? null;
                if (!$aggregateId) {
                    throw new Exception('Aggregate ID is required');
                }
                
                $processor = new AsyncProcessor();
                $aggregatedResults = $processor->aggregateResults($aggregateId);
                
                $result = [
                    'status' => 'success',
                    'aggregate_id' => $aggregateId,
                    'results' => $aggregatedResults
                ];
            } catch (\Exception $e) {
                logActivity("V2RaySocks Traffic Analysis async_aggregate error: " . $e->getMessage(), 0);
                $result = [
                    'status' => 'error',
                    'message' => 'Failed to get aggregated results: ' . $e->getMessage()
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

function v2raysocks_traffic_activate()
{
    // Module activation function
    // This is called when the module is first activated
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