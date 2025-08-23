# V2RaySocks Traffic Analysis - 用户流量测试数据生成器

## 概述

本项目为 `user_usage` 表生成200万条随机测试数据，用于验证大数据量下的查询性能和统计准确性。

## 📋 需求规格

### 目标表结构
```sql
CREATE TABLE `user_usage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `t` int NOT NULL,
  `u` bigint NOT NULL,
  `d` bigint NOT NULL,
  `node` varchar(50) NOT NULL,
  `count_rate` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 数据规格
- **记录数量**: 2,000,000 条
- **用户ID**: 18个现有用户 (14,16,18,22,24,25,26,27,32,34,40,42,43,44,45,46,47,48)
- **节点名称**: 4个节点
  - 🇩🇪 Germany 01
  - 🇯🇵 Japan 01  
  - 🇺🇸 United States 01
  - 🇯🇵 Japan 02
- **时间范围**: 2023-01-01 到 2025-08-23 (Unix时间戳)
- **上传流量(u)**: 10KB 到 1GB (10,000 到 1,000,000,000 字节)
- **下载流量(d)**: 100KB 到 50GB (100,000 到 50,000,000,000 字节)
- **倍率(count_rate)**: 0.5 到 2.0

## 📁 文件说明

### 1. `generate_user_usage_test_data.sql`
主要的SQL脚本文件，包含：
- **GenerateUserUsageTestData()**: 生成200万条记录的存储过程
- **ValidateUserUsageTestData()**: 数据验证存储过程
- **AnalyzeUserUsagePerformance()**: 性能分析存储过程

### 2. `test_data_generator.sql` 
测试版本，生成1000条记录用于验证逻辑正确性

### 3. `USER_USAGE_TEST_DATA_README.md`
详细说明文档（本文件）

## 🚀 执行步骤

### 步骤1: 测试验证（推荐）
```sql
-- 1. 导入测试脚本
SOURCE test_data_generator.sql;

-- 2. 执行测试生成（1000条记录）
CALL GenerateUserUsageTestDataSmall();

-- 3. 验证测试数据
CALL ValidateTestData();

-- 4. 清理测试数据
DROP TABLE user_usage_test;
```

### 步骤2: 生产环境执行
```sql
-- 1. 导入主脚本
SOURCE generate_user_usage_test_data.sql;

-- 2. 生成200万条记录（预计10-30分钟）
CALL GenerateUserUsageTestData();

-- 3. 验证生成的数据
CALL ValidateUserUsageTestData();

-- 4. 性能分析
CALL AnalyzeUserUsagePerformance();
```

## ⚡ 性能优化特性

### 批量处理
- 每10,000条记录提交一次事务
- 避免长时间锁表和内存溢出
- 实时进度报告

### 随机数据生成
- 使用临时表存储用户ID和节点名称
- 高效的随机选择算法
- 符合真实数据分布模式

### 错误处理
- 完整的异常捕获和回滚机制
- 详细的错误信息报告
- 事务安全保证

## 📊 验证功能

### 数据完整性验证
- ✅ 总记录数检查
- ✅ 用户ID分布统计
- ✅ 节点分布统计
- ✅ 时间范围验证
- ✅ 流量数据范围检查
- ✅ 倍率数据验证

### 性能分析
- 📈 表大小统计
- 📈 索引建议
- 📈 查询性能测试
- 📈 最近活动统计

## 💾 系统要求

### 硬件要求
- **磁盘空间**: 500MB - 1GB
- **内存**: 至少2GB可用内存
- **CPU**: 建议多核处理器

### 软件要求
- **MySQL版本**: 8.0+ 
- **存储引擎**: InnoDB
- **字符集**: utf8mb4

### 配置建议
```sql
-- 临时增加批量插入性能
SET SESSION bulk_insert_buffer_size = 256*1024*1024;
SET SESSION innodb_buffer_pool_size = 1G;  -- 如果可能
```

## 📈 预期执行结果

### 执行时间
- **测试版本(1000条)**: < 1分钟
- **完整版本(200万条)**: 10-30分钟

### 数据分布
- 每个用户ID: ~111,111条记录 (均匀分布)
- 每个节点: ~500,000条记录 (均匀分布)
- 时间分布: 965天范围内均匀分布

### 存储占用
- 表数据: ~400-600MB
- 索引数据: ~100-200MB
- 总计: ~500-800MB

## 🛠️ 故障排除

### 常见问题

#### 1. 执行超时
```sql
-- 增加执行超时时间
SET SESSION wait_timeout = 28800;
SET SESSION interactive_timeout = 28800;
```

#### 2. 内存不足
```sql
-- 减少批处理大小
-- 修改存储过程中的 batch_size 变量为更小值（如5000）
```

#### 3. 磁盘空间不足
- 检查可用磁盘空间：`SHOW VARIABLES LIKE 'datadir';`
- 清理临时文件和日志

#### 4. 权限问题
```sql
-- 确保用户有足够权限
GRANT INSERT, CREATE, DROP ON database_name.* TO 'username'@'localhost';
GRANT CREATE TEMPORARY TABLES ON database_name.* TO 'username'@'localhost';
```

### 监控执行进度
```sql
-- 查看当前进程
SHOW PROCESSLIST;

-- 查看表记录数
SELECT COUNT(*) FROM user_usage;

-- 查看最新插入的记录
SELECT * FROM user_usage ORDER BY id DESC LIMIT 10;
```

## 📋 索引建议

生成数据后，建议创建以下索引以提升查询性能：

```sql
-- 用户和时间复合索引
CREATE INDEX idx_user_usage_user_id_t ON user_usage(user_id, t);

-- 节点和时间复合索引  
CREATE INDEX idx_user_usage_node_t ON user_usage(node, t);

-- 时间索引
CREATE INDEX idx_user_usage_t ON user_usage(t);

-- 用户索引
CREATE INDEX idx_user_usage_user_id ON user_usage(user_id);
```

## 🔍 数据验证查询示例

```sql
-- 检查数据分布
SELECT 
    user_id,
    COUNT(*) as record_count,
    MIN(FROM_UNIXTIME(t)) as earliest_time,
    MAX(FROM_UNIXTIME(t)) as latest_time
FROM user_usage 
GROUP BY user_id 
ORDER BY user_id;

-- 检查流量统计
SELECT 
    node,
    COUNT(*) as records,
    AVG(u) as avg_upload,
    AVG(d) as avg_download,
    SUM(u + d) as total_traffic
FROM user_usage 
GROUP BY node;

-- 检查时间分布
SELECT 
    DATE(FROM_UNIXTIME(t)) as date,
    COUNT(*) as daily_records
FROM user_usage 
GROUP BY DATE(FROM_UNIXTIME(t))
ORDER BY date 
LIMIT 10;
```

## 📞 支持

如遇到问题，请检查：
1. MySQL错误日志
2. 系统资源使用情况
3. 权限配置
4. 数据库配置参数

## 🎯 使用场景

生成的测试数据可用于：
- 🔍 流量分析算法验证
- 📊 大数据量查询性能测试
- 📈 统计报表准确性验证
- 🚀 系统负载测试
- 🛠️ 索引优化策略测试

---

**注意**: 在生产环境执行前，请先在测试环境验证脚本功能，并确保有足够的系统资源和数据备份。