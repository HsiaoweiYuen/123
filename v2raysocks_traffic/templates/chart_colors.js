// V2RaySocks Traffic Monitor - Standardized Chart Colors
// This file defines consistent colors across all charts - aligned with v2raysocks main colors

const CHART_COLORS = {
    // Primary traffic colors - aligned with search service chart colors  
    upload: '#007bff',        // Blue for upload (same as total)
    download: '#e83e8c',      // Pink for download  
    total: '#007bff',         // Blue for total traffic
    
    // Secondary colors for variations - aligned with v2raysocks palette
    secondary: '#6c757d',     // Gray
    warning: '#ffc107',       // Yellow/Orange
    success: '#007bff'        // Blue (same as upload/total)
};

// Legacy compatibility - keep the old structure for any code that might use it
const V2RAYSOCKS_CHART_COLORS = {
    upload: {
        border: CHART_COLORS.upload,
        background: 'rgba(0, 123, 255, 0.1)',
        backgroundFilled: 'rgba(0, 123, 255, 0.2)'
    },
    download: {
        border: CHART_COLORS.download,
        background: 'rgba(232, 62, 140, 0.1)',
        backgroundFilled: 'rgba(232, 62, 140, 0.2)'
    },
    total: {
        border: CHART_COLORS.total,
        background: 'rgba(0, 123, 255, 0.1)',
        backgroundFilled: 'rgba(0, 123, 255, 0.2)'
    },
    secondary: {
        border: CHART_COLORS.secondary,
        background: 'rgba(108, 117, 125, 0.1)',
        backgroundFilled: 'rgba(108, 117, 125, 0.2)'
    },
    warning: {
        border: CHART_COLORS.warning,
        background: 'rgba(255, 193, 7, 0.1)',
        backgroundFilled: 'rgba(255, 193, 7, 0.2)'
    },
    success: {
        border: CHART_COLORS.success,
        background: 'rgba(0, 123, 255, 0.1)',
        backgroundFilled: 'rgba(0, 123, 255, 0.2)'
    }
};

// Helper function to get consistent dataset configurations
function getStandardDatasetConfig(type, label, data, options = {}) {
    const config = {
        label: label,
        data: data,
        borderColor: V2RAYSOCKS_CHART_COLORS[type].border,
        backgroundColor: (options.fill === true || options.filled) ? 
            V2RAYSOCKS_CHART_COLORS[type].backgroundFilled : 
            V2RAYSOCKS_CHART_COLORS[type].background,
        tension: options.tension || 0.4,
        fill: options.fill !== undefined ? options.fill : false,
        borderWidth: 2,
        pointBorderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5
    };
    
    // Add any additional options
    return Object.assign(config, options);
}