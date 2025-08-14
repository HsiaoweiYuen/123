<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$todayTrafficChartHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('today_traffic_chart') . '</title>
    <style>
        .dashboard-container {
            padding: 20px;
        }
        .navigation-bar {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .nav-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .nav-link {
            color: #007bff;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .nav-link:hover {
            background-color: #e9ecef;
            text-decoration: none;
        }
        .nav-link.active {
            background-color: #007bff;
            color: white;
        }
        .chart-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        .time-controls {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .control-group label {
            min-width: 100px;
            font-weight: 500;
        }
        .control-group select,
        .control-group button {
            padding: 5px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .refresh-indicator {
            color: #28a745;
            margin-left: 10px;
        }
        @media (max-width: 768px) {
            .stats-summary {
                flex-direction: column;
            }
            .control-group {
                flex-direction: column;
                align-items: flex-start;
            }
            .control-group label {
                min-width: auto;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Include standardized chart colors
        ' . file_get_contents(__DIR__ . '/chart_colors.js') . '
    </script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        <div class="navigation-bar">
            <div class="nav-links">
                <a href="addonmodules.php?module=v2raysocks_traffic" class="nav-link">' . v2raysocks_traffic_lang('traffic_dashboard') . '</a>
                <a href="addonmodules.php?module=v2raysocks_traffic&action=real_time" class="nav-link">' . v2raysocks_traffic_lang('real_time_monitor') . '</a>
                <a href="addonmodules.php?module=v2raysocks_traffic&action=node_stats" class="nav-link">' . v2raysocks_traffic_lang('node_statistics') . '</a>
                <a href="addonmodules.php?module=v2raysocks_traffic&action=service_search" class="nav-link">' . v2raysocks_traffic_lang('service_search') . '</a>
                <a href="addonmodules.php?module=v2raysocks_traffic&action=today_traffic_chart" class="nav-link active">' . v2raysocks_traffic_lang('today_traffic_chart') . '</a>
            </div>
        </div>

        <h1>' . v2raysocks_traffic_lang('today_traffic_chart') . ' <span id="refresh-indicator" class="refresh-indicator"></span></h1>
        
        <!-- Today\'s Summary Statistics -->
        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-value" id="today-total-upload">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('upload') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="today-total-download">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('download') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="today-total-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="today-peak-hour">--</div>
                <div class="stat-label" id="peak-label">Peak Hour</div>
            </div>
        </div>
        
        <!-- Time Controls and Search (moved above chart) -->
        <div class="time-controls">
            <div class="control-group">
                <label for="time-range">' . v2raysocks_traffic_lang('time_range') . ':</label>
                <select id="time-range">
                    <option value="today" selected>Today</option>
                    <option value="7days">Last 7 Days</option>
                    <option value="30days">Last 30 Days</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div class="control-group">
                <label for="chart-type">Chart Type:</label>
                <select id="chart-type">
                    <option value="combined" selected>Upload vs Download</option>
                    <option value="hourly">Total Traffic</option>
                    <option value="cumulative">Cumulative Traffic</option>
                </select>
            </div>
            <div class="control-group">
                <label for="data-unit">Unit:</label>
                <select id="data-unit">
                    <option value="auto">Auto</option>
                    <option value="MB">MB</option>
                    <option value="GB" selected>GB</option>
                    <option value="TB">TB</option>
                </select>
            </div>
            <div class="control-group">
                <button id="refresh-chart" class="btn-primary">' . v2raysocks_traffic_lang('refresh') . '</button>
                <button id="export-chart" class="btn-primary">Export Chart</button>
            </div>
        </div>
        
        <!-- Today\'s Traffic Chart -->
        <div class="chart-container">
            <canvas id="today-traffic-chart" width="400" height="200"></canvas>
        </div>
        
        <div id="loading" class="loading" style="display: none;">' . v2raysocks_traffic_lang('loading') . '</div>
    </div>

    <script>
        let todayChart = null;
        let todayData = {};
        
        $(document).ready(function() {
            initTodayChart();
            loadTodayTrafficData();
            
            // Auto-refresh every 5 minutes
            setInterval(loadTodayTrafficData, 300000);
            
            $("#refresh-chart").click(function() {
                loadTodayTrafficData();
            });
            
            $("#chart-type, #data-unit, #time-range").change(function() {
                updateTodayChart();
                updateTodayStats(); // Update stats when time range changes
            });
            
            $("#export-chart").click(function() {
                exportChart();
            });
        });
        
        function initTodayChart() {
            const ctx = document.getElementById("today-traffic-chart").getContext("2d");
            todayChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "' . v2raysocks_traffic_lang('traffic') . ' (GB)"
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: "' . v2raysocks_traffic_lang('time') . '"
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: "' . v2raysocks_traffic_lang('hourly_traffic_pattern') . '"
                        },
                        legend: {
                            display: true,
                            position: "top"
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || "";
                                    const value = context.parsed.y;
                                    const unit = label.match(/\\(([^)]+)\\)/);
                                    const unitText = unit ? unit[1] : "GB";
                                    // Format: "下载：100 GB" instead of "下载 (GB)：100"
                                    const cleanLabel = label.replace(/\\s*\\([^)]*\\)/, "");
                                    return cleanLabel + "：" + value.toFixed(2) + " " + unitText;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function loadTodayTrafficData() {
            $("#refresh-indicator").text("🔄");
            $("#loading").show();
            
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_today_traffic_data",
                type: "GET",
                dataType: "json",
                timeout: 15000, // 15 second timeout
                success: function(response) {
                    console.log("Today traffic data response:", response);
                    if (response.status === "success" && response.data) {
                        todayData = response.data;
                        updateTodayStats();
                        updateTodayChart();
                        $("#refresh-indicator").text("✓");
                    } else {
                        console.error("Today traffic data error:", response);
                        $("#refresh-indicator").text("❌");
                    }
                    setTimeout(() => $("#refresh-indicator").text(""), 2000);
                    $("#loading").hide();
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error loading today traffic data:", status, error);
                    $("#refresh-indicator").text("❌");
                    setTimeout(() => $("#refresh-indicator").text(""), 2000);
                    $("#loading").hide();
                }
            });
        }
        
        function updateTodayStats() {
            if (!todayData.hourly_stats) return;
            
            let totalUpload = 0;
            let totalDownload = 0;
            let peakHour = "00:00";
            let peakTraffic = 0;
            let peakDisplayText = "--";
            
            const timeRange = $("#time-range").val() || "today";
            
            // Update peak label based on time range
            const peakLabel = timeRange === "today" ? "Peak Hour" : "Peak Date";
            $("#peak-label").text(peakLabel);
            
            if (timeRange === "today") {
                // For today: show peak hour
                Object.keys(todayData.hourly_stats).forEach(hour => {
                    const stats = todayData.hourly_stats[hour];
                    totalUpload += stats.upload || 0;
                    totalDownload += stats.download || 0;
                    
                    const hourTraffic = (stats.upload || 0) + (stats.download || 0);
                    if (hourTraffic > peakTraffic) {
                        peakTraffic = hourTraffic;
                        peakHour = hour + ":00";
                    }
                });
                peakDisplayText = peakHour;
            } else {
                // For 7 days and other periods: show peak date
                // This will be implemented when we add multi-day data support
                if (todayData.daily_stats) {
                    let peakDate = "";
                    Object.keys(todayData.daily_stats).forEach(date => {
                        const stats = todayData.daily_stats[date];
                        totalUpload += stats.upload || 0;
                        totalDownload += stats.download || 0;
                        
                        const dayTraffic = (stats.upload || 0) + (stats.download || 0);
                        if (dayTraffic > peakTraffic) {
                            peakTraffic = dayTraffic;
                            peakDate = date;
                        }
                    });
                    peakDisplayText = peakDate || "--";
                } else {
                    // Fallback to current day data for now
                    Object.keys(todayData.hourly_stats).forEach(hour => {
                        const stats = todayData.hourly_stats[hour];
                        totalUpload += stats.upload || 0;
                        totalDownload += stats.download || 0;
                        
                        const hourTraffic = (stats.upload || 0) + (stats.download || 0);
                        if (hourTraffic > peakTraffic) {
                            peakTraffic = hourTraffic;
                            peakHour = hour + ":00";
                        }
                    });
                    peakDisplayText = peakHour;
                }
            }
            
            $("#today-total-upload").text(formatBytes(totalUpload));
            $("#today-total-download").text(formatBytes(totalDownload));
            $("#today-total-traffic").text(formatBytes(totalUpload + totalDownload));
            $("#today-peak-hour").text(peakDisplayText);
        }
        
        function updateTodayChart() {
            if (!todayData || !todayData.hourly_stats) {
                console.log("No today data available for chart");
                return;
            }
            
            const chartType = $("#chart-type").val();
            let unit = $("#data-unit").val();
            
            // Sort hours chronologically instead of alphabetically
            const availableHours = Object.keys(todayData.hourly_stats).sort((a, b) => {
                if (a.includes(":") && !a.includes("/")) {
                    // Time format sorting (HH:MM)
                    const [aHour, aMin] = a.split(":").map(Number);
                    const [bHour, bMin] = b.split(":").map(Number);
                    return (aHour * 60 + aMin) - (bHour * 60 + bMin);
                } else if (a.includes("/")) {
                    // Date format sorting (YYYY/MM/DD or MM/DD)
                    const aParts = a.split("/").map(Number);
                    const bParts = b.split("/").map(Number);
                    
                    if (aParts.length === 3 && bParts.length === 3) {
                        // Format: YYYY/MM/DD
                        const aDate = new Date(aParts[0], aParts[1] - 1, aParts[2]);
                        const bDate = new Date(bParts[0], bParts[1] - 1, bParts[2]);
                        return aDate - bDate;
                    } else if (aParts.length === 2 && bParts.length === 2) {
                        // Format: MM/DD (assume same year)
                        const aDate = new Date(2024, aParts[0] - 1, aParts[1]);
                        const bDate = new Date(2024, bParts[0] - 1, bParts[1]);
                        return aDate - bDate;
                    }
                    return a.localeCompare(b);
                } else if (a.includes("-")) {
                    // Date format sorting (YYYY-MM-DD)
                    return new Date(a) - new Date(b);
                } else {
                    // For numeric hour strings (0, 1, 2, ..., 23)
                    return parseInt(a) - parseInt(b);
                }
            });
            
            // Ensure continuous time axis by including all hours (0-23) even if they have no data
            const allHours = [];
            for (let hour = 0; hour < 24; hour++) {
                const hourStr = hour.toString();
                allHours.push(hourStr);
                // If this hour is not in the available data, ensure it exists with zero values
                if (!todayData.hourly_stats[hourStr]) {
                    todayData.hourly_stats[hourStr] = { upload: 0, download: 0 };
                }
            }
            
            // Use complete hour range to prevent time gaps
            const hours = allHours;
            
            // Now hours.length will always be 24, so no need to check for empty data
            const labels = hours.map(h => h + ":00");
            
            let datasets = [];
            let allDataPoints = [];
            
            // Collect all data points to determine best unit for auto mode
            hours.forEach(hour => {
                const stats = todayData.hourly_stats[hour];
                if (stats) {
                    allDataPoints.push(stats.upload || 0);
                    allDataPoints.push(stats.download || 0);
                    allDataPoints.push((stats.upload || 0) + (stats.download || 0));
                }
            });
            
            // If auto unit is selected, determine the best unit
            if (unit === "auto") {
                unit = getBestUnitForData(allDataPoints);
            }
            
            const unitDivisor = getUnitDivisor(unit);
            
            switch (chartType) {
                case "combined":
                    // Default: Show upload, download, and total traffic
                    const uploadData = hours.map(hour => {
                        const stats = todayData.hourly_stats[hour];
                        return (stats.upload || 0) / unitDivisor;
                    });
                    
                    const downloadData = hours.map(hour => {
                        const stats = todayData.hourly_stats[hour];
                        return (stats.download || 0) / unitDivisor;
                    });
                    
                    const totalData = hours.map(hour => {
                        const stats = todayData.hourly_stats[hour];
                        return ((stats.upload || 0) + (stats.download || 0)) / unitDivisor;
                    });
                    
                    datasets = [
                        getStandardDatasetConfig("upload", `' . v2raysocks_traffic_lang('upload') . ' (${unit})`, uploadData),
                        getStandardDatasetConfig("download", `' . v2raysocks_traffic_lang('download') . ' (${unit})`, downloadData),
                        getStandardDatasetConfig("total", `' . v2raysocks_traffic_lang('total_traffic') . ' (${unit})`, totalData, {fill: false})
                    ];
                    break;
                    
                case "hourly":
                    const totalOnlyData = hours.map(hour => {
                        const stats = todayData.hourly_stats[hour];
                        return ((stats.upload || 0) + (stats.download || 0)) / unitDivisor;
                    });
                    
                    datasets = [
                        getStandardDatasetConfig("total", `' . v2raysocks_traffic_lang('total_traffic') . ' (${unit})`, totalOnlyData, {fill: true})
                    ];
                    break;
                    
                case "cumulative":
                    let cumulativeUpload = 0;
                    let cumulativeDownload = 0;
                    
                    const cumulativeUploadData = hours.map(hour => {
                        const stats = todayData.hourly_stats[hour];
                        cumulativeUpload += (stats.upload || 0);
                        return cumulativeUpload / unitDivisor;
                    });
                    
                    const cumulativeDownloadData = hours.map(hour => {
                        const stats = todayData.hourly_stats[hour];
                        cumulativeDownload += (stats.download || 0);
                        return cumulativeDownload / unitDivisor;
                    });
                    
                    datasets = [
                        getStandardDatasetConfig("upload", `' . v2raysocks_traffic_lang('cumulative_upload') . ' (${unit})`, cumulativeUploadData, {fill: false}),
                        getStandardDatasetConfig("download", `' . v2raysocks_traffic_lang('cumulative_download') . ' (${unit})`, cumulativeDownloadData, {fill: false})
                    ];
                    break;
            }
            
            todayChart.data.labels = labels;
            todayChart.data.datasets = datasets;
            todayChart.options.scales.y.title.text = `' . v2raysocks_traffic_lang('traffic') . ' (${unit})`;
            todayChart.update();
        }
        
        function getUnitDivisor(unit) {
            // Use consistent decimal (1000-based) conversion like formatBytes function
            switch (unit) {
                case "MB": return 1000000;
                case "GB": return 1000000000;
                case "TB": return 1000000000000;
                case "PB": return 1000000000000000;
                case "EB": return 1000000000000000000;
                case "auto": return 1000000000; // Default to GB for auto
                default: return 1000000000; // Default to GB
            }
        }
        
        function getBestUnitForData(dataArray) {
            if (!dataArray || dataArray.length === 0) return "GB";
            
            const maxValue = Math.max(...dataArray.filter(v => v && !isNaN(v)));
            
            // Use decimal system (1000-based) for consistency
            if (maxValue < 1000000) return "KB";
            else if (maxValue < 1000000000) return "MB";
            else if (maxValue < 1000000000000) return "GB";
            else if (maxValue < 1000000000000000) return "TB";
            else if (maxValue < 1000000000000000000) return "PB";
            else return "EB";
        }
        
        function formatBytes(bytes) {
            // Enhanced input validation and type conversion
            if (bytes === null || bytes === undefined || bytes === "") {
                return "0 B";
            }
            
            // Convert to number, handling both strings and numbers
            let size;
            if (typeof bytes === "string") {
                // Remove any non-numeric characters except decimal point and minus sign
                const cleanedBytes = bytes.replace(/[^\\d.-]/g, "");
                size = parseFloat(cleanedBytes);
            } else {
                size = parseFloat(bytes);
            }
            
            // Check if conversion resulted in valid number
            if (isNaN(size) || !isFinite(size)) {
                return "0 B";
            }
            
            // Ensure non-negative
            size = Math.abs(size);
            
            if (size === 0) {
                return "0 B";
            }
            
            // Use decimal system (1000-based) for consistency with existing logic
            // Fixed: Add more precise boundary checking to prevent TB display for small values
            
            if (size < 1000) {
                // Show bytes for very small values
                return size.toFixed(0) + " B";
            } else if (size < 1000000) {
                // Show KB for values under 1MB
                const kb = size / 1000;
                return kb.toFixed(2) + " KB";
            } else if (size < 1000000000) {
                // Show MB for values under 1GB  
                const mb = size / 1000000;
                return mb.toFixed(2) + " MB";
            } else if (size < 1000000000000) {
                // Show GB for values under 1TB
                const gb = size / 1000000000;
                return gb.toFixed(2) + " GB";
            } else if (size < 1000000000000000) {
                // Show TB for values under 1PB
                const tb = size / 1000000000000;
                return tb.toFixed(2) + " TB";
            } else if (size < 1000000000000000000) {
                // Show PB for values under 1EB
                const pb = size / 1000000000000000;
                return pb.toFixed(2) + " PB";
            } else {
                // Show EB for truly massive values
                const eb = size / 1000000000000000000;
                return eb.toFixed(2) + " EB";
            }
        }
        
        function exportChart() {
            // export chart preserves the time formatting already displayed  
            const link = document.createElement("a");
            link.download = "today_traffic_chart_" + new Date().toISOString().slice(0, 10) + ".png";
            link.href = todayChart.toBase64Image();
            link.click();
        }
    </script>
</body>
</html>';
?>