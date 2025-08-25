<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/class-large-data-processor.php';

/**
 * Traffic Aggregator for V2RaySocks Traffic Analysis
 * 
 * Provides efficient traffic data aggregation for large datasets:
 * - Streaming aggregation without full memory load
 * - Time-based grouping and summarization  
 * - User and node ranking calculations
 * - Statistical analysis and metrics
 * 
 * @version 1.0.0
 * @author V2RaySocks Traffic Optimization Team
 */
class V2RaySocks_TrafficAggregator
{
    private $largeDataProcessor;
    private $pdo;
    private $cacheManager;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     * @param int $batchSize Batch size for processing (default: 50000)
     */
    public function __construct($pdo, $batchSize = 50000)
    {
        $this->pdo = $pdo;
        $this->largeDataProcessor = new V2RaySocks_LargeDataProcessor($pdo, $batchSize);
        
        if (function_exists('v2raysocks_traffic_redisOperate')) {
            $this->cacheManager = true;
        }
    }
    
    /**
     * Get user traffic rankings with optimized large dataset handling
     * 
     * @param string $sortBy Sorting criteria
     * @param string $timeRange Time range for data
     * @param int $limit Result limit
     * @param int $startTimestamp Start time
     * @param int $endTimestamp End time
     * @return array User rankings
     */
    public function getUserTrafficRankings($sortBy = 'traffic_desc', $timeRange = 'today', $limit = PHP_INT_MAX, $startTimestamp = null, $endTimestamp = null)
    {
        try {
            $cacheKey = 'large_user_rankings_' . md5($sortBy . '_' . $timeRange . '_' . $limit . '_' . $startTimestamp . '_' . $endTimestamp);
            
            // Calculate time range
            $timeRanges = $this->calculateTimeRange($timeRange, $startTimestamp, $endTimestamp);
            $startTime = $timeRanges['start'];
            $endTime = $timeRanges['end'];
            
            $this->logActivity("Processing user rankings for time range: " . date('Y-m-d H:i:s', $startTime) . " to " . date('Y-m-d H:i:s', $endTime));
            
            // Build optimized query for streaming
            $query = "
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
                    uu.t,
                    uu.u as period_upload,
                    uu.d as period_download,
                    uu.node
                FROM user u
                INNER JOIN user_usage uu ON u.id = uu.user_id 
                WHERE uu.t >= :start_time AND uu.t <= :end_time
                ORDER BY u.id, uu.t
            ";
            
            $params = [
                ':start_time' => $startTime,
                ':end_time' => $endTime
            ];
            
            // Use stream processing for aggregation
            $result = $this->largeDataProcessor->streamProcess(
                $query,
                $params,
                [$this, 'aggregateUserTrafficStream'],
                $cacheKey
            );
            
            if (empty($result)) {
                return [];
            }
            
            // Post-process aggregated data
            $processedUsers = $this->postProcessUserRankings($result, $startTime, $endTime);
            
            // Apply sorting and limiting
            $sortedUsers = $this->sortUserRankings($processedUsers, $sortBy);
            
            return array_slice($sortedUsers, 0, $limit);
            
        } catch (Exception $e) {
            $this->logActivity("Error in getUserTrafficRankings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get node traffic rankings with optimized processing
     * 
     * @param string $sortBy Sorting criteria
     * @param string $timeRange Time range for data
     * @param int $startTimestamp Start time
     * @param int $endTimestamp End time
     * @return array Node rankings
     */
    public function getNodeTrafficRankings($sortBy = 'traffic_desc', $timeRange = 'today', $startTimestamp = null, $endTimestamp = null)
    {
        try {
            $cacheKey = 'large_node_rankings_' . md5($sortBy . '_' . $timeRange . '_' . $startTimestamp . '_' . $endTimestamp);
            
            // Calculate time range
            $timeRanges = $this->calculateTimeRange($timeRange, $startTimestamp, $endTimestamp);
            $startTime = $timeRanges['start'];
            $endTime = $timeRanges['end'];
            
            $this->logActivity("Processing node rankings for time range: " . date('Y-m-d H:i:s', $startTime) . " to " . date('Y-m-d H:i:s', $endTime));
            
            // Build optimized query for streaming
            $query = "
                SELECT 
                    n.id,
                    n.name,
                    n.address,
                    n.enable,
                    n.statistics,
                    n.max_traffic,
                    n.last_online,
                    n.country,
                    COALESCE(n.type, '') as type,
                    COALESCE(n.excessive_speed_limit, '') as excessive_speed_limit,
                    COALESCE(n.speed_limit, '') as speed_limit,
                    uu.t,
                    uu.u as upload,
                    uu.d as download,
                    uu.user_id
                FROM node n
                LEFT JOIN user_usage uu ON (uu.node = n.id OR uu.node = n.name) 
                    AND uu.t >= :start_time AND uu.t <= :end_time AND uu.node != 'DAY'
                ORDER BY n.id, uu.t
            ";
            
            $params = [
                ':start_time' => $startTime,
                ':end_time' => $endTime
            ];
            
            // Use stream processing for aggregation
            $result = $this->largeDataProcessor->streamProcess(
                $query,
                $params,
                [$this, 'aggregateNodeTrafficStream'],
                $cacheKey
            );
            
            if (empty($result)) {
                return [];
            }
            
            // Post-process aggregated data
            $processedNodes = $this->postProcessNodeRankings($result, $startTime, $endTime);
            
            // Apply sorting
            $sortedNodes = $this->sortNodeRankings($processedNodes, $sortBy);
            
            return $sortedNodes;
            
        } catch (Exception $e) {
            $this->logActivity("Error in getNodeTrafficRankings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get aggregated traffic data with time grouping
     * 
     * @param array $filters Filter parameters
     * @param string $groupBy Time grouping (hour, day, week)
     * @return array Aggregated traffic data
     */
    public function getAggregatedTrafficData($filters = [], $groupBy = 'hour')
    {
        try {
            $cacheKey = 'large_traffic_agg_' . md5(serialize($filters) . '_' . $groupBy);
            
            $this->logActivity("Processing aggregated traffic data with grouping: $groupBy");
            
            // Build query based on filters
            $query = $this->buildTrafficAggregationQuery($filters, $groupBy);
            $params = $this->buildTrafficAggregationParams($filters);
            
            // Use batch processing for large aggregations
            $result = $this->largeDataProcessor->processBatches(
                $query,
                $params,
                [$this, 'aggregateTrafficBatch'],
                $cacheKey
            );
            
            return $this->finalizeTrafficAggregation($result, $groupBy);
            
        } catch (Exception $e) {
            $this->logActivity("Error in getAggregatedTrafficData: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Stream aggregator for user traffic data
     * 
     * @param array $row Current row from database
     * @param array $result Accumulated result
     * @return array Updated result
     */
    public function aggregateUserTrafficStream($row, $result)
    {
        if ($result === null) {
            $result = [];
        }
        
        $userId = $row['user_id'];
        
        if (!isset($result[$userId])) {
            // Initialize user data
            $result[$userId] = [
                'user_id' => intval($row['user_id']),
                'uuid' => $row['uuid'],
                'sid' => intval($row['sid']),
                'total_upload_user' => floatval($row['total_upload_user']),
                'total_download_user' => floatval($row['total_download_user']),
                'transfer_enable' => floatval($row['transfer_enable']),
                'enable' => intval($row['enable']),
                'created_at' => $row['created_at'],
                'remark' => $row['remark'],
                'speedlimitss' => $row['speedlimitss'],
                'speedlimitother' => $row['speedlimitother'],
                
                // Aggregated fields
                'period_upload' => 0,
                'period_download' => 0,
                'period_traffic' => 0,
                'traffic_5min' => 0,
                'traffic_1hour' => 0,
                'traffic_4hour' => 0,
                'nodes_used' => [],
                'usage_records' => 0,
                'first_usage' => null,
                'last_usage' => null
            ];
        }
        
        // Aggregate traffic data
        $upload = floatval($row['period_upload'] ?? 0);
        $download = floatval($row['period_download'] ?? 0);
        $timestamp = intval($row['t']);
        $currentTime = time();
        
        $result[$userId]['period_upload'] += $upload;
        $result[$userId]['period_download'] += $download;
        $result[$userId]['period_traffic'] += ($upload + $download);
        $result[$userId]['usage_records']++;
        
        // Track nodes used
        if (!empty($row['node'])) {
            $result[$userId]['nodes_used'][$row['node']] = true;
        }
        
        // Time-based aggregations
        if ($timestamp >= $currentTime - 300) { // 5 minutes
            $result[$userId]['traffic_5min'] += ($upload + $download);
        }
        if ($timestamp >= $currentTime - 3600) { // 1 hour
            $result[$userId]['traffic_1hour'] += ($upload + $download);
        }
        if ($timestamp >= $currentTime - 14400) { // 4 hours
            $result[$userId]['traffic_4hour'] += ($upload + $download);
        }
        
        // Track usage time range
        if ($result[$userId]['first_usage'] === null || $timestamp < $result[$userId]['first_usage']) {
            $result[$userId]['first_usage'] = $timestamp;
        }
        if ($result[$userId]['last_usage'] === null || $timestamp > $result[$userId]['last_usage']) {
            $result[$userId]['last_usage'] = $timestamp;
        }
        
        return $result;
    }
    
    /**
     * Stream aggregator for node traffic data
     * 
     * @param array $row Current row from database
     * @param array $result Accumulated result
     * @return array Updated result
     */
    public function aggregateNodeTrafficStream($row, $result)
    {
        if ($result === null) {
            $result = [];
        }
        
        $nodeId = $row['id'];
        
        if (!isset($result[$nodeId])) {
            // Initialize node data
            $result[$nodeId] = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'address' => $row['address'],
                'enable' => intval($row['enable']),
                'statistics' => floatval($row['statistics']),
                'max_traffic' => floatval($row['max_traffic']),
                'last_online' => intval($row['last_online']),
                'country' => $row['country'],
                'type' => $row['type'],
                'excessive_speed_limit' => $row['excessive_speed_limit'],
                'speed_limit' => $row['speed_limit'],
                
                // Aggregated fields
                'total_upload' => 0,
                'total_download' => 0,
                'total_traffic' => 0,
                'traffic_5min' => 0,
                'traffic_1hour' => 0,
                'traffic_4hour' => 0,
                'unique_users' => [],
                'usage_records' => 0
            ];
        }
        
        // Only aggregate if we have traffic data
        if (!empty($row['upload']) || !empty($row['download'])) {
            $upload = floatval($row['upload'] ?? 0);
            $download = floatval($row['download'] ?? 0);
            $timestamp = intval($row['t']);
            $currentTime = time();
            
            $result[$nodeId]['total_upload'] += $upload;
            $result[$nodeId]['total_download'] += $download;
            $result[$nodeId]['total_traffic'] += ($upload + $download);
            $result[$nodeId]['usage_records']++;
            
            // Track unique users
            if (!empty($row['user_id'])) {
                $result[$nodeId]['unique_users'][$row['user_id']] = true;
            }
            
            // Time-based aggregations
            if ($timestamp >= $currentTime - 300) { // 5 minutes
                $result[$nodeId]['traffic_5min'] += ($upload + $download);
            }
            if ($timestamp >= $currentTime - 3600) { // 1 hour
                $result[$nodeId]['traffic_1hour'] += ($upload + $download);
            }
            if ($timestamp >= $currentTime - 14400) { // 4 hours
                $result[$nodeId]['traffic_4hour'] += ($upload + $download);
            }
        }
        
        return $result;
    }
    
    /**
     * Batch aggregator for traffic data
     * 
     * @param array $batchData Batch of traffic records
     * @param int $batchNumber Current batch number
     * @param int $totalBatches Total number of batches
     * @return array Batch aggregation result
     */
    public function aggregateTrafficBatch($batchData, $batchNumber, $totalBatches)
    {
        $batchResult = [];
        
        foreach ($batchData as $row) {
            $timeKey = $this->getTimeGroupKey($row['t'], 'hour'); // Adjust based on groupBy
            
            if (!isset($batchResult[$timeKey])) {
                $batchResult[$timeKey] = [
                    'time_key' => $timeKey,
                    'upload' => 0,
                    'download' => 0,
                    'total_traffic' => 0,
                    'unique_users' => [],
                    'unique_nodes' => [],
                    'record_count' => 0
                ];
            }
            
            $upload = floatval($row['u'] ?? 0);
            $download = floatval($row['d'] ?? 0);
            
            $batchResult[$timeKey]['upload'] += $upload;
            $batchResult[$timeKey]['download'] += $download;
            $batchResult[$timeKey]['total_traffic'] += ($upload + $download);
            $batchResult[$timeKey]['record_count']++;
            
            if (!empty($row['user_id'])) {
                $batchResult[$timeKey]['unique_users'][$row['user_id']] = true;
            }
            
            if (!empty($row['node'])) {
                $batchResult[$timeKey]['unique_nodes'][$row['node']] = true;
            }
        }
        
        return $batchResult;
    }
    
    /**
     * Post-process user rankings data
     */
    private function postProcessUserRankings($aggregatedData, $startTime, $endTime)
    {
        $processedUsers = [];
        
        foreach ($aggregatedData as $userId => $userData) {
            // Convert nodes_used to count
            $userData['nodes_used'] = count($userData['nodes_used']);
            
            // Calculate additional metrics
            $totalUsed = $userData['total_upload_user'] + $userData['total_download_user'];
            $userData['used_traffic'] = $totalUsed;
            $userData['remaining_quota'] = max(0, $userData['transfer_enable'] - $totalUsed);
            $userData['quota_utilization'] = $userData['transfer_enable'] > 0 ? 
                ($totalUsed / $userData['transfer_enable']) * 100 : 0;
            
            $processedUsers[] = $userData;
        }
        
        return $processedUsers;
    }
    
    /**
     * Post-process node rankings data
     */
    private function postProcessNodeRankings($aggregatedData, $startTime, $endTime)
    {
        $processedNodes = [];
        
        foreach ($aggregatedData as $nodeId => $nodeData) {
            // Convert unique_users to count
            $nodeData['unique_users'] = count($nodeData['unique_users']);
            
            // Calculate additional metrics
            $avgTrafficPerUser = $nodeData['unique_users'] > 0 ? 
                $nodeData['total_traffic'] / $nodeData['unique_users'] : 0;
            
            $nodeData['avg_traffic_per_user'] = $avgTrafficPerUser;
            $nodeData['traffic_utilization'] = $nodeData['max_traffic'] > 0 ? 
                ($nodeData['total_traffic'] / ($nodeData['max_traffic'] * 1000000000)) * 100 : 0;
            
            // Online status
            $currentTime = time();
            $nodeData['is_online'] = ($currentTime - $nodeData['last_online']) < 300; // 5 minutes
            $nodeData['last_seen'] = $currentTime - $nodeData['last_online'];
            
            $processedNodes[] = $nodeData;
        }
        
        return $processedNodes;
    }
    
    /**
     * Sort user rankings based on criteria
     */
    private function sortUserRankings($users, $sortBy)
    {
        usort($users, function($a, $b) use ($sortBy) {
            switch ($sortBy) {
                case 'traffic_desc':
                    return $b['period_traffic'] <=> $a['period_traffic'];
                case 'traffic_asc':
                    return $a['period_traffic'] <=> $b['period_traffic'];
                case 'remaining_desc':
                    return $b['remaining_quota'] <=> $a['remaining_quota'];
                case 'remaining_asc':
                    return $a['remaining_quota'] <=> $b['remaining_quota'];
                case 'nodes_desc':
                    return $b['nodes_used'] <=> $a['nodes_used'];
                case 'recent_activity':
                    return $b['last_usage'] <=> $a['last_usage'];
                default:
                    return $b['period_traffic'] <=> $a['period_traffic'];
            }
        });
        
        return $users;
    }
    
    /**
     * Sort node rankings based on criteria
     */
    private function sortNodeRankings($nodes, $sortBy)
    {
        usort($nodes, function($a, $b) use ($sortBy) {
            switch ($sortBy) {
                case 'traffic_desc':
                    return $b['total_traffic'] <=> $a['total_traffic'];
                case 'traffic_asc':
                    return $a['total_traffic'] <=> $b['total_traffic'];
                case 'users_desc':
                    return $b['unique_users'] <=> $a['unique_users'];
                case 'name_asc':
                    return strcmp($a['name'], $b['name']);
                default:
                    return $b['total_traffic'] <=> $a['total_traffic'];
            }
        });
        
        return $nodes;
    }
    
    /**
     * Calculate time range from parameters
     */
    private function calculateTimeRange($timeRange, $startTimestamp, $endTimestamp)
    {
        if ($startTimestamp !== null && $endTimestamp !== null) {
            return [
                'start' => intval($startTimestamp),
                'end' => intval($endTimestamp)
            ];
        }
        
        switch ($timeRange) {
            case 'today':
                return [
                    'start' => strtotime('today'),
                    'end' => strtotime('tomorrow') - 1
                ];
            case 'week':
            case '7days':
                return [
                    'start' => strtotime('-6 days', strtotime('today')),
                    'end' => strtotime('tomorrow') - 1
                ];
            case 'month':
            case '30days':
                return [
                    'start' => strtotime('-29 days', strtotime('today')),
                    'end' => strtotime('tomorrow') - 1
                ];
            default:
                return [
                    'start' => strtotime('today'),
                    'end' => strtotime('tomorrow') - 1
                ];
        }
    }
    
    /**
     * Build traffic aggregation query
     */
    private function buildTrafficAggregationQuery($filters, $groupBy)
    {
        $query = "
            SELECT 
                t,
                u,
                d,
                user_id,
                node
            FROM user_usage 
            WHERE 1=1
        ";
        
        // Add filters
        if (!empty($filters['start_timestamp'])) {
            $query .= " AND t >= :start_timestamp";
        }
        if (!empty($filters['end_timestamp'])) {
            $query .= " AND t <= :end_timestamp";
        }
        if (!empty($filters['user_id'])) {
            $query .= " AND user_id = :user_id";
        }
        if (!empty($filters['node'])) {
            $query .= " AND node = :node";
        }
        
        $query .= " ORDER BY t";
        
        return $query;
    }
    
    /**
     * Build traffic aggregation parameters
     */
    private function buildTrafficAggregationParams($filters)
    {
        $params = [];
        
        if (!empty($filters['start_timestamp'])) {
            $params[':start_timestamp'] = $filters['start_timestamp'];
        }
        if (!empty($filters['end_timestamp'])) {
            $params[':end_timestamp'] = $filters['end_timestamp'];
        }
        if (!empty($filters['user_id'])) {
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['node'])) {
            $params[':node'] = $filters['node'];
        }
        
        return $params;
    }
    
    /**
     * Get time group key for aggregation
     */
    private function getTimeGroupKey($timestamp, $groupBy)
    {
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        
        switch ($groupBy) {
            case 'hour':
                return $date->format('Y-m-d H:00:00');
            case 'day':
                return $date->format('Y-m-d');
            case 'week':
                return $date->format('Y-\WW');
            default:
                return $date->format('Y-m-d H:00:00');
        }
    }
    
    /**
     * Finalize traffic aggregation
     */
    private function finalizeTrafficAggregation($batchResults, $groupBy)
    {
        $finalResult = [];
        
        // Merge all batch results
        foreach ($batchResults as $batchResult) {
            foreach ($batchResult as $timeKey => $data) {
                if (!isset($finalResult[$timeKey])) {
                    $finalResult[$timeKey] = $data;
                    $finalResult[$timeKey]['unique_users'] = count($data['unique_users']);
                    $finalResult[$timeKey]['unique_nodes'] = count($data['unique_nodes']);
                } else {
                    $finalResult[$timeKey]['upload'] += $data['upload'];
                    $finalResult[$timeKey]['download'] += $data['download'];
                    $finalResult[$timeKey]['total_traffic'] += $data['total_traffic'];
                    $finalResult[$timeKey]['record_count'] += $data['record_count'];
                    $finalResult[$timeKey]['unique_users'] = count(array_merge(
                        array_keys($finalResult[$timeKey]['unique_users']),
                        array_keys($data['unique_users'])
                    ));
                    $finalResult[$timeKey]['unique_nodes'] = count(array_merge(
                        array_keys($finalResult[$timeKey]['unique_nodes']),
                        array_keys($data['unique_nodes'])
                    ));
                }
            }
        }
        
        // Sort by time key
        ksort($finalResult);
        
        return array_values($finalResult);
    }
    
    /**
     * Log activity for monitoring
     */
    private function logActivity($message)
    {
        if (function_exists('logActivity')) {
            logActivity("V2RaySocks Traffic Aggregator: $message", 0);
        }
    }
}