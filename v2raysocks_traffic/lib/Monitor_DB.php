<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/Monitor_Redis.php';

use WHMCS\Database\Capsule;

/**
 * Load language file based on module configuration
 */
function v2raysocks_traffic_loadLanguage()
{
    static $langLoaded = false;
    static $lang = [];
    
    if ($langLoaded) {
        return $lang;
    }
    
    try {
        $cacheKey = 'language_config';
        
        // Try to get from cache first
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    $lang = $decodedData;
                    $langLoaded = true;
                    return $lang;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for language config: " . $e->getMessage(), 0);
        }
        
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'v2raysocks_traffic')
            ->where('setting', 'language')
            ->value('value');
            
        $language = $settings ?: 'english';
        $langFile = __DIR__ . '/../lang/' . $language . '.php';
        
        if (file_exists($langFile)) {
            require $langFile;
            $lang = $_LANG;
        } else {
            // Fallback to English
            require __DIR__ . '/../lang/english.php';
            $lang = $_LANG;
        }
        
        $langLoaded = true;
        
        // Try to cache the language data for 10 minutes (configuration data)
        if (!empty($lang)) {
            try {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($lang),
                    'ttl' => 600 // 10 minutes for language configuration
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed for language config: " . $e->getMessage(), 0);
            }
        }
        
        return $lang;
    } catch (\Exception $e) {
        // Fallback to English on error
        require __DIR__ . '/../lang/english.php';
        $lang = $_LANG;
        $langLoaded = true;
        return $lang;
    }
}

/**
 * Get translated text
 */
function v2raysocks_traffic_lang($key)
{
    $lang = v2raysocks_traffic_loadLanguage();
    return isset($lang[$key]) ? $lang[$key] : $key;
}

function v2raysocks_traffic_serverInfo()
{
    try {
        $cacheKey = 'server_info_config';
        
        // Try to get from cache first
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    // Convert back to object to maintain compatibility
                    return (object) $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for server info: " . $e->getMessage(), 0);
        }
        
        $v2raysocksServerId = Capsule::table('tbladdonmodules')->where('module', 'v2raysocks_traffic')->where('setting', 'v2raysocks_server')->value('value');
        $v2raysocksServer = Capsule::table('tblservers')->where('type', 'V2RaySocks')->where('id', $v2raysocksServerId)->first();
        
        // Try to cache the result for 10 minutes (configuration data)
        if ($v2raysocksServer) {
            try {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($v2raysocksServer),
                    'ttl' => 600 // 10 minutes for configuration data
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed for server info: " . $e->getMessage(), 0);
            }
        }
        
        return $v2raysocksServer;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor serverInfo error: " . $e->getMessage(), 0);
        return null;
    }
}

function v2raysocks_traffic_createPDO()
{
    $serverInfo = v2raysocks_traffic_serverInfo();
    
    // Check if server configuration exists
    if (!$serverInfo) {
        logActivity("V2RaySocks Traffic Monitor: No V2RaySocks server configured. Please configure a V2RaySocks server in WHMCS first.", 0);
        return null;
    }
    
    // Validate required server properties
    if (empty($serverInfo->ipaddress) || empty($serverInfo->name) || empty($serverInfo->username)) {
        logActivity("V2RaySocks Traffic Monitor: Incomplete server configuration. Missing required fields (ipaddress, name, or username).", 0);
        return null;
    }
    
    try {
        $dsn = "mysql:host={$serverInfo->ipaddress};dbname={$serverInfo->name}";
        $pdo = new PDO($dsn, $serverInfo->username, decrypt($serverInfo->password));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES 'utf8mb4'");
        return $pdo;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return null;
    }
}


function v2raysocks_traffic_getDayTraffic($filters = [])
{
    try {
        $cacheKey = 'day_traffic_' . md5(serialize($filters));
        $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
        
        if ($cachedData) {
            $decodedData = json_decode($cachedData, true);
            if (!empty($decodedData)) {
                return $decodedData;
            }
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return [];
        }

        $sql = 'SELECT t, sid, u as upload, d as download FROM user_usage WHERE 1=1';
        $params = [];

        if (!empty($filters['sid'])) {
            $sql .= ' AND sid = :sid';
            $params[':sid'] = $filters['sid'];
        }

        if (!empty($filters['uuid'])) {
            // First get the sid from the uuid
            $userStmt = $pdo->prepare('SELECT id FROM user WHERE uuid = :uuid');
            $userStmt->execute([':uuid' => $filters['uuid']]);
            $user = $userStmt->fetch(PDO::FETCH_OBJ);
            if ($user) {
                $sql .= ' AND sid = :sid';
                $params[':sid'] = $user->id;
            } else {
                return [];
            }
        }

        if (!empty($filters['start'])) {
            // Ensure we have a valid date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['start'])) {
                $sql .= ' AND t >= :start';
                $params[':start'] = strtotime($filters['start']);
            }
        }

        if (!empty($filters['end'])) {
            // Ensure we have a valid date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['end'])) {
                $sql .= ' AND t <= :end';
                $params[':end'] = strtotime($filters['end'] . ' 23:59:59');
            }
        }

        $sql .= ' ORDER BY t DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group data by date using actual timestamps (PR#37 pattern)
        $dayData = [];
        
        foreach ($results as $row) {
            $timestamp = intval($row['t']);
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            
            // Group by date using server local time - consistent with PR#37 pattern
            $dateKey = $date->format('Y-m-d');
            
            if (!isset($dayData[$dateKey])) {
                $dayData[$dateKey] = [];
            }
            if (!isset($dayData[$dateKey][$row['sid']])) {
                $dayData[$dateKey][$row['sid']] = [
                    'date' => $dateKey,
                    'sid' => $row['sid'],
                    'upload' => 0,
                    'download' => 0
                ];
            }
            
            $dayData[$dateKey][$row['sid']]['upload'] += floatval($row['upload']);
            $dayData[$dateKey][$row['sid']]['download'] += floatval($row['download']);
        }
        
        // Flatten the array and sort by date descending
        $results = [];
        foreach ($dayData as $dateKey => $sids) {
            foreach ($sids as $data) {
                $results[] = $data;
            }
        }
        
        // Sort by date descending
        usort($results, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        // Validate and clean the traffic data to prevent extreme values
        v2raysocks_traffic_validateTrafficData($results);
        
        // Cache with optimized TTL based on data recency
        $cacheTime = 300; // Default 5 minutes
        if (!empty($filters['start']) && strtotime($filters['start']) > strtotime('-1 day')) {
            $cacheTime = 120; // 2 minutes for recent data
        } elseif (!empty($filters['start']) && strtotime($filters['start']) > strtotime('-1 hour')) {
            $cacheTime = 60; // 1 minute for very recent data
        }
        
        if (!empty($results)) {
            try {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($results),
                    'ttl' => $cacheTime
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed for day traffic: " . $e->getMessage(), 0);
            }
        }
        
        return $results;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return [];
    }
}
function v2raysocks_traffic_getTrafficData($filters = [])
{
    try {
        $cacheKey = 'traffic_data_' . md5(serialize($filters));
        
        // Try to get from cache first, but don't fail if caching is unavailable
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for traffic data: " . $e->getMessage(), 0);
        }
        
        // Use monitor module's PDO creation for independence
        $pdo = v2raysocks_traffic_createPDO();
        
        if (!$pdo) {
            logActivity("V2RaySocks Traffic Monitor: Cannot retrieve traffic data - database connection failed", 0);
            return [];
        }
        
        // Build query based on filters - aligned with nodes module approach
        $sql = 'SELECT 
                    uu.*, 
                    u.uuid, 
                    u.sid as service_id, 
                    u.transfer_enable, 
                    u.u as total_upload,
                    u.d as total_download,
                    u.speedlimitss,
                    u.speedlimitother,
                    u.illegal,
                    n.name as node_name,
                    n.address as node_address,
                    uu.node as node_identifier
                FROM user_usage AS uu
                LEFT JOIN user AS u ON uu.user_id = u.id
                LEFT JOIN node AS n ON uu.node = n.name
                WHERE 1=1';
        
        $params = [];
        
        // Apply filters - following nodes module pattern
        if (!empty($filters['user_id'])) {
            $sql .= ' AND uu.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['service_id']) || !empty($filters['sid'])) {
            $sid = $filters['service_id'] ?: $filters['sid'];
            $sql .= ' AND u.sid = :service_id';
            $params[':service_id'] = $sid;
        }
        
        if (!empty($filters['node_id'])) {
            $sql .= ' AND uu.node = :node_id';
            $params[':node_id'] = $filters['node_id'];
        }
        
        if (!empty($filters['uuid'])) {
            $sql .= ' AND u.uuid LIKE :uuid';
            $params[':uuid'] = '%' . $filters['uuid'] . '%';
        }
        
        // Time range filtering - simplified to match nodes module approach
        if (!empty($filters['time_range'])) {
            switch ($filters['time_range']) {
                case 'today':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('today');
                    break;
                case 'last_1_hour':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('-1 hour');
                    break;
                case 'last_3_hours':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('-3 hours');
                    break;
                case 'last_6_hours':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('-6 hours');
                    break;
                case 'last_12_hours':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('-12 hours');
                    break;
                case 'week':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('-6 days', strtotime('today'));
                    break;
                case 'halfmonth':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('-14 days', strtotime('today'));
                    break;
                case 'month':
                case 'month_including_today':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('-29 days', strtotime('today'));
                    break;
                case 'current_month':
                    $sql .= ' AND uu.t >= :start_time';
                    $params[':start_time'] = strtotime('first day of this month 00:00:00');
                    break;
                case 'custom':
                    // For custom range, skip time_range filtering and rely on start_date/end_date filters below
                    break;
                default:
                    if (is_numeric($filters['time_range'])) {
                        $sql .= ' AND uu.t >= :start_time';
                        $params[':start_time'] = time() - (intval($filters['time_range']) * 60);
                    }
                    break;
            }
        }
        
        // Custom date range - enhanced with better validation
        if (!empty($filters['start_date']) || !empty($filters['start'])) {
            $startDate = $filters['start_date'] ?: $filters['start'];
            // Ensure we have a valid date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                $startTime = strtotime($startDate . ' 00:00:00');
                if ($startTime !== false) {
                    $sql .= ' AND uu.t >= :custom_start';
                    $params[':custom_start'] = $startTime;
                }
            }
        }
        
        if (!empty($filters['end_date']) || !empty($filters['end'])) {
            $endDate = $filters['end_date'] ?: $filters['end'];
            // Ensure we have a valid date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $endTime = strtotime($endDate . ' 23:59:59');
                if ($endTime !== false) {
                    $sql .= ' AND uu.t <= :custom_end';
                    $params[':custom_end'] = $endTime;
                }
            }
        }
        
        // Timestamp-based filtering (for real-time monitor time selection)
        if (!empty($filters['start_timestamp'])) {
            $sql .= ' AND uu.t >= :timestamp_start';
            $params[':timestamp_start'] = $filters['start_timestamp'];
        }
        
        if (!empty($filters['end_timestamp'])) {
            $sql .= ' AND uu.t <= :timestamp_end';
            $params[':timestamp_end'] = $filters['end_timestamp'];
        }
        
        $sql .= ' ORDER BY uu.t DESC LIMIT 1000';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validate and clean the traffic data to prevent extreme values
        v2raysocks_traffic_validateTrafficData($data);
        
        // Add current_node_name field for "used node name" functionality
        $todayStart = strtotime('today');
        foreach ($data as &$row) {
            // Ensure node_name is not empty; if it is, try to use the node identifier
            if (empty($row['node_name']) && !empty($row['node_identifier'])) {
                // If node_name is null/empty but we have a node identifier, use it
                $row['node_name'] = $row['node_identifier'];
            }
            
            // For today's data, show the node name as "current_node_name"
            if ($row['t'] >= $todayStart) {
                $row['current_node_name'] = $row['node_name'];
            } else {
                // For historical data, leave empty
                $row['current_node_name'] = '';
            }
        }
        unset($row); // Break reference
        
        // Try to cache with shorter time for real-time data - but don't fail if caching fails
        try {
            $cacheTime = 60; // 1 minute default
            if (isset($filters['time_range'])) {
                switch ($filters['time_range']) {
                    case 'today':
                        $cacheTime = 120; // 2 minutes for today's data
                        break;
                    default:
                        $cacheTime = 300; // 5 minutes for longer periods
                        break;
                }
            }
            
            v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => json_encode($data),
                'ttl' => $cacheTime
            ]);
        } catch (\Exception $e) {
            // Cache write failed, but we can still return the data
            logActivity("V2RaySocks Traffic Monitor: Cache write failed for traffic data: " . $e->getMessage(), 0);
        }
        
        return $data;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getTrafficData error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Get enhanced traffic data with improved node name resolution
 * Following the nodes module approach for better compatibility
 */
