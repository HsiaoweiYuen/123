# Database Index Optimization for V2RaySocks Traffic Analysis

## Recommended Indexes for Handling Millions of Records

To support optimal performance when handling millions or tens of millions of records, the following database indexes are recommended:

### Primary Tables

#### user_usage table (Main traffic data)
```sql
-- Primary index on timestamp for time-based queries
CREATE INDEX idx_user_usage_time ON user_usage (t);

-- Composite index for user-based queries with time filtering
CREATE INDEX idx_user_usage_user_time ON user_usage (user_id, t);

-- Composite index for node-based queries with time filtering  
CREATE INDEX idx_user_usage_node_time ON user_usage (node, t);

-- Composite index for pagination optimization
CREATE INDEX idx_user_usage_time_id ON user_usage (t DESC, id);

-- Index for aggregation queries
CREATE INDEX idx_user_usage_user_node_time ON user_usage (user_id, node, t);
```

#### user table (User information)
```sql
-- Index on service ID for service-based filtering
CREATE INDEX idx_user_sid ON user (sid);

-- Index on UUID for UUID-based searches
CREATE INDEX idx_user_uuid ON user (uuid);

-- Composite index for quota and enable status
CREATE INDEX idx_user_quota_enable ON user (enable, transfer_enable);

-- Index on creation date for user analytics
CREATE INDEX idx_user_created_at ON user (created_at);
```

#### node table (Node information)
```sql
-- Index on node name for node-based queries
CREATE INDEX idx_node_name ON node (name);

-- Index on node status and address
CREATE INDEX idx_node_status ON node (status, address);
```

### Performance Optimization Queries

#### For Traffic Data Retrieval
```sql
-- Optimize time range queries
ALTER TABLE user_usage ADD INDEX idx_time_range (t, user_id, u, d);

-- Optimize user ranking calculations
ALTER TABLE user_usage ADD INDEX idx_ranking_calc (user_id, t, u, d);
```

#### For Export Operations
```sql
-- Optimize large exports with consistent ordering
ALTER TABLE user_usage ADD INDEX idx_export_order (t DESC, user_id, node);
```

### Table Partitioning (For Very Large Datasets)

For systems with tens of millions of records, consider partitioning the user_usage table by time:

```sql
-- Example monthly partitioning (MySQL 8.0+)
ALTER TABLE user_usage 
PARTITION BY RANGE (UNIX_TIMESTAMP(FROM_UNIXTIME(t))) (
    PARTITION p202401 VALUES LESS THAN (UNIX_TIMESTAMP('2024-02-01')),
    PARTITION p202402 VALUES LESS THAN (UNIX_TIMESTAMP('2024-03-01')),
    -- Add more partitions as needed
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### Memory Optimization

#### InnoDB Settings (my.cnf)
```ini
# Increase buffer pool for large datasets
innodb_buffer_pool_size = 2G  # Adjust based on available RAM

# Optimize for read-heavy workloads
innodb_read_ahead_threshold = 0

# Increase log file size for large transactions
innodb_log_file_size = 512M

# Optimize query cache for repetitive queries
query_cache_size = 128M
query_cache_type = 1
```

### Query Optimization Guidelines

1. **Always use LIMIT with OFFSET** for pagination
2. **Include ORDER BY with indexed columns** for consistent results
3. **Use composite indexes** for complex WHERE clauses
4. **Avoid SELECT *** on large tables, specify needed columns
5. **Use prepared statements** to improve query plan caching

### Monitoring and Maintenance

#### Regular Maintenance Tasks
```sql
-- Analyze table statistics weekly
ANALYZE TABLE user_usage, user, node;

-- Optimize tables monthly
OPTIMIZE TABLE user_usage, user, node;

-- Check index usage
SHOW INDEX FROM user_usage;
SELECT * FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'user_usage' AND TABLE_SCHEMA = 'your_database';
```

#### Performance Monitoring
```sql
-- Monitor slow queries
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Check query performance
EXPLAIN SELECT * FROM user_usage WHERE t >= ? AND t <= ? ORDER BY t DESC LIMIT 50 OFFSET 0;
```

### Implementation Priority

1. **High Priority**: `idx_user_usage_time`, `idx_user_usage_user_time`
2. **Medium Priority**: `idx_user_sid`, `idx_user_usage_node_time`  
3. **Low Priority**: Table partitioning (only for 50M+ records)

These optimizations will significantly improve performance when handling large datasets and enable the pagination system to work efficiently with millions of records.