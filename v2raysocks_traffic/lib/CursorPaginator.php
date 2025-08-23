<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Cursor-based Pagination System for V2RaySocks Traffic Analysis
 * 
 * Provides efficient pagination for large datasets using cursor-based approach
 * instead of traditional OFFSET pagination. Supports both ID and timestamp-based cursors.
 */
class CursorPaginator
{
    private $pdo;
    private $defaultPageSize = 1000;
    private $maxPageSize = 10000;
    
    public function __construct($pdo = null)
    {
        $this->pdo = $pdo ?: v2raysocks_traffic_createPDO();
        
        // Load pagination size from module configuration
        $config = v2raysocks_traffic_getModuleConfig();
        $configuredPageSize = intval($config['pagination_size'] ?? 1000);
        
        // Validate configured page size
        if ($configuredPageSize > 0 && $configuredPageSize <= 10000) {
            $this->defaultPageSize = $configuredPageSize;
        }
    }
    
    /**
     * Paginate a simple table with cursor
     * 
     * @param string $table Table name
     * @param int $pageSize Number of records per page
     * @param mixed $cursor Cursor value (ID or timestamp)
     * @param string $orderBy Order field (id, t, etc.)
     * @param string $direction Order direction (ASC/DESC)
     * @param array $conditions Additional WHERE conditions
     * @return array Paginated results with metadata
     */
    public function paginate($table, $pageSize = null, $cursor = null, $orderBy = 'id', $direction = 'DESC', $conditions = [])
    {
        $pageSize = $this->validatePageSize($pageSize);
        
        // Build base query
        $sql = "SELECT * FROM {$table}";
        $params = [];
        
        // Add conditions
        $whereConditions = [];
        foreach ($conditions as $field => $value) {
            $whereConditions[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }
        
        // Add cursor condition
        if ($cursor !== null) {
            $operator = ($direction === 'DESC') ? '<' : '>';
            $whereConditions[] = "{$orderBy} {$operator} :cursor";
            $params[':cursor'] = $cursor;
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY {$orderBy} {$direction} LIMIT :limit";
        $params[':limit'] = $pageSize + 1; // Get one extra to check if there are more pages
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if there are more pages
        $hasNextPage = count($results) > $pageSize;
        if ($hasNextPage) {
            array_pop($results); // Remove the extra record
        }
        
        // Get next cursor
        $nextCursor = null;
        if ($hasNextPage && !empty($results)) {
            $lastRecord = end($results);
            $nextCursor = $lastRecord[$orderBy];
        }
        
        return [
            'data' => $results,
            'pagination' => [
                'cursor' => $cursor,
                'next_cursor' => $nextCursor,
                'has_next_page' => $hasNextPage,
                'page_size' => $pageSize,
                'actual_count' => count($results),
                'order_by' => $orderBy,
                'direction' => $direction
            ]
        ];
    }
    
    /**
     * Paginate with complex SQL conditions
     * 
     * @param string $baseQuery Base SQL query (without ORDER BY and LIMIT)
     * @param array $params Query parameters
     * @param int $pageSize Number of records per page
     * @param mixed $cursor Cursor value
     * @param string $orderBy Order field
     * @param string $direction Order direction
     * @return array Paginated results with metadata
     */
    public function paginateWithConditions($baseQuery, $params = [], $pageSize = null, $cursor = null, $orderBy = 'id', $direction = 'DESC')
    {
        $pageSize = $this->validatePageSize($pageSize);
        
        // Add cursor condition to query
        if ($cursor !== null) {
            $operator = ($direction === 'DESC') ? '<' : '>';
            
            // Check if WHERE clause exists
            if (stripos($baseQuery, 'WHERE') !== false) {
                $baseQuery .= " AND {$orderBy} {$operator} :cursor";
            } else {
                $baseQuery .= " WHERE {$orderBy} {$operator} :cursor";
            }
            $params[':cursor'] = $cursor;
        }
        
        $baseQuery .= " ORDER BY {$orderBy} {$direction} LIMIT :limit";
        $params[':limit'] = $pageSize + 1; // Get one extra to check if there are more pages
        
        $stmt = $this->pdo->prepare($baseQuery);
        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if there are more pages
        $hasNextPage = count($results) > $pageSize;
        if ($hasNextPage) {
            array_pop($results); // Remove the extra record
        }
        
        // Get next cursor
        $nextCursor = null;
        if ($hasNextPage && !empty($results)) {
            $lastRecord = end($results);
            $nextCursor = $lastRecord[$orderBy];
        }
        
        return [
            'data' => $results,
            'pagination' => [
                'cursor' => $cursor,
                'next_cursor' => $nextCursor,
                'has_next_page' => $hasNextPage,
                'page_size' => $pageSize,
                'actual_count' => count($results),
                'order_by' => $orderBy,
                'direction' => $direction
            ]
        ];
    }
    
    /**
     * Get cursor value from results for pagination
     */
    public function getNextCursor($results, $orderField = 'id')
    {
        if (empty($results)) {
            return null;
        }
        
        $lastRecord = end($results);
        return isset($lastRecord[$orderField]) ? $lastRecord[$orderField] : null;
    }
    
    /**
     * Convert cursor pagination to traditional page-based response
     * Maintains compatibility with existing frontend pagination
     */
    public function convertToPageBasedResponse($cursorResults, $requestedPage = 1, $requestedPageSize = null)
    {
        $requestedPageSize = $requestedPageSize ?: $this->defaultPageSize;
        
        $data = $cursorResults['data'];
        $pagination = $cursorResults['pagination'];
        
        // Calculate approximate page information
        // Note: Total count estimation is expensive for large datasets,
        // so we provide basic pagination info only
        $currentPage = $requestedPage;
        $hasNextPage = $pagination['has_next_page'];
        $hasPrevPage = $currentPage > 1;
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $requestedPageSize,
                'has_next_page' => $hasNextPage,
                'has_prev_page' => $hasPrevPage,
                'actual_count' => count($data),
                // Cursor info for internal use
                'cursor' => $pagination['cursor'],
                'next_cursor' => $pagination['next_cursor'],
                'order_by' => $pagination['order_by'],
                'direction' => $pagination['direction']
            ]
        ];
    }
    
