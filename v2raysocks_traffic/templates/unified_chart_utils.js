// V2RaySocks Traffic Monitor - Unified Chart Utilities
// This file provides unified functions for chart data processing and time axis management
// Ensures continuous time axes by filling missing data points with zero values

/**
 * Generate complete time labels for different time ranges
 * @param {string} timeRange - Time range type (today, week, month, etc.)
 * @param {number} points - Number of time points to generate
 * @returns {Array} Array of time labels
 */
function generateUnifiedTimeLabels(timeRange = "today", points = 24) {
    const labels = [];
    const now = new Date();
    
    switch (timeRange) {
        case "5min":
            // Generate labels for last 5 minutes with 30-second intervals
            for (let i = points - 1; i >= 0; i--) {
                const time = new Date(now.getTime() - i * 30 * 1000);
                const hour = time.getHours().toString().padStart(2, "0");
                const minute = time.getMinutes().toString().padStart(2, "0");
                labels.push(hour + ":" + minute);
            }
            break;
            
        case "10min":
            // Generate labels for last 10 minutes with 1-minute intervals
            for (let i = points - 1; i >= 0; i--) {
                const time = new Date(now.getTime() - i * 60 * 1000);
                const hour = time.getHours().toString().padStart(2, "0");
                const minute = time.getMinutes().toString().padStart(2, "0");
                labels.push(hour + ":" + minute);
            }
            break;
            
        case "30min":
            // Generate labels for last 30 minutes with 2-minute intervals
            for (let i = points - 1; i >= 0; i--) {
                const time = new Date(now.getTime() - i * 2 * 60 * 1000);
                const hour = time.getHours().toString().padStart(2, "0");
                const minute = time.getMinutes().toString().padStart(2, "0");
                labels.push(hour + ":" + minute);
            }
            break;
            
        case "1hour":
            // Generate labels for last hour with 3-minute intervals
            for (let i = points - 1; i >= 0; i--) {
                const time = new Date(now.getTime() - i * 3 * 60 * 1000);
                const hour = time.getHours().toString().padStart(2, "0");
                const minute = time.getMinutes().toString().padStart(2, "0");
                labels.push(hour + ":" + minute);
            }
            break;
            
        case "today":
            // Generate all 24 hour labels for today
            for (let i = 0; i < 24; i++) {
                labels.push(String(i).padStart(2, "0") + ":00");
            }
            break;
            
        case "week":
        case "7days":
            // Generate labels for last 7 days
            for (let i = 6; i >= 0; i--) {
                const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                const month = String(date.getMonth() + 1).padStart(2, "0");
                const day = String(date.getDate()).padStart(2, "0");
                labels.push(month + "/" + day);
            }
            break;
            
        case "halfmonth":
        case "15days":
            // Generate labels for last 15 days
            for (let i = 14; i >= 0; i--) {
                const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                const month = String(date.getMonth() + 1).padStart(2, "0");
                const day = String(date.getDate()).padStart(2, "0");
                labels.push(month + "/" + day);
            }
            break;
            
        case "month":
        case "30days":
        case "month_including_today":
            // Generate labels for last 30 days
            for (let i = 29; i >= 0; i--) {
                const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                const month = String(date.getMonth() + 1).padStart(2, "0");
                const day = String(date.getDate()).padStart(2, "0");
                labels.push(month + "/" + day);
            }
            break;
            
        default:
            // Fallback: generate numeric labels
            for (let i = 1; i <= points; i++) {
                labels.push(String(i));
            }
            break;
    }
    
    return labels;
}

/**
 * Fill missing time points with zero values to ensure continuous time axes
 * @param {Array} timeLabels - Complete array of time labels
 * @param {Object} timeData - Existing time data object
 * @returns {Object} Filled time data with zeros for missing points
 */
function fillMissingTimePoints(timeLabels, timeData) {
    const filledData = {};
    
    timeLabels.forEach(function(timeLabel) {
        if (timeData[timeLabel]) {
            // Use existing data
            filledData[timeLabel] = {
                upload: timeData[timeLabel].upload || 0,
                download: timeData[timeLabel].download || 0
            };
        } else {
            // Fill missing data with zeros
            filledData[timeLabel] = {
                upload: 0,
                download: 0
            };
        }
    });
    
    return filledData;
}

/**
 * Create datasets from filled time data
 * @param {Array} timeLabels - Array of time labels
 * @param {Object} filledTimeData - Time data with zeros filled
 * @param {string} chartType - Type of chart (combined, separate, total, etc.)
 * @param {string} unit - Data unit for display
 * @param {number} unitDivisor - Divisor for unit conversion
 * @returns {Array} Array of dataset configurations
 */
