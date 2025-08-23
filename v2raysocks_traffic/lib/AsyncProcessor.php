<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Asynchronous Processing System for V2RaySocks Traffic Analysis
 * 
 * Provides Redis-based task queue system for handling large data operations
 * without blocking the main request thread. Supports batch processing,
 * priority queues, and result aggregation.
 */
class AsyncProcessor
{
    private $redis;
    private $queuePrefix = 'v2raysocks_async';
    private $resultPrefix = 'v2raysocks_results';
    private $statusPrefix = 'v2raysocks_status';
    private $defaultPriority = 0;
    private $maxRetries = 3;
    private $resultTTL = 3600; // 1 hour
    private $statusTTL = 1800; // 30 minutes
    
    public function __construct()
    {
        $this->redis = v2raysocks_traffic_getRedisInstance();
        if (!$this->redis) {
            throw new Exception("Redis connection required for AsyncProcessor");
        }
    }
    
    /**
     * Enqueue a task for asynchronous processing
     * 
     * @param string $taskType Type of task (traffic_data, rankings, etc.)
     * @param array $data Task data and parameters
     * @param int $priority Task priority (higher = more important)
     * @param int $delay Delay in seconds before processing
     * @return string Task ID
     */
    public function enqueue($taskType, $data, $priority = null, $delay = 0)
    {
        $priority = $priority ?? $this->defaultPriority;
        $taskId = $this->generateTaskId();
        
        $task = [
            'id' => $taskId,
            'type' => $taskType,
            'data' => $data,
            'priority' => $priority,
            'created_at' => time(),
            'scheduled_at' => time() + $delay,
            'attempts' => 0,
            'max_retries' => $this->maxRetries,
            'status' => 'queued'
        ];
        
        try {
            // Store task details
            $this->redis->setex(
                $this->getTaskKey($taskId),
                $this->statusTTL,
                json_encode($task)
            );
            
            // Add to priority queue
            $queueKey = $this->getQueueKey($taskType);
            $score = $priority * 1000000 + time(); // Priority + timestamp for FIFO within priority
            $this->redis->zadd($queueKey, $score, $taskId);
            
            // Update task status
            $this->updateTaskStatus($taskId, 'queued', [
                'queued_at' => time(),
                'queue' => $queueKey
            ]);
            
            logActivity("V2RaySocks Async: Task {$taskId} queued for type {$taskType}", 0);
            return $taskId;
            
        } catch (Exception $e) {
            logActivity("V2RaySocks Async: Failed to enqueue task: " . $e->getMessage(), 0);
            throw $e;
        }
    }
    
    /**
     * Process tasks in batches for a specific task type
     * 
     * @param string $taskType Task type to process
     * @param int $batchSize Number of tasks to process in batch
     * @param int $timeout Maximum processing time in seconds
     * @return array Processing results
     */
    public function processInBatches($taskType, $batchSize = 10, $timeout = 300)
    {
        $queueKey = $this->getQueueKey($taskType);
        $processed = 0;
        $results = [];
        $startTime = time();
        
        try {
            while ((time() - $startTime) < $timeout) {
                // Get batch of tasks with highest priority
                $taskIds = $this->redis->zrevrange($queueKey, 0, $batchSize - 1);
                
                if (empty($taskIds)) {
                    break; // No more tasks
                }
                
                // Remove from queue
                foreach ($taskIds as $taskId) {
                    $this->redis->zrem($queueKey, $taskId);
                }
                
                // Process batch
                $batchResults = $this->processBatch($taskIds, $taskType);
                $results = array_merge($results, $batchResults);
                $processed += count($taskIds);
                
                // Check memory usage
                if (memory_get_usage() > 256 * 1024 * 1024) { // 256MB limit
                    logActivity("V2RaySocks Async: Memory limit approaching, stopping batch processing", 0);
                    break;
                }
            }
            
            logActivity("V2RaySocks Async: Processed {$processed} tasks of type {$taskType}", 0);
            return [
                'processed' => $processed,
                'results' => $results,
                'processing_time' => time() - $startTime
            ];
            
        } catch (Exception $e) {
            logActivity("V2RaySocks Async: Batch processing failed: " . $e->getMessage(), 0);
            throw $e;
        }
    }
    
