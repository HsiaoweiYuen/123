# Configurable Pagination System Documentation

## Overview

The V2RaySocks Traffic Analysis module now features a comprehensive configurable pagination system that removes all hardcoded data limits and enables efficient handling of large datasets.

## Key Features

### 1. Configurable Pagination Size
- **Default**: 1,000 records per page
- **Presets**: 1,000 | 3,000 | 5,000 | 10,000 | 30,000 | 50,000 | 100,000
- **Custom**: User-defined size (100 - 1,000,000 records)
- **Configuration Location**: Module Settings → Pagination Size

### 2. Updated Refresh Intervals

#### Real-time Monitor Refresh Interval
- **Removed**: 5s, 10s, 15s (performance optimization)
- **Available**: 30s, 60s, 90s, 120s
- **Default**: 30 seconds

#### Module Refresh Interval (Optional)
- **New Feature**: Optional automatic page refresh
- **Available**: Disabled | 120s | 180s | 240s | 300s | 600s
- **Default**: Disabled

### 3. Enhanced Data Retrieval

#### No More Data Truncation
- All hardcoded `LIMIT 500` and `LIMIT 1000` clauses removed
- Complete datasets can be retrieved through batch processing
- Configurable limits applied consistently across all functions

#### Affected Functions
- `v2raysocks_traffic_getTrafficData()` - Traffic data queries
- `v2raysocks_traffic_getEnhancedTrafficData()` - Enhanced traffic data
- `v2raysocks_traffic_getUsageRecords()` - Usage record queries  
- `v2raysocks_traffic_getUserTrafficRankings()` - User rankings
- All export functions

### 4. API Enhancements

#### New Parameters
- `limit` - Number of records to retrieve
- `offset` - Starting position for pagination
- Both parameters support frontend pagination controls

#### Response Metadata
```json
{
  "status": "success",
  "data": [...],
  "pagination": {
    "limit": 1000,
    "offset": 0,
    "has_more": true
  }
}
```

## Configuration Guide

### Setting Up Pagination Size

1. **Navigate to**: WHMCS Admin → Setup → Addon Modules → V2RaySocks Traffic Analysis
2. **Find**: "Pagination Size" dropdown
3. **Options**:
   - Select preset value (1,000 - 100,000)
   - Choose "Custom" for user-defined size
4. **Custom Size**: If "Custom" selected, enter value in "Custom Pagination Size" field
5. **Validation**: Custom values must be between 100 and 1,000,000

### Setting Refresh Intervals

1. **Real-time Monitor**: Controls auto-refresh of statistics pages
2. **Module Refresh**: Optional auto-refresh for all module pages
3. **Recommendation**: Use 30-60s for real-time, 240-300s for module refresh

## Technical Implementation

### Database Query Changes

**Before** (Hardcoded):
```sql
SELECT * FROM user_usage ORDER BY t DESC LIMIT 1000
```

**After** (Configurable):
```sql
SELECT * FROM user_usage ORDER BY t DESC LIMIT :limit OFFSET :offset
```

### Backward Compatibility

- Existing frontend pagination continues to work
- Default values maintain current behavior
- No breaking changes to API endpoints
- Progressive enhancement approach

### Performance Benefits

1. **Memory Efficiency**: Pagination reduces memory usage for large datasets
2. **Faster Queries**: Smaller result sets improve query performance  
3. **Better UX**: Configurable limits allow optimization per use case
4. **Scalability**: System can handle datasets of any size

## Migration Notes

### For Existing Installations
- No manual migration required
- Default pagination size: 1,000 records (preserves current behavior)
- All existing functionality remains unchanged
- New features are optional enhancements

### For Large Datasets
- Increase pagination size for better performance
- Use module refresh interval to reduce server load
- Monitor database performance with higher limits
- Consider server resources when setting custom limits

## Troubleshooting

### Common Issues

1. **Performance Degradation**
   - **Cause**: Pagination size too large for server capacity
   - **Solution**: Reduce pagination size to 1,000-5,000 records

2. **Incomplete Data Display**
   - **Cause**: Pagination not loading all data
   - **Solution**: Use frontend pagination controls to load additional pages

3. **High Server Load**
   - **Cause**: Refresh intervals too frequent
   - **Solution**: Increase refresh intervals to 60s+ for real-time monitor

### Configuration Validation

The system automatically validates:
- Custom pagination size (100 - 1,000,000)
- Refresh interval selections
- API parameter ranges
- Database query limits

## Best Practices

1. **Production Environments**:
   - Use pagination size 1,000-5,000 for most scenarios
   - Set real-time refresh to 60s minimum
   - Enable module refresh only if needed

2. **Development/Testing**:
   - Higher pagination sizes acceptable for testing
   - Lower refresh intervals for debugging

3. **Large Datasets**:
   - Use progressive loading through frontend pagination
   - Monitor database performance
   - Consider implementing data archiving for historical data

## Support

For technical issues or questions about the pagination system:
1. Check WHMCS system logs for error messages
2. Verify database performance with current settings
3. Test with default values to isolate configuration issues
4. Review this documentation for configuration guidance