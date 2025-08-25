-- ================================================================
-- MySQL Configuration Optimization for Large-Scale Data Processing
-- Designed to handle 300k-500k traffic records efficiently
-- ================================================================

-- ================================================================
-- Memory and Buffer Optimizations
-- ================================================================

-- Increase InnoDB buffer pool for better caching (adjust based on available RAM)
-- Recommendation: 70-80% of available RAM for dedicated MySQL servers
SET GLOBAL innodb_buffer_pool_size = 2147483648; -- 2GB (adjust for your server)

-- Optimize read buffer for large sequential scans
SET GLOBAL read_buffer_size = 2097152;            -- 2MB (up from default 131072)
SET GLOBAL read_rnd_buffer_size = 4194304;        -- 4MB (up from default 262144)

-- Optimize sort buffer for large sorting operations
SET GLOBAL sort_buffer_size = 16777216;           -- 16MB (up from default 262144)

-- Increase join buffer for complex JOIN operations
SET GLOBAL join_buffer_size = 33554432;           -- 32MB (up from default 262144)

-- Optimize temporary table sizes for aggregation queries
SET GLOBAL tmp_table_size = 1073741824;           -- 1GB (up from default 16777216)
SET GLOBAL max_heap_table_size = 1073741824;      -- 1GB (must match tmp_table_size)

-- ================================================================
-- InnoDB Specific Optimizations
-- ================================================================

-- Optimize InnoDB log files for write performance
SET GLOBAL innodb_log_file_size = 536870912;      -- 512MB (requires restart)
SET GLOBAL innodb_log_buffer_size = 67108864;     -- 64MB (up from default 16777216)

-- Increase InnoDB write threads for better concurrency
SET GLOBAL innodb_write_io_threads = 8;           -- Up from default 4
SET GLOBAL innodb_read_io_threads = 8;            -- Up from default 4

-- Optimize InnoDB flushing behavior
SET GLOBAL innodb_flush_log_at_trx_commit = 2;    -- Better performance, slight risk
SET GLOBAL innodb_flush_method = 'O_DIRECT';      -- Bypass OS cache

-- Increase concurrent connections for aggregation processing
SET GLOBAL innodb_thread_concurrency = 0;         -- Let InnoDB decide

-- Optimize page size and compression
SET GLOBAL innodb_page_size = 16384;              -- 16KB (default, good for mixed workload)

-- ================================================================
-- Query Cache and Connection Optimizations
-- ================================================================

-- Query cache (use with caution - may hurt performance on write-heavy workloads)
SET GLOBAL query_cache_type = 0;                  -- Disabled for write-heavy systems
SET GLOBAL query_cache_size = 0;                  -- Disabled

-- Connection optimizations
SET GLOBAL max_connections = 200;                 -- Adjust based on expected load
SET GLOBAL max_connect_errors = 100000;           -- Prevent connection blocking
SET GLOBAL connect_timeout = 60;                  -- 60 seconds
SET GLOBAL wait_timeout = 28800;                  -- 8 hours
SET GLOBAL interactive_timeout = 28800;           -- 8 hours

-- ================================================================
-- MyISAM Optimizations (if using MyISAM tables)
-- ================================================================

-- Key buffer for MyISAM indexes
SET GLOBAL key_buffer_size = 268435456;           -- 256MB

-- MyISAM bulk insert optimization
SET GLOBAL bulk_insert_buffer_size = 67108864;    -- 64MB

-- ================================================================
-- Large Data Processing Specific Settings
-- ================================================================

-- Increase packet sizes for large result sets
SET GLOBAL max_allowed_packet = 134217728;        -- 128MB (up from default 4MB)

-- Optimize for large GROUP BY operations
SET GLOBAL group_concat_max_len = 4294967295;     -- 4GB for large concatenations

-- Optimize thread handling
SET GLOBAL thread_cache_size = 50;                -- Cache connection threads
SET GLOBAL thread_stack = 262144;                 -- 256KB stack size

-- ================================================================
-- Timeout and Limit Optimizations
-- ================================================================

-- Increase timeouts for long-running queries
SET GLOBAL lock_wait_timeout = 300;               -- 5 minutes
SET GLOBAL innodb_lock_wait_timeout = 300;        -- 5 minutes

-- Optimize statement timeout
SET SESSION max_execution_time = 600000;          -- 10 minutes (MySQL 5.7+)

-- ================================================================
-- Binary Logging Optimizations (for replication)
-- ================================================================

-- Optimize binary log for large transactions
SET GLOBAL max_binlog_size = 1073741824;          -- 1GB
SET GLOBAL binlog_cache_size = 33554432;          -- 32MB
SET GLOBAL max_binlog_cache_size = 1073741824;    -- 1GB

-- Optimize sync frequency for performance
SET GLOBAL sync_binlog = 10;                      -- Sync every 10 transactions

-- ================================================================
-- Performance Schema Optimizations
-- ================================================================

-- Disable performance schema if not needed (saves memory)
-- Note: This requires restart and my.cnf configuration
-- performance_schema = OFF

-- If keeping performance schema, optimize memory usage
SET GLOBAL performance_schema_max_table_instances = 1000;
SET GLOBAL performance_schema_max_sql_text_length = 4096;

-- ================================================================
-- Session-Level Optimizations (Apply per connection)
-- ================================================================

