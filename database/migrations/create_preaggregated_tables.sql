-- ================================================================
-- Pre-aggregated Tables for Large-Scale Data Optimization
-- Designed to improve performance for historical data queries
-- ================================================================

-- Hourly traffic aggregation table
CREATE TABLE IF NOT EXISTS user_usage_hourly_agg (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    hour_timestamp INT NOT NULL,
    user_id INT NOT NULL,
    node VARCHAR(255) NOT NULL,
    total_upload BIGINT NOT NULL DEFAULT 0,
    total_download BIGINT NOT NULL DEFAULT 0,
    total_traffic BIGINT NOT NULL DEFAULT 0,
    record_count INT NOT NULL DEFAULT 0,
    first_record_time INT NOT NULL,
    last_record_time INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_hourly_time (hour_timestamp),
    INDEX idx_hourly_user (user_id, hour_timestamp),
    INDEX idx_hourly_node (node, hour_timestamp),
    INDEX idx_hourly_composite (hour_timestamp, user_id, node),
    
    -- Unique constraint to prevent duplicates
    UNIQUE KEY uk_hourly_agg (hour_timestamp, user_id, node)
) ENGINE=InnoDB 
  COMMENT='Hourly aggregated traffic data for performance optimization';

-- Daily traffic aggregation table  
CREATE TABLE IF NOT EXISTS user_usage_daily_agg (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    date_key DATE NOT NULL,
    user_id INT NOT NULL,
    node VARCHAR(255) NOT NULL,
    total_upload BIGINT NOT NULL DEFAULT 0,
    total_download BIGINT NOT NULL DEFAULT 0,
    total_traffic BIGINT NOT NULL DEFAULT 0,
    record_count INT NOT NULL DEFAULT 0,
    unique_hours_active INT NOT NULL DEFAULT 0,
    first_record_time INT NOT NULL,
    last_record_time INT NOT NULL,
    peak_hour_traffic BIGINT NOT NULL DEFAULT 0,
    peak_hour INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_daily_date (date_key),
    INDEX idx_daily_user (user_id, date_key),
    INDEX idx_daily_node (node, date_key),
    INDEX idx_daily_composite (date_key, user_id, node),
    INDEX idx_daily_traffic (total_traffic DESC),
    
    -- Unique constraint to prevent duplicates
    UNIQUE KEY uk_daily_agg (date_key, user_id, node)
) ENGINE=InnoDB
  COMMENT='Daily aggregated traffic data for performance optimization';

-- User summary table for quick rankings
CREATE TABLE IF NOT EXISTS user_traffic_summary (
    user_id INT PRIMARY KEY,
    uuid VARCHAR(100),
    sid INT,
    
    -- Current period aggregations (updated by background job)
    today_upload BIGINT NOT NULL DEFAULT 0,
    today_download BIGINT NOT NULL DEFAULT 0,
    today_traffic BIGINT NOT NULL DEFAULT 0,
    
    week_upload BIGINT NOT NULL DEFAULT 0,
    week_download BIGINT NOT NULL DEFAULT 0,
    week_traffic BIGINT NOT NULL DEFAULT 0,
    
    month_upload BIGINT NOT NULL DEFAULT 0,
    month_download BIGINT NOT NULL DEFAULT 0,
    month_traffic BIGINT NOT NULL DEFAULT 0,
    
    -- Activity metrics
    total_records_today INT NOT NULL DEFAULT 0,
    total_records_week INT NOT NULL DEFAULT 0,
    total_records_month INT NOT NULL DEFAULT 0,
    
    nodes_used_today INT NOT NULL DEFAULT 0,
    nodes_used_week INT NOT NULL DEFAULT 0,
    nodes_used_month INT NOT NULL DEFAULT 0,
    
    first_activity_today INT NULL,
    last_activity_today INT NULL,
    first_activity_week INT NULL,
    last_activity_week INT NULL,
    
    -- Cache and optimization
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    aggregation_version INT NOT NULL DEFAULT 1,
    
    -- Indexes for ranking queries
    INDEX idx_summary_today_traffic (today_traffic DESC),
    INDEX idx_summary_week_traffic (week_traffic DESC),
    INDEX idx_summary_month_traffic (month_traffic DESC),
    INDEX idx_summary_uuid (uuid),
    INDEX idx_summary_sid (sid),
    INDEX idx_summary_updated (last_updated)
) ENGINE=InnoDB
  COMMENT='User traffic summary for fast ranking queries';