    /**
     * Process a batch of tasks
     */
    private function processBatch($taskIds, $taskType)
    {
        $results = [];
        
        foreach ($taskIds as $taskId) {
            try {
                $result = $this->processTask($taskId, $taskType);
                $results[$taskId] = $result;
            } catch (Exception $e) {
                logActivity("V2RaySocks Async: Task {$taskId} failed: " . $e->getMessage(), 0);
                $this->handleTaskFailure($taskId, $e);
            }
        }
        
        return $results;
    }
    
    /**
     * Process a single task
     */
    private function processTask($taskId, $taskType)
    {
        // Get task details
        $taskData = $this->getTaskData($taskId);
        if (!$taskData) {
            throw new Exception("Task data not found for {$taskId}");
        }
        
        $this->updateTaskStatus($taskId, 'processing', [
            'started_at' => time(),
            'attempts' => $taskData['attempts'] + 1
        ]);
        
        // Process based on task type
        $result = null;
        switch ($taskType) {
            case 'traffic_data':
                $result = $this->processTrafficDataTask($taskData);
                break;
            case 'user_rankings':
                $result = $this->processUserRankingsTask($taskData);
                break;
            case 'node_rankings':
                $result = $this->processNodeRankingsTask($taskData);
                break;
            case 'usage_records':
                $result = $this->processUsageRecordsTask($taskData);
                break;
            case 'aggregate_results':
                $result = $this->processAggregateTask($taskData);
                break;
            default:
                throw new Exception("Unknown task type: {$taskType}");
        }
        
        // Store result
        $this->storeResult($taskId, $result);
        
        $this->updateTaskStatus($taskId, 'completed', [
            'completed_at' => time(),
            'result_key' => $this->getResultKey($taskId)
        ]);
        
        return $result;
    }
    
    /**
     * Process traffic data task
     */
    private function processTrafficDataTask($taskData)
    {
        $filters = $taskData['data']['filters'] ?? [];
        $cursor = $taskData['data']['cursor'] ?? null;
        $config = v2raysocks_traffic_getModuleConfig();
        $pageSize = $taskData['data']['page_size'] ?? intval($config['pagination_size'] ?? 1000);
        
        // Use cursor pagination for traffic data
        require_once __DIR__ . '/CursorPaginator.php';
        $paginator = new CursorPaginator();
        
        // Process based on specific traffic data type
        $dataType = $taskData['data']['data_type'] ?? 'standard';
        switch ($dataType) {
            case 'enhanced':
                return $this->processEnhancedTrafficData($filters, $cursor, $pageSize, $paginator);
            case 'day':
                return $this->processDayTrafficData($filters, $cursor, $pageSize, $paginator);
            default:
                return $this->processStandardTrafficData($filters, $cursor, $pageSize, $paginator);
        }
    }
    
    /**
     * Process user rankings task
     */
    private function processUserRankingsTask($taskData)
    {
        $sortBy = $taskData['data']['sort_by'] ?? 'traffic_desc';
        $timeRange = $taskData['data']['time_range'] ?? 'today';
        $limit = $taskData['data']['limit'] ?? 1000;
        $cursor = $taskData['data']['cursor'] ?? null;
        
        // Use existing function with cursor support
        return v2raysocks_traffic_getUserTrafficRankings($sortBy, $timeRange, $limit, null, null, null, null, $cursor);
    }
    
