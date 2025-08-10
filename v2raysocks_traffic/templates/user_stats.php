<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$userStatsHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('user_statistics') . '</title>
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
        .search-form { 
            background: #f8f9fa; 
            padding: 20px; 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
        }
        .form-group { 
            flex: 1;
            min-width: 150px;
        }
        .form-group label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ced4da; 
            border-radius: 4px; 
        }
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .user-info {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.2em;
            color: #495057;
        }
        
        .chart-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .table-responsive { overflow-x: auto; }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .table-striped tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        
        .loading, .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .no-data {
            font-style: italic;
        }
        
        /* Responsive styles for mobile devices */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 10px;
            }
            .nav-links {
                flex-direction: column;
                gap: 8px;
            }
            .nav-link {
                text-align: center;
                padding: 10px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .stat-card {
                padding: 15px;
            }
            .filter-panel, .navigation-bar {
                padding: 15px;
            }
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            .filter-group {
                min-width: auto;
                width: 100%;
            }
            .table-responsive {
                font-size: 0.9em;
            }
            .table th, .table td {
                padding: 8px 4px;
                font-size: 0.9em;
            }
        }
        
        /* Responsive styles for very small devices */
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 5px;
            }
            .stat-value {
                font-size: 1.5em;
            }
            .stat-card {
                padding: 10px;
            }
            .filter-panel, .navigation-bar {
                padding: 10px;
            }
            .table th, .table td {
                padding: 6px 2px;
                font-size: 0.8em;
            }
            .btn {
                padding: 6px 12px;
                font-size: 0.9em;
            }
            /* Fix modal dialogs for mobile */
            div[id$="-modal"] > div {
                min-width: 90% !important;
                max-width: 95% !important;
                left: 2.5% !important;
                transform: translateY(-50%) !important;
                padding: 15px !important;
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
                <a href="addonmodules.php?module=v2raysocks_traffic&action=user_stats" class="nav-link active">' . v2raysocks_traffic_lang('user_statistics') . '</a>
                <a href="addonmodules.php?module=v2raysocks_traffic&action=node_stats" class="nav-link">' . v2raysocks_traffic_lang('node_statistics') . '</a>
                <a href="addonmodules.php?module=v2raysocks_traffic&action=service_search" class="nav-link">' . v2raysocks_traffic_lang('service_search') . '</a>
            </div>
        </div>

        <h1>' . v2raysocks_traffic_lang('user_statistics') . '</h1>
        
        <div class="search-form">
            <form id="user-search-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="search_type">Search by:</label>
                        <select id="search_type" name="search_type">
                            <option value="user_id">User ID</option>
                            <option value="service_id">Service ID</option>
                            <option value="uuid">UUID</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="search_value">Value:</label>
                        <input type="text" id="search_value" name="search_value" placeholder="Enter search value" required>
                    </div>
                    <div class="form-group">
                        <label for="time_range">' . v2raysocks_traffic_lang('time_range') . ':</label>
                        <select id="time_range" name="time_range">
                            <option value="today">Today</option>
                            <option value="week">Last 7 Days</option>
                            <option value="month_including_today" selected>Last 30 Days</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div id="user-results" style="display: none;">
            <!-- User Information Card -->
            <div class="user-info">
                <h3>User Information</h3>
                <div class="info-grid" id="user-info-grid">
                    <div class="info-item">
                        <div class="info-label">User ID</div>
                        <div class="info-value" id="user-id-display">--</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Service ID</div>
                        <div class="info-value" id="service-id-display">--</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">UUID</div>
                        <div class="info-value" id="uuid-display">--</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Transfer Limit</div>
                        <div class="info-value" id="transfer-limit-display">--</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">SS Speed Limit</div>
                        <div class="info-value" id="ss-speed-display">--</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">V2Ray Speed Limit</div>
                        <div class="info-value" id="v2ray-speed-display">--</div>
                    </div>
                </div>
            </div>
            
            <!-- Traffic Chart -->
            <div class="chart-container">
                <h3>User Traffic Chart</h3>
                
                <!-- Chart Controls -->
                <div class="chart-controls" style="margin-bottom: 15px;">
                    <div class="control-group" style="display: inline-block; margin-right: 20px;">
                        <label for="user-chart-type">Chart Type:</label>
                        <select id="user-chart-type" style="margin-left: 5px; padding: 5px;">
                            <option value="combined" selected>Upload and Download</option>
                            <option value="total">Total Traffic</option>
                            <option value="cumulative">Cumulative Traffic</option>
                        </select>
                    </div>
                    <div class="control-group" style="display: inline-block;">
                        <label for="user-chart-unit">Unit:</label>
                        <select id="user-chart-unit" style="margin-left: 5px; padding: 5px;">
                            <option value="auto">Auto</option>
                            <option value="MB">MB</option>
                            <option value="GB" selected>GB</option>
                            <option value="TB">TB</option>
                        </select>
                    </div>
                </div>
                
                <canvas id="user-traffic-chart" width="400" height="200"></canvas>
            </div>
            
            <!-- Traffic History Table -->
            <div class="chart-container">
                <h3>Traffic History 
                    <a href="#" id="export-user-data" class="btn btn-success" style="float: right;">Export CSV</a>
                </h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Node</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="user-traffic-history">
                            <tr>
                                <td colspan="5" class="loading">Search for a user to view traffic history...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="no-results" style="display: none;">
            <div class="no-data">No user found with the provided search criteria.</div>
        </div>
    </div>

    <script>
        let userChart;
        let currentUserData = {};
        
        $(document).ready(function() {
            $("#user-search-form").on("submit", function(e) {
                e.preventDefault();
                searchUser();
            });
            
            $("#export-user-data").on("click", function(e) {
                e.preventDefault();
                if (Object.keys(currentUserData).length > 0) {
                    const params = $("#user-search-form").serialize();
                    window.open("addonmodules.php?module=v2raysocks_traffic&action=export_data&" + params);
                }
            });
        });
        
        function searchUser() {
            const searchType = $("#search_type").val();
            const searchValue = $("#search_value").val();
            const timeRange = $("#time_range").val();
            
            if (!searchValue.trim()) {
                alert("Please enter a search value");
                return;
            }
            
            $("#user-results").hide();
            $("#no-results").hide();
            $("#user-traffic-history").html("<tr><td colspan=\\"5\\" class=\\"loading\\">Searching user data...</td></tr>");
            
            const params = {
                time_range: timeRange
            };
            params[searchType] = searchValue;
            
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_traffic_data",
                type: "GET",
                data: params,
                dataType: "json",
                success: function(response) {
                    if (response.status === "success" && response.data.length > 0) {
                        currentUserData = response.data[0]; // Get first result for user info
                        displayUserInfo(currentUserData);
                        updateUserTrafficHistory(response.data);
                        updateUserTrafficChart(response.data);
                        $("#user-results").show();
                    } else {
                        $("#no-results").show();
                    }
                },
                error: function() {
                    $("#user-traffic-history").html("<tr><td colspan=\\"5\\" class=\\"loading\\">Error loading user data</td></tr>");
                }
            });
        }
        
        function displayUserInfo(userData) {
            $("#user-id-display").text(userData.user_id || "--");
            $("#service-id-display").text(userData.service_id || "--");
            $("#uuid-display").text(userData.uuid || "--");
            $("#transfer-limit-display").text(userData.transfer_enable ? formatBytes(userData.transfer_enable) : "--");
            $("#ss-speed-display").text(userData.speedlimitss || "--");
            $("#v2ray-speed-display").text(userData.speedlimitother || "--");
        }
        
        function updateUserTrafficHistory(data) {
            let html = "";
            
            if (data.length === 0) {
                html = "<tr><td colspan=\\"5\\" class=\\"no-data\\">No traffic history found</td></tr>";
            } else {
                data.forEach(function(row) {
                    html += `<tr>
                        <td>${new Date(row.t * 1000).toLocaleString()}</td>
                        <td>${row.node_name || "--"}</td>
                        <td>${formatBytes(row.u || 0)}</td>
                        <td>${formatBytes(row.d || 0)}</td>
                        <td>${formatBytes((row.u || 0) + (row.d || 0))}</td>
                    </tr>`;
                });
            }
            
            $("#user-traffic-history").html(html);
        }
        
        function updateUserTrafficChart(data) {
            // Group data by time for chart
            const timeData = {};
            
            data.forEach(function(row) {
                const date = new Date(row.t * 1000);
                // Use consistent date formatting to avoid timezone variations
                const month = String(date.getMonth() + 1).padStart(2, "0");
                const day = String(date.getDate()).padStart(2, "0");
                const year = date.getFullYear();
                const timeKey = month + "/" + day + "/" + year;
                
                if (!timeData[timeKey]) {
                    timeData[timeKey] = { upload: 0, download: 0 };
                }
                timeData[timeKey].upload += (row.u || 0);
                timeData[timeKey].download += (row.d || 0);
            });
            
            // Sort labels chronologically instead of alphabetically
            const labels = Object.keys(timeData).sort((a, b) => {
                if (a.includes(":") && !a.includes("/")) {
                    // Time format sorting (HH:MM)
                    const [aHour, aMin] = a.split(":").map(Number);
                    const [bHour, bMin] = b.split(":").map(Number);
                    return (aHour * 60 + aMin) - (bHour * 60 + bMin);
                } else if (a.includes("/")) {
                    // Date format sorting (MM/DD or MM/DD/YYYY)
                    const aParts = a.split("/").map(Number);
                    const bParts = b.split("/").map(Number);
                    
                    if (aParts.length === 3 && bParts.length === 3) {
                        // Format: MM/DD/YYYY
                        const aDate = new Date(aParts[2], aParts[0] - 1, aParts[1]);
                        const bDate = new Date(bParts[2], bParts[0] - 1, bParts[1]);
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
                    return a.localeCompare(b);
                }
            });
            const uploadData = labels.map(time => (timeData[time].upload / (1000 * 1000 * 1000)).toFixed(2));
            const downloadData = labels.map(time => (timeData[time].download / (1000 * 1000 * 1000)).toFixed(2));
            
            if (userChart) {
                userChart.destroy();
            }
            
            const ctx = document.getElementById("user-traffic-chart").getContext("2d");
            userChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: labels,
                    datasets: [
                        getStandardDatasetConfig("upload", "' . v2raysocks_traffic_lang('upload') . ' (GB)", uploadData),
                        getStandardDatasetConfig("download", "' . v2raysocks_traffic_lang('download') . ' (GB)", downloadData)
                    ]
                },
                options: {
                    responsive: true,
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
                                text: "Date"
                            }
                        }
                    },
                    plugins: {
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
    </script>
</body>
</html>';
?>