function v2raysocks_traffic_getEnhancedTrafficData($filters = [])
{
    try {
        $cacheKey = 'enhanced_traffic_' . md5(serialize($filters));
        
        // Try to get from cache first
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for enhanced traffic data: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            logActivity("V2RaySocks Traffic Monitor: Cannot retrieve enhanced traffic data - database connection failed", 0);
            return [];
        }
        
        // Two-part query approach: get day traffic records and regular traffic records
        $allData = [];
        
        // Part 1: Get daily aggregated traffic (following nodes module pattern)
        if (empty($filters['exclude_day_traffic'])) {
            $dayTrafficSql = 'SELECT 
                        uu.*, 
                        u.uuid, 
                        u.sid as service_id, 
                        u.transfer_enable, 
                        u.u as total_upload,
                        u.d as total_download,
                        u.speedlimitss,
                        u.speedlimitother,
                        u.illegal,
                        "Day Summary" as node_name,
                        uu.node as node_identifier
                    FROM user_usage AS uu
                    LEFT JOIN user AS u ON uu.user_id = u.id
                    WHERE uu.node = "DAY"';
            
            $dayParams = [];
            
            // Apply day traffic filters
            if (!empty($filters['user_id'])) {
                $dayTrafficSql .= ' AND uu.user_id = :day_user_id';
                $dayParams[':day_user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['service_id']) || !empty($filters['sid'])) {
                $sid = $filters['service_id'] ?: $filters['sid'];
                $dayTrafficSql .= ' AND u.sid = :day_service_id';
                $dayParams[':day_service_id'] = $sid;
            }
            
            // Time range filtering for day traffic - skip if custom dates are provided
            if (!empty($filters['time_range']) && $filters['time_range'] !== 'custom') {
                $timeFilter = v2raysocks_traffic_getTimeFilter($filters['time_range']);
                if ($timeFilter) {
                    $dayTrafficSql .= ' AND uu.t >= :day_start_time';
                    $dayParams[':day_start_time'] = $timeFilter;
                }
            }
            
            // Custom date range for day traffic
            if (!empty($filters['start_date']) || !empty($filters['start'])) {
                $startDate = $filters['start_date'] ?: $filters['start'];
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                    $startTime = strtotime($startDate . ' 00:00:00');
                    if ($startTime !== false) {
                        $dayTrafficSql .= ' AND uu.t >= :day_custom_start';
                        $dayParams[':day_custom_start'] = $startTime;
                    }
                }
            }
            
            if (!empty($filters['end_date']) || !empty($filters['end'])) {
                $endDate = $filters['end_date'] ?: $filters['end'];
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                    $endTime = strtotime($endDate . ' 23:59:59');
                    if ($endTime !== false) {
                        $dayTrafficSql .= ' AND uu.t <= :day_custom_end';
                        $dayParams[':day_custom_end'] = $endTime;
                    }
                }
            }
            
            // Timestamp-based filtering for day traffic (for real-time monitor time selection)
            if (!empty($filters['start_timestamp'])) {
                $dayTrafficSql .= ' AND uu.t >= :day_timestamp_start';
                $dayParams[':day_timestamp_start'] = $filters['start_timestamp'];
            }
            
            if (!empty($filters['end_timestamp'])) {
                $dayTrafficSql .= ' AND uu.t <= :day_timestamp_end';
                $dayParams[':day_timestamp_end'] = $filters['end_timestamp'];
            }
            
            $dayTrafficSql .= ' ORDER BY uu.t DESC LIMIT 500';
            
            $dayStmt = $pdo->prepare($dayTrafficSql);
            $dayStmt->execute($dayParams);
            $dayData = $dayStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($dayData)) {
                $allData = array_merge($allData, $dayData);
            }
        }
        
        // Part 2: Get regular traffic records (for real-time monitoring)
        $regularTrafficSql = 'SELECT 
                    uu.*, 
                    u.uuid, 
                    u.sid as service_id, 
                    u.transfer_enable, 
                    u.u as total_upload,
                    u.d as total_download,
                    u.speedlimitss,
                    u.speedlimitother,
                    u.illegal,
                    COALESCE(n.name, uu.node, CONCAT("Node ", uu.node)) as node_name,
                    uu.node as node_identifier
                FROM user_usage AS uu
                LEFT JOIN user AS u ON uu.user_id = u.id
                LEFT JOIN node AS n ON (
                    CASE 
                        WHEN uu.node REGEXP "^[0-9]+$" THEN uu.node = n.id
                        ELSE uu.node = n.name
                    END
                )
                WHERE uu.node != "DAY"';
        
        $regularParams = [];
        
        // Apply regular traffic filters
        if (!empty($filters['user_id'])) {
            $regularTrafficSql .= ' AND uu.user_id = :user_id';
            $regularParams[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['service_id']) || !empty($filters['sid'])) {
            $sid = $filters['service_id'] ?: $filters['sid'];
            $regularTrafficSql .= ' AND u.sid = :service_id';
            $regularParams[':service_id'] = $sid;
        }
        
        if (!empty($filters['node_id'])) {
            $regularTrafficSql .= ' AND uu.node = :node_id';
            $regularParams[':node_id'] = $filters['node_id'];
        }
        
        if (!empty($filters['uuid'])) {
            $regularTrafficSql .= ' AND u.uuid LIKE :uuid';
            $regularParams[':uuid'] = '%' . $filters['uuid'] . '%';
        }
        
        // Time range filtering for regular traffic - skip if custom dates are provided
        if (!empty($filters['time_range']) && $filters['time_range'] !== 'custom') {
            $timeFilter = v2raysocks_traffic_getTimeFilter($filters['time_range']);
            if ($timeFilter) {
                $regularTrafficSql .= ' AND uu.t >= :start_time';
                $regularParams[':start_time'] = $timeFilter;
            }
        }
        
        // Custom date range - enhanced with better validation
        if (!empty($filters['start_date']) || !empty($filters['start'])) {
            $startDate = $filters['start_date'] ?: $filters['start'];
            // Ensure we have a valid date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                $startTime = strtotime($startDate . ' 00:00:00');
                if ($startTime !== false) {
                    $regularTrafficSql .= ' AND uu.t >= :custom_start';
                    $regularParams[':custom_start'] = $startTime;
                }
            }
        }
        
        if (!empty($filters['end_date']) || !empty($filters['end'])) {
            $endDate = $filters['end_date'] ?: $filters['end'];
            // Ensure we have a valid date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $endTime = strtotime($endDate . ' 23:59:59');
                if ($endTime !== false) {
                    $regularTrafficSql .= ' AND uu.t <= :custom_end';
                    $regularParams[':custom_end'] = $endTime;
                }
            }
        }
        
        // Timestamp-based filtering for regular traffic (for real-time monitor time selection)
        if (!empty($filters['start_timestamp'])) {
            $regularTrafficSql .= ' AND uu.t >= :timestamp_start';
            $regularParams[':timestamp_start'] = $filters['start_timestamp'];
        }
        
        if (!empty($filters['end_timestamp'])) {
            $regularTrafficSql .= ' AND uu.t <= :timestamp_end';
            $regularParams[':timestamp_end'] = $filters['end_timestamp'];
        }
        
        $regularTrafficSql .= ' ORDER BY uu.t DESC LIMIT 500';
        
        $regularStmt = $pdo->prepare($regularTrafficSql);
        $regularStmt->execute($regularParams);
        $regularData = $regularStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($regularData)) {
            $allData = array_merge($allData, $regularData);
        }
        
        // Sort all data by timestamp descending
        usort($allData, function($a, $b) {
            return $b['t'] - $a['t'];
        });
        
        // Limit total results
        $allData = array_slice($allData, 0, 1000);
        
        // Validate and clean the traffic data
        v2raysocks_traffic_validateTrafficData($allData);
        
        // Add current_node_name field for "used node name" functionality
        $todayStart = strtotime('today');
        foreach ($allData as &$row) {
            // Ensure node_name is not empty; if it is, try to use the node identifier
            if (empty($row['node_name']) && !empty($row['node_identifier'])) {
                $row['node_name'] = $row['node_identifier'];
            }
            
            // For today's data, show the node name as "current_node_name"
            if ($row['t'] >= $todayStart) {
                $row['current_node_name'] = $row['node_name'];
            } else {
                // For historical data, leave empty
                $row['current_node_name'] = '';
            }
        }
        unset($row); // Break reference
        
        // Try to cache the results
        try {
            $cacheTime = 120; // 2 minutes for enhanced data
            v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => json_encode($allData),
                'ttl' => $cacheTime
            ]);
        } catch (\Exception $e) {
            // Cache write failed, but we can still return the data
            logActivity("V2RaySocks Traffic Monitor: Cache write failed for enhanced traffic data: " . $e->getMessage(), 0);
        }
        
        return $allData;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getEnhancedTrafficData error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Helper function to get time filter timestamp
 */
function v2raysocks_traffic_getTimeFilter($timeRange)
{
    switch ($timeRange) {
        case 'today':
            return strtotime('today');
        case 'last_1_hour':
            return strtotime('-1 hour');
        case 'last_3_hours':
            return strtotime('-3 hours');
        case 'last_6_hours':
            return strtotime('-6 hours');
        case 'last_12_hours':
            return strtotime('-12 hours');
        case 'week':
            return strtotime('-6 days', strtotime('today'));
        case 'halfmonth':
            return strtotime('-14 days', strtotime('today'));
        case 'month':
        case 'month_including_today':
            return strtotime('-29 days', strtotime('today'));
        case 'current_month':
            return strtotime('first day of this month 00:00:00');
        default:
            if (is_numeric($timeRange)) {
                return time() - (intval($timeRange) * 60);
            }
            return null;
    }
}

/**
 * Validate and clean traffic data to prevent extreme values
 */
function v2raysocks_traffic_validateTrafficData(&$data)
{
    $maxReasonableValue = 1e18; // 1 exabyte - anything above this is likely corrupted
    $correctionFactors = [1000000000000, 1000000000, 1000000]; // Try trillion, billion, million
    
    foreach ($data as &$row) {
        // Fields to check for extreme values
        $trafficFields = ['u', 'd', 'upload', 'download', 'total_upload', 'total_download'];
        
        foreach ($trafficFields as $field) {
            if (isset($row[$field]) && $row[$field] > $maxReasonableValue) {
                logActivity("V2RaySocks Traffic Monitor: Detected extreme {$field} value: " . $row[$field], 0);
                
                $corrected = false;
                foreach ($correctionFactors as $factor) {
                    $testValue = $row[$field] / $factor;
                    if ($testValue < 1e15 && $testValue > 1000) { // Reasonable range: 1KB to 1PB
                        $row[$field] = $testValue;
                        logActivity("V2RaySocks Traffic Monitor: Corrected {$field} from " . ($row[$field] * $factor) . " to " . $row[$field] . " (factor: " . $factor . ")", 0);
                        $corrected = true;
                        break;
                    }
                }
                
                if (!$corrected) {
                    $row[$field] = 0; // Set to 0 if no correction worked
                    logActivity("V2RaySocks Traffic Monitor: Reset extreme {$field} value to 0 (no correction possible)", 0);
                }
            }
        }
    }
    
    return $data;
}

/**
 * Get all nodes from database - independent implementation
 * Fixed to be more reliable and follow nodes module pattern
 */
function v2raysocks_traffic_getAllNode()
{
    try {
        // Try cache first, but don't fail if cache is unavailable
        $cacheKey = 'all_nodes_basic';
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    // Convert back to objects to maintain compatibility
                    return array_map(function($node) {
                        return (object) $node;
                    }, $decodedData);
                }
            }
        } catch (\Exception $e) {
            // Cache failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed in getAllNode: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            logActivity("V2RaySocks Traffic Monitor: Database connection failed in getAllNode", 0);
            return [];
        }

        // Use the same simple query as the working nodes module
        $stmt = $pdo->prepare('SELECT * FROM node ORDER BY id ASC');
        $stmt->execute();
        
        $nodes = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        // Try to cache for 5 minutes, but don't fail if caching fails
        if (!empty($nodes)) {
            try {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($nodes),
                    'ttl' => 300
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed in getAllNode: " . $e->getMessage(), 0);
            }
        }
        
        return $nodes;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return [];
    }
}

