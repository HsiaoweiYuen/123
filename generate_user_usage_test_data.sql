-- =====================================================
-- V2RaySocks Traffic Analysis - Test Data Generator
-- Generate 2,000,000 random user_usage records
-- Compatible with MySQL 8.x
-- =====================================================

DELIMITER $$

-- Drop procedure if exists to allow re-creation
DROP PROCEDURE IF EXISTS GenerateUserUsageTestData$$

-- Create the stored procedure
CREATE PROCEDURE GenerateUserUsageTestData()
BEGIN
    -- Declare variables
    DECLARE done INT DEFAULT FALSE;
    DECLARE counter INT DEFAULT 0;
    DECLARE batch_counter INT DEFAULT 0;
    DECLARE total_records INT DEFAULT 2000000;
    DECLARE batch_size INT DEFAULT 10000;
    DECLARE start_time TIMESTAMP DEFAULT NOW();
    DECLARE progress_msg VARCHAR(255);
    
    -- Random data variables
    DECLARE random_user_id INT;
    DECLARE random_timestamp INT;
    DECLARE random_upload BIGINT;
    DECLARE random_download BIGINT;
    DECLARE random_node VARCHAR(50);
    DECLARE random_count_rate DECIMAL(3,2);
    
    -- User IDs array (18 existing users)
    DECLARE user_ids VARCHAR(255) DEFAULT '14,16,18,22,24,25,26,27,32,34,40,42,43,44,45,46,47,48';
    
    -- Node names array (4 nodes)
    DECLARE node_names VARCHAR(500) DEFAULT '🇩🇪 Germany 01,🇯🇵 Japan 01,🇺🇸 United States 01,🇯🇵 Japan 02';
    
    -- Time range constants
    DECLARE start_timestamp INT DEFAULT 1672531200; -- 2023-01-01 00:00:00
    DECLARE end_timestamp INT DEFAULT 1755907200;   -- 2025-08-23 00:00:00
    
    -- Error handling
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE, 
            @errno = MYSQL_ERRNO, 
            @text = MESSAGE_TEXT;
        SET progress_msg = CONCAT('Error: ', @errno, ' - ', @text);
        SELECT progress_msg AS error_message;
    END;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Create temporary table for user IDs
    DROP TEMPORARY TABLE IF EXISTS temp_user_ids;
    CREATE TEMPORARY TABLE temp_user_ids (
        idx INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT
    );
    
    -- Populate user IDs
    INSERT INTO temp_user_ids (user_id) VALUES 
    (14),(16),(18),(22),(24),(25),(26),(27),(32),(34),
    (40),(42),(43),(44),(45),(46),(47),(48);
    
    -- Create temporary table for node names
    DROP TEMPORARY TABLE IF EXISTS temp_nodes;
    CREATE TEMPORARY TABLE temp_nodes (
        idx INT AUTO_INCREMENT PRIMARY KEY,
        node_name VARCHAR(50)
    );
    
    -- Populate node names
    INSERT INTO temp_nodes (node_name) VALUES 
    ('🇩🇪 Germany 01'),
    ('🇯🇵 Japan 01'),
    ('🇺🇸 United States 01'),
    ('🇯🇵 Japan 02');
    
    -- Display start message
    SELECT CONCAT('Starting generation of ', total_records, ' user_usage records...') AS status;
    SELECT CONCAT('Batch size: ', batch_size, ' records per transaction') AS batch_info;
    SELECT 'Progress will be reported every 10,000 records' AS progress_info;
    
    -- Main generation loop
    generation_loop: LOOP
        -- Exit condition
        IF counter >= total_records THEN
            LEAVE generation_loop;
        END IF;
        
        -- Generate random data
        SET random_user_id = (SELECT user_id FROM temp_user_ids ORDER BY RAND() LIMIT 1);
        SET random_timestamp = start_timestamp + FLOOR(RAND() * (end_timestamp - start_timestamp));
        SET random_upload = 10000 + FLOOR(RAND() * (1000000000 - 10000));     -- 10KB to 1GB
        SET random_download = 100000 + FLOOR(RAND() * (50000000000 - 100000)); -- 100KB to 50GB
        SET random_node = (SELECT node_name FROM temp_nodes ORDER BY RAND() LIMIT 1);
        SET random_count_rate = 0.5 + (RAND() * 1.5); -- 0.5 to 2.0
        
        -- Insert record
        INSERT INTO user_usage (user_id, t, u, d, node, count_rate) 
        VALUES (random_user_id, random_timestamp, random_upload, random_download, random_node, random_count_rate);
        
        SET counter = counter + 1;
        SET batch_counter = batch_counter + 1;
        
        -- Commit batch and show progress
        IF batch_counter >= batch_size THEN
            COMMIT;
            START TRANSACTION;
            
            SET progress_msg = CONCAT(
                'Progress: ', counter, '/', total_records, 
                ' (', ROUND((counter / total_records) * 100, 2), '%) - ',
                'Elapsed: ', TIMESTAMPDIFF(SECOND, start_time, NOW()), 's'
            );
            SELECT progress_msg AS progress_status;
            
            SET batch_counter = 0;
        END IF;
        
    END LOOP generation_loop;
    
    -- Final commit
    COMMIT;
    
    -- Cleanup temporary tables
    DROP TEMPORARY TABLE IF EXISTS temp_user_ids;
    DROP TEMPORARY TABLE IF EXISTS temp_nodes;
    
    -- Final status
    SELECT CONCAT('Successfully generated ', counter, ' user_usage records!') AS final_status;
    SELECT CONCAT('Total execution time: ', TIMESTAMPDIFF(SECOND, start_time, NOW()), ' seconds') AS execution_time;
    
END$$

DELIMITER ;

-- =====================================================  
-- DATA VALIDATION QUERIES
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS ValidateUserUsageTestData$$

CREATE PROCEDURE ValidateUserUsageTestData()
BEGIN
    DECLARE total_count INT;
    
    -- Get total count of records
    SELECT COUNT(*) INTO total_count FROM user_usage;
    
    SELECT 'DATA VALIDATION REPORT' AS section;
    SELECT '========================' AS separator;
    
    -- 1. Total record count
    SELECT 
        'Total Records' AS metric,
        total_count AS value,
        CASE 
            WHEN total_count >= 2000000 THEN 'PASS'
            ELSE 'FAIL'
        END AS status;
    
    -- 2. User ID distribution
    SELECT 'USER ID DISTRIBUTION' AS section;
    SELECT 
        user_id,
        COUNT(*) AS record_count,
        ROUND((COUNT(*) / total_count) * 100, 2) AS percentage
    FROM user_usage 
    GROUP BY user_id 
    ORDER BY user_id;
    
    -- 3. Node distribution
    SELECT 'NODE DISTRIBUTION' AS section;
    SELECT 
        node,
        COUNT(*) AS record_count,
        ROUND((COUNT(*) / total_count) * 100, 2) AS percentage
    FROM user_usage 
    WHERE node != 'DAY'  -- Exclude existing DAY aggregation records
    GROUP BY node 
    ORDER BY record_count DESC;
    
    -- 4. Time range validation
    SELECT 'TIME RANGE VALIDATION' AS section;
    SELECT 
        'Earliest timestamp' AS metric,
        MIN(t) AS value,
        FROM_UNIXTIME(MIN(t)) AS readable_date
    FROM user_usage
    UNION ALL
    SELECT 
        'Latest timestamp' AS metric,
        MAX(t) AS value,
        FROM_UNIXTIME(MAX(t)) AS readable_date
    FROM user_usage;
    
    -- 5. Traffic data validation
    SELECT 'TRAFFIC DATA VALIDATION' AS section;
    SELECT 
        'Upload (u)' AS traffic_type,
        MIN(u) AS min_bytes,
        MAX(u) AS max_bytes,
        AVG(u) AS avg_bytes,
        CONCAT(ROUND(MIN(u)/1024, 2), ' KB') AS min_readable,
        CONCAT(ROUND(MAX(u)/1024/1024, 2), ' MB') AS max_readable
    FROM user_usage
    UNION ALL
    SELECT 
        'Download (d)' AS traffic_type,
        MIN(d) AS min_bytes,
        MAX(d) AS max_bytes,
        AVG(d) AS avg_bytes,
        CONCAT(ROUND(MIN(d)/1024, 2), ' KB') AS min_readable,
        CONCAT(ROUND(MAX(d)/1024/1024, 2), ' MB') AS max_readable
    FROM user_usage;
    
    -- 6. Count rate validation
    SELECT 'COUNT RATE VALIDATION' AS section;
    SELECT 
        MIN(count_rate) AS min_count_rate,
        MAX(count_rate) AS max_count_rate,
        AVG(count_rate) AS avg_count_rate,
        COUNT(CASE WHEN count_rate IS NULL THEN 1 END) AS null_count
    FROM user_usage;
    
    -- 7. Recent activity (last 30 days from latest timestamp)
    SELECT 'RECENT ACTIVITY (Last 30 days)' AS section;
    SELECT 
        node,
        COUNT(*) AS recent_records
    FROM user_usage 
    WHERE t >= (SELECT MAX(t) - (30 * 86400) FROM user_usage)
    GROUP BY node
    ORDER BY recent_records DESC;
    
END$$

DELIMITER ;

-- =====================================================
-- PERFORMANCE ANALYSIS QUERIES  
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS AnalyzeUserUsagePerformance$$

CREATE PROCEDURE AnalyzeUserUsagePerformance()
BEGIN
    SELECT 'PERFORMANCE ANALYSIS' AS section;
    SELECT '=====================' AS separator;
    
    -- 1. Table size information
    SELECT 
        'Table Size Information' AS section,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
        ROUND(((data_length + index_length) / 1024 / 1024 / 1024), 2) AS size_gb,
        table_rows
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'user_usage';
    
    -- 2. Index usage recommendations
    SELECT 'RECOMMENDED INDEXES' AS section;
    SELECT 'Consider adding these indexes for better performance:' AS recommendation
    UNION ALL
    SELECT 'CREATE INDEX idx_user_usage_user_id_t ON user_usage(user_id, t);'
    UNION ALL  
    SELECT 'CREATE INDEX idx_user_usage_node_t ON user_usage(node, t);'
    UNION ALL
    SELECT 'CREATE INDEX idx_user_usage_t ON user_usage(t);';
    
    -- 3. Sample query performance test
    SELECT 'SAMPLE QUERY PERFORMANCE TEST' AS section;
    
    SET @start_time = NOW(6);
    SELECT COUNT(*) INTO @result FROM user_usage WHERE user_id = 22 AND t >= 1672531200;
    SET @end_time = NOW(6);
    
    SELECT 
        'User-specific query' AS query_type,
        @result AS result_count,
        TIMESTAMPDIFF(MICROSECOND, @start_time, @end_time) / 1000 AS execution_time_ms;
    
END$$

DELIMITER ;

-- =====================================================
-- EXECUTION INSTRUCTIONS
-- =====================================================

/*
EXECUTION INSTRUCTIONS:
========================

1. To generate 2 million test records:
   CALL GenerateUserUsageTestData();

2. To validate the generated data:
   CALL ValidateUserUsageTestData();

3. To analyze performance:
   CALL AnalyzeUserUsagePerformance();

4. To check progress during generation, monitor the output messages.

5. Expected execution time: 10-30 minutes depending on server performance.

6. Disk space required: Approximately 500MB-1GB for 2M records.

NOTES:
======
- The procedure generates records in batches of 10,000 for optimal performance
- Progress is reported every 10,000 records
- All data follows the specified ranges:
  * User IDs: 14,16,18,22,24,25,26,27,32,34,40,42,43,44,45,46,47,48
  * Nodes: 🇩🇪 Germany 01, 🇯🇵 Japan 01, 🇺🇸 United States 01, 🇯🇵 Japan 02
  * Time range: 2023-01-01 to 2025-08-23
  * Upload: 10KB to 1GB
  * Download: 100KB to 50GB  
  * Count rate: 0.5 to 2.0

TROUBLESHOOTING:
================
- If the procedure times out, you can run it multiple times with smaller batches
- Monitor MySQL process list during execution: SHOW PROCESSLIST;
- Check error log if generation fails
- Ensure sufficient disk space and memory
*/

-- =====================================================
-- QUICK START COMMANDS
-- =====================================================

-- Uncomment the following lines to execute immediately:
-- CALL GenerateUserUsageTestData();
-- CALL ValidateUserUsageTestData();
-- CALL AnalyzeUserUsagePerformance();