-- Node summary table for quick node analytics
CREATE TABLE IF NOT EXISTS node_traffic_summary (
    node_id VARCHAR(255) PRIMARY KEY,
    node_name VARCHAR(255),
    
    -- Current period aggregations
    today_upload BIGINT NOT NULL DEFAULT 0,
    today_download BIGINT NOT NULL DEFAULT 0,
    today_traffic BIGINT NOT NULL DEFAULT 0,
    
    week_upload BIGINT NOT NULL DEFAULT 0,
    week_download BIGINT NOT NULL DEFAULT 0,
    week_traffic BIGINT NOT NULL DEFAULT 0,
    
    month_upload BIGINT NOT NULL DEFAULT 0,
    month_download BIGINT NOT NULL DEFAULT 0,
    month_traffic BIGINT NOT NULL DEFAULT 0,
    
    -- User activity metrics
    unique_users_today INT NOT NULL DEFAULT 0,
    unique_users_week INT NOT NULL DEFAULT 0,
    unique_users_month INT NOT NULL DEFAULT 0,
    
    total_records_today INT NOT NULL DEFAULT 0,
    total_records_week INT NOT NULL DEFAULT 0,
    total_records_month INT NOT NULL DEFAULT 0,
    
    -- Performance metrics
    avg_traffic_per_user_today DECIMAL(15,2) NOT NULL DEFAULT 0,
    peak_concurrent_users_today INT NOT NULL DEFAULT 0,
    peak_hour_traffic_today BIGINT NOT NULL DEFAULT 0,
    
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    aggregation_version INT NOT NULL DEFAULT 1,
    
    -- Indexes for ranking queries
    INDEX idx_node_summary_today_traffic (today_traffic DESC),
    INDEX idx_node_summary_week_traffic (week_traffic DESC),
    INDEX idx_node_summary_month_traffic (month_traffic DESC),
    INDEX idx_node_summary_users_today (unique_users_today DESC),
    INDEX idx_node_summary_name (node_name),
    INDEX idx_node_summary_updated (last_updated)
) ENGINE=InnoDB
  COMMENT='Node traffic summary for fast analytics queries';

-- Aggregation status tracking table
CREATE TABLE IF NOT EXISTS aggregation_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aggregation_type ENUM('hourly', 'daily', 'user_summary', 'node_summary') NOT NULL,
    target_period VARCHAR(50) NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    records_processed INT NOT NULL DEFAULT 0,
    total_records INT NOT NULL DEFAULT 0,
    start_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_agg_status_type (aggregation_type),
    INDEX idx_agg_status_period (target_period),
    INDEX idx_agg_status_status (status),
    UNIQUE KEY uk_agg_status (aggregation_type, target_period)
) ENGINE=InnoDB
  COMMENT='Tracking table for aggregation background jobs';

-- ================================================================
-- Views for Easy Data Access
-- ================================================================

-- View for current day rankings (uses pre-aggregated data when available)
CREATE OR REPLACE VIEW v_user_rankings_today AS
SELECT 
    uts.user_id,
    uts.uuid,
    uts.sid,
    uts.today_upload as period_upload,
    uts.today_download as period_download,
    uts.today_traffic as period_traffic,
    uts.total_records_today as usage_records,
    uts.nodes_used_today as nodes_used,
    uts.first_activity_today as first_usage,
    uts.last_activity_today as last_usage,
    u.transfer_enable,
    u.enable,
    u.created_at,
    u.remark,
    COALESCE(u.speedlimitss, '') as speedlimitss,
    COALESCE(u.speedlimitother, '') as speedlimitother,
    -- Calculated fields
    (u.u + u.d) as used_traffic,
    GREATEST(0, u.transfer_enable - (u.u + u.d)) as remaining_quota,
    CASE 
        WHEN u.transfer_enable > 0 THEN ((u.u + u.d) / u.transfer_enable) * 100 
        ELSE 0 
    END as quota_utilization
