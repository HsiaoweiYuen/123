<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Unified Pagination Utility for V2RaySocks Traffic Analysis
 * Supports million-level concurrency and async processing
 */
class V2RaySocksPagination
{
    const DEFAULT_PAGE_SIZE = 1000;
    const MAX_PAGE_SIZE = 10000;
    const MIN_PAGE_SIZE = 100;
    
    // Supported pagination sizes for high-concurrency scenarios
    const SUPPORTED_PAGE_SIZES = [500, 1000, 3000, 5000, 10000];
    
    private $page;
    private $pageSize;
    private $offset;
    private $totalRecords;
    private $totalPages;
    
    public function __construct($page = 1, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $this->setPage($page);
        $this->setPageSize($pageSize);
        $this->calculateOffset();
    }
    
    /**
     * Set current page number
     */
    public function setPage($page)
    {
        $this->page = max(1, intval($page));
        $this->calculateOffset();
        return $this;
    }
    
    /**
     * Set page size with validation
     */
    public function setPageSize($pageSize)
    {
        $pageSize = intval($pageSize);
        
        // Validate page size for high-concurrency scenarios
        if (!in_array($pageSize, self::SUPPORTED_PAGE_SIZES)) {
            // Default to closest supported size
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }
        
        $this->pageSize = min(max($pageSize, self::MIN_PAGE_SIZE), self::MAX_PAGE_SIZE);
        $this->calculateOffset();
        return $this;
    }
    
    /**
     * Calculate offset for SQL queries
     */
    private function calculateOffset()
    {
        $this->offset = ($this->page - 1) * $this->pageSize;
    }
    
    /**
     * Set total records count and calculate total pages
     */
    public function setTotalRecords($totalRecords)
    {
        $this->totalRecords = max(0, intval($totalRecords));
        $this->totalPages = $this->pageSize > 0 ? ceil($this->totalRecords / $this->pageSize) : 0;
        return $this;
    }
    
    /**
     * Get SQL LIMIT clause for server-side pagination
     */
    public function getSqlLimit()
    {
        return "LIMIT {$this->pageSize} OFFSET {$this->offset}";
    }
    
    /**
     * Get pagination parameters for PDO binding
     */
    public function getPdoParams()
    {
        return [
            ':limit' => $this->pageSize,
            ':offset' => $this->offset
        ];
    }
    
    /**
     * Bind pagination parameters to PDO statement
     */
    public function bindPdoParams($stmt)
    {
        $stmt->bindValue(':limit', $this->pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $this->offset, PDO::PARAM_INT);
    }
    
    /**
     * Get pagination info for frontend
     */
    public function getPaginationInfo()
    {
        $startRecord = $this->totalRecords > 0 ? $this->offset + 1 : 0;
        $endRecord = min($this->offset + $this->pageSize, $this->totalRecords);
        
        return [
            'current_page' => $this->page,
            'page_size' => $this->pageSize,
            'total_pages' => $this->totalPages,
            'total_records' => $this->totalRecords,
            'start_record' => $startRecord,
            'end_record' => $endRecord,
            'has_previous' => $this->page > 1,
            'has_next' => $this->page < $this->totalPages,
            'offset' => $this->offset
        ];
    }
    
    /**
     * Get pagination controls data for templates
     */
    public function getPaginationControls()
    {
        $info = $this->getPaginationInfo();
        
        return [
            'pagination_info' => $info,
            'supported_page_sizes' => self::SUPPORTED_PAGE_SIZES,
            'current_page_size' => $this->pageSize
        ];
    }
    
    /**
     * Create pagination instance from request parameters
     */
    public static function fromRequest($defaultPageSize = self::DEFAULT_PAGE_SIZE)
    {
        $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
        $pageSize = isset($_REQUEST['page_size']) ? intval($_REQUEST['page_size']) : $defaultPageSize;
        
        return new self($page, $pageSize);
    }
    
    /**
     * Get count query for total records (for async processing)
     */
    public static function getCountQuery($baseQuery)
    {
        // Extract SELECT and FROM clauses, remove ORDER BY and LIMIT
        $query = preg_replace('/\s+ORDER\s+BY\s+.*$/i', '', $baseQuery);
        $query = preg_replace('/\s+LIMIT\s+.*$/i', '', $query);
        
        // Replace SELECT fields with COUNT(*)
        $query = preg_replace('/^SELECT\s+.*?\s+FROM/i', 'SELECT COUNT(*) as total FROM', $query);
        
        return $query;
    }
    
    /**
     * Execute async count query for large datasets
     */
    public static function getAsyncTotalCount($pdo, $countQuery, $params = [])
    {
        try {
            $stmt = $pdo->prepare($countQuery);
            
            // Execute count query with parameters (excluding pagination params)
            $countParams = array_filter($params, function($key) {
                return !in_array($key, [':limit', ':offset']);
            }, ARRAY_FILTER_USE_KEY);
            
            foreach ($countParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return intval($result['total'] ?? 0);
        } catch (Exception $e) {
            logActivity("V2RaySocks Traffic Monitor: Async count query failed: " . $e->getMessage(), 0);
            return 0;
        }
    }
    
    // Getters
    public function getPage() { return $this->page; }
    public function getPageSize() { return $this->pageSize; }
    public function getOffset() { return $this->offset; }
    public function getTotalRecords() { return $this->totalRecords; }
    public function getTotalPages() { return $this->totalPages; }
}

/**
 * Helper function to create pagination instance
 */
function v2raysocks_traffic_createPagination($page = 1, $pageSize = V2RaySocksPagination::DEFAULT_PAGE_SIZE)
{
    return new V2RaySocksPagination($page, $pageSize);
}

/**
 * Helper function to get default pagination configuration
 */
function v2raysocks_traffic_getDefaultPageSize()
{
    // Get from module configuration or use default
    $settings = \WHMCS\Database\Capsule::table('tbladdonmodules')
        ->where('module', 'v2raysocks_traffic')
        ->where('setting', 'default_page_size')
        ->value('value');
        
    $pageSize = $settings ? intval($settings) : V2RaySocksPagination::DEFAULT_PAGE_SIZE;
    
    // Validate against supported sizes
    if (!in_array($pageSize, V2RaySocksPagination::SUPPORTED_PAGE_SIZES)) {
        $pageSize = V2RaySocksPagination::DEFAULT_PAGE_SIZE;
    }
    
    return $pageSize;
}