# 执行示例和验证结果

## 快速开始

### 1. 测试版本执行（推荐先运行）
```sql
-- 导入测试脚本
SOURCE test_data_generator.sql;

-- 生成1000条测试记录
CALL GenerateUserUsageTestDataSmall();

-- 验证测试数据
CALL ValidateTestData();

-- 清理测试数据
DROP TABLE user_usage_test;
```

### 2. 完整版本执行
```sql
-- 导入主脚本
SOURCE generate_user_usage_test_data.sql;

-- 生成200万条记录
CALL GenerateUserUsageTestData();

-- 验证生成的数据
CALL ValidateUserUsageTestData();

-- 性能分析
CALL AnalyzeUserUsagePerformance();
```

## 预期输出示例

### 数据生成进度
```
+-------------------------------------------+
| status                                    |
+-------------------------------------------+
| Starting generation of 2000000 records...| 
+-------------------------------------------+

+------------------------------------------+
| progress_status                          |
+------------------------------------------+
| Progress: 10000/2000000 (0.50%) - Elapsed: 15s |
+------------------------------------------+

+------------------------------------------+
| progress_status                          |
+------------------------------------------+
| Progress: 20000/2000000 (1.00%) - Elapsed: 30s |
+------------------------------------------+

... (继续直到100%)

+---------------------------------------+
| final_status                          |
+---------------------------------------+
| Successfully generated 2000000 records! |
+---------------------------------------+
```

### 数据验证结果示例
```sql
-- 用户ID分布
+--------+--------------+------------+
| user_id| record_count | percentage |
+--------+--------------+------------+
|    14  |    111111    |    5.56    |
|    16  |    111111    |    5.56    |
|    18  |    111111    |    5.56    |
|    22  |    111111    |    5.56    |
... (共18个用户，每个约11万条记录)

-- 节点分布  
+--------------------+--------------+------------+
| node               | record_count | percentage |
+--------------------+--------------+------------+
| 🇩🇪 Germany 01     |    500000    |   25.00    |
| 🇯🇵 Japan 01       |    500000    |   25.00    |
| 🇺🇸 United States 01|   500000    |   25.00    |
| 🇯🇵 Japan 02       |    500000    |   25.00    |
+--------------------+--------------+------------+

-- 流量数据验证
+--------------+------------+---------------+
| traffic_type | min_bytes  | max_bytes     |
+--------------+------------+---------------+
| Upload (u)   |   10000    |  999999999    |
| Download (d) |  100000    | 49999999999   |
+--------------+------------+---------------+
```

## 性能基准

### 预期执行时间
- **测试版本 (1,000条)**: < 1分钟
- **完整版本 (2,000,000条)**: 10-30分钟

### 资源使用
- **CPU使用率**: 20-50%
- **内存使用**: 100-500MB
- **磁盘I/O**: 中等负载
- **存储空间**: ~500-800MB

### 优化建议
```sql
-- 执行前设置优化参数
SET SESSION bulk_insert_buffer_size = 256*1024*1024;
SET SESSION innodb_buffer_pool_size = 1073741824;  -- 1GB
SET SESSION wait_timeout = 28800;
SET SESSION interactive_timeout = 28800;

-- 执行后创建索引
CREATE INDEX idx_user_usage_user_id_t ON user_usage(user_id, t);
CREATE INDEX idx_user_usage_node_t ON user_usage(node, t);
CREATE INDEX idx_user_usage_t ON user_usage(t);
```

## 常用查询示例

### 1. 用户流量统计
```sql
SELECT 
    user_id,
    COUNT(*) as total_records,
    SUM(u) as total_upload,
    SUM(d) as total_download,
    SUM(u + d) as total_traffic,
    AVG(count_rate) as avg_rate
FROM user_usage 
GROUP BY user_id 
ORDER BY total_traffic DESC;
```

### 2. 节点流量分析
```sql
SELECT 
    node,
    COUNT(*) as records,
    COUNT(DISTINCT user_id) as unique_users,
    SUM(u) as total_upload,
    SUM(d) as total_download,
    AVG(u + d) as avg_traffic_per_record
FROM user_usage 
GROUP BY node 
ORDER BY total_upload + total_download DESC;
```

### 3. 时间段分析
```sql
SELECT 
    DATE(FROM_UNIXTIME(t)) as date,
    COUNT(*) as daily_records,
    SUM(u + d) as daily_traffic,
    COUNT(DISTINCT user_id) as active_users
FROM user_usage 
GROUP BY DATE(FROM_UNIXTIME(t))
ORDER BY date 
LIMIT 10;
```

### 4. 高流量用户识别
```sql
SELECT 
    user_id,
    SUM(u + d) as total_traffic,
    AVG(u + d) as avg_traffic_per_session,
    COUNT(*) as session_count
FROM user_usage 
GROUP BY user_id 
HAVING total_traffic > 100000000000  -- > 100GB
ORDER BY total_traffic DESC;
```

## 故障排除示例

### 问题1：执行超时
```sql
-- 查看当前执行状态
SHOW PROCESSLIST;

-- 增加超时时间
SET SESSION wait_timeout = 86400;  -- 24小时
```

### 问题2：磁盘空间不足
```bash
# 检查磁盘使用
df -h

# 查看MySQL数据目录
SHOW VARIABLES LIKE 'datadir';

# 清理临时文件
rm -f /tmp/mysql-*
```

### 问题3：内存不足
```sql
-- 减少批处理大小（修改存储过程）
-- 将 batch_size 从 10000 改为 5000

-- 或者分多次执行较小的批次
CALL GenerateUserUsageTestDataSmall();  -- 多次调用
```

## 数据质量检查

### 完整性检查
```sql
-- 检查是否有重复记录
SELECT id, COUNT(*) 
FROM user_usage 
GROUP BY user_id, t, u, d, node 
HAVING COUNT(*) > 1;

-- 检查数据范围
SELECT 
    MIN(u) as min_upload,
    MAX(u) as max_upload,
    MIN(d) as min_download, 
    MAX(d) as max_download,
    MIN(count_rate) as min_rate,
    MAX(count_rate) as max_rate
FROM user_usage;
```

### 业务逻辑检查
```sql
-- 检查异常大的流量值
SELECT * FROM user_usage 
WHERE u > 10000000000 OR d > 100000000000  -- > 10GB upload or > 100GB download
LIMIT 10;

-- 检查异常的时间戳
SELECT * FROM user_usage 
WHERE t < 1672531200 OR t > 1755907200  -- 超出预期时间范围
LIMIT 10;
```

---

**提示**: 建议在生产环境执行前，先在测试环境完整测试一遍，确保系统资源充足且备份完整。