-- =====================================================
-- TEST VERSION - Generate 1000 records for testing
-- =====================================================

DELIMITER $$

-- Drop procedure if exists to allow re-creation
DROP PROCEDURE IF EXISTS GenerateUserUsageTestDataSmall$$

-- Create the test stored procedure
CREATE PROCEDURE GenerateUserUsageTestDataSmall()
BEGIN
    -- Declare variables
    DECLARE done INT DEFAULT FALSE;
    DECLARE counter INT DEFAULT 0;
    DECLARE batch_counter INT DEFAULT 0;
    DECLARE total_records INT DEFAULT 1000;  -- Small test batch
    DECLARE batch_size INT DEFAULT 100;      -- Smaller batches for testing
    DECLARE start_time TIMESTAMP DEFAULT NOW();
    DECLARE progress_msg VARCHAR(255);
    
    -- Random data variables
    DECLARE random_user_id INT;
    DECLARE random_timestamp INT;
    DECLARE random_upload BIGINT;
    DECLARE random_download BIGINT;
    DECLARE random_node VARCHAR(50);
    DECLARE random_count_rate DECIMAL(3,2);
    
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
    DROP TEMPORARY TABLE IF EXISTS temp_user_ids_test;
    CREATE TEMPORARY TABLE temp_user_ids_test (
        idx INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT
    );
    
    -- Populate user IDs
    INSERT INTO temp_user_ids_test (user_id) VALUES 
    (14),(16),(18),(22),(24),(25),(26),(27),(32),(34),
    (40),(42),(43),(44),(45),(46),(47),(48);
    
    -- Create temporary table for node names
    DROP TEMPORARY TABLE IF EXISTS temp_nodes_test;
    CREATE TEMPORARY TABLE temp_nodes_test (
        idx INT AUTO_INCREMENT PRIMARY KEY,
        node_name VARCHAR(50)
    );
    
    -- Populate node names
    INSERT INTO temp_nodes_test (node_name) VALUES 
    ('🇩🇪 Germany 01'),
    ('🇯🇵 Japan 01'),
    ('🇺🇸 United States 01'),
    ('🇯🇵 Japan 02');
    
    -- Display start message
    SELECT CONCAT('Starting TEST generation of ', total_records, ' user_usage records...') AS status;
    
    -- Main generation loop
    generation_loop: LOOP
        -- Exit condition
        IF counter >= total_records THEN
            LEAVE generation_loop;
        END IF;
        
        -- Generate random data
        SET random_user_id = (SELECT user_id FROM temp_user_ids_test ORDER BY RAND() LIMIT 1);
        SET random_timestamp = start_timestamp + FLOOR(RAND() * (end_timestamp - start_timestamp));
        SET random_upload = 10000 + FLOOR(RAND() * (1000000000 - 10000));     -- 10KB to 1GB
        SET random_download = 100000 + FLOOR(RAND() * (50000000000 - 100000)); -- 100KB to 50GB
        SET random_node = (SELECT node_name FROM temp_nodes_test ORDER BY RAND() LIMIT 1);
        SET random_count_rate = 0.5 + (RAND() * 1.5); -- 0.5 to 2.0
        
        -- Insert record (we'll create a test table to avoid affecting real data)
        INSERT INTO user_usage_test (user_id, t, u, d, node, count_rate) 
        VALUES (random_user_id, random_timestamp, random_upload, random_download, random_node, random_count_rate);
        
        SET counter = counter + 1;
        SET batch_counter = batch_counter + 1;
        
        -- Commit batch and show progress
        IF batch_counter >= batch_size THEN
            COMMIT;
            START TRANSACTION;
            
            SET progress_msg = CONCAT(
                'Progress: ', counter, '/', total_records, 
                ' (', ROUND((counter / total_records) * 100, 2), '%)'
            );
            SELECT progress_msg AS progress_status;
            
            SET batch_counter = 0;
        END IF;
        
    END LOOP generation_loop;
    
    -- Final commit
    COMMIT;
    
    -- Cleanup temporary tables
    DROP TEMPORARY TABLE IF EXISTS temp_user_ids_test;
    DROP TEMPORARY TABLE IF EXISTS temp_nodes_test;
    
    -- Final status
    SELECT CONCAT('Successfully generated ', counter, ' test records!') AS final_status;
    SELECT CONCAT('Total execution time: ', TIMESTAMPDIFF(SECOND, start_time, NOW()), ' seconds') AS execution_time;
    
END$$

DELIMITER ;

-- Create test table (identical structure to user_usage)
CREATE TABLE IF NOT EXISTS user_usage_test (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    t INT NOT NULL,
    u BIGINT NOT NULL,
    d BIGINT NOT NULL,
    node VARCHAR(50) NOT NULL,
    count_rate FLOAT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Quick validation query for test data
DELIMITER $$

CREATE PROCEDURE ValidateTestData()
BEGIN
    SELECT 'TEST DATA VALIDATION' AS section;
    
    SELECT COUNT(*) AS total_test_records FROM user_usage_test;
    
    SELECT 
        user_id,
        COUNT(*) AS count
    FROM user_usage_test 
    GROUP BY user_id 
    ORDER BY user_id;
    
    SELECT 
        node,
        COUNT(*) AS count
    FROM user_usage_test 
    GROUP BY node;
    
    SELECT 
        MIN(u) AS min_upload,
        MAX(u) AS max_upload,
        MIN(d) AS min_download,
        MAX(d) AS max_download,
        MIN(count_rate) AS min_rate,
        MAX(count_rate) AS max_rate
    FROM user_usage_test;
    
END$$

DELIMITER ;

-- Test execution commands:
-- CALL GenerateUserUsageTestDataSmall();
-- CALL ValidateTestData();
-- DROP TABLE user_usage_test;  -- Clean up after testing