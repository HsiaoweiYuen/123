# Redis缓存优化功能说明

## 概述
本次优化实现了对Redis缓存系统的全面改进，旨在降低内存碎片率、提高缓存命中率和优化内存使用效率。

## 新增优化功能

### 1. 数据存储结构优化

#### Hash结构存储
- **功能**: 对于复杂对象自动使用Redis Hash结构替代JSON字符串存储
- **触发条件**: 
  - 明确指定 `use_hash => true`
  - 自动检测：JSON对象大于500字节且包含多个字段
- **优势**: 减少内存碎片，支持字段级别操作

#### 数据压缩
- **功能**: 自动压缩大型数据以减少内存占用
- **触发条件**: 数据大小超过1024字节
- **压缩率要求**: 至少20%的空间节省才会使用压缩
- **算法**: gzip压缩（级别6），平衡压缩率和性能

#### 优化的key命名
- **结构**: `v2raysocks_traffic:v1:原始key`
- **Hash数据**: `原始key:hash`
- **元数据**: `原始key:meta`

### 2. 批量操作支持

#### Pipeline批量设置
```php
$items = [
    ['key' => 'key1', 'value' => 'value1', 'ttl' => 60],
    ['key' => 'key2', 'value' => 'value2', 'ttl' => 120]
];
v2raysocks_traffic_batchSet($items);
```

#### Pipeline批量获取
```php
$keys = ['key1', 'key2', 'key3'];
$results = v2raysocks_traffic_batchGet($keys);
```

### 3. 内存管理优化

#### 内存碎片检测
```php
$memoryInfo = v2raysocks_traffic_getMemoryInfo();
// 返回fragmentation_ratio和status
```

#### 主动内存整理
```php
$success = v2raysocks_traffic_defragMemory();
```

#### 连接池优化
- 保持单例Redis连接
- 自动重连机制
- 连接失败延迟重试

### 4. 缓存策略优化

#### 缓存预热
```php
$warmConfig = [
    'cache_key' => [
        'generator' => function() { return getData(); },
        'ttl' => 300
    ]
];
v2raysocks_traffic_warmCache($warmConfig);
```

#### 缓存穿透保护
```php
$data = v2raysocks_traffic_getCacheWithFallback('key', null, [
    'generator' => function() { return generateData(); },
    'ttl' => 300
]);
```

#### 智能TTL策略
- 实时数据：60秒
- 配置数据：600秒
- 流量数据：120-300秒（根据时间范围动态调整）
- 图表数据：120-180秒
- 排行数据：180-300秒

### 5. 性能监控增强

#### 增强统计信息
```php
$stats = v2raysocks_traffic_getEnhancedCacheStats();
```
返回数据包括：
- 基础统计（命中率、请求数）
- 内存信息（碎片率、使用量）
- 优化指标（压缩率、优化启用状态）

#### 新API端点
- `/path/to/module?action=memory_info` - 获取内存优化信息
- `/path/to/module?action=cache_stats` - 获取增强缓存统计

## 新增函数接口

### 核心优化函数
- `v2raysocks_traffic_setOptimizedCache()` - 智能缓存设置
- `v2raysocks_traffic_batchSet()` - 批量设置
- `v2raysocks_traffic_batchGet()` - 批量获取
- `v2raysocks_traffic_getMemoryInfo()` - 内存信息
- `v2raysocks_traffic_defragMemory()` - 内存整理
- `v2raysocks_traffic_warmCache()` - 缓存预热
- `v2raysocks_traffic_getEnhancedCacheStats()` - 增强统计

### 向后兼容性
所有现有函数保持兼容：
- `v2raysocks_traffic_redisOperate()` - 增强但兼容
- `v2raysocks_traffic_setCacheWithTTL()` - 功能增强
- `v2raysocks_traffic_getCacheWithFallback()` - 添加穿透保护
- `v2raysocks_traffic_getCacheStats()` - 自动使用增强版本

## 优化效果

### 预期性能提升
1. **内存碎片率降低**: 通过Hash结构和压缩减少30-50%
2. **缓存命中率提升**: 批量操作和预热机制提升10-20%
3. **内存使用效率**: 压缩大数据节省20-40%空间
4. **网络延迟降低**: 批量操作减少50-80%往返次数

### 监控指标
- 内存碎片率状态：low/moderate/high
- 压缩节省率：显示压缩操作的效果
- 批量操作成功率
- 缓存预热覆盖率

## 使用建议

1. **大数据对象**: 使用 `prefer_hash => true` 启用Hash结构
2. **频繁访问数据**: 配置缓存预热
3. **批量操作**: 优先使用batch函数减少网络开销
4. **监控内存**: 定期检查fragmentation_ratio
5. **适时整理**: 当碎片率>1.5时考虑执行defrag

## 安全考虑

- 所有优化功能都有完善的错误处理
- 保持TTL策略不变，确保数据时效性
- 自动降级机制，优化失败时使用原始方法
- 压缩数据完整性验证