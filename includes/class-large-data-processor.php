<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Large Data Processor for V2RaySocks Traffic Analysis
 * 
 * Handles processing of 300k-500k traffic records efficiently through:
 * - Batch processing with configurable chunk sizes
 * - Streaming data processing to avoid memory overflow
 * - Parallel time-range processing
 * - Memory management and garbage collection
 * 
 * @version 1.0.0
 * @author V2RaySocks Traffic Optimization Team
 */
class V2RaySocks_LargeDataProcessor
{
    private $pdo;
    private $batchSize;
    private $memoryLimit;
    private $maxExecutionTime;
    private $cacheManager;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     * @param int $batchSize Records per batch (default: 50000)
     * @param int $memoryLimit Memory limit in bytes (default: 512MB)
     * @param int $maxExecutionTime Max execution time in seconds (default: 300)
     */
    public function __construct($pdo, $batchSize = 50000, $memoryLimit = 536870912, $maxExecutionTime = 300)
    {
        $this->pdo = $pdo;
        $this->batchSize = $batchSize;
        $this->memoryLimit = $memoryLimit;
        $this->maxExecutionTime = $maxExecutionTime;
        
        // Set execution time limit
        set_time_limit($this->maxExecutionTime);
        
        // Initialize cache manager if available
        if (function_exists('v2raysocks_traffic_redisOperate')) {
            $this->cacheManager = true;
        }
    }
    