function v2raysocks_traffic_getLiveStats()
{
    try {
        $cacheKey = 'live_stats';
        
        // Try cache first with fallback strategy
        try {
            $cachedStats = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedStats) {
                $decodedStats = json_decode($cachedStats, true);
                if (!empty($decodedStats)) {
                    return $decodedStats;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for live stats: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        
        if (!$pdo) {
            return [
                'total_users' => 0,
                'active_users' => [
                    '5min' => 0,
                    '1hour' => 0,
                    '24hours' => 0
                ],
                'total_nodes' => 0,
                'online_nodes' => 0,
                'today_upload' => 0,
                'today_download' => 0,
                'traffic_periods' => [
                    '5min' => ['upload' => 0, 'download' => 0, 'total' => 0],
                    '1hour' => ['upload' => 0, 'download' => 0, 'total' => 0],
                    'monthly' => ['upload' => 0, 'download' => 0, 'total' => 0]
                ],
                'last_updated' => time(),
            ];
        }
        
        // Get total users (only enabled users)
        $stmt = $pdo->prepare('SELECT COUNT(*) as total_users FROM user WHERE enable IN (1, 2)');
        $stmt->execute();
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
        
        // Get active users (last 24 hours)
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) as active_users FROM user_usage WHERE t >= :yesterday');
        $stmt->execute([':yesterday' => strtotime('-24 hours')]);
        $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'];
        
        // Get total nodes
        $stmt = $pdo->prepare('SELECT COUNT(*) as total_nodes FROM node');
        $stmt->execute();
        $totalNodes = $stmt->fetch(PDO::FETCH_ASSOC)['total_nodes'];
        
        // Get online nodes (last 10 minutes)
        $stmt = $pdo->prepare('SELECT COUNT(*) as online_nodes FROM node WHERE last_online >= :recent');
        $stmt->execute([':recent' => time() - 600]);
        $onlineNodes = $stmt->fetch(PDO::FETCH_ASSOC)['online_nodes'];
        
        // Get today's traffic - enhanced with better error handling
        $todayTraffic = ['total_upload' => 0, 'total_download' => 0];
        try {
            $todayStart = strtotime('today');
            $stmt = $pdo->prepare('SELECT SUM(u) as total_upload, SUM(d) as total_download FROM user_usage WHERE t >= :today AND t < :tomorrow');
            $stmt->execute([
                ':today' => $todayStart, 
                ':tomorrow' => $todayStart + 86400
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $todayTraffic = [
                    'total_upload' => floatval($result['total_upload'] ?: 0),
                    'total_download' => floatval($result['total_download'] ?: 0)
                ];
            }
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Error calculating today's traffic: " . $e->getMessage(), 0);
        }
        
        // Get active users for different timeframes
        $activeUsers5min = 0;
        $activeUsers1hour = 0;
        $activeUsers24h = $activeUsers; // We already have 24h data
        
        try {
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) as active_users FROM user_usage WHERE t >= :time_5min');
            $stmt->execute([':time_5min' => time() - 300]); // 5 minutes
            $activeUsers5min = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'];
            
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) as active_users FROM user_usage WHERE t >= :time_1hour');
            $stmt->execute([':time_1hour' => time() - 3600]); // 1 hour
            $activeUsers1hour = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'];
        } catch (\Exception $e) {
            // Use fallback values if queries fail
        }
        
        // Get traffic for different periods
        $traffic5min = ['upload' => 0, 'download' => 0];
        $traffic1hour = ['upload' => 0, 'download' => 0];
        $trafficMonthly = ['upload' => 0, 'download' => 0];
        
        try {
            // Fix for traffic calculation to respect v2raysocks data merging mechanism
            // Prevent lookback beyond today's start to avoid including merged data from yesterday
            $todayStart = strtotime('today');
            
            // 5-minute traffic - don't look back beyond today's start
            $time5minStart = max(time() - 300, $todayStart);
            $stmt = $pdo->prepare('SELECT SUM(u) as upload, SUM(d) as download FROM user_usage WHERE t >= :time_5min');
            $stmt->execute([':time_5min' => $time5minStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $traffic5min = [
                'upload' => floatval($result['upload'] ?? 0),
                'download' => floatval($result['download'] ?? 0)
            ];
            
            // 1-hour traffic - don't look back beyond today's start
            $time1hourStart = max(time() - 3600, $todayStart);
            $stmt = $pdo->prepare('SELECT SUM(u) as upload, SUM(d) as download FROM user_usage WHERE t >= :time_1hour');
            $stmt->execute([':time_1hour' => $time1hourStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $traffic1hour = [
                'upload' => floatval($result['upload'] ?? 0),
                'download' => floatval($result['download'] ?? 0)
            ];
            
            // Monthly traffic (current month)
            $monthStart = strtotime('first day of this month 00:00:00');
            $stmt = $pdo->prepare('SELECT SUM(u) as upload, SUM(d) as download FROM user_usage WHERE t >= :month_start');
            $stmt->execute([':month_start' => $monthStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $trafficMonthly = [
                'upload' => floatval($result['upload'] ?? 0),
                'download' => floatval($result['download'] ?? 0)
            ];
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Error calculating traffic periods: " . $e->getMessage(), 0);
        }
        
        $stats = [
            'total_users' => $totalUsers,
            'active_users' => [
                '5min' => $activeUsers5min,
                '1hour' => $activeUsers1hour,
                '24hours' => $activeUsers24h
            ],
            'total_nodes' => $totalNodes,
            'online_nodes' => $onlineNodes,
            'today_upload' => $todayTraffic['total_upload'] ?? 0,
            'today_download' => $todayTraffic['total_download'] ?? 0,
            'traffic_periods' => [
                '5min' => [
                    'upload' => $traffic5min['upload'],
                    'download' => $traffic5min['download'],
                    'total' => $traffic5min['upload'] + $traffic5min['download']
                ],
                '1hour' => [
                    'upload' => $traffic1hour['upload'],
                    'download' => $traffic1hour['download'],
                    'total' => $traffic1hour['upload'] + $traffic1hour['download']
                ],
                'monthly' => [
                    'upload' => $trafficMonthly['upload'],
                    'download' => $trafficMonthly['download'],
                    'total' => $trafficMonthly['upload'] + $trafficMonthly['download']
                ]
            ],
            'last_updated' => time(),
        ];
        
        // Cache for 60 seconds for live stats (real-time data)
        $cacheTime = 60;
        if (!$activeUsers5min && !$activeUsers1hour) {
            // If we couldn't get active user counts, cache for shorter time
            $cacheTime = 30; 
        }
        
        // Try to cache with error handling
        try {
            v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => json_encode($stats),
                'ttl' => $cacheTime
            ]);
        } catch (\Exception $e) {
            // Cache write failed, but we can still return the data
            logActivity("V2RaySocks Traffic Monitor: Cache write failed for live stats: " . $e->getMessage(), 0);
        }
        
        return $stats;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getLiveStats error: " . $e->getMessage(), 0);
        return [
            'total_users' => 0,
            'active_users' => [
                '5min' => 0,
                '1hour' => 0,
                '24hours' => 0
            ],
            'total_nodes' => 0,
            'online_nodes' => 0,
            'today_upload' => 0,
            'today_download' => 0,
            'traffic_periods' => [
                '5min' => ['upload' => 0, 'download' => 0, 'total' => 0],
                '1hour' => ['upload' => 0, 'download' => 0, 'total' => 0],
                'monthly' => ['upload' => 0, 'download' => 0, 'total' => 0]
            ],
            'last_updated' => time(),
        ];
    }
}

function v2raysocks_traffic_getUserDetails($userID)
{
    try {
        $cacheKey = "user_details:$userID";
        $cachedDetails = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
        
        if ($cachedDetails) {
            return json_decode($cachedDetails, true);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        
        // Get user info
        $stmt = $pdo->prepare('SELECT * FROM user WHERE id = :user_id');
        $stmt->execute([':user_id' => $userID]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Get user's traffic history (last 30 days) using PR#37 pattern
        $stmt = $pdo->prepare('
            SELECT 
                t,
                u as daily_upload,
                d as daily_download
            FROM user_usage 
            WHERE user_id = :user_id AND t >= :thirty_days_ago
            ORDER BY t ASC
        ');
        $stmt->execute([
            ':user_id' => $userID,
            ':thirty_days_ago' => strtotime('-29 days', strtotime('today'))
        ]);
        $rawTrafficHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by date using actual timestamps (PR#37 pattern)
        $trafficHistory = [];
        $dailyData = [];
        
        foreach ($rawTrafficHistory as $row) {
            $timestamp = intval($row['t']);
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            
            // Group by date using server local time - consistent with PR#37 pattern
            $dateKey = $date->format('Y-m-d');
            
            if (!isset($dailyData[$dateKey])) {
                $dailyData[$dateKey] = [
                    'date' => $dateKey,
                    'daily_upload' => 0,
                    'daily_download' => 0
                ];
            }
            
            $dailyData[$dateKey]['daily_upload'] += floatval($row['daily_upload']);
            $dailyData[$dateKey]['daily_download'] += floatval($row['daily_download']);
        }
        
        // Convert to array and sort by date descending
        foreach ($dailyData as $data) {
            $trafficHistory[] = $data;
        }
        
        usort($trafficHistory, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        $details = [
            'user' => $user,
            'traffic_history' => $trafficHistory,
        ];
        
        // Cache for 5 minutes
        v2raysocks_traffic_redisOperate('set', [
            'key' => $cacheKey,
            'value' => json_encode($details),
            'ttl' => 300
        ]);
        
        return $details;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor's " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return null;
    }
}

function v2raysocks_traffic_getNodeDetails($nodeID)
{
    try {
        $cacheKey = "node_details:$nodeID";
        
        // Try to get from cache, but don't fail if caching is unavailable
        try {
            $cachedDetails = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedDetails) {
                $decodedDetails = json_decode($cachedDetails, true);
                if (!empty($decodedDetails)) {
                    return $decodedDetails;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for node details: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        
        if (!$pdo) {
            logActivity("V2RaySocks Traffic Monitor: Cannot retrieve node details - database connection failed", 0);
            return null;
        }
        
        // Get node info with better error handling
        $stmt = $pdo->prepare('SELECT * FROM node WHERE id = :node_id');
        $stmt->execute([':node_id' => $nodeID]);
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$node) {
            logActivity("V2RaySocks Traffic Monitor: Node with ID $nodeID not found", 0);
            return null;
        }
        
        // Get node's traffic stats (last 30 days) using PR#37 pattern
        $trafficStats = [];
        try {
            // Handle both node ID and node name in user_usage.node field
            $stmt = $pdo->prepare('
                SELECT 
                    t,
                    user_id,
                    u as daily_upload,
                    d as daily_download
                FROM user_usage 
                WHERE (node = :node_id OR node = :node_name) AND t >= :thirty_days_ago
                ORDER BY t ASC
            ');
            $stmt->execute([
                ':node_id' => $nodeID,
                ':node_name' => $node['name'],
                ':thirty_days_ago' => strtotime('-29 days', strtotime('today'))
            ]);
            $rawTrafficStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by date using actual timestamps (PR#37 pattern)
            $dailyStats = [];
            
            foreach ($rawTrafficStats as $row) {
                $timestamp = intval($row['t']);
                $date = new DateTime();
                $date->setTimestamp($timestamp);
                
                // Group by date using server local time - consistent with PR#37 pattern
                $dateKey = $date->format('Y-m-d');
                
                if (!isset($dailyStats[$dateKey])) {
                    $dailyStats[$dateKey] = [
                        'date' => $dateKey,
                        'unique_users' => [],
                        'daily_upload' => 0,
                        'daily_download' => 0
                    ];
                }
                
                $dailyStats[$dateKey]['unique_users'][$row['user_id']] = true;
                $dailyStats[$dateKey]['daily_upload'] += floatval($row['daily_upload']);
                $dailyStats[$dateKey]['daily_download'] += floatval($row['daily_download']);
            }
            
            // Convert to final format and sort by date descending
            foreach ($dailyStats as $data) {
                $trafficStats[] = [
                    'date' => $data['date'],
                    'unique_users' => count($data['unique_users']),
                    'daily_upload' => $data['daily_upload'],
                    'daily_download' => $data['daily_download']
                ];
            }
            
            usort($trafficStats, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Error retrieving traffic stats for node $nodeID: " . $e->getMessage(), 0);
            // Continue with empty traffic stats rather than failing completely
        }
        
        $details = [
            'node' => $node,
            'traffic_stats' => $trafficStats,
        ];
        
        // Try to cache for 5 minutes, but don't fail if caching fails
        try {
            v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => json_encode($details),
                'ttl' => 300
            ]);
        } catch (\Exception $e) {
            // Cache write failed, but we can still return the data
            logActivity("V2RaySocks Traffic Monitor: Cache write failed for node details: " . $e->getMessage(), 0);
        }
        
        return $details;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor's " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return null;
    }
}

function v2raysocks_traffic_serverOptions()
{
    try {
        $cacheKey = 'server_options_list';
        
        // Try to get from cache first
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for server options: " . $e->getMessage(), 0);
        }
        
        $servers = Capsule::table('tblservers')
            ->where('type', 'V2RaySocks')
            ->get();
        $options = [];
        foreach ($servers as $server) {
            $options[$server->id] = $server->name;
        }
        
        // If no servers found, provide helpful message
        if (empty($options)) {
            $options[''] = 'No V2RaySocks servers configured - Please add a server first';
        }
        
        // Try to cache server options for 10 minutes (configuration data)
        if (!empty($options)) {
            try {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($options),
                    'ttl' => 600 // 10 minutes for server configuration
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed for server options: " . $e->getMessage(), 0);
            }
        }
        
        return $options;
    } catch (Exception $e) {
        // If database error, provide fallback option
        logActivity("V2RaySocks Traffic - Error loading server options: " . $e->getMessage(), 0);
        return ['' => 'Error loading servers - Check WHMCS database connection'];
    }
}

function v2raysocks_traffic_displayTrafficDashboard($LANG)
{
    require_once(__DIR__ . '/../templates/traffic_dashboard.php');
    return $trafficDashboardHtml;
}

function v2raysocks_traffic_displayRealTimeMonitor($LANG)
{
    require_once(__DIR__ . '/../templates/real_time_monitor.php');
    return $realTimeMonitorHtml;
}

function v2raysocks_traffic_displayUserStats($LANG, $formData)
{
    require_once(__DIR__ . '/../templates/user_stats.php');
    return $userStatsHtml;
}

function v2raysocks_traffic_displayNodeStats($LANG, $formData)
{
    require_once(__DIR__ . '/../templates/node_stats.php');
    return $nodeStatsHtml;
}

function v2raysocks_traffic_displayUserRankings($LANG, $formData)
{
    require_once(__DIR__ . '/../templates/user_rankings.php');
    return $userRankingsHtml;
}

function v2raysocks_traffic_displayServiceSearch($LANG, $formData)
{
    require_once(__DIR__ . '/../templates/service_search.php');
    return $serviceSearchHtml;
}

function v2raysocks_traffic_displayTodayTrafficChart($LANG)
{
    require_once(__DIR__ . '/../templates/today_traffic_chart.php');
    return $todayTrafficChartHtml;
}

function v2raysocks_traffic_exportTrafficData($filters, $format = 'csv', $limit = null)
{
    try {
        // Check if this is a new ranking export type
        $exportType = $filters['export_type'] ?? 'traffic_data';
        
        switch ($exportType) {
            case 'node_rankings':
                return v2raysocks_traffic_exportNodeRankings($filters, $format, $limit);
            case 'user_rankings':
                return v2raysocks_traffic_exportUserRankings($filters, $format, $limit);
            case 'usage_records':
                return v2raysocks_traffic_exportUsageRecords($filters, $format, $limit);
            default:
                // Original traffic data export
                break;
        }
        
        // Get traffic data with applied filters
        $data = v2raysocks_traffic_getTrafficData($filters);
        
        // Apply limit if specified
        if ($limit && is_numeric($limit) && $limit > 0) {
            $data = array_slice($data, 0, intval($limit));
        }
        
        // Generate filename with timestamp and filter info
        $filterInfo = '';
        if (!empty($filters['service_id'])) {
            $filterInfo .= '_service_' . $filters['service_id'];
        }
        if (!empty($filters['time_range'])) {
            $filterInfo .= '_' . $filters['time_range'];
        }
        
        $filename = 'traffic_export' . $filterInfo . '_' . date('Y-m-d_H-i-s');
        
        switch (strtolower($format)) {
            case 'json':
                $filename .= '.json';
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                // Add formatted traffic values to each record for JSON export
                $formattedData = array_map(function($row) {
                    $upload = $row['u'] ?? 0;
                    $download = $row['d'] ?? 0;
                    $total = $upload + $download;
                    
                    // Only keep formatted data, remove raw bytes
                    $exportRow = [
                        'timestamp' => $row['t'],
                        'formatted_time' => (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($row['t']),
                        'user_id' => $row['user_id'] ?? '',
                        'service_id' => $row['service_id'] ?? '',
                        'uuid' => $row['uuid'] ?? '',
                        'node_name' => $row['node_name'] ?? '',
                        'formatted_upload' => v2raysocks_traffic_formatBytesConfigurable($upload),
                        'formatted_download' => v2raysocks_traffic_formatBytesConfigurable($download),
                        'formatted_total' => v2raysocks_traffic_formatBytesConfigurable($total),
                        'speedlimitss' => $row['speedlimitss'] ?? '',
                        'speedlimitother' => $row['speedlimitother'] ?? '',
                        'illegal' => $row['illegal'] ?? '0'
                    ];
                    
                    return $exportRow;
                }, $data);
                
                $exportData = [
                    'export_info' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'total_records' => count($formattedData),
                        'filters_applied' => array_filter($filters),
                        'format' => 'json'
                    ],
                    'data' => $formattedData
                ];
                
                echo json_encode($exportData, JSON_PRETTY_PRINT);
                break;
                
            case 'csv':
            default:
                $filename .= '.csv';
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                
                // CSV headers
                fputcsv($output, [
                    'Timestamp', 'User ID', 'Service ID', 'UUID', 'Node Name', 
                    'Upload (Formatted)', 'Download (Formatted)', 'Total (Formatted)',
                    'SS Speed Limit', 'V2Ray Speed Limit', 'Violation Count'
                ]);
                
                // Data rows
                foreach ($data as $row) {
                    $upload = $row['u'] ?? 0;
                    $download = $row['d'] ?? 0;
                    $total = $upload + $download;
                    
                    fputcsv($output, [
                        (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($row['t']),
                        $row['user_id'] ?? '',
                        $row['service_id'] ?? '',
                        $row['uuid'] ?? '',
                        $row['node_name'] ?? '',
                        v2raysocks_traffic_formatBytesConfigurable($upload),
                        v2raysocks_traffic_formatBytesConfigurable($download),
                        v2raysocks_traffic_formatBytesConfigurable($total),
                        $row['speedlimitss'] ?? '',
                        $row['speedlimitother'] ?? '',
                        $row['illegal'] ?? '0'
                    ]);
                }
                
                fclose($output);
                break;
        }
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor's " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        echo "Error exporting data: " . $e->getMessage();
    }
}

/**
 * Get timestamps for predefined time ranges
 */
function v2raysocks_traffic_getTimeRangeTimestamps($range)
{
    $now = time();
    
    switch ($range) {
        case '5min':
            return ['start' => $now - 300];
        case '10min':
            return ['start' => $now - 600];
        case '30min':
            return ['start' => $now - 1800];
        case '1hour':
            return ['start' => $now - 3600];
        case '2hours':
            return ['start' => $now - 7200];
        case '6hours':
            return ['start' => $now - 21600];
        case '12hours':
            return ['start' => $now - 43200];
        case 'today':
            return ['start' => strtotime('today')];
        case 'week':
            return ['start' => strtotime('-6 days', strtotime('today'))];
        case 'halfmonth':
            return ['start' => strtotime('-14 days', strtotime('today'))];
        case 'month':
            return ['start' => strtotime('-29 days', strtotime('today'))];
        case 'month_including_today':
            // Fix for 30-day query to include current day
            return ['start' => strtotime('-29 days', strtotime('today'))];
        default:
            return false;
    }
}

/**
 * Generate default time labels for charts when no data is available
 * This ensures charts always show a proper time axis even with empty data
 */
function v2raysocks_traffic_generateDefaultTimeLabels($range, $points = 10)
{
    $timestamps = v2raysocks_traffic_getTimeRangeTimestamps($range);
    if (!$timestamps) {
        return ['暂无数据'];
    }
    
    $start = $timestamps['start'];
    $end = time();
    $interval = ($end - $start) / ($points - 1);
    
    $labels = [];
    for ($i = 0; $i < $points; $i++) {
        $timestamp = $start + ($i * $interval);
        
        // Format based on time range
        switch ($range) {
            case '5min':
            case '10min':
            case '30min':
            case '1hour':
            case '2hours':
                $labels[] = date('H:i', $timestamp);
                break;
            case '6hours':
            case '12hours':
            case 'today':
                $labels[] = date('H:i', $timestamp);
                break;
            case 'week':
            case 'halfmonth':
            case 'month':
            case 'month_including_today':
                $labels[] = date('m-d', $timestamp);
                break;
            default:
                $labels[] = date('H:i', $timestamp);
        }
    }
    
    return $labels;
}

/**
 * Convert bytes to human readable format - uses decimal system only
 * Aligned with nodes module approach: simple conversion using / 1000000000 for GB
 */
function v2raysocks_traffic_formatBytesConfigurable($bytes, $precision = 2, $unit = 'auto', $system = 'decimal')
{
    // Convert to float and validate input
    $bytes = floatval($bytes);
    
    // Handle invalid, zero, or negative values
    if ($bytes === 0 || !is_finite($bytes) || $bytes < 0) {
        return '0 B';
    }
    
    // Handle extremely large values that are likely corrupted data
    // Real-world check: 1 PB (petabyte) is 1e15 bytes, so anything above 1e18 is suspicious
    if ($bytes > 1e18) { 
        logActivity("V2RaySocks Traffic Monitor: Detected corrupted traffic value: " . $bytes . " bytes", 0);
        // Try to see if this is a factor-of-1000 error - try multiple correction factors
        $correctionFactors = [1000000000000, 1000000000, 1000000]; // Try trillion, billion, million
        
        foreach ($correctionFactors as $factor) {
            $corrected = $bytes / $factor;
            if ($corrected < 1e15 && $corrected > 1000) { // If corrected value is reasonable (1KB to 1PB)
                logActivity("V2RaySocks Traffic Monitor: Corrected corrupted value from " . $bytes . " to " . $corrected . " (factor: " . $factor . ")", 0);
                $bytes = $corrected;
                break;
            }
        }
        
        // If still too large after correction attempts
        if ($bytes > 1e18) {
            return 'ERROR (Value too large)';
        }
    }
    
    // Always use decimal system only (no binary option)
    $base = 1000;
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB'];
    
    if ($unit === 'auto') {
        $exp = floor(log($bytes) / log($base));
        $exp = min($exp, count($units) - 1);
        $exp = max($exp, 0);
    } else {
        $unitMap = array_flip($units);
        $exp = isset($unitMap[$unit]) ? $unitMap[$unit] : 0;
        $exp = max($exp, 0);
    }
    
    $value = $bytes / pow($base, $exp);
    
    // Ensure the calculated value is reasonable
    if (!is_finite($value) || $value < 0) {
        return '0 B';
    }
    
    // Format like nodes module - use number_format for consistency
    if ($units[$exp] === 'GB') {
        return number_format($value, 2) . ' GB';
    }
    
    return round($value, $precision) . ' ' . $units[$exp];
}

/**
 * Get traffic data by service ID
 */
function v2raysocks_traffic_getTrafficByServiceId($serviceId, $filters = [])
{
    try {
        $filters['service_id'] = $serviceId;
        return v2raysocks_traffic_getTrafficData($filters);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor's " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Get module configuration settings
 */
function v2raysocks_traffic_getModuleConfig()
{
    try {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'v2raysocks_traffic')
            ->pluck('value', 'setting');
            
        return [
            'default_unit' => $settings['default_unit'] ?? 'auto',
            'refresh_interval' => $settings['refresh_interval'] ?? '300',
            'realtime_refresh_interval' => $settings['realtime_refresh_interval'] ?? '5',
            'chart_unit' => $settings['chart_unit'] ?? 'auto'
        ];
    } catch (\Exception $e) {
        return [
            'default_unit' => 'auto',
            'refresh_interval' => '300',
            'realtime_refresh_interval' => '5',
            'chart_unit' => 'auto'
        ];
    }
}

/**
 * Get today's traffic data by hour
 */
function v2raysocks_traffic_getTodayTrafficData()
{
    try {
        $cacheKey = 'today_traffic_' . date('Y-m-d');
        $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
        
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        
        if (!$pdo) {
            return [
                'date' => date('Y-m-d'),
                'hourly_stats' => [],
                'timezone' => date_default_timezone_get(),
                'last_updated' => time(),
                'error' => 'Database connection failed'
            ];
        }
        
        $todayStart = strtotime('today');
        
        // Get hourly traffic data for today only (not 24-hour lookback)
        // This ensures we only count current day's unmerged data, not data merged from yesterday
        $hourlyStats = [];
        
        // Initialize all hours from 00 to current hour (using server local time)
        $currentHour = intval(date('H'));
        for ($hour = 0; $hour <= $currentHour; $hour++) {
            $hourlyStats[sprintf('%02d', $hour)] = [
                'upload' => 0,
                'download' => 0
            ];
        }
        
        // Query traffic data using real timestamps (PR#37 pattern)
        try {
            $sql = 'SELECT 
                        t,
                        u as upload,
                        d as download
                    FROM user_usage 
                    WHERE t >= :today_start AND t < :today_end
                    ORDER BY t ASC';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':today_start' => $todayStart,
                ':today_end' => $todayStart + 86400
            ]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group data by hour using actual timestamps (server local time, not UTC)
            foreach ($results as $row) {
                $timestamp = intval($row['t']);
                $date = new DateTime();
                $date->setTimestamp($timestamp);
                
                // Group by hour using server local time - consistent with PR#37 pattern
                $hour = $date->format('H');
                
                if (!isset($hourlyStats[$hour])) {
                    $hourlyStats[$hour] = ['upload' => 0, 'download' => 0];
                }
                
                $hourlyStats[$hour]['upload'] += floatval($row['upload']);
                $hourlyStats[$hour]['download'] += floatval($row['download']);
            }
            
        } catch (\Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Error querying today's hourly traffic: " . $e->getMessage(), 0);
        }
        
        $data = [
            'date' => date('Y-m-d'),
            'hourly_stats' => $hourlyStats,
            'timezone' => date_default_timezone_get(),
            'last_updated' => time()
        ];
        
        // Cache for 5 minutes
        v2raysocks_traffic_redisOperate('set', [
            'key' => $cacheKey,
            'value' => json_encode($data),
            'ttl' => 300
        ]);
        
        return $data;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getTodayTrafficData error: " . $e->getMessage(), 0);
        return [
            'date' => date('Y-m-d'),
            'hourly_stats' => [],
            'timezone' => date_default_timezone_get(),
            'last_updated' => time(),
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get total traffic statistics since site launch
 */
function v2raysocks_traffic_getTotalTrafficSinceLaunch()
{
    try {
        $cacheKey = 'total_traffic_since_launch';
        $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
        
        if ($cachedData) {
            return json_decode($cachedData, true);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        
        if (!$pdo) {
            logActivity("V2RaySocks Traffic Monitor: Database connection failed in getTotalTrafficSinceLaunch", 0);
            return [
                'total_upload' => 0,
                'total_download' => 0,
                'total_traffic' => 0,
                'first_record' => null,
                'last_updated' => time()
            ];
        }
        
        // Get total traffic from user_usage table (all historical data)
        // Use DECIMAL to handle large numbers safely and avoid integer overflow
        $sql = 'SELECT 
                    SUM(CAST(u AS DECIMAL(20,0))) as total_upload,
                    SUM(CAST(d AS DECIMAL(20,0))) as total_download,
                    MIN(t) as first_record,
                    MAX(t) as last_record,
                    COUNT(*) as total_records,
                    AVG(CAST(u AS DECIMAL(20,0))) as avg_upload,
                    AVG(CAST(d AS DECIMAL(20,0))) as avg_download
                FROM user_usage 
                WHERE u > 0 OR d > 0';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalUpload = floatval($result['total_upload'] ?? 0);
        $totalDownload = floatval($result['total_download'] ?? 0);
        $avgUpload = floatval($result['avg_upload'] ?? 0);
        $avgDownload = floatval($result['avg_download'] ?? 0);
        
        // Data validation - check for suspiciously large values
        $maxReasonableValue = 1e18; // 1 EB
        if ($totalUpload > $maxReasonableValue || $totalDownload > $maxReasonableValue) {
            logActivity("V2RaySocks Traffic Monitor: Detected extremely large traffic values - Upload: $totalUpload, Download: $totalDownload", 0);
        }
        
        // Check for negative values (should not happen but just in case)
        $totalUpload = max(0, $totalUpload);
        $totalDownload = max(0, $totalDownload);
        
        $totalTraffic = $totalUpload + $totalDownload;
        
        $data = [
            'total_upload' => $totalUpload,
            'total_download' => $totalDownload,
            'total_traffic' => $totalTraffic,
            'avg_upload' => $avgUpload,
            'avg_download' => $avgDownload,
            'first_record' => $result['first_record'] ? (function($timestamp) {
                $date = new DateTime();
                $date->setTimestamp($timestamp);
                return $date->format('Y-m-d H:i:s');
            })($result['first_record']) : null,
            'last_record' => $result['last_record'] ? (function($timestamp) {
                $date = new DateTime();
                $date->setTimestamp($timestamp);
                return $date->format('Y-m-d H:i:s');
            })($result['last_record']) : null,
            'total_records' => intval($result['total_records'] ?? 0),
            'days_active' => $result['first_record'] ? ceil((time() - $result['first_record']) / 86400) : 0,
            'last_updated' => time()
        ];
        
        // Cache for 10 minutes
        v2raysocks_traffic_redisOperate('set', [
            'key' => $cacheKey,
            'value' => json_encode($data),
            'ttl' => 600
        ]);
        
        return $data;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor's " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return [
            'total_upload' => 0,
            'total_download' => 0,
            'total_traffic' => 0,
            'avg_upload' => 0,
            'avg_download' => 0,
            'first_record' => null,
            'last_record' => null,
            'total_records' => 0,
            'days_active' => 0,
            'last_updated' => time()
        ];
    }
}

/**
 * Get all available nodes from the database with comprehensive statistics
 * Fixed to follow the same reliable pattern as v2raysocks_nodes_getAllNode()
 */
function v2raysocks_traffic_getAllNodes()
{
    try {
        $cacheKey = 'monitor_all_nodes_list';
        
        // Try to get from cache first, but don't rely on it completely
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                // Only return cached data if it's not empty
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for getAllNodes: " . $e->getMessage(), 0);
        }
        
        // Get database connection using traffic module's PDO creation
        $pdo = v2raysocks_traffic_createPDO();
        
        if (!$pdo) {
            logActivity("V2RaySocks Traffic Monitor: Database connection failed in getAllNodes", 0);
            return [];
        }
        
        // Get basic nodes data first - following nodes module pattern
        $stmt = $pdo->prepare('SELECT * FROM node ORDER BY id ASC');
        $stmt->execute();
        $nodes = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        if (!$nodes) {
            logActivity("V2RaySocks Traffic Monitor: No nodes found in database. This could indicate a database connection issue or empty node table.", 0);
            return [];
        }
        
        // Process node data with comprehensive monitoring info
        $nodeList = [];
        foreach ($nodes as $node) {
            try {
                // Get traffic statistics for this node
                // Handle both node ID and node name in user_usage.node field
                $stmt = $pdo->prepare('
                    SELECT 
                        SUM(u) as total_upload,
                        SUM(d) as total_download,
                        COUNT(DISTINCT user_id) as unique_users,
                        COUNT(*) as total_records,
                        MIN(t) as first_record,
                        MAX(t) as last_record
                    FROM user_usage 
                    WHERE (node = :node_id OR node = :node_name)
                ');
                $stmt->execute([
                    ':node_id' => $node->id,
                    ':node_name' => $node->name
                ]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get recent activity (last 24 hours)
                $stmt = $pdo->prepare('
                    SELECT 
                        SUM(u) as recent_upload,
                        SUM(d) as recent_download,
                        COUNT(DISTINCT user_id) as recent_users
                    FROM user_usage 
                    WHERE (node = :node_id OR node = :node_name) AND t >= :last_24h
                ');
                $stmt->execute([
                    ':node_id' => $node->id,
                    ':node_name' => $node->name,
                    ':last_24h' => strtotime('-24 hours')
                ]);
                $recentStats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Calculate performance metrics
                $currentTime = time();
                $isOnline = ($currentTime - intval($node->last_online ?: 0)) < 600; // 10 minutes
                $totalTraffic = floatval($stats['total_upload'] ?: 0) + floatval($stats['total_download'] ?: 0);
                $recentTraffic = floatval($recentStats['recent_upload'] ?: 0) + floatval($recentStats['recent_download'] ?: 0);
                
                // Calculate average traffic per user
                $avgTrafficPerUser = $stats['unique_users'] > 0 ? $totalTraffic / $stats['unique_users'] : 0;
                
                $nodeList[] = [
                    'id' => intval($node->id),
                    'name' => $node->name ?: 'Node ' . $node->id,
                    'address' => $node->address ?: '',
                    'speedlimit' => intval($node->speed_limit ?: 0),
                    'status' => $isOnline,
                    'enable' => intval($node->enable ?: 0),
                    'statistics' => floatval($node->statistics ?: 0),
                    'max_traffic' => floatval($node->max_traffic ?: 0),
                    'last_online' => intval($node->last_online ?: 0),
                    'country' => $node->country ?: '',
                    'tag' => $node->tag ?: '',
                    'type' => $node->type ?: '',
                    
                    // Comprehensive traffic statistics
                    'total_upload' => floatval($stats['total_upload'] ?: 0),
                    'total_download' => floatval($stats['total_download'] ?: 0),
                    'total_traffic' => $totalTraffic,
                    'user_count' => intval($stats['unique_users'] ?: 0),
                    'total_records' => intval($stats['total_records'] ?: 0),
                    'first_record' => intval($stats['first_record'] ?: 0),
                    'last_record' => intval($stats['last_record'] ?: 0),
                    
                    // Recent activity (24h)
                    'recent_upload' => floatval($recentStats['recent_upload'] ?: 0),
                    'recent_download' => floatval($recentStats['recent_download'] ?: 0),
                    'recent_traffic' => $recentTraffic,
                    'recent_users' => intval($recentStats['recent_users'] ?: 0),
                    
                    // Performance indicators
                    'avg_traffic_per_user' => $avgTrafficPerUser,
                    'traffic_utilization' => $node->max_traffic > 0 ? ($totalTraffic / ($node->max_traffic * 1000000000)) * 100 : 0,
                    'is_online' => $isOnline,
                    'last_seen' => $currentTime - intval($node->last_online ?: 0)
                ];
            } catch (\Exception $e) {
                // If there's an error processing this specific node, still include it with default values
                logActivity("V2RaySocks Traffic Monitor: Error processing node {$node->id}: " . $e->getMessage(), 0);
                
                $nodeList[] = [
                    'id' => intval($node->id),
                    'name' => $node->name ?: 'Node ' . $node->id,
                    'address' => $node->address ?: '',
                    'speedlimit' => intval($node->speed_limit ?: 0),
                    'status' => false,
                    'enable' => intval($node->enable ?: 0),
                    'statistics' => floatval($node->statistics ?: 0),
                    'max_traffic' => floatval($node->max_traffic ?: 0),
                    'last_online' => intval($node->last_online ?: 0),
                    'country' => $node->country ?: '',
                    'tag' => $node->tag ?: '',
                    'type' => $node->type ?: '',
                    'total_upload' => 0,
                    'total_download' => 0,
                    'total_traffic' => 0,
                    'user_count' => 0,
                    'total_records' => 0,
                    'first_record' => 0,
                    'last_record' => 0,
                    'recent_upload' => 0,
                    'recent_download' => 0,
                    'recent_traffic' => 0,
                    'recent_users' => 0,
                    'avg_traffic_per_user' => 0,
                    'traffic_utilization' => 0,
                    'is_online' => false,
                    'last_seen' => time() - intval($node->last_online ?: 0),
                    'processing_error' => true
                ];
            }
        }
        
        // Ensure we have at least basic node data - add fallback for nodes without detailed stats
        if (empty($nodeList) && !empty($nodes)) {
            // If detailed processing failed completely, return basic node info
            logActivity("V2RaySocks Traffic Monitor: Detailed node processing failed, returning basic node information", 0);
            foreach ($nodes as $node) {
                $currentTime = time();
                $isOnline = ($currentTime - intval($node->last_online ?: 0)) < 600;
                
                $nodeList[] = [
                    'id' => intval($node->id),
                    'name' => $node->name ?: 'Node ' . $node->id,
                    'address' => $node->address ?: '',
                    'speedlimit' => intval($node->speed_limit ?: 0),
                    'status' => $isOnline,
                    'enable' => intval($node->enable ?: 0),
                    'statistics' => floatval($node->statistics ?: 0),
                    'max_traffic' => floatval($node->max_traffic ?: 0),
                    'last_online' => intval($node->last_online ?: 0),
                    'country' => $node->country ?: '',
                    'tag' => $node->tag ?: '',
                    'type' => $node->type ?: '',
                    'total_upload' => 0,
                    'total_download' => 0,
                    'total_traffic' => 0,
                    'user_count' => 0,
                    'total_records' => 0,
                    'first_record' => 0,
                    'last_record' => 0,
                    'recent_upload' => 0,
                    'recent_download' => 0,
                    'recent_traffic' => 0,
                    'recent_users' => 0,
                    'avg_traffic_per_user' => 0,
                    'traffic_utilization' => 0,
                    'is_online' => $isOnline,
                    'last_seen' => $currentTime - intval($node->last_online ?: 0),
                    'fallback_data' => true
                ];
            }
        }
        
        // Sort nodes by total traffic descending, but handle fallback data appropriately
        usort($nodeList, function($a, $b) {
            // If both have fallback data, sort by node ID
            if (isset($a['fallback_data']) && isset($b['fallback_data'])) {
                return $a['id'] <=> $b['id'];
            }
            // If one has fallback data, put it at the end
            if (isset($a['fallback_data'])) return 1;
            if (isset($b['fallback_data'])) return -1;
            // Normal sorting by traffic
            return $b['total_traffic'] <=> $a['total_traffic'];
        });
        
        // Cache for 5 minutes - only cache if we have results
        if (!empty($nodeList)) {
            try {
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($nodeList),
                    'ttl' => 300
                ]);
            } catch (\Exception $e) {
                // Don't fail if caching fails
                logActivity("V2RaySocks Traffic Monitor: Caching failed for getAllNodes: " . $e->getMessage(), 0);
            }
        }
        
        return $nodeList;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor's " . __FUNCTION__ . " error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Get cache performance statistics
 */
function v2raysocks_traffic_getCacheStats()
{
    try {
        $stats = v2raysocks_traffic_redisOperate('stats', []);
        if (!$stats) {
            return [
                'hits' => 0,
                'misses' => 0,
                'sets' => 0,
                'errors' => 0,
                'hit_rate' => 0,
                'redis_available' => false
            ];
        }
        
        $totalRequests = $stats['hits'] + $stats['misses'];
        $hitRate = $totalRequests > 0 ? ($stats['hits'] / $totalRequests) * 100 : 0;
        
        return array_merge($stats, [
            'hit_rate' => round($hitRate, 2),
            'redis_available' => true,
            'total_requests' => $totalRequests
        ]);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Cache stats failed: " . $e->getMessage(), 0);
        return [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'errors' => 0,
            'hit_rate' => 0,
            'redis_available' => false
        ];
    }
}

/**
 * Utility function to clear specific cache entries with improved strategy
 */
function v2raysocks_traffic_clearCache($cacheKeys = [], $clearPattern = null)
{
    $defaultKeys = [
        'live_stats',
        'monitor_all_nodes_list', 
        'total_traffic_since_launch',
        'all_nodes_basic'
    ];
    
    try {
        // Clear by pattern if specified
        if ($clearPattern) {
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => $clearPattern]);
            return true;
        }
        
        // Clear specific keys
        $keysToClear = empty($cacheKeys) ? $defaultKeys : $cacheKeys;
        
        foreach ($keysToClear as $key) {
            v2raysocks_traffic_redisOperate('del', ['key' => $key]);
        }
        
        // Clear commonly updated cache patterns when doing full clear
        if (empty($cacheKeys)) {
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'traffic_data_*']);
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => 'enhanced_traffic_*']);
            v2raysocks_traffic_redisOperate('clear_pattern', ['pattern' => '*_rankings_*']);
        }
        
        return true;
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor: Cache clear failed: " . $e->getMessage(), 0);
        return false;
    }
}

/**
 * Get traffic data by service ID - simplified approach
 */
function v2raysocks_traffic_searchByServiceId($serviceId, $filters = [])
{
    try {
        $filters['service_id'] = $serviceId;
        // Use enhanced traffic data for better node name resolution
        return v2raysocks_traffic_getEnhancedTrafficData($filters);
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor searchByServiceId error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Get node traffic rankings with today's data only
 */
function v2raysocks_traffic_getNodeTrafficRankings($sortBy = 'traffic_desc', $onlyToday = true)
{
    try {
        // Try cache first, but don't fail if cache is unavailable
        $cacheKey = 'node_traffic_rankings_' . md5($sortBy . '_' . ($onlyToday ? 'today' : 'all'));
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for node rankings: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return [];
        }

        // Calculate today's date range and short-term time ranges
        $todayStart = $onlyToday ? strtotime('today') : 0;
        $todayEnd = $onlyToday ? strtotime('tomorrow') - 1 : time();
        $currentTime = time();
        $time5min = $currentTime - 300;     // 5 minutes ago
        $time1hour = $currentTime - 3600;   // 1 hour ago  
        $time4hour = $currentTime - 14400;  // 4 hours ago

        // Get nodes with traffic data - handle both node ID and name matching
        $sql = '
            SELECT 
                n.id,
                n.name,
                n.address,
                n.enable,
                n.statistics,
                n.max_traffic,
                n.last_online,
                n.country,
                n.type,
                COALESCE(SUM(uu.u), 0) as total_upload,
                COALESCE(SUM(uu.d), 0) as total_download,
                COALESCE(SUM(uu.u + uu.d), 0) as total_traffic,
                COALESCE(SUM(CASE WHEN uu.t >= :time_5min THEN uu.u + uu.d ELSE 0 END), 0) as traffic_5min,
                COALESCE(SUM(CASE WHEN uu.t >= :time_1hour THEN uu.u + uu.d ELSE 0 END), 0) as traffic_1hour,
                COALESCE(SUM(CASE WHEN uu.t >= :time_4hour THEN uu.u + uu.d ELSE 0 END), 0) as traffic_4hour,
                COUNT(DISTINCT uu.user_id) as unique_users,
                COUNT(uu.id) as usage_records
            FROM node n
            LEFT JOIN user_usage uu ON (uu.node = n.id OR uu.node = n.name) 
                AND uu.t >= :start_time AND uu.t <= :end_time AND uu.node != \'DAY\'
            GROUP BY n.id, n.name, n.address, n.enable, n.statistics, n.max_traffic, n.last_online, n.country, n.type
        ';

        // Add sorting
        switch ($sortBy) {
            case 'traffic_desc':
                $sql .= ' ORDER BY total_traffic DESC';
                break;
            case 'traffic_asc':
                $sql .= ' ORDER BY total_traffic ASC';
                break;
            case 'remaining_desc':
                $sql .= ' ORDER BY (n.max_traffic * 1000000000 - COALESCE(SUM(uu.u + uu.d), 0)) DESC';
                break;
            case 'remaining_asc':
                $sql .= ' ORDER BY (n.max_traffic * 1000000000 - COALESCE(SUM(uu.u + uu.d), 0)) ASC';
                break;
            case 'users_desc':
                $sql .= ' ORDER BY unique_users DESC';
                break;
            case 'name_asc':
                $sql .= ' ORDER BY n.name ASC';
                break;
            default:
                $sql .= ' ORDER BY total_traffic DESC';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':start_time' => $todayStart,
            ':end_time' => $todayEnd,
            ':time_5min' => $time5min,
            ':time_1hour' => $time1hour,
            ':time_4hour' => $time4hour
        ]);
        
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $currentTime = time();
        
        // Process results and add calculated fields
        foreach ($nodes as &$node) {
            $node['id'] = intval($node['id']);
            $node['total_upload'] = floatval($node['total_upload']);
            $node['total_download'] = floatval($node['total_download']);
            $node['total_traffic'] = floatval($node['total_traffic']);
            $node['traffic_5min'] = floatval($node['traffic_5min']);
            $node['traffic_1hour'] = floatval($node['traffic_1hour']);
            $node['traffic_4hour'] = floatval($node['traffic_4hour']);
            $node['unique_users'] = intval($node['unique_users']);
            $node['usage_records'] = intval($node['usage_records']);
            $node['max_traffic'] = floatval($node['max_traffic']);
            $node['statistics'] = floatval($node['statistics']);
            $node['last_online'] = intval($node['last_online']);
            
            // Calculate remaining traffic
            $maxTrafficBytes = $node['max_traffic'] * 1000000000; // Convert GB to bytes
            $node['remaining_traffic'] = max(0, $maxTrafficBytes - $node['total_traffic']);
            $node['traffic_utilization'] = $maxTrafficBytes > 0 ? ($node['total_traffic'] / $maxTrafficBytes) * 100 : 0;
            
            // Online status (within last 10 minutes)
            $node['is_online'] = ($currentTime - $node['last_online']) < 600;
            $node['last_seen_minutes'] = intval(($currentTime - $node['last_online']) / 60);
            
            // Traffic efficiency
            $node['avg_traffic_per_user'] = $node['unique_users'] > 0 ? $node['total_traffic'] / $node['unique_users'] : 0;
        }
        
        // Try to cache for optimized duration based on data type
        if (!empty($nodes)) {
            try {
                // Use shorter TTL for node rankings as this is frequently accessed real-time data
                $cacheTTL = $onlyToday ? 180 : 300; // 3 minutes for today, 5 minutes for all-time
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($nodes),
                    'ttl' => $cacheTTL
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed for node rankings: " . $e->getMessage(), 0);
            }
        }
        
        return $nodes;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getNodeTrafficRankings error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Get user traffic rankings
 */
function v2raysocks_traffic_getUserTrafficRankings($sortBy = 'traffic_desc', $timeRange = 'today', $limit = 100, $startDate = null, $endDate = null)
{
    try {
        // Try cache first, but don't fail if cache is unavailable
        $cacheKey = 'user_traffic_rankings_' . md5($sortBy . '_' . $timeRange . '_' . $limit . '_' . ($startDate ?: '') . '_' . ($endDate ?: ''));
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for user rankings: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return [];
        }

        // Calculate time range
        switch ($timeRange) {
            case 'today':
                $startTime = strtotime('today');
                $endTime = strtotime('tomorrow') - 1;
                break;
            case 'week':
            case '7days':
                // Fix: Use exactly 7 complete days from 7 days ago at 00:00:00 to end of today
                $startTime = strtotime('-6 days', strtotime('today')); // Start of 7 days ago (today - 6 = 7 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                break;
            case '15days':
                // Fix: Use exactly 15 complete days from 15 days ago at 00:00:00 to end of today
                $startTime = strtotime('-14 days', strtotime('today')); // Start of 15 days ago (today - 14 = 15 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                break;
            case 'month':
            case '30days':
                // Fix: Use exactly 30 complete days from 30 days ago at 00:00:00 to end of today
                $startTime = strtotime('-29 days', strtotime('today')); // Start of 30 days ago (today - 29 = 30 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $startTime = strtotime($startDate . ' 00:00:00'); // Ensure start of day
                    $endTime = strtotime($endDate . ' 23:59:59'); // End of the end date
                } else {
                    // Fallback to today if custom dates are invalid
                    $startTime = strtotime('today');
                    $endTime = strtotime('tomorrow') - 1;
                }
                break;
            case 'all':
            default:
                $startTime = 0;
                $endTime = time();
                break;
        }

        // Calculate short-term time ranges
        $currentTime = time();
        $time5min = $currentTime - 300;     // 5 minutes ago
        $time1hour = $currentTime - 3600;   // 1 hour ago  
        $time4hour = $currentTime - 14400;  // 4 hours ago

        // Get users with traffic data including short-term traffic
        $sql = '
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
            WHERE u.enable = 1
            GROUP BY u.id, u.uuid, u.sid, u.u, u.d, u.transfer_enable, u.enable, u.created_at, u.remark
        ';

        // Add sorting
        switch ($sortBy) {
            case 'traffic_desc':
                $sql .= ' ORDER BY period_traffic DESC';
                break;
            case 'traffic_asc':
                $sql .= ' ORDER BY period_traffic ASC';
                break;
            case 'remaining_desc':
                $sql .= ' ORDER BY (u.transfer_enable - (u.u + u.d)) DESC';
                break;
            case 'remaining_asc':
                $sql .= ' ORDER BY (u.transfer_enable - (u.u + u.d)) ASC';
                break;
            case 'nodes_desc':
                $sql .= ' ORDER BY nodes_used DESC';
                break;
            case 'recent_activity':
                $sql .= ' ORDER BY last_usage DESC';
                break;
            default:
                $sql .= ' ORDER BY period_traffic DESC';
        }

        $sql .= ' LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_INT);
        $stmt->bindValue(':end_time', $endTime, PDO::PARAM_INT);
        $stmt->bindValue(':time_5min', $time5min, PDO::PARAM_INT);
        $stmt->bindValue(':time_1hour', $time1hour, PDO::PARAM_INT);
        $stmt->bindValue(':time_4hour', $time4hour, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process results and add calculated fields
        foreach ($users as &$user) {
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
            
            // Calculate remaining quota
            $totalUsed = $user['total_upload_user'] + $user['total_download_user'];
            $user['remaining_quota'] = max(0, $user['transfer_enable'] - $totalUsed);
            $user['quota_utilization'] = $user['transfer_enable'] > 0 ? ($totalUsed / $user['transfer_enable']) * 100 : 0;
            
            // Activity metrics
            $user['has_activity'] = $user['period_traffic'] > 0;
            $user['avg_traffic_per_node'] = $user['nodes_used'] > 0 ? $user['period_traffic'] / $user['nodes_used'] : 0;
        }
        
        // Try to cache with optimized TTL based on time range
        if (!empty($users)) {
            try {
                // Use shorter TTL for real-time rankings, longer for historical data
                $cacheTTL = ($timeRange === 'today' || $timeRange === 'custom') ? 180 : 600; // 3 min for today, 10 min for historical
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($users),
                    'ttl' => $cacheTTL
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed for user rankings: " . $e->getMessage(), 0);
            }
        }
        
        return $users;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getUserTrafficRankings error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Get detailed traffic chart data for a specific node (today only by default)
 */
function v2raysocks_traffic_getNodeTrafficChart($nodeId, $timeRange = 'today')
{
    try {
        // Try cache first, but don't fail if cache is unavailable
        $cacheKey = 'node_traffic_chart_' . md5($nodeId . '_' . $timeRange);
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for node chart: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return ['labels' => [], 'data' => []];
        }

        // Calculate time range
        switch ($timeRange) {
            case 'today':
                $startTime = strtotime('today');
                $endTime = strtotime('tomorrow') - 1;
                $interval = 3600; // 1 hour intervals
                break;
            case 'week':
                $startTime = strtotime('-6 days', strtotime('today'));
                $endTime = time();
                $interval = 86400; // 1 day intervals
                break;
            case 'month':
                $startTime = strtotime('-29 days', strtotime('today'));
                $endTime = time();
                $interval = 86400; // 1 day intervals
                break;
            default:
                $startTime = strtotime('today');
                $endTime = strtotime('tomorrow') - 1;
                $interval = 3600;
        }

        // Get raw traffic data ordered by timestamp - use actual data timestamps like traffic dashboard
        // First, get the node name to handle both ID and name matching
        $nodeStmt = $pdo->prepare('SELECT name FROM node WHERE id = :node_id');
        $nodeStmt->execute([':node_id' => $nodeId]);
        $nodeInfo = $nodeStmt->fetch(PDO::FETCH_ASSOC);
        $nodeName = $nodeInfo ? $nodeInfo['name'] : '';
        
        $sql = '
            SELECT 
                t,
                u as upload,
                d as download,
                (u + d) as total,
                user_id
            FROM user_usage 
            WHERE (node = :node_id OR node = :node_name) AND t >= :start_time AND t <= :end_time
            ORDER BY t ASC
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':node_id' => $nodeId,
            ':node_name' => $nodeName,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group data by time periods using actual timestamps (server local time, not UTC)
        $timeData = [];
        
        foreach ($results as $row) {
            // Use actual data timestamp like traffic dashboard
            $timestamp = intval($row['t']);
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            
            // Time grouping using server local time (not UTC) - consistent with traffic_dashboard.php
            if ($timeRange === 'today') {
                // For today, group by hour with proper time display
                $timeKey = $date->format('H') . ':00';
            } else if (in_array($timeRange, ['week', 'month'])) {
                // For weekly/monthly ranges, group by day using local time
                $timeKey = $date->format('Y-m-d');
            } else {
                // For other ranges, group by day using local time
                $timeKey = $date->format('Y-m-d');
            }
            
            if (!isset($timeData[$timeKey])) {
                $timeData[$timeKey] = ['upload' => 0, 'download' => 0, 'users' => []];
            }
            
            $timeData[$timeKey]['upload'] += floatval($row['upload']);
            $timeData[$timeKey]['download'] += floatval($row['download']);
            $timeData[$timeKey]['users'][] = $row['user_id'];
        }
        
        // Sort time keys properly
        ksort($timeData);
        
        // Prepare chart data arrays
        $labels = [];
        $uploadData = [];
        $downloadData = [];
        $totalData = [];
        $userCounts = [];
        
        foreach ($timeData as $timeKey => $data) {
            $labels[] = $timeKey;
            $uploadData[] = $data['upload'] / 1000000000; // Convert to GB
            $downloadData[] = $data['download'] / 1000000000; // Convert to GB
            $totalData[] = ($data['upload'] + $data['download']) / 1000000000; // Convert to GB
            $userCounts[] = count(array_unique($data['users']));
        }
        
        $chartData = [
            'labels' => $labels,
            'upload' => $uploadData,
            'download' => $downloadData,
            'total' => $totalData,
            'users' => $userCounts,
            'node_id' => $nodeId,
            'time_range' => $timeRange
        ];
        
        // Try to cache with optimized TTL for chart data
        try {
            // Use shorter TTL for chart data as it's frequently updated and viewed
            $cacheTTL = ($timeRange === 'today') ? 120 : 300; // 2 min for today, 5 min for historical
            v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => json_encode($chartData),
                'ttl' => $cacheTTL
            ]);
        } catch (\Exception $e) {
            // Cache write failed, but we can still return the data
            logActivity("V2RaySocks Traffic Monitor: Cache write failed for node chart: " . $e->getMessage(), 0);
        }
        
        return $chartData;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getNodeTrafficChart error: " . $e->getMessage(), 0);
        return ['labels' => [], 'data' => []];
    }
}

/**
 * Get detailed traffic chart data for a specific user
 */
function v2raysocks_traffic_getUserTrafficChart($userId, $timeRange = 'today', $startDate = null, $endDate = null)
{
    try {
        // Try cache first, but don't fail if cache is unavailable
        $cacheKey = 'user_traffic_chart_' . md5($userId . '_' . $timeRange . '_' . ($startDate ?: '') . '_' . ($endDate ?: ''));
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for user chart: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return ['labels' => [], 'data' => []];
        }

        // Calculate time range
        switch ($timeRange) {
            case 'today':
                $startTime = strtotime('today');
                $endTime = strtotime('tomorrow') - 1;
                $interval = 3600; // 1 hour intervals
                break;
            case 'week':
            case '7days':
                // Fix: Use exactly 7 complete days from 7 days ago at 00:00:00 to end of today
                $startTime = strtotime('-6 days', strtotime('today')); // Start of 7 days ago (today - 6 = 7 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                $interval = 86400; // 1 day intervals
                break;
            case '15days':
                // Fix: Use exactly 15 complete days from 15 days ago at 00:00:00 to end of today
                $startTime = strtotime('-14 days', strtotime('today')); // Start of 15 days ago (today - 14 = 15 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                $interval = 86400; // 1 day intervals
                break;
            case 'month':
            case '30days':
                // Fix: Use exactly 30 complete days from 30 days ago at 00:00:00 to end of today
                $startTime = strtotime('-29 days', strtotime('today')); // Start of 30 days ago (today - 29 = 30 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                $interval = 86400; // 1 day intervals
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $startTime = strtotime($startDate . ' 00:00:00'); // Ensure start of day
                    $endTime = strtotime($endDate . ' 23:59:59'); // End of the end date
                    
                    // Determine interval based on date range duration
                    $daysDiff = ($endTime - $startTime) / 86400;
                    if ($daysDiff <= 1) {
                        $interval = 3600; // 1 hour intervals for 1 day or less
                    } else {
                        $interval = 86400; // 1 day intervals for longer periods
                    }
                } else {
                    // Fallback to today if custom dates are invalid
                    $startTime = strtotime('today');
                    $endTime = strtotime('tomorrow') - 1;
                    $interval = 3600;
                }
                break;
            default:
                $startTime = strtotime('today');
                $endTime = strtotime('tomorrow') - 1;
                $interval = 3600;
        }

        // Get raw traffic data ordered by timestamp - use actual data timestamps like traffic dashboard
        $sql = '
            SELECT 
                t,
                u as upload,
                d as download,
                (u + d) as total,
                node
            FROM user_usage 
            WHERE user_id = :user_id AND t >= :start_time AND t <= :end_time
            ORDER BY t ASC
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group data by time periods using actual timestamps (server local time, not UTC)
        $timeData = [];
        
        foreach ($results as $row) {
            // Use actual data timestamp like traffic dashboard
            $timestamp = intval($row['t']);
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            
            // Time grouping using server local time (not UTC) - consistent with traffic_dashboard.php
            if ($timeRange === 'today') {
                // For today, group by hour with proper time display
                $timeKey = $date->format('H') . ':00';
            } else if (in_array($timeRange, ['week', '7days', '15days', 'month', '30days'])) {
                // For weekly/bi-weekly/monthly ranges, group by day using local time
                $timeKey = $date->format('Y-m-d');
            } else {
                // For longer ranges, group by day using local time
                $timeKey = $date->format('Y-m-d');
            }
            
            if (!isset($timeData[$timeKey])) {
                $timeData[$timeKey] = ['upload' => 0, 'download' => 0, 'nodes' => []];
            }
            
            $timeData[$timeKey]['upload'] += floatval($row['upload']);
            $timeData[$timeKey]['download'] += floatval($row['download']);
            $timeData[$timeKey]['nodes'][] = $row['node'];
        }
        
        // Sort time keys properly
        ksort($timeData);
        
        // Prepare chart data arrays
        $labels = [];
        $uploadData = [];
        $downloadData = [];
        $totalData = [];
        $nodeCounts = [];
        
        foreach ($timeData as $timeKey => $data) {
            $labels[] = $timeKey;
            $uploadData[] = $data['upload'] / 1000000000; // Convert to GB
            $downloadData[] = $data['download'] / 1000000000; // Convert to GB
            $totalData[] = ($data['upload'] + $data['download']) / 1000000000; // Convert to GB
            $nodeCounts[] = count(array_unique($data['nodes']));
        }
        
        $chartData = [
            'labels' => $labels,
            'upload' => $uploadData,
            'download' => $downloadData,
            'total' => $totalData,
            'nodes' => $nodeCounts,
            'user_id' => $userId,
            'time_range' => $timeRange
        ];
        
        // Try to cache with optimized TTL for chart data
        try {
            // Use shorter TTL for chart data as it's frequently updated and viewed
            $cacheTTL = ($timeRange === 'today') ? 120 : 300; // 2 min for today, 5 min for historical
            v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => json_encode($chartData),
                'ttl' => $cacheTTL
            ]);
        } catch (\Exception $e) {
            // Cache write failed, but we can still return the data
            logActivity("V2RaySocks Traffic Monitor: Cache write failed for user chart: " . $e->getMessage(), 0);
        }
        
        return $chartData;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getUserTrafficChart error: " . $e->getMessage(), 0);
        return ['labels' => [], 'data' => []];
    }
}

/**
 * Get usage records for a specific node or user
 */
function v2raysocks_traffic_getUsageRecords($nodeId = null, $userId = null, $timeRange = 'today', $limit = 100, $startDate = null, $endDate = null, $uuid = null, $startTimestamp = null, $endTimestamp = null)
{
    try {
        // Try cache first, but don't fail if cache is unavailable
        $cacheKey = 'usage_records_' . md5(($nodeId ?: 'null') . '_' . ($userId ?: 'null') . '_' . ($uuid ?: 'null') . '_' . $timeRange . '_' . $limit . '_' . ($startDate ?: '') . '_' . ($endDate ?: '') . '_' . ($startTimestamp ?: '') . '_' . ($endTimestamp ?: ''));
        try {
            $cachedData = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            if ($cachedData) {
                $decodedData = json_decode($cachedData, true);
                if (!empty($decodedData)) {
                    return $decodedData;
                }
            }
        } catch (\Exception $e) {
            // Cache read failed, continue without cache
            logActivity("V2RaySocks Traffic Monitor: Cache read failed for usage records: " . $e->getMessage(), 0);
        }
        
        $pdo = v2raysocks_traffic_createPDO();
        if (!$pdo) {
            return [];
        }

        // Calculate time range
        switch ($timeRange) {
            case 'today':
                $startTime = strtotime('today');
                $endTime = strtotime('tomorrow') - 1;
                break;
            case 'last_1_hour':
                $startTime = strtotime('-1 hour');
                $endTime = time();
                break;
            case 'last_3_hours':
                $startTime = strtotime('-3 hours');
                $endTime = time();
                break;
            case 'last_6_hours':
                $startTime = strtotime('-6 hours');
                $endTime = time();
                break;
            case 'last_12_hours':
                $startTime = strtotime('-12 hours');
                $endTime = time();
                break;
            case 'week':
            case '7days':
                // Fix: Use exactly 7 complete days from 7 days ago at 00:00:00 to end of today
                $startTime = strtotime('-6 days', strtotime('today')); // Start of 7 days ago (today - 6 = 7 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                break;
            case '15days':
                // Fix: Use exactly 15 complete days from 15 days ago at 00:00:00 to end of today
                $startTime = strtotime('-14 days', strtotime('today')); // Start of 15 days ago (today - 14 = 15 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                break;
            case 'month':
            case '30days':
                // Fix: Use exactly 30 complete days from 30 days ago at 00:00:00 to end of today
                $startTime = strtotime('-29 days', strtotime('today')); // Start of 30 days ago (today - 29 = 30 days total)
                $endTime = strtotime('tomorrow') - 1; // End of today
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $startTime = strtotime($startDate . ' 00:00:00'); // Ensure start of day
                    $endTime = strtotime($endDate . ' 23:59:59'); // End of the end date
                } else {
                    // Fallback to today if custom dates are invalid
                    $startTime = strtotime('today');
                    $endTime = strtotime('tomorrow') - 1;
                }
                break;
            case 'all':
            default:
                $startTime = 0;
                $endTime = time();
                break;
        }

        // Override time range if timestamp parameters are provided
        if ($startTimestamp !== null && $endTimestamp !== null) {
            $startTime = intval($startTimestamp);
            $endTime = intval($endTimestamp);
        }

        $sql = '
            SELECT 
                uu.id,
                uu.user_id,
                uu.t,
                uu.u,
                uu.d,
                uu.node,
                uu.count_rate,
                u.uuid,
                u.sid,
                n.name as node_name,
                n.country as node_country
            FROM user_usage uu
            LEFT JOIN user u ON uu.user_id = u.id
            LEFT JOIN node n ON (uu.node = n.id OR uu.node = n.name)
            WHERE uu.t >= :start_time AND uu.t <= :end_time
        ';

        $params = [
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ];

        if ($nodeId !== null) {
            // Get the node name as well to handle both cases
            $nodeStmt = $pdo->prepare('SELECT name FROM node WHERE id = :node_id');
            $nodeStmt->execute([':node_id' => $nodeId]);
            $nodeInfo = $nodeStmt->fetch(PDO::FETCH_ASSOC);
            $nodeName = $nodeInfo ? $nodeInfo['name'] : '';
            
            $sql .= ' AND (uu.node = :node_id OR uu.node = :node_name)';
            $params[':node_id'] = $nodeId;
            $params[':node_name'] = $nodeName;
        }

        if ($userId !== null) {
            $sql .= ' AND uu.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if ($uuid !== null) {
            $sql .= ' AND u.uuid = :uuid';
            $params[':uuid'] = $uuid;
        }

        $sql .= ' ORDER BY uu.t DESC LIMIT :limit';
        $params[':limit'] = $limit;

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process results
        foreach ($records as &$record) {
            $record['id'] = intval($record['id']);
            $record['user_id'] = intval($record['user_id']);
            $record['t'] = intval($record['t']);
            $record['u'] = floatval($record['u']);
            $record['d'] = floatval($record['d']);
            $record['node'] = intval($record['node']);
            $record['count_rate'] = floatval($record['count_rate'] ?: 1.0);
            $record['total_traffic'] = $record['u'] + $record['d'];
            $record['formatted_time'] = (function($timestamp) {
                $date = new DateTime();
                $date->setTimestamp($timestamp);
                return $date->format('Y-m-d H:i:s');
            })($record['t']);
            $record['formatted_upload'] = v2raysocks_traffic_formatBytesConfigurable($record['u']);
            $record['formatted_download'] = v2raysocks_traffic_formatBytesConfigurable($record['d']);
            $record['formatted_total'] = v2raysocks_traffic_formatBytesConfigurable($record['total_traffic']);
        }
        
        // Try to cache for 5 minutes, but don't fail if caching fails
        if (!empty($records)) {
            try {
                // Use 5-minute TTL for usage records
                v2raysocks_traffic_redisOperate('set', [
                    'key' => $cacheKey,
                    'value' => json_encode($records),
                    'ttl' => 300
                ]);
            } catch (\Exception $e) {
                // Cache write failed, but we can still return the data
                logActivity("V2RaySocks Traffic Monitor: Cache write failed for usage records: " . $e->getMessage(), 0);
            }
        }
        
        return $records;
        
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor getUsageRecords error: " . $e->getMessage(), 0);
        return [];
    }
}

/**
 * Export node rankings data
 */
function v2raysocks_traffic_exportNodeRankings($filters, $format = 'csv', $limit = null)
{
    try {
        $sortBy = $filters['sort_by'] ?? 'traffic_desc';
        $onlyToday = ($filters['only_today'] ?? 'true') === 'true';
        
        $data = v2raysocks_traffic_getNodeTrafficRankings($sortBy, $onlyToday);
        
        // Apply limit if specified
        if ($limit && is_numeric($limit) && $limit > 0) {
            $data = array_slice($data, 0, intval($limit));
        }
        
        $timeRange = $onlyToday ? 'today' : 'all';
        $filename = 'node_rankings_' . $timeRange . '_' . date('Y-m-d_H-i-s');
        
        switch (strtolower($format)) {
            case 'json':
                $filename .= '.json';
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                // Add formatted traffic values to each node record for JSON export
                $formattedData = array_map(function($node) {
                    // Only keep formatted data, remove raw bytes
                    $exportNode = [
                        'id' => $node['id'],
                        'name' => $node['name'],
                        'address' => $node['address'],
                        'country' => $node['country'],
                        'type' => $node['type'],
                        'formatted_total_upload' => v2raysocks_traffic_formatBytesConfigurable($node['total_upload']),
                        'formatted_total_download' => v2raysocks_traffic_formatBytesConfigurable($node['total_download']),
                        'formatted_total_traffic' => v2raysocks_traffic_formatBytesConfigurable($node['total_traffic']),
                        'max_traffic' => $node['max_traffic'],
                        'formatted_remaining_traffic' => v2raysocks_traffic_formatBytesConfigurable($node['remaining_traffic']),
                        'traffic_utilization' => round($node['traffic_utilization'], 2),
                        'formatted_avg_traffic_per_user' => v2raysocks_traffic_formatBytesConfigurable($node['avg_traffic_per_user']),
                        'unique_users' => $node['unique_users'],
                        'usage_records' => $node['usage_records'],
                        'is_online' => $node['is_online'],
                        'formatted_last_online' => (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($node['last_online'])
                    ];
                    
                    return $exportNode;
                }, $data);
                
                $exportData = [
                    'export_info' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'total_records' => count($formattedData),
                        'sort_by' => $sortBy,
                        'only_today' => $onlyToday,
                        'format' => 'json'
                    ],
                    'data' => $formattedData
                ];
                
                echo json_encode($exportData, JSON_PRETTY_PRINT);
                break;
                
            case 'csv':
            default:
                $filename .= '.csv';
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                
                // CSV headers
                fputcsv($output, [
                    'Rank', 'Node ID', 'Node Name', 'Address', 'Country', 'Type',
                    'Total Upload (Formatted)', 'Total Download (Formatted)', 'Total Traffic (Formatted)',
                    'Max Traffic (GB)', 'Remaining Traffic (Formatted)', 'Traffic Utilization (%)',
                    'Unique Users', 'Usage Records', 'Online Status', 'Last Online',
                    'Average Traffic Per User (Formatted)'
                ]);
                
                // Data rows
                foreach ($data as $index => $node) {
                    $rank = $index + 1;
                    fputcsv($output, [
                        $rank,
                        $node['id'],
                        $node['name'],
                        $node['address'],
                        $node['country'],
                        $node['type'],
                        v2raysocks_traffic_formatBytesConfigurable($node['total_upload']),
                        v2raysocks_traffic_formatBytesConfigurable($node['total_download']),
                        v2raysocks_traffic_formatBytesConfigurable($node['total_traffic']),
                        $node['max_traffic'],
                        v2raysocks_traffic_formatBytesConfigurable($node['remaining_traffic']),
                        round($node['traffic_utilization'], 2),
                        $node['unique_users'],
                        $node['usage_records'],
                        $node['is_online'] ? 'Online' : 'Offline',
                        (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($node['last_online']),
                        v2raysocks_traffic_formatBytesConfigurable($node['avg_traffic_per_user'])
                    ]);
                }
                
                fclose($output);
                break;
        }
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor exportNodeRankings error: " . $e->getMessage(), 0);
        echo "Error exporting node rankings: " . $e->getMessage();
    }
}

/**
 * Export user rankings data
 */
function v2raysocks_traffic_exportUserRankings($filters, $format = 'csv', $limit = null)
{
    try {
        $sortBy = $filters['sort_by'] ?? 'traffic_desc';
        $timeRange = $filters['time_range'] ?? 'today';
        $limitNum = $limit ?: intval($filters['limit'] ?? 100);
        
        $data = v2raysocks_traffic_getUserTrafficRankings($sortBy, $timeRange, $limitNum);
        
        $filename = 'user_rankings_' . $timeRange . '_' . date('Y-m-d_H-i-s');
        
        switch (strtolower($format)) {
            case 'json':
                $filename .= '.json';
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                // Add formatted traffic values to each user record for JSON export
                $formattedData = array_map(function($user) {
                    // Only keep formatted data, remove raw bytes
                    $exportUser = [
                        'user_id' => $user['user_id'],
                        'uuid' => $user['uuid'],
                        'sid' => $user['sid'],
                        'enable' => $user['enable'],
                        'formatted_period_upload' => v2raysocks_traffic_formatBytesConfigurable($user['period_upload']),
                        'formatted_period_download' => v2raysocks_traffic_formatBytesConfigurable($user['period_download']),
                        'formatted_period_traffic' => v2raysocks_traffic_formatBytesConfigurable($user['period_traffic']),
                        'formatted_total_upload_user' => v2raysocks_traffic_formatBytesConfigurable($user['total_upload_user']),
                        'formatted_total_download_user' => v2raysocks_traffic_formatBytesConfigurable($user['total_download_user']),
                        'formatted_transfer_enable' => v2raysocks_traffic_formatBytesConfigurable($user['transfer_enable']),
                        'formatted_remaining_quota' => v2raysocks_traffic_formatBytesConfigurable($user['remaining_quota']),
                        'quota_utilization' => round($user['quota_utilization'], 2),
                        'formatted_avg_traffic_per_node' => v2raysocks_traffic_formatBytesConfigurable($user['avg_traffic_per_node']),
                        'nodes_used' => $user['nodes_used'],
                        'usage_records' => $user['usage_records'],
                        'formatted_first_usage' => $user['first_usage'] ? (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($user['first_usage']) : '',
                        'formatted_last_usage' => $user['last_usage'] ? (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($user['last_usage']) : '',
                        'remark' => $user['remark']
                    ];
                    
                    return $exportUser;
                }, $data);
                
                $exportData = [
                    'export_info' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'total_records' => count($formattedData),
                        'sort_by' => $sortBy,
                        'time_range' => $timeRange,
                        'limit' => $limitNum,
                        'format' => 'json'
                    ],
                    'data' => $formattedData
                ];
                
                echo json_encode($exportData, JSON_PRETTY_PRINT);
                break;
                
            case 'csv':
            default:
                $filename .= '.csv';
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                
                // CSV headers
                fputcsv($output, [
                    'Rank', 'User ID', 'UUID', 'Service ID', 'Enable Status',
                    'Period Upload (Formatted)', 'Period Download (Formatted)', 'Period Total (Formatted)',
                    'Total Upload (Formatted)', 'Total Download (Formatted)', 'Transfer Enable (Formatted)',
                    'Remaining Quota (Formatted)', 'Quota Utilization (%)',
                    'Nodes Used', 'Usage Records', 'First Usage', 'Last Usage',
                    'Average Traffic Per Node (Formatted)', 'Remark'
                ]);
                
                // Data rows
                foreach ($data as $index => $user) {
                    $rank = $index + 1;
                    fputcsv($output, [
                        $rank,
                        $user['user_id'],
                        $user['uuid'],
                        $user['sid'],
                        $user['enable'] ? 'Enabled' : 'Disabled',
                        v2raysocks_traffic_formatBytesConfigurable($user['period_upload']),
                        v2raysocks_traffic_formatBytesConfigurable($user['period_download']),
                        v2raysocks_traffic_formatBytesConfigurable($user['period_traffic']),
                        v2raysocks_traffic_formatBytesConfigurable($user['total_upload_user']),
                        v2raysocks_traffic_formatBytesConfigurable($user['total_download_user']),
                        v2raysocks_traffic_formatBytesConfigurable($user['transfer_enable']),
                        v2raysocks_traffic_formatBytesConfigurable($user['remaining_quota']),
                        round($user['quota_utilization'], 2),
                        $user['nodes_used'],
                        $user['usage_records'],
                        $user['first_usage'] ? (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($user['first_usage']) : '',
                        $user['last_usage'] ? (function($timestamp) {
                            $date = new DateTime();
                            $date->setTimestamp($timestamp);
                            return $date->format('Y-m-d H:i:s');
                        })($user['last_usage']) : '',
                        v2raysocks_traffic_formatBytesConfigurable($user['avg_traffic_per_node']),
                        $user['remark']
                    ]);
                }
                
                fclose($output);
                break;
        }
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor exportUserRankings error: " . $e->getMessage(), 0);
        echo "Error exporting user rankings: " . $e->getMessage();
    }
}

/**
 * Export usage records data
 */
function v2raysocks_traffic_exportUsageRecords($filters, $format = 'csv', $limit = null)
{
    try {
        $nodeId = $filters['node_id'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $uuid = $filters['uuid'] ?? null;
        $nodeSearch = $filters['node_search'] ?? null;
        $timeRange = $filters['time_range'] ?? 'today';
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $startTimestamp = $filters['start_timestamp'] ?? null;
        $endTimestamp = $filters['end_timestamp'] ?? null;
        $exportLimit = $limit ?? 1000; // Default to 1000 records if not specified
        
        // Get usage records data
        $records = v2raysocks_traffic_getUsageRecords($nodeId, $userId, $timeRange, $exportLimit, $startDate, $endDate, $uuid, $startTimestamp, $endTimestamp);
        
        // Apply node name filtering if specified
        if (!empty($nodeSearch) && !empty($records)) {
            $nodeSearchLower = strtolower(trim($nodeSearch));
            $records = array_filter($records, function($record) use ($nodeSearchLower) {
                $nodeName = strtolower($record['node_name'] ?? '节点 ' . ($record['node'] ?? ''));
                return strpos($nodeName, $nodeSearchLower) !== false;
            });
        }
        
        if (empty($records)) {
            echo "No usage records found for the specified criteria.";
            return;
        }
        
        // Generate filename
        $filename = 'usage_records';
        if ($nodeId) {
            $filename .= '_node_' . $nodeId;
        }
        if ($userId) {
            $filename .= '_user_' . $userId;
        }
        if ($uuid) {
            $filename .= '_uuid_' . substr($uuid, 0, 8); // Only first 8 characters for filename
        }
        if ($nodeSearch) {
            $filename .= '_search_' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $nodeSearch), 0, 10);
        }
        $filename .= '_' . $timeRange . '_' . date('Y-m-d_H-i-s');
        
        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '.json"');
                
                // Filter out raw byte data from records for JSON export
                $filteredRecords = array_map(function($record) {
                    // Only keep formatted data, remove raw bytes and timestamps
                    return [
                        'formatted_time' => $record['formatted_time'] ?? '',
                        'id' => $record['id'] ?? '',
                        'user_id' => $record['user_id'] ?? '',
                        'uuid' => $record['uuid'] ?? '',
                        'sid' => $record['sid'] ?? '',
                        'node' => $record['node'] ?? '',
                        'node_name' => $record['node_name'] ?? '',
                        'formatted_upload' => $record['formatted_upload'] ?? '',
                        'formatted_download' => $record['formatted_download'] ?? '',
                        'formatted_total' => $record['formatted_total'] ?? '',
                        'count_rate' => $record['count_rate'] ?? 1,
                        'node_country' => $record['node_country'] ?? ''
                    ];
                }, $records);
                
                $exportData = [
                    'export_info' => [
                        'type' => 'usage_records',
                        'node_id' => $nodeId,
                        'user_id' => $userId,
                        'time_range' => $timeRange,
                        'record_count' => count($filteredRecords),
                        'generated_at' => date('Y-m-d H:i:s'),
                        'timezone' => date_default_timezone_get()
                    ],
                    'data' => $filteredRecords
                ];
                
                echo json_encode($exportData, JSON_PRETTY_PRINT);
                break;
                
            default: // CSV
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // CSV Headers
                fputcsv($output, [
                    'Formatted Time',
                    'Record ID',
                    'User ID', 
                    'UUID',
                    'Service ID',
                    'Node ID',
                    'Node Name',
                    'Upload (Formatted)',
                    'Download (Formatted)',
                    'Total (Formatted)',
                    'Count Rate',
                    'Node Country'
                ]);
                
                // CSV Data
                foreach ($records as $record) {
                    fputcsv($output, [
                        $record['formatted_time'] ?? '',
                        $record['id'] ?? '',
                        $record['user_id'] ?? '',
                        $record['uuid'] ?? '',
                        $record['sid'] ?? '',
                        $record['node'] ?? '',
                        $record['node_name'] ?? '',
                        $record['formatted_upload'] ?? '',
                        $record['formatted_download'] ?? '',
                        $record['formatted_total'] ?? '',
                        $record['count_rate'] ?? 1,
                        $record['node_country'] ?? ''
                    ]);
                }
                
                fclose($output);
                break;
        }
    } catch (\Exception $e) {
        logActivity("V2RaySocks Traffic Monitor exportUsageRecords error: " . $e->getMessage(), 0);
        echo "Error exporting usage records: " . $e->getMessage();
    }
}