    /**
     * Aggregate results from multiple tasks
     * 
     * @param string $aggregateId Aggregate task identifier
     * @return array Aggregated results
     */
    public function aggregateResults($aggregateId)
    {
        try {
            $aggregateKey = $this->getAggregateKey($aggregateId);
            $taskIds = $this->redis->smembers($aggregateKey);
            
            if (empty($taskIds)) {
                return ['status' => 'no_tasks', 'data' => []];
            }
            
            $results = [];
            $completedTasks = 0;
            $totalTasks = count($taskIds);
            
            foreach ($taskIds as $taskId) {
                $status = $this->getTaskStatus($taskId);
                
                if ($status['status'] === 'completed') {
                    $result = $this->getResult($taskId);
                    if ($result) {
                        $results[$taskId] = $result;
                        $completedTasks++;
                    }
                } elseif ($status['status'] === 'failed') {
                    $results[$taskId] = ['error' => $status['error'] ?? 'Task failed'];
                }
            }
            
            $isComplete = $completedTasks === $totalTasks;
            
            return [
                'status' => $isComplete ? 'completed' : 'processing',
                'progress' => [
                    'completed' => $completedTasks,
                    'total' => $totalTasks,
                    'percentage' => round(($completedTasks / $totalTasks) * 100, 2)
                ],
                'data' => $results
            ];
            
        } catch (Exception $e) {
            logActivity("V2RaySocks Async: Result aggregation failed: " . $e->getMessage(), 0);
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create aggregate task group
     */
    public function createAggregateGroup($aggregateId, $taskIds)
    {
        try {
            $aggregateKey = $this->getAggregateKey($aggregateId);
            
            foreach ($taskIds as $taskId) {
                $this->redis->sadd($aggregateKey, $taskId);
            }
            
            $this->redis->expire($aggregateKey, $this->resultTTL);
            return true;
            
        } catch (Exception $e) {
            logActivity("V2RaySocks Async: Failed to create aggregate group: " . $e->getMessage(), 0);
            return false;
        }
    }
    
    /**
     * Get task status
     */
    public function getTaskStatus($taskId)
    {
        try {
            $statusKey = $this->getStatusKey($taskId);
            $statusData = $this->redis->get($statusKey);
            
            if ($statusData) {
                return json_decode($statusData, true);
            }
            
            return ['status' => 'not_found'];
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get task result
     */
    public function getResult($taskId)
    {
        try {
            $resultKey = $this->getResultKey($taskId);
            $resultData = $this->redis->get($resultKey);
            
            if ($resultData) {
                return json_decode($resultData, true);
            }
            
            return null;
        } catch (Exception $e) {
            logActivity("V2RaySocks Async: Failed to get result for {$taskId}: " . $e->getMessage(), 0);
            return null;
        }
    }
    
    /**
     * Clean up completed tasks and results
     */
    public function cleanup($olderThanSeconds = 3600)
    {
        try {
            $cutoffTime = time() - $olderThanSeconds;
            $cleaned = 0;
            
            // Find old task keys
            $pattern = $this->statusPrefix . ':*';
            $keys = $this->redis->keys($pattern);
            
            foreach ($keys as $key) {
                $statusData = $this->redis->get($key);
                if ($statusData) {
                    $status = json_decode($statusData, true);
                    $completedAt = $status['completed_at'] ?? $status['created_at'] ?? time();
                    
                    if ($completedAt < $cutoffTime) {
                        $taskId = str_replace($this->statusPrefix . ':', '', $key);
                        
                        // Clean up task, status, and result
                        $this->redis->del([
                            $this->getTaskKey($taskId),
                            $this->getStatusKey($taskId),
                            $this->getResultKey($taskId)
                        ]);
                        
                        $cleaned++;
                    }
                }
            }
            
            logActivity("V2RaySocks Async: Cleaned up {$cleaned} old tasks", 0);
            return $cleaned;
            
        } catch (Exception $e) {
            logActivity("V2RaySocks Async: Cleanup failed: " . $e->getMessage(), 0);
            return 0;
        }
    }
    
    // Helper methods
    private function generateTaskId()
    {
        return uniqid('task_', true) . '_' . mt_rand(1000, 9999);
    }
    
    private function getQueueKey($taskType)
    {
        return $this->queuePrefix . ':queue:' . $taskType;
    }
    
    private function getTaskKey($taskId)
    {
        return $this->queuePrefix . ':task:' . $taskId;
    }
    
    private function getStatusKey($taskId)
    {
        return $this->statusPrefix . ':' . $taskId;
    }
    
    private function getResultKey($taskId)
    {
        return $this->resultPrefix . ':' . $taskId;
    }
    
    private function getAggregateKey($aggregateId)
    {
        return $this->queuePrefix . ':aggregate:' . $aggregateId;
    }
    
    private function getTaskData($taskId)
    {
        $taskKey = $this->getTaskKey($taskId);
        $taskData = $this->redis->get($taskKey);
        return $taskData ? json_decode($taskData, true) : null;
    }
    
    private function updateTaskStatus($taskId, $status, $additionalData = [])
    {
        $statusKey = $this->getStatusKey($taskId);
        $existingStatus = $this->redis->get($statusKey);
        $statusData = $existingStatus ? json_decode($existingStatus, true) : [];
        
        $statusData['status'] = $status;
        $statusData['updated_at'] = time();
        $statusData = array_merge($statusData, $additionalData);
        
        $this->redis->setex($statusKey, $this->statusTTL, json_encode($statusData));
    }
    
    private function storeResult($taskId, $result)
    {
        $resultKey = $this->getResultKey($taskId);
        $this->redis->setex($resultKey, $this->resultTTL, json_encode($result));
    }
    
    private function handleTaskFailure($taskId, $exception)
    {
        $taskData = $this->getTaskData($taskId);
        $attempts = ($taskData['attempts'] ?? 0) + 1;
        
        if ($attempts >= $this->maxRetries) {
            $this->updateTaskStatus($taskId, 'failed', [
                'failed_at' => time(),
                'error' => $exception->getMessage(),
                'attempts' => $attempts
            ]);
        } else {
            // Retry task with exponential backoff
            $delay = pow(2, $attempts) * 60; // 2^attempts minutes
            $this->enqueue($taskData['type'], $taskData['data'], $taskData['priority'], $delay);
            
            $this->updateTaskStatus($taskId, 'retry_scheduled', [
                'retry_at' => time() + $delay,
                'error' => $exception->getMessage(),
                'attempts' => $attempts
            ]);
        }
    }
    
    // Placeholder methods for specific data processing
    private function processStandardTrafficData($filters, $cursor, $pageSize, $paginator)
    {
        // Implementation would call the refactored v2raysocks_traffic_getTrafficData
        // with cursor pagination support
        return ['message' => 'Standard traffic data processing not implemented yet'];
    }
    
    private function processEnhancedTrafficData($filters, $cursor, $pageSize, $paginator)
    {
        // Implementation would call the refactored v2raysocks_traffic_getEnhancedTrafficData
        // with cursor pagination support  
        return ['message' => 'Enhanced traffic data processing not implemented yet'];
    }
    
    private function processDayTrafficData($filters, $cursor, $pageSize, $paginator)
    {
        // Implementation would call the refactored v2raysocks_traffic_getDayTraffic
        // with cursor pagination support
        return ['message' => 'Day traffic data processing not implemented yet'];
    }
    
    private function processNodeRankingsTask($taskData)
    {
        // Implementation would call the refactored node rankings function
        return ['message' => 'Node rankings processing not implemented yet'];
    }
    
    private function processUsageRecordsTask($taskData)
    {
        // Implementation would call the refactored usage records function
        return ['message' => 'Usage records processing not implemented yet'];
    }
    
    private function processAggregateTask($taskData)
    {
        // Implementation for result aggregation
        return ['message' => 'Aggregate processing not implemented yet'];
    }
}