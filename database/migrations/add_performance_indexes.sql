-- ================================================================
-- Performance Indexes for Large-Scale Data Optimization
-- Designed to handle 300k-500k traffic records efficiently
-- ================================================================

-- Core index for timestamp-based queries (most common operation)
CREATE INDEX IF NOT EXISTS idx_user_usage_timestamp ON user_usage(t);

-- Composite index for user-based queries with time filtering  
CREATE INDEX IF NOT EXISTS idx_user_usage_user_time ON user_usage(user_id, t);

-- Composite index for node-based queries with time filtering
CREATE INDEX IF NOT EXISTS idx_user_usage_node_time ON user_usage(node, t);

-- Composite index for user-node-time queries (covering common JOIN patterns)
CREATE INDEX IF NOT EXISTS idx_user_usage_user_node_time ON user_usage(user_id, node, t);

-- Covering index for traffic aggregation queries (avoids table lookups)
CREATE INDEX IF NOT EXISTS idx_user_usage_traffic_agg ON user_usage(t, user_id, node, u, d);

-- Index for node filtering (supports both ID and name lookups)
CREATE INDEX IF NOT EXISTS idx_user_usage_node ON user_usage(node);

-- Index for user table lookups by UUID
CREATE INDEX IF NOT EXISTS idx_user_uuid ON user(uuid);

-- Index for user table lookups by service ID (sid)
CREATE INDEX IF NOT EXISTS idx_user_sid ON user(sid);

-- Composite index for user table with transfer limits (for quota calculations)
CREATE INDEX IF NOT EXISTS idx_user_transfer_calc ON user(id, transfer_enable, u, d);

-- Index for node table lookups by name (for node matching)
CREATE INDEX IF NOT EXISTS idx_node_name ON node(name);

-- Composite index for node statistics queries
CREATE INDEX IF NOT EXISTS idx_node_stats ON node(id, enable, statistics, max_traffic);

-- ================================================================
-- Specialized Indexes for Time-Range Queries
-- ================================================================

-- Index for today's traffic queries (optimized for daily aggregations)
CREATE INDEX IF NOT EXISTS idx_user_usage_today ON user_usage(t, user_id) 
WHERE t >= UNIX_TIMESTAMP(CURDATE());

-- Index for recent activity queries (last 24 hours)
CREATE INDEX IF NOT EXISTS idx_user_usage_recent ON user_usage(t DESC, user_id, u, d)
WHERE t >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR));

-- ================================================================
-- Covering Indexes for Common Ranking Queries
-- ================================================================

-- Covering index for user traffic rankings (includes all needed columns)
CREATE INDEX IF NOT EXISTS idx_user_ranking_coverage ON user_usage(user_id, t, u, d, node);

-- Covering index for node traffic rankings (includes all needed columns)  
CREATE INDEX IF NOT EXISTS idx_node_ranking_coverage ON user_usage(node, t, user_id, u, d);

-- ================================================================
-- Optimize Existing Tables for Better Performance
-- ================================================================

-- Analyze tables to update statistics for query optimization
ANALYZE TABLE user_usage;
ANALYZE TABLE user;
ANALYZE TABLE node;

-- ================================================================
-- Index Usage Monitoring (Optional - for performance analysis)
-- ================================================================

-- Create a simple view to monitor index usage
CREATE OR REPLACE VIEW v_index_usage_stats AS
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    CARDINALITY,
    COMMENT
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN ('user_usage', 'user', 'node')
    AND INDEX_NAME LIKE 'idx_%'
ORDER BY TABLE_NAME, INDEX_NAME;

-- ================================================================
-- Performance Recommendations
-- ================================================================

/*
NOTES:
1. These indexes are designed for read-heavy workloads typical of traffic analysis
2. Write performance may be slightly impacted due to index maintenance
3. Monitor index usage with: SELECT * FROM v_index_usage_stats;
4. Consider PARTITION BY RANGE(t) for user_usage table if growth continues
5. Regularly run OPTIMIZE TABLE user_usage; for large datasets

QUERY OPTIMIZATION PATTERNS:
- Always include timestamp (t) in WHERE clauses when possible
- Use composite indexes in the order: (most_selective, time, other_columns)
- Prefer LIMIT with ORDER BY for pagination over OFFSET
- Use covering indexes to avoid table lookups for aggregation queries

MAINTENANCE:
- Monitor query performance with EXPLAIN ANALYZE
- Update table statistics regularly: ANALYZE TABLE table_name;
- Consider index consolidation if too many indexes impact write performance
*/