-- These settings should be applied per session for large data processing
/*
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';
SET SESSION optimizer_search_depth = 62;
SET SESSION optimizer_prune_level = 1;
SET SESSION optimizer_switch = 'index_merge=on,index_merge_union=on,index_merge_sort_union=on,index_merge_intersection=on,engine_condition_pushdown=on,index_condition_pushdown=on,mrr=on,mrr_cost_based=on,block_nested_loop=on,batched_key_access=off,materialization=on,semijoin=on,loosescan=on,firstmatch=on,duplicateweedout=on,subquery_materialization_cost_based=on,use_index_extensions=on,condition_fanout_filter=on,derived_merge=on';
*/

-- ================================================================
-- Monitoring and Analysis Queries
-- ================================================================

-- Monitor buffer pool usage
/*
SELECT 
    VARIABLE_NAME,
    VARIABLE_VALUE,
    CASE 
        WHEN VARIABLE_NAME = 'Innodb_buffer_pool_pages_total' THEN ROUND(VARIABLE_VALUE * 16384 / 1024 / 1024, 2)
        WHEN VARIABLE_NAME = 'Innodb_buffer_pool_pages_free' THEN ROUND(VARIABLE_VALUE * 16384 / 1024 / 1024, 2)
        WHEN VARIABLE_NAME = 'Innodb_buffer_pool_pages_data' THEN ROUND(VARIABLE_VALUE * 16384 / 1024 / 1024, 2)
    END AS 'Size_MB'
FROM information_schema.GLOBAL_STATUS 
WHERE VARIABLE_NAME IN (
    'Innodb_buffer_pool_pages_total',
    'Innodb_buffer_pool_pages_free', 
    'Innodb_buffer_pool_pages_data'
);
*/

-- Monitor connection usage
/*
SELECT 
    VARIABLE_NAME,
    VARIABLE_VALUE
FROM information_schema.GLOBAL_STATUS 
WHERE VARIABLE_NAME IN (
    'Connections',
    'Max_used_connections',
    'Threads_connected',
    'Threads_running'
);
*/

-- Monitor query performance
/*
SELECT 
    VARIABLE_NAME,
    VARIABLE_VALUE
FROM information_schema.GLOBAL_STATUS 
WHERE VARIABLE_NAME IN (
    'Slow_queries',
    'Questions',
    'Queries',
    'Select_scan',
    'Select_full_join',
    'Sort_merge_passes'
);
*/

-- ================================================================
-- Table-Specific Optimizations
-- ================================================================

-- Analyze tables for optimization
ANALYZE TABLE user_usage;
ANALYZE TABLE user;
ANALYZE TABLE node;
ANALYZE TABLE user_usage_hourly_agg;
ANALYZE TABLE user_usage_daily_agg;

-- Optimize tables (use during maintenance windows)
/*
OPTIMIZE TABLE user_usage;
OPTIMIZE TABLE user_usage_hourly_agg;
OPTIMIZE TABLE user_usage_daily_agg;
*/

-- ================================================================
-- Configuration File Recommendations (my.cnf/my.ini)
-- ================================================================

/*
Add these to your MySQL configuration file for persistent settings:

[mysqld]
# Basic Settings
default-storage-engine = InnoDB
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Memory Settings (adjust based on your RAM)
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M
tmp_table_size = 1G
max_heap_table_size = 1G
sort_buffer_size = 16M
read_buffer_size = 2M
read_rnd_buffer_size = 4M
join_buffer_size = 32M

# InnoDB Settings
innodb_file_per_table = 1
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_thread_concurrency = 0
innodb_read_io_threads = 8
innodb_write_io_threads = 8

# Connection Settings
max_connections = 200
max_connect_errors = 100000
connect_timeout = 60
wait_timeout = 28800
interactive_timeout = 28800

# Large Data Settings
max_allowed_packet = 128M
group_concat_max_len = 4294967295

# Binary Log Settings (for replication)
max_binlog_size = 1G
binlog_cache_size = 32M
sync_binlog = 10

# Performance Schema (disable if not needed)
performance_schema = OFF

# Slow Query Log (for monitoring)
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1
*/

-- ================================================================
-- Performance Monitoring Commands
-- ================================================================

/*
-- Check current configuration
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';
SHOW VARIABLES LIKE 'tmp_table_size';
SHOW VARIABLES LIKE 'max_connections';

-- Monitor active processes
SHOW PROCESSLIST;

-- Check table status
SHOW TABLE STATUS LIKE 'user_usage';

-- Monitor InnoDB status
SHOW ENGINE INNODB STATUS;

-- Check query cache status (if enabled)
SHOW STATUS LIKE 'Qcache%';
*/

-- ================================================================
-- Notes and Warnings
-- ================================================================

/*
IMPORTANT NOTES:

1. MEMORY ALLOCATION:
   - Ensure total memory allocated doesn't exceed 90% of available RAM
   - Monitor swap usage - should be minimal
   - Adjust buffer sizes based on your specific workload

2. RESTART REQUIREMENTS:
   - Some settings require MySQL restart (innodb_log_file_size, innodb_buffer_pool_size)
   - Plan maintenance windows for applying these changes

3. MONITORING:
   - Continuously monitor query performance after applying changes
   - Use EXPLAIN on problematic queries
   - Monitor system resources (CPU, RAM, I/O)

4. BACKUP CONSIDERATIONS:
   - Large buffer pools may increase backup time
   - Ensure backup strategies account for increased memory usage

5. REPLICATION:
   - If using replication, ensure slave servers have similar configuration
   - Monitor replication lag with large datasets

6. HARDWARE RECOMMENDATIONS:
   - SSD storage highly recommended for large datasets
   - Sufficient RAM for buffer pools (minimum 4GB recommended)
   - Multiple CPU cores for parallel processing

7. GRADUAL IMPLEMENTATION:
   - Implement changes gradually and monitor impact
   - Start with conservative values and increase as needed
   - Keep baseline performance metrics for comparison
*/