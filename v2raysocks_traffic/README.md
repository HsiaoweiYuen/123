# V2RaySocks Traffic Monitor - Enhanced Version

## Overview

This enhanced version of the V2RaySocks Traffic Monitor provides comprehensive traffic analytics, real-time monitoring, and advanced user/node management capabilities. The module has been significantly improved to meet modern WHMCS integration requirements.

## Key Features

### 🚀 Enhanced Dashboard
- **Multi-timeframe Active Users**: Track active users across 5min, 1hour, 24hour, and monthly periods
- **Real-time Traffic Stats**: Monitor traffic across multiple time periods (5min, hourly, daily, monthly)
- **Advanced Time Filtering**: 12 different time range options from 5 minutes to 30 days
- **Service ID Integration**: Full support for service ID search and display
- **Speed Limit Display**: View SS and V2Ray speed limits for all services

### 📊 Advanced Analytics
- **Decimal Unit System**: Uses decimal (1000-based) units for traffic calculations (KB/MB/GB/TB)
- **Flexible Display Units**: Auto-detection, MB, or GB display options
- **Enhanced Charts**: Proper axis labels, adaptive time grouping, and better scaling
- **Comprehensive Export**: CSV exports with service ID and speed limit data

### 🔍 Dedicated Search Tools
- **Service ID Search**: Dedicated page for searching traffic by service ID
- **Enhanced User Statistics**: Search by User ID, Service ID, or UUID
- **Advanced Node Analytics**: Detailed node performance and traffic analysis

### ⚡ Real-time Monitoring
- **5-minute WHMCS Sync**: Aligned with WHMCS synchronization requirements
- **Live Status Updates**: Real-time node and user status monitoring
- **Multi-metric Dashboard**: Comprehensive overview of system performance

## Installation

1. Upload the module to `/modules/addons/v2raysocks_monitor/`
2. Go to WHMCS Admin → Setup → Addon Modules  
3. Activate the "V2RaySocks Traffic Monitor" module
4. Configure the module settings:
   - V2RaySocks Database Server
   - Redis connection details
   - Unit system preferences (decimal only)
   - Default display units
   - Refresh interval (recommended: 300 seconds)

## Configuration Options

### Module Settings
- **Unit System**: Uses decimal unit conversion (1000-based, KB/MB/GB/TB)
- **Default Display Unit**: Set preferred unit for traffic display (Auto/MB/GB)
- **Chart Display Unit**: Configure units used in charts and graphs
- **Refresh Interval**: Set auto-refresh interval (recommended: 300 seconds for WHMCS sync)

### Time Range Options
- **Real-time**: 5 minutes, 10 minutes, 30 minutes
- **Short-term**: 1 hour, 2 hours, 6 hours, 12 hours
- **Daily**: Today, Yesterday
- **Long-term**: 7 days, 15 days, 30 days (including today)
- **Custom**: User-defined date ranges

## Dashboard Views

### Main Dashboard
- Live statistics with multi-timeframe active users
- Traffic periods display (5min, hourly, monthly)
- Advanced filtering with 12 time range options
- Service ID search integration
- Enhanced traffic table with speed limits

### Real-time Monitor
- 5-second refresh for truly real-time monitoring
- Multi-metric active user display
- Live traffic statistics across different time periods
- Enhanced visual indicators

### Service ID Search
- Dedicated search page for service ID queries
- Interactive traffic charts with adaptive time grouping
- Comprehensive traffic history with export functionality
- Speed limit information display

### User Statistics
- Search by User ID, Service ID, or UUID
- Detailed user information including transfer limits
- Interactive traffic charts and usage history
- Speed limit display for SS and V2Ray

### Node Statistics
- Node performance analytics and monitoring
- Online/offline status with last seen information
- Traffic distribution analysis
- Unique user count per node

## Technical Improvements

### Database Enhancements
- Optimized queries for multi-timeframe data retrieval
- Service ID support throughout the database layer
- Enhanced time range calculations with bug fixes
- Improved date handling for accurate "Today's Traffic"

### Caching System
- 5-minute cache TTL for WHMCS synchronization alignment
- Enhanced Redis integration with proper error handling
- Smart cache keys with v2raysocks_monitor prefix
- Improved cache invalidation strategies

### Frontend Improvements
- Responsive design for mobile compatibility
- Enhanced Chart.js integration with proper labeling
- Better error handling and user feedback
- Improved navigation and user experience

## API Endpoints

### Enhanced Endpoints
- `/get_live_stats` - Multi-timeframe live statistics
- `/get_traffic_data` - Enhanced filtering with service_id support
- `/get_user_details` - Comprehensive user information
- `/get_node_details` - Detailed node analytics
- `/export_data` - Enhanced CSV export with speed limits
- `/get_module_config` - Configuration settings retrieval

### New Search Functionality
- Service ID search with time range filtering
- UUID-based user lookup
- Multi-criteria search capabilities

## Function Naming

All functions maintain the `v2raysocks_traffic_` prefix for consistency:
- `v2raysocks_traffic_getTimeRangeTimestamps()` - New time range helper
- `v2raysocks_traffic_formatBytesConfigurable()` - Enhanced unit conversion
- `v2raysocks_traffic_getTrafficByServiceId()` - Service ID search
- `v2raysocks_traffic_getModuleConfig()` - Configuration management

## Performance Considerations

- **Enhanced Caching**: 5-minute intervals for optimal WHMCS sync
- **Optimized Queries**: Improved database performance for large datasets
- **Responsive Design**: Better performance on mobile devices
- **Smart Loading**: Efficient data loading with proper pagination

## Version History

- **v2025-01-01 Enhanced**: 
  - Multi-timeframe active user statistics
  - Configurable unit conversion system
  - Service ID integration throughout
  - Enhanced real-time monitoring
  - Advanced search capabilities
  - Improved chart visualization
  - Comprehensive time filtering
- **Previous versions**: Basic traffic monitoring functionality

## Backward Compatibility

This enhanced version maintains full backward compatibility with:
- Existing V2RaySocks installations
- Previous module configurations
- Existing API endpoints
- Database schema requirements

All existing functionality continues to work while providing significant enhancements and new features.