    /**
     * Extract cursor from page-based request parameters
     */
    public function extractCursorFromRequest($page = 1, $storedCursorMap = [])
    {
        // For page 1, no cursor needed
        if ($page <= 1) {
            return null;
        }
        
        // Look up cursor for the requested page
        // This requires maintaining a cursor map in cache/session
        return isset($storedCursorMap[$page]) ? $storedCursorMap[$page] : null;
    }
    
    /**
     * Store cursor mapping for page-based navigation
     */
    public function storeCursorMapping($sessionKey, $page, $cursor)
    {
        // Store in Redis cache for session-based pagination
        try {
            $cacheKey = "cursor_map:{$sessionKey}";
            $existingMap = v2raysocks_traffic_redisOperate('get', ['key' => $cacheKey]);
            $cursorMap = $existingMap ? json_decode($existingMap, true) : [];
            
            $cursorMap[$page] = $cursor;
            
            // Keep only last 50 pages to prevent memory bloat
            if (count($cursorMap) > 50) {
                $sortedKeys = array_keys($cursorMap);
                sort($sortedKeys);
                $cursorMap = array_slice($cursorMap, -50, 50, true);
            }
            
            v2raysocks_traffic_redisOperate('set', [
                'key' => $cacheKey,
                'value' => json_encode($cursorMap),
                'ttl' => 3600 // 1 hour
            ]);
            
            return true;
        } catch (Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Failed to store cursor mapping: " . $e->getMessage(), 0);
            return false;
        }
    }
    
    /**
     * Validate and normalize page size
     */
    private function validatePageSize($pageSize)
    {
        if ($pageSize === null) {
            return $this->defaultPageSize;
        }
        
        $pageSize = intval($pageSize);
        
        if ($pageSize <= 0) {
            return $this->defaultPageSize;
        }
        
        if ($pageSize > $this->maxPageSize) {
            return $this->maxPageSize;
        }
        
        return $pageSize;
    }
    
    /**
     * Estimate total count for a query (expensive operation, use sparingly)
     */
    public function estimateCount($baseQuery, $params = [])
    {
        try {
            // Convert to COUNT query
            $countQuery = "SELECT COUNT(*) as total FROM (" . $baseQuery . ") as count_subquery";
            
            $stmt = $this->pdo->prepare($countQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return intval($result['total']);
        } catch (Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Count estimation failed: " . $e->getMessage(), 0);
            return null;
        }
    }
}