    /**
     * Process large dataset with batch processing
     * 
     * @param string $query Base SQL query
     * @param array $params Query parameters
     * @param callable $processor Callback function to process each batch
     * @param string $cacheKey Optional cache key for results
     * @return array Aggregated results
     */
    public function processBatches($query, $params, $processor, $cacheKey = null)
    {
        try {
            // Check cache first if key provided
            if ($cacheKey && $this->cacheManager) {
                $cached = $this->getCachedResult($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
            
            $this->logActivity("Starting batch processing for large dataset");
            $startTime = microtime(true);
            
            // Get total record count for progress tracking
            $totalRecords = $this->getTotalRecords($query, $params);
            $this->logActivity("Total records to process: " . number_format($totalRecords));
            
            if ($totalRecords == 0) {
                return [];
            }
            
            // Calculate number of batches
            $totalBatches = ceil($totalRecords / $this->batchSize);
            $aggregatedResult = [];
            $processedRecords = 0;
            
            // Process in batches
            for ($batch = 0; $batch < $totalBatches; $batch++) {
                $offset = $batch * $this->batchSize;
                
                // Memory check before each batch
                if (!$this->checkMemoryUsage()) {
                    $this->logActivity("Memory limit approaching, triggering garbage collection");
                    $this->cleanupMemory();
                }
                
                // Get batch data with streaming
                $batchData = $this->getBatchData($query, $params, $offset, $this->batchSize);
                
                if (!empty($batchData)) {
                    // Process this batch
                    $batchResult = call_user_func($processor, $batchData, $batch, $totalBatches);
                    
                    // Merge results
                    $aggregatedResult = $this->mergeResults($aggregatedResult, $batchResult);
                    
                    $processedRecords += count($batchData);
                    
                    // Progress logging
                    $progress = round(($processedRecords / $totalRecords) * 100, 1);
                    $this->logActivity("Batch " . ($batch + 1) . "/$totalBatches complete. Progress: $progress% ($processedRecords/$totalRecords records)");
                }
                
                // Cleanup batch data to free memory
                unset($batchData, $batchResult);
                
                // Force garbage collection every 10 batches
                if ($batch % 10 == 0) {
                    $this->cleanupMemory();
                }
            }
            
            $endTime = microtime(true);
            $processingTime = round($endTime - $startTime, 2);
            $this->logActivity("Batch processing completed. Total time: {$processingTime}s, Records processed: " . number_format($processedRecords));
            
            // Cache results if key provided
            if ($cacheKey && $this->cacheManager && !empty($aggregatedResult)) {
                $this->cacheResult($cacheKey, $aggregatedResult, $totalRecords);
            }
            
            return $aggregatedResult;
            
        } catch (Exception $e) {
            $this->logActivity("Error in batch processing: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Stream process large dataset without loading all into memory
     * 
     * @param string $query SQL query
     * @param array $params Query parameters  
     * @param callable $aggregator Callback function to aggregate each row
     * @param string $cacheKey Optional cache key
     * @return mixed Aggregated result
     */
    public function streamProcess($query, $params, $aggregator, $cacheKey = null)
    {
        try {
            // Check cache first
            if ($cacheKey && $this->cacheManager) {
                $cached = $this->getCachedResult($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
            
            $this->logActivity("Starting stream processing for large dataset");
            $startTime = microtime(true);
            
            // Prepare statement for streaming
            $stmt = $this->pdo->prepare($query);
            
            // Set fetch mode to unbuffered for true streaming
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            
            // Execute with parameters
            $stmt->execute($params);
            
            $result = null;
            $processedRows = 0;
            $lastProgressReport = 0;
            
            // Stream process each row
            while ($row = $stmt->fetch()) {
                $result = call_user_func($aggregator, $row, $result);
                $processedRows++;
                
                // Progress reporting every 10000 rows
                if ($processedRows - $lastProgressReport >= 10000) {
                    $this->logActivity("Stream processed " . number_format($processedRows) . " rows");
                    $lastProgressReport = $processedRows;
                    
                    // Memory check
                    if (!$this->checkMemoryUsage()) {
                        $this->cleanupMemory();
                    }
                }
            }
            
            $stmt->closeCursor();
            
            $endTime = microtime(true);
            $processingTime = round($endTime - $startTime, 2);
            $this->logActivity("Stream processing completed. Time: {$processingTime}s, Rows: " . number_format($processedRows));
            
            // Cache results
            if ($cacheKey && $this->cacheManager && $result !== null) {
                $this->cacheResult($cacheKey, $result, $processedRows);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logActivity("Error in stream processing: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process time ranges in parallel chunks
     * 
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @param callable $processor Function to process each time chunk
     * @param int $chunkHours Hours per chunk (default: 24)
     * @return array Combined results from all chunks
     */
    public function processTimeRangeChunks($startTime, $endTime, $processor, $chunkHours = 24)
    {
        try {
            $this->logActivity("Starting time-range chunk processing");
            
            $chunkSize = $chunkHours * 3600; // Convert hours to seconds
            $chunks = [];
            $results = [];
            
            // Create time chunks
            for ($time = $startTime; $time < $endTime; $time += $chunkSize) {
                $chunkEnd = min($time + $chunkSize - 1, $endTime);
                $chunks[] = ['start' => $time, 'end' => $chunkEnd];
            }
            
            $this->logActivity("Processing " . count($chunks) . " time chunks of $chunkHours hours each");
            
            // Process each chunk
            foreach ($chunks as $index => $chunk) {
                $chunkStart = microtime(true);
                
                $chunkResult = call_user_func($processor, $chunk['start'], $chunk['end'], $index);
                $results[] = $chunkResult;
                
                $chunkTime = round(microtime(true) - $chunkStart, 2);
                $this->logActivity("Chunk " . ($index + 1) . "/" . count($chunks) . " processed in {$chunkTime}s");
                
                // Memory management
                if (!$this->checkMemoryUsage()) {
                    $this->cleanupMemory();
                }
            }
            
            $this->logActivity("Time-range chunk processing completed");
            return $results;
            
        } catch (Exception $e) {
            $this->logActivity("Error in time-range chunk processing: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get total record count for a query
     */
    private function getTotalRecords($query, $params)
    {
        // Create count query from original query
        $countQuery = "SELECT COUNT(*) FROM ($query) as count_subquery";
        
        $stmt = $this->pdo->prepare($countQuery);
        $stmt->execute($params);
        
        return intval($stmt->fetchColumn());
    }
    
    /**
     * Get batch of data with offset and limit
     */
    private function getBatchData($query, $params, $offset, $limit)
    {
        $batchQuery = $query . " LIMIT $offset, $limit";
        
        $stmt = $this->pdo->prepare($batchQuery);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Merge batch results into aggregated result
     */
    private function mergeResults($existingResult, $batchResult)
    {
        if (empty($existingResult)) {
            return $batchResult;
        }
        
        if (is_array($existingResult) && is_array($batchResult)) {
            return array_merge($existingResult, $batchResult);
        }
        
        return $batchResult;
    }
    
    /**
     * Check memory usage against limit
     */
    private function checkMemoryUsage()
    {
        $currentUsage = memory_get_usage(true);
        $usagePercent = ($currentUsage / $this->memoryLimit) * 100;
        
        if ($usagePercent > 80) {
            $this->logActivity("Memory usage at " . round($usagePercent, 1) . "% (" . $this->formatBytes($currentUsage) . ")");
            return false;
        }
        
        return true;
    }
    
    /**
     * Cleanup memory and trigger garbage collection
     */
    private function cleanupMemory()
    {
        $beforeMemory = memory_get_usage(true);
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            $cycles = gc_collect_cycles();
            $this->logActivity("Garbage collection freed $cycles cycles");
        }
        
        $afterMemory = memory_get_usage(true);
        $freed = $beforeMemory - $afterMemory;
        
        if ($freed > 0) {
            $this->logActivity("Memory cleanup freed " . $this->formatBytes($freed));
        }
    }
    
    /**
     * Cache result with metadata
     */
    private function cacheResult($key, $result, $recordCount)
    {
        if (function_exists('v2raysocks_traffic_setCacheWithTTL')) {
            $cacheData = [
                'data' => $result,
                'metadata' => [
                    'record_count' => $recordCount,
                    'generated_at' => time(),
                    'processor_version' => '1.0.0'
                ]
            ];
            
            // Use longer TTL for large dataset results
            v2raysocks_traffic_setCacheWithTTL($key, json_encode($cacheData), [
                'data_type' => 'large_dataset',
                'time_range' => 'extended'
            ]);
        }
    }
    
    /**
     * Get cached result
     */
    private function getCachedResult($key)
    {
        if (function_exists('v2raysocks_traffic_redisOperate')) {
            try {
                $cached = v2raysocks_traffic_redisOperate('get', ['key' => $key]);
                if ($cached) {
                    $decoded = json_decode($cached, true);
                    if (isset($decoded['data'])) {
                        $this->logActivity("Using cached result with " . number_format($decoded['metadata']['record_count']) . " records");
                        return $decoded['data'];
                    }
                }
            } catch (Exception $e) {
                $this->logActivity("Cache read failed: " . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Format bytes for human readable output
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Log activity for monitoring
     */
    private function logActivity($message)
    {
        if (function_exists('logActivity')) {
            logActivity("V2RaySocks Large Data Processor: $message", 0);
        }
    }
}