function createUnifiedDatasets(timeLabels, filledTimeData, chartType, unit, unitDivisor) {
    let datasets = [];
    
    switch (chartType) {
        case "combined":
        case "separate":
            const uploadData = timeLabels.map(time => 
                parseFloat((filledTimeData[time].upload / unitDivisor).toFixed(3))
            );
            const downloadData = timeLabels.map(time => 
                parseFloat((filledTimeData[time].download / unitDivisor).toFixed(3))
            );
            
            datasets = [
                getStandardDatasetConfig("upload", `Upload (${unit})`, uploadData),
                getStandardDatasetConfig("download", `Download (${unit})`, downloadData)
            ];
            break;
            
        case "total":
            const totalData = timeLabels.map(time => {
                const upload = filledTimeData[time].upload || 0;
                const download = filledTimeData[time].download || 0;
                return parseFloat(((upload + download) / unitDivisor).toFixed(3));
            });
            
            datasets = [
                getStandardDatasetConfig("total", `Total Traffic (${unit})`, totalData, {fill: true})
            ];
            break;
            
        case "cumulative":
            let cumulativeUpload = 0;
            let cumulativeDownload = 0;
            
            const cumulativeUploadData = timeLabels.map(time => {
                cumulativeUpload += filledTimeData[time].upload || 0;
                return parseFloat((cumulativeUpload / unitDivisor).toFixed(3));
            });
            
            const cumulativeDownloadData = timeLabels.map(time => {
                cumulativeDownload += filledTimeData[time].download || 0;
                return parseFloat((cumulativeDownload / unitDivisor).toFixed(3));
            });
            
            datasets = [
                getStandardDatasetConfig("upload", `Cumulative Upload (${unit})`, cumulativeUploadData, {fill: false}),
                getStandardDatasetConfig("download", `Cumulative Download (${unit})`, cumulativeDownloadData, {fill: false})
            ];
            break;
            
        case "total_cumulative":
            let cumulativeTotal = 0;
            const cumulativeTotalData = timeLabels.map(time => {
                const upload = filledTimeData[time].upload || 0;
                const download = filledTimeData[time].download || 0;
                cumulativeTotal += upload + download;
                return parseFloat((cumulativeTotal / unitDivisor).toFixed(3));
            });
            
            datasets = [
                getStandardDatasetConfig("total", `Cumulative Total (${unit})`, cumulativeTotalData, {fill: true})
            ];
            break;
    }
    
    return datasets;
}

/**
 * Get unified chart options that prevent time axis skipping
 * @param {string} unit - Data unit for y-axis label
 * @returns {Object} Chart.js options configuration
 */
function getUnifiedChartOptions(unit = "GB") {
    return {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: `Traffic (${unit})`
                }
            },
            x: {
                title: {
                    display: true,
                    text: "Time"
                },
                ticks: {
                    autoSkip: false,  // Prevent skipping time labels
                    maxRotation: 45,
                    minRotation: 0
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: "top"
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || "";
                        const value = context.parsed.y;
                        const unit = label.match(/\(([^)]+)\)/);
                        const unitText = unit ? unit[1] : "GB";
                        const cleanLabel = label.replace(/\s*\([^)]*\)/, "");
                        return cleanLabel + ": " + value.toFixed(2) + " " + unitText;
                    }
                }
            }
        },
        interaction: {
            mode: 'index',
            intersect: false
        }
    };
}

/**
 * Process raw data into time-grouped format
 * @param {Array} data - Raw data array
 * @param {string} timeRange - Time range for grouping
 * @returns {Object} Grouped time data
 */
function processRawDataIntoTimeGroups(data, timeRange) {
    const timeData = {};
    
    if (!data || !Array.isArray(data) || data.length === 0) {
        return timeData;
    }
    
    data.forEach(function(row) {
        if (!row || !row.t) return;
        
        const date = new Date(row.t * 1000);
        let timeKey;
        
        // Group by different time periods based on range
        if (timeRange === "today" || timeRange.includes("hour") || timeRange.includes("min")) {
            timeKey = date.getHours().toString().padStart(2, "0") + ":00";
        } else {
            // Format as MM/DD for multi-day ranges
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            timeKey = month + "/" + day;
        }
        
        if (!timeData[timeKey]) {
            timeData[timeKey] = { upload: 0, download: 0 };
        }
        
        timeData[timeKey].upload += (parseFloat(row.u) || 0);
        timeData[timeKey].download += (parseFloat(row.d) || 0);
    });
    
    return timeData;
}

/**
 * Main function to update chart with continuous time axis
 * @param {Object} chart - Chart.js instance
 * @param {Array} data - Raw data array
 * @param {string} timeRange - Time range setting
 * @param {string} chartType - Chart display type
 * @param {string} unit - Data unit
 * @param {Object} groupedData - Pre-grouped data (optional)
 */
function updateChartWithContinuousTimeAxis(chart, data, timeRange, chartType, unit, groupedData = null) {
    // Determine unit divisor
    let unitDivisor;
    switch (unit) {
        case "MB": unitDivisor = 1000000; break;
        case "GB": unitDivisor = 1000000000; break;
        case "TB": unitDivisor = 1000000000000; break;
        default: unitDivisor = 1000000000; break; // Default to GB
    }
    
    // Generate complete time labels
    const timeLabels = generateUnifiedTimeLabels(timeRange, timeRange === "today" ? 24 : 30);
    
    // Process data into time groups
    let timeData;
    if (groupedData) {
        // Use pre-grouped data from server
        timeData = groupedData;
    } else {
        // Process raw data
        timeData = processRawDataIntoTimeGroups(data, timeRange);
    }
    
    // Fill missing time points with zeros
    const filledTimeData = fillMissingTimePoints(timeLabels, timeData);
    
    // Create datasets
    const datasets = createUnifiedDatasets(timeLabels, filledTimeData, chartType, unit, unitDivisor);
    
    // Update chart
    chart.data.labels = timeLabels;
    chart.data.datasets = datasets;
    chart.options = getUnifiedChartOptions(unit);
    chart.update();
}