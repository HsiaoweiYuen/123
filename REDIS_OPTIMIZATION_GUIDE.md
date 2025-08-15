# Redis Cache Performance Optimization Guide

This guide provides Redis configuration recommendations to achieve optimal performance with the V2RaySocks Traffic Monitor cache optimization features.

## Target Performance Goals

- **Memory Fragmentation**: Reduce from 1.5-2.0 to 1.1-1.3
- **Cache Hit Rate**: Improve by 15-25%
- **Memory Usage**: Reduce by 10-20%
- **Response Time**: Improve cache hit response by 20-30%

## Recommended Redis Configuration

### redis.conf Settings

```ini
# Memory Management
maxmemory 2gb
maxmemory-policy allkeys-lru
maxmemory-samples 5

# Reduce Memory Fragmentation
hash-max-ziplist-entries 512
hash-max-ziplist-value 64
list-max-ziplist-size -2
list-compress-depth 0
set-max-intset-entries 512
zset-max-ziplist-entries 128
zset-max-ziplist-value 64

# Performance Optimization
tcp-keepalive 300
timeout 0
tcp-backlog 511

# Pipeline Optimization
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit replica 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60

# Persistence (adjust based on your needs)
save 900 1
save 300 10
save 60 10000

# Memory Usage Optimization
stop-writes-on-bgsave-error no
rdbcompression yes
rdbchecksum yes

# Log Level (adjust for production)
loglevel notice

# Network
bind 127.0.0.1
port 6379
```

### System-Level Optimizations

#### Linux Kernel Parameters

Add to `/etc/sysctl.conf`:

```ini
# Memory overcommit
vm.overcommit_memory = 1

# Transparent Huge Pages (disable for Redis)
echo never > /sys/kernel/mm/transparent_hugepage/enabled
echo never > /sys/kernel/mm/transparent_hugepage/defrag

# TCP settings
net.core.somaxconn = 65535
net.ipv4.tcp_max_syn_backlog = 65535
```

#### Process Limits

Add to `/etc/security/limits.conf`:

```
redis soft nofile 65535
redis hard nofile 65535
```

## Cache Optimization Features Usage

### 1. Enable Cache Optimization

In WHMCS Admin -> Addon Modules -> V2RaySocks Traffic -> Configure:

- **Enable Cache Optimization**: Yes
- **Auto Prewarm Cache**: Yes (for production)
- **Memory Fragmentation Monitoring**: Yes

### 2. Performance Monitoring

Access the Performance Dashboard through:
- Module Navigation: "Performance Dashboard"
- Direct URL: `addonmodules.php?module=v2raysocks_traffic&action=performance_dashboard`

### 3. Optimization Actions

#### Automatic Cache Prewarming

The system can automatically prewarm frequently accessed data:

```php
// Programmatic prewarming
v2raysocks_traffic_prewarmCache(['live_stats', 'all_nodes', 'server_config']);
```

#### Smart Cache Clearing

Use smart cache clearing to protect important configuration data:

```php
// Clear volatile data only
v2raysocks_traffic_smartCacheClear('selective');

// More aggressive clearing
v2raysocks_traffic_smartCacheClear('aggressive');
```

#### Batch Operations

Reduce memory fragmentation with batch operations:

```php
$operations = [
    [
        'key' => 'user_stats_' . $userId,
        'value' => json_encode($userData),
        'context' => ['access_frequency' => 'high', 'priority' => 'normal']
    ],
    // ... more operations
];

v2raysocks_traffic_batchCacheOperations($operations);
```

## Monitoring and Maintenance

### 1. Memory Fragmentation Monitoring

Check fragmentation status:

- **Excellent**: < 1.3 (green)
- **Good**: 1.3-1.5 (blue)
- **Warning**: 1.5-2.0 (yellow)
- **Critical**: > 2.0 (red)

### 2. Regular Maintenance Tasks

#### Daily Tasks

1. Monitor memory fragmentation ratio
2. Check cache hit rates
3. Review memory usage trends

#### Weekly Tasks

1. Analyze cache key patterns
2. Optimize TTL settings based on access patterns
3. Review and cleanup unused cache patterns

#### Monthly Tasks

1. Review Redis configuration based on usage patterns
2. Update cache optimization strategies
3. Performance baseline analysis

### 3. Performance Metrics

Monitor these key metrics:

- **Cache Hit Rate**: Target > 80%
- **Memory Fragmentation**: Target < 1.3
- **Memory Usage**: Monitor against maxmemory setting
- **Response Time**: Track cache operation latency

## Troubleshooting

### High Memory Fragmentation

1. Enable automatic defragmentation (Redis 4.0+):
   ```
   config set activedefrag yes
   config set active-defrag-ignore-bytes 100mb
   config set active-defrag-threshold-lower 10
   ```

2. Use batch operations for related cache updates
3. Restart Redis during low-traffic periods if fragmentation > 2.0

### Low Cache Hit Rate

1. Review TTL strategies
2. Enable cache prewarming
3. Analyze access patterns in Performance Dashboard
4. Adjust cache key patterns

### High Memory Usage

1. Review maxmemory-policy settings
2. Enable memory-efficient data structures
3. Implement more aggressive cache expiration
4. Use smart cache clearing

## Security Considerations

### 1. Network Security

```ini
# Bind to specific interface
bind 127.0.0.1

# Enable authentication
requirepass your_secure_password

# Disable dangerous commands
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command KEYS ""
```

### 2. Data Protection

- Use Redis AUTH for password protection
- Consider Redis TLS for network encryption
- Implement proper firewall rules
- Regular backup of Redis data

## Performance Testing

### Benchmarking Commands

```bash
# Test Redis performance
redis-benchmark -h 127.0.0.1 -p 6379 -c 50 -n 10000

# Test pipeline performance
redis-benchmark -h 127.0.0.1 -p 6379 -c 50 -n 10000 -P 16

# Memory usage analysis
redis-cli info memory
redis-cli memory usage cache_key_name
```

### Application-Level Testing

Monitor these through the Performance Dashboard:

1. Cache operation latency
2. Memory fragmentation trends
3. Hit rate improvements
4. Memory usage optimization

## Additional Resources

- [Redis Official Documentation](https://redis.io/documentation)
- [Redis Memory Optimization](https://redis.io/topics/memory-optimization)
- [Redis Performance Tuning](https://redis.io/topics/latency)

---

This configuration guide should help you achieve the target performance improvements for your V2RaySocks Traffic Monitor caching system.