FROM user_traffic_summary uts
INNER JOIN user u ON uts.user_id = u.id
WHERE uts.today_traffic > 0
ORDER BY uts.today_traffic DESC;

-- View for current day node rankings
CREATE OR REPLACE VIEW v_node_rankings_today AS
SELECT 
    nts.node_id,
    nts.node_name,
    nts.today_upload as total_upload,
    nts.today_download as total_download,
    nts.today_traffic as total_traffic,
    nts.unique_users_today as unique_users,
    nts.total_records_today as usage_records,
    nts.avg_traffic_per_user_today as avg_traffic_per_user,
    nts.peak_concurrent_users_today as peak_users,
    nts.peak_hour_traffic_today as peak_hour_traffic,
    n.address,
    n.enable,
    n.statistics,
    n.max_traffic,
    n.last_online,
    n.country,
    COALESCE(n.type, '') as type,
    COALESCE(n.excessive_speed_limit, '') as excessive_speed_limit,
    COALESCE(n.speed_limit, '') as speed_limit,
    -- Calculated fields
    CASE 
        WHEN n.max_traffic > 0 THEN (nts.today_traffic / (n.max_traffic * 1000000000)) * 100 
        ELSE 0 
    END as traffic_utilization,
    (UNIX_TIMESTAMP() - n.last_online < 300) as is_online,
    (UNIX_TIMESTAMP() - n.last_online) as last_seen
FROM node_traffic_summary nts
LEFT JOIN node n ON nts.node_id = n.id OR nts.node_id = n.name
ORDER BY nts.today_traffic DESC;

-- ================================================================
-- Stored Procedures for Aggregation (Optional - for automation)
-- ================================================================

DELIMITER //

-- Procedure to aggregate hourly data
CREATE PROCEDURE IF NOT EXISTS sp_aggregate_hourly_data(IN target_hour INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        UPDATE aggregation_status 
        SET status = 'failed', 
            error_message = 'SQL Exception during hourly aggregation',
            end_time = NOW()
        WHERE aggregation_type = 'hourly' AND target_period = target_hour;
    END;

    START TRANSACTION;
    
    -- Update aggregation status
    INSERT INTO aggregation_status (aggregation_type, target_period, status, start_time)
    VALUES ('hourly', target_hour, 'running', NOW())
    ON DUPLICATE KEY UPDATE 
        status = 'running', 
        start_time = NOW(),
        records_processed = 0,
        error_message = NULL;

    -- Perform hourly aggregation
    INSERT INTO user_usage_hourly_agg (
        hour_timestamp, user_id, node, 
        total_upload, total_download, total_traffic,
        record_count, first_record_time, last_record_time
    )
    SELECT 
        target_hour as hour_timestamp,
        user_id,
        node,
        SUM(u) as total_upload,
        SUM(d) as total_download,
        SUM(u + d) as total_traffic,
        COUNT(*) as record_count,
        MIN(t) as first_record_time,
        MAX(t) as last_record_time
    FROM user_usage
    WHERE t >= target_hour AND t < (target_hour + 3600)
        AND node != 'DAY'
    GROUP BY user_id, node
    ON DUPLICATE KEY UPDATE
        total_upload = VALUES(total_upload),
        total_download = VALUES(total_download),
        total_traffic = VALUES(total_traffic),
        record_count = VALUES(record_count),
        first_record_time = VALUES(first_record_time),
        last_record_time = VALUES(last_record_time),
        updated_at = NOW();

    -- Update completion status
    UPDATE aggregation_status 
    SET status = 'completed', 
        end_time = NOW(),
        records_processed = ROW_COUNT()
    WHERE aggregation_type = 'hourly' AND target_period = target_hour;

    COMMIT;
END //

-- Procedure to aggregate daily data
CREATE PROCEDURE IF NOT EXISTS sp_aggregate_daily_data(IN target_date DATE)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        UPDATE aggregation_status 
        SET status = 'failed', 
            error_message = 'SQL Exception during daily aggregation',
            end_time = NOW()
        WHERE aggregation_type = 'daily' AND target_period = target_date;
    END;

    START TRANSACTION;
    
    -- Update aggregation status
    INSERT INTO aggregation_status (aggregation_type, target_period, status, start_time)
    VALUES ('daily', target_date, 'running', NOW())
    ON DUPLICATE KEY UPDATE 
        status = 'running', 
        start_time = NOW(),
        records_processed = 0,
        error_message = NULL;

    -- Perform daily aggregation from hourly data
    INSERT INTO user_usage_daily_agg (
        date_key, user_id, node,
        total_upload, total_download, total_traffic,
        record_count, unique_hours_active,
        first_record_time, last_record_time,
        peak_hour_traffic, peak_hour
    )
    SELECT 
        target_date,
        user_id,
        node,
        SUM(total_upload),
        SUM(total_download),
        SUM(total_traffic),
        SUM(record_count),
        COUNT(DISTINCT hour_timestamp),
        MIN(first_record_time),
        MAX(last_record_time),
        MAX(total_traffic),
        HOUR(FROM_UNIXTIME(hour_timestamp))
    FROM user_usage_hourly_agg
    WHERE DATE(FROM_UNIXTIME(hour_timestamp)) = target_date
    GROUP BY user_id, node
    ON DUPLICATE KEY UPDATE
        total_upload = VALUES(total_upload),
        total_download = VALUES(total_download),
        total_traffic = VALUES(total_traffic),
        record_count = VALUES(record_count),
        unique_hours_active = VALUES(unique_hours_active),
        first_record_time = VALUES(first_record_time),
        last_record_time = VALUES(last_record_time),
        peak_hour_traffic = VALUES(peak_hour_traffic),
        peak_hour = VALUES(peak_hour),
        updated_at = NOW();

    -- Update completion status
    UPDATE aggregation_status 
    SET status = 'completed', 
        end_time = NOW(),
        records_processed = ROW_COUNT()
    WHERE aggregation_type = 'daily' AND target_period = target_date;

    COMMIT;
END //

DELIMITER ;

-- ================================================================
-- Initial Data Population (Run once after table creation)
-- ================================================================

-- Populate aggregation status for current day
INSERT IGNORE INTO aggregation_status (aggregation_type, target_period, status)
VALUES 
    ('user_summary', 'current', 'pending'),
    ('node_summary', 'current', 'pending'),
    ('hourly', DATE_FORMAT(NOW(), '%Y-%m-%d'), 'pending'),
    ('daily', DATE_FORMAT(NOW(), '%Y-%m-%d'), 'pending');

-- ================================================================
-- Usage Notes and Recommendations
-- ================================================================

/*
AGGREGATION STRATEGY:
1. Raw data goes to user_usage table (real-time)
2. Hourly aggregation runs every hour (user_usage_hourly_agg)
3. Daily aggregation runs once per day (user_usage_daily_agg)
4. Summary tables updated multiple times per day for rankings

PERFORMANCE BENEFITS:
- User rankings: Query summary table instead of scanning millions of records
- Historical analysis: Use daily/hourly aggregated data
- Real-time data: Still available from user_usage table
- Memory efficiency: Smaller result sets for large time ranges

MAINTENANCE:
1. Run hourly aggregation: CALL sp_aggregate_hourly_data(UNIX_TIMESTAMP(DATE_FORMAT(NOW(), '%Y-%m-%d %H:00:00')));
2. Run daily aggregation: CALL sp_aggregate_daily_data(CURDATE());
3. Monitor aggregation_status table for failures
4. Cleanup old aggregated data periodically (>90 days)

QUERY OPTIMIZATION:
- Use v_user_rankings_today for today's user rankings
- Use v_node_rankings_today for today's node rankings
- Query aggregated tables for historical data analysis
- Fall back to raw user_usage table only when needed

BACKUP CONSIDERATIONS:
- Aggregated tables can be rebuilt from raw data
- Focus backup/replication on user_usage table
- Aggregated tables are optimization, not critical data
*/