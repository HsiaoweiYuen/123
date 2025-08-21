<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include unified navigation component
require_once(__DIR__ . '/navigation_component.php');

$realTimeMonitorHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('real_time_monitor') . '</title>
    <style>
        ' . v2raysocks_traffic_getNavigationCSS() . '
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; color: #007bff; margin-bottom: 10px; }
        .stat-label { color: #6c757d; font-weight: 500; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .status-online { background-color: #28a745; }
        .status-offline { background-color: #dc3545; }
        .refresh-status { text-align: center; margin: 20px 0; color: #6c757d; }
        .filter-panel { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .filter-row { display: flex; gap: 20px; align-items: end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-weight: 500; }
        .filter-group input, .filter-group select { padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; }
        /* Make custom date inputs more compact */
        #rt-custom-dates, #rt-custom-dates-end, #rt-custom-times, #rt-custom-times-end { flex: 0 0 auto; max-width: 160px; }
        #rt-custom-dates input, #rt-custom-dates-end input, #rt-custom-times input, #rt-custom-times-end input { width: 100%; max-width: 100%; }
        .btn { padding: 8px 15px; border-radius: 4px; text-decoration: none; border: none; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background: #6c757d; color: white; }
        
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
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            .filter-group {
                width: 100%;
            }
            .refresh-status {
                font-size: 0.9em;
                text-align: left;
            }
            /* Make custom time/date inputs full width on mobile */
            #rt-custom-dates, #rt-custom-dates-end, #rt-custom-times, #rt-custom-times-end {
                max-width: 100% !important;
            }
            #today-custom-times, #today-custom-times-end {
                max-width: 100% !important;
                flex: 1 1 100% !important;
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
            .refresh-status {
                font-size: 0.8em;
            }
            /* Ensure custom time/date inputs are full width on very small devices */
            #rt-custom-dates, #rt-custom-dates-end, #rt-custom-times, #rt-custom-times-end {
                max-width: 100% !important;
            }
            #today-custom-times, #today-custom-times-end {
                max-width: 100% !important;
                flex: 1 1 100% !important;
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
        
        /* Standard styles for export modal inputs */
        #export-modal input[type="date"], 
        #export-modal input[type="time"], 
        #export-modal input[type="number"] {
            width: 200px;
            padding: 5px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        #export-modal label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        #export-modal .form-group {
            margin-bottom: 15px;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Include standardized chart colors for consistency
        ' . file_get_contents(__DIR__ . '/chart_colors.js') . '
    </script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        ' . v2raysocks_traffic_getNavigationHTML('real_time') . '

        <h1>' . v2raysocks_traffic_lang('real_time_monitor') . '</h1>
        <div class="refresh-status">
            <span class="status-indicator status-online"></span>
            <span id="refresh-status-text">Auto-refreshing every 5 seconds</span> | Last update: <span id="last-update">--</span>
        </div>
        
        <div class="stats-grid" id="real-time-stats">
            <div class="stat-card">
                <div class="stat-value" id="rt-total-users">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total_users') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-active-users-5min">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('active_users_5min') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-active-users-1hour">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('active_users_1hour') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-active-users-4hour">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('active_users_4hour') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-active-users-24h">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('active_users_24h') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-online-nodes">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('online_nodes') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-5min-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('traffic_5min') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-hourly-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('traffic_1hour') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-4hour-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('traffic_4hour') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-current-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('today_traffic') . '</div>
            </div>
        </div>
        
        <!-- Custom Time Range Controls -->
        <div class="filter-panel">
            <form id="custom-time-filter">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="rt-time-range">' . v2raysocks_traffic_lang('time_range') . ':</label>
                        <select id="rt-time-range" name="time_range">
                            <option value="today" selected>' . v2raysocks_traffic_lang('today') . '</option>
                            <option value="week">' . v2raysocks_traffic_lang('last_7_days') . '</option>
                            <option value="halfmonth">' . v2raysocks_traffic_lang('last_15_days') . '</option>
                            <option value="month_including_today">' . v2raysocks_traffic_lang('last_30_days') . '</option>
                            <option value="custom">' . v2raysocks_traffic_lang('custom_date_range') . '</option>
                            <option value="time_range">' . v2raysocks_traffic_lang('custom_time_range') . '</option>
                        </select>
                    </div>
                    <div class="filter-group" id="rt-custom-dates" style="display: none;">
                        <label for="rt-start-date">' . v2raysocks_traffic_lang('start_date') . ':</label>
                        <input type="date" id="rt-start-date" name="start_date">
                    </div>
                    <div class="filter-group" id="rt-custom-dates-end" style="display: none;">
                        <label for="rt-end-date">' . v2raysocks_traffic_lang('end_date') . ':</label>
                        <input type="date" id="rt-end-date" name="end_date">
                    </div>
                    <div class="filter-group" id="rt-custom-times" style="display: none;">
                        <label for="rt-start-time">' . v2raysocks_traffic_lang('start_time_label') . ':</label>
                        <input type="time" id="rt-start-time" name="start_time" step="1">
                    </div>
                    <div class="filter-group" id="rt-custom-times-end" style="display: none;">
                        <label for="rt-end-time">' . v2raysocks_traffic_lang('end_time_label') . ':</label>
                        <input type="time" id="rt-end-time" name="end_time" step="1">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('apply_filter') . '</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Custom Time Range Results -->
        <div class="stats-grid" id="custom-time-results">
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-upload">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('upload') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-download">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('download') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-total">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total_traffic') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-records">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('records_found') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-peak">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('peak_time') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-idle-time">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('idle_time') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-peak-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('peak_traffic') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="rt-custom-idle-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('idle_traffic') . '</div>
            </div>
        </div>

        <!-- Today Traffic Chart Section -->
        <div class="filter-panel">
            <h3>' . v2raysocks_traffic_lang('today_traffic_chart') . '</h3>
            <div class="chart-mode-controls" style="margin-bottom: 15px;">
                <div class="control-group" style="display: inline-block; margin-right: 20px;">
                    <label for="chart-display-mode" style="margin-right: 10px; font-weight: 500;">' . v2raysocks_traffic_lang('display_mode') . ':</label>
                    <select id="chart-display-mode" style="padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                        <option value="separate">' . v2raysocks_traffic_lang('upload_and_download') . '</option>
                        <option value="total">' . v2raysocks_traffic_lang('total_traffic') . '</option>
                        <option value="cumulative">' . v2raysocks_traffic_lang('cumulative_traffic') . '</option>
                        <option value="total_cumulative">' . v2raysocks_traffic_lang('total_cumulative_traffic') . '</option>
                    </select>
                </div>
                <div class="control-group" style="display: inline-block;">
                    <label for="chart-unit-select" style="margin-right: 10px; font-weight: 500;">' . v2raysocks_traffic_lang('unit') . ':</label>
                    <select id="chart-unit-select" style="padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                        <option value="auto" selected>Auto</option>
                        <option value="MB">MB</option>
                        <option value="GB">GB</option>
                        <option value="TB">TB</option>
                    </select>
                </div>
            </div>
            <div style="position: relative; height: 400px; width: 100%; overflow: hidden;">
                <canvas id="todayTrafficChart"></canvas>
            </div>
        </div>
        
        <!-- Today Traffic History Table -->
        <div class="filter-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">' . v2raysocks_traffic_lang('today_traffic_history') . '</h3>
            </div>
            
            <!-- Independent Search Controls -->
            <div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 15px;">
                <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div style="flex: 0 0 200px; min-width: 150px;">
                        <label for="today-service-id" style="display: block; margin-bottom: 5px; font-weight: 500;">' . v2raysocks_traffic_lang('service_id') . ':</label>
                        <input type="text" id="today-service-id" placeholder="' . v2raysocks_traffic_lang('enter_service_id') . '" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="flex: 0 0 200px; min-width: 150px;">
                        <label for="today-time-range" style="display: block; margin-bottom: 5px; font-weight: 500;">' . v2raysocks_traffic_lang('time_range') . ':</label>
                        <select id="today-time-range" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="today" selected>' . v2raysocks_traffic_lang('today') . '</option>
                            <option value="last_1_hour">' . v2raysocks_traffic_lang('last_hour') . '</option>
                            <option value="last_3_hours">' . v2raysocks_traffic_lang('last_3_hours') . '</option>
                            <option value="last_6_hours">' . v2raysocks_traffic_lang('last_6_hours') . '</option>
                            <option value="last_12_hours">' . v2raysocks_traffic_lang('last_12_hours') . '</option>
                            <option value="custom_time">' . v2raysocks_traffic_lang('custom_time_range') . '</option>
                        </select>
                    </div>
                    <div class="filter-group" id="today-custom-times" style="display: none; flex: 0 0 auto; max-width: 160px;">
                        <label for="today-start-time">' . v2raysocks_traffic_lang('start_time_label') . ':</label>
                        <input type="time" id="today-start-time" name="start_time" step="1" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div class="filter-group" id="today-custom-times-end" style="display: none; flex: 0 0 auto; max-width: 160px;">
                        <label for="today-end-time">' . v2raysocks_traffic_lang('end_time_label') . ':</label>
                        <input type="time" id="today-end-time" name="end_time" step="1" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="search-today-traffic" class="btn btn-primary">' . v2raysocks_traffic_lang('search') . '</button>
                        <button id="export-today-data" class="btn btn-success">' . v2raysocks_traffic_lang('export_data') . '</button>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>' . v2raysocks_traffic_lang('time') . '</th>
                            <th>' . v2raysocks_traffic_lang('service_id') . '</th>
                            <th>' . v2raysocks_traffic_lang('user_id') . '</th>
                            <th>' . v2raysocks_traffic_lang('uuid') . '</th>
                            <th>' . v2raysocks_traffic_lang('node_name') . '</th>
                            <th>' . v2raysocks_traffic_lang('upload') . '</th>
                            <th>' . v2raysocks_traffic_lang('download') . '</th>
                            <th>' . v2raysocks_traffic_lang('total') . '</th>
                            <th>' . v2raysocks_traffic_lang('ss_limit') . '</th>
                            <th>' . v2raysocks_traffic_lang('v2ray_limit') . '</th>
                            <th>' . v2raysocks_traffic_lang('violation_count') . '</th>
                        </tr>
                    </thead>
                    <tbody id="today-traffic-data">
                        <tr>
                            <td colspan="11" class="loading">' . v2raysocks_traffic_lang('loading') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls for Today Traffic -->
            <div id="today-pagination-controls" style="margin-top: 15px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span id="today-pagination-info">' . v2raysocks_traffic_lang('showing_records') . '</span>
                    </div>
                    <div>
                        <label for="today-records-per-page" style="margin-right: 10px;">' . v2raysocks_traffic_lang('records_per_page_label') . ':</label>
                        <select id="today-records-per-page" style="margin-right: 15px; padding: 5px;">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        
                        <button id="today-first-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('first_page') . '</button>
                        <button id="today-prev-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('previous_page') . '</button>
                        <span id="today-page-info" style="margin: 0 10px;">' . v2raysocks_traffic_lang('page_info') . '</span>
                        <button id="today-next-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('next_page') . '</button>
                        <button id="today-last-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('last_page') . '</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let refreshInterval;
        let chartRefreshInterval;
        let moduleConfig = {
            realtime_refresh_interval: 5,
            default_unit: "auto",
            chart_unit: "auto"
        };
        
        // Unified date formatting function - returns YYYY-MM-DD HH:MM:SS format
        function formatDateTime(timestamp) {
            const date = new Date(timestamp * 1000);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            const hours = String(date.getHours()).padStart(2, "0");
            const minutes = String(date.getMinutes()).padStart(2, "0");
            const seconds = String(date.getSeconds()).padStart(2, "0");
            return year + "-" + month + "-" + day + " " + hours + ":" + minutes + ":" + seconds;
        }
        
        // Load module configuration
        function loadModuleConfig() {
            return $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_module_config",
                type: "GET",
                dataType: "json",
                success: function(response) {
                    if (response.status === "success" && response.data) {
                        moduleConfig = response.data;
                        // Update refresh status text
                        const interval = parseInt(moduleConfig.realtime_refresh_interval || 5);
                        const intervalText = interval >= 60 ? 
                            (interval / 60) + " minute" + (interval > 60 ? "s" : "") :
                            interval + " second" + (interval > 1 ? "s" : "");
                        $("#refresh-status-text").text("Auto-refreshing every " + intervalText);
                        
                        // Set chart unit selector to module configuration
                        if (moduleConfig.chart_unit) {
                            $("#chart-unit-select").val(moduleConfig.chart_unit);
                        }
                    }
                },
                error: function() {
                    console.warn("Failed to load module configuration, using defaults");
                }
            });
        }
        
        function loadRealTimeStats() {
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_live_stats",
                type: "GET",
                dataType: "json",
                success: function(response) {
                    if (response.status === "success") {
                        const data = response.data;
                        $("#rt-total-users").text(data.total_users || 0);
                        
                        // Handle multiple active user timeframes
                        if (data.active_users) {
                            $("#rt-active-users-5min").text(data.active_users["5min"] || 0);
                            $("#rt-active-users-1hour").text(data.active_users["1hour"] || 0);
                            $("#rt-active-users-4hour").text(data.active_users["4hour"] || 0);
                            $("#rt-active-users-24h").text(data.active_users["24hours"] || 0);
                        }
                        
                        $("#rt-online-nodes").text((data.online_nodes || 0) + "/" + (data.total_nodes || 0));
                        
                        const totalTraffic = (data.today_upload || 0) + (data.today_download || 0);
                        $("#rt-current-traffic").text(formatBytes(totalTraffic));
                        
                        // Handle traffic periods
                        if (data.traffic_periods) {
                            $("#rt-5min-traffic").text(formatBytes(data.traffic_periods["5min"]?.total || 0));
                            $("#rt-hourly-traffic").text(formatBytes(data.traffic_periods["1hour"]?.total || 0));
                            $("#rt-4hour-traffic").text(formatBytes(data.traffic_periods["4hour"]?.total || 0));
                        }
                        
                        $("#last-update").text(new Date().toLocaleTimeString());
                        $(".status-indicator").removeClass("status-offline").addClass("status-online");
                    }
                },
                error: function() {
                    $(".status-indicator").removeClass("status-online").addClass("status-offline");
                    $("#last-update").text("Error");
                }
            });
        }
        
        function loadCustomTimeRangeData() {
            let params = $("#custom-time-filter").serialize() + "&grouped=true";
            
            // Handle time_range option by adding timestamp parameters
            const timeRange = $("#rt-time-range").val();
            if (timeRange === "time_range") {
                const startTime = $("#rt-start-time").val();
                const endTime = $("#rt-end-time").val();
                
                if (startTime && endTime) {
                    // Convert time to today date + time for timestamp calculation
                    const today = new Date();
                    const todayStr = today.getFullYear() + "-" + 
                                    (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                    today.getDate().toString().padStart(2, "0");
                    
                    const startDateTime = todayStr + " " + startTime;
                    const endDateTime = todayStr + " " + endTime;
                    
                    const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                    const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                    
                    params += "&start_timestamp=" + startTimestamp + "&end_timestamp=" + endTimestamp;
                }
            }
            
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_traffic_data",
                type: "GET",
                data: params,
                dataType: "json",
                timeout: 15000,
                success: function(response) {
                    console.log("Custom time range response:", response);
                    if (response.status === "success" && response.data) {
                        let totalUpload = 0;
                        let totalDownload = 0;
                        let recordCount = response.data.length;
                        
                        // Use server-side grouped data for peak calculation (PR#37 pattern)
                        let timeStats = {};
                        if (response.grouped_data) {
                            // Use pre-grouped data from server (avoids client-side timezone issues)
                            Object.keys(response.grouped_data).forEach(function(timeKey) {
                                const groupData = response.grouped_data[timeKey];
                                timeStats[timeKey] = groupData.total;
                            });
                        }
                        
                        // Calculate totals from raw data
                        response.data.forEach(function(row) {
                            const upload = parseFloat(row.u) || 0;
                            const download = parseFloat(row.d) || 0;
                            
                            totalUpload += upload;
                            totalDownload += download;
                        });
                        
                        // Find peak time and idle time
                        let peakTime = "";
                        let peakTraffic = 0;
                        let idleTime = "";
                        let idleTraffic = Number.MAX_VALUE;
                        
                        for (const [time, traffic] of Object.entries(timeStats)) {
                            if (traffic > peakTraffic) {
                                peakTraffic = traffic;
                                peakTime = time;
                            }
                            if (traffic < idleTraffic && traffic > 0) {
                                idleTraffic = traffic;
                                idleTime = time;
                            }
                        }
                        
                        // If no valid idle traffic found, set to 0
                        if (idleTraffic === Number.MAX_VALUE) {
                            idleTraffic = 0;
                        }
                        
                        $("#rt-custom-upload").text(formatBytes(totalUpload));
                        $("#rt-custom-download").text(formatBytes(totalDownload));
                        $("#rt-custom-total").text(formatBytes(totalUpload + totalDownload));
                        $("#rt-custom-records").text(recordCount.toLocaleString());
                        
                        // Add peak time display (only show time/date without text)
                        const peakDisplay = peakTime || "";
                        $("#rt-custom-peak").text(peakDisplay);
                        
                        // Add idle time display
                        const idleDisplay = idleTime || "";
                        $("#rt-custom-idle-time").text(idleDisplay);
                        
                        // Add peak traffic display
                        $("#rt-custom-peak-traffic").text(formatBytes(peakTraffic));
                        
                        // Add idle traffic display
                        $("#rt-custom-idle-traffic").text(formatBytes(idleTraffic));
                        
                        $("#custom-time-results").show();
                    } else {
                        console.error("Custom time range error:", response);
                        $("#rt-custom-upload").text("Error");
                        $("#rt-custom-download").text("Error");
                        $("#rt-custom-total").text("Error");
                        $("#rt-custom-records").text("Error");
                        $("#rt-custom-peak").text("Error");
                        $("#rt-custom-idle-time").text("Error");
                        $("#rt-custom-peak-traffic").text("Error");
                        $("#rt-custom-idle-traffic").text("Error");
                        $("#custom-time-results").show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error loading custom time range data:", status, error);
                    $("#rt-custom-upload").text("Error");
                    $("#rt-custom-download").text("Error");
                    $("#rt-custom-total").text("Error");
                    $("#rt-custom-records").text("Error");
                    $("#rt-custom-peak").text("Error");
                    $("#rt-custom-idle-time").text("Error");
                    $("#rt-custom-peak-traffic").text("Error");
                    $("#rt-custom-idle-traffic").text("Error");
                    $("#custom-time-results").show();
                }
            });
        }
        
        // Today Traffic Chart functionality
        let todayTrafficChart = null;
        
        function initTodayTrafficChart() {
            const ctx = document.getElementById("todayTrafficChart").getContext("2d");
            todayTrafficChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: 10
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "' . v2raysocks_traffic_lang('traffic') . ' (GB)"
                            },
                            ticks: {
                                callback: function(value, index, values) {
                                    // Show only numeric values without units on Y-axis
                                    // The value is already in the correct unit from chart data processing
                                    return value.toFixed(2);
                                }
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
                        legend: {
                            display: true,
                            position: "top"
                        },
                        tooltip: {
                            mode: "nearest",
                            intersect: true,
                            callbacks: {
                                title: function(context) {
                                    return "' . v2raysocks_traffic_lang('time') . ': " + context[0].label;
                                },
                                label: function(context) {
                                    const label = context.dataset.label || "";
                                    const value = context.parsed.y;
                                    const unit = label.match(/\\(([^)]+)\\)/);
                                    const unitText = unit ? unit[1] : "GB";
                                    
                                    // Use translation functions instead of regex replacement
                                    let cleanLabel;
                                    if (label.includes("upload") || label.includes("上传") || label.includes("上傳")) {
                                        cleanLabel = "' . v2raysocks_traffic_lang('upload') . '";
                                    } else if (label.includes("download") || label.includes("下载") || label.includes("下載")) {
                                        cleanLabel = "' . v2raysocks_traffic_lang('download') . '";
                                    } else if (label.includes("total") || label.includes("总") || label.includes("總")) {
                                        cleanLabel = "' . v2raysocks_traffic_lang('total_traffic') . '";
                                    } else if (label.includes("cumulative")) {
                                        if (label.includes("upload") || label.includes("上传") || label.includes("上傳")) {
                                            cleanLabel = "' . v2raysocks_traffic_lang('cumulative_upload') . '";
                                        } else if (label.includes("download") || label.includes("下载") || label.includes("下載")) {
                                            cleanLabel = "' . v2raysocks_traffic_lang('cumulative_download') . '";
                                        } else {
                                            cleanLabel = "' . v2raysocks_traffic_lang('total_cumulative_traffic') . '";
                                        }
                                    } else {
                                        // Fallback: remove unit parentheses as before
                                        cleanLabel = label.replace(/\\s*\\([^)]*\\)/, "");
                                    }
                                    
                                    return cleanLabel + "：" + value.toFixed(2) + " " + unitText;
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: true,
                        mode: "nearest"
                    }
                }
            });
        }
        
        function loadTodayTrafficData() {
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_today_traffic_data",
                type: "GET",
                dataType: "json",
                timeout: 10000,
                success: function(response) {
                    if (response.status === "success" && response.data) {
                        updateTodayTrafficChart(response.data);
                    } else {
                        console.error("Today traffic data error:", response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Today traffic data request failed:", error);
                }
            });
        }
        
        // Generate complete time series to prevent chart discontinuity
        function generateCompleteTimeSeriesForRealTimeChart(timeRange) {
            const labels = [];
            const now = new Date();
            
            switch (timeRange) {
                case "today":
                    // Generate hours up to current time only
                    const currentHour = now.getHours();
                    for (let hour = 0; hour <= currentHour; hour++) {
                        labels.push(hour.toString().padStart(2, "0") + ":00");
                    }
                    break;
                    
                // Future expansion for other time ranges can be added here
                default:
                    // Default to today range
                    const defaultCurrentHour = now.getHours();
                    for (let hour = 0; hour <= defaultCurrentHour; hour++) {
                        labels.push(hour.toString().padStart(2, "0") + ":00");
                    }
                    break;
            }
            
            return labels;
        }

        // Generate default time labels for empty charts
        function generateDefaultHourlyLabels(points = 8) {
            const now = new Date();
            const labels = [];
            const hours = [];
            
            for (let i = points - 1; i >= 0; i--) {
                const time = new Date(now.getTime() - (i * 3 * 60 * 60 * 1000)); // 3-hour intervals
                const hour = time.getHours().toString().padStart(2, "0");
                labels.push(hour + ":00");
                hours.push(hour);
            }
            
            return { labels, hours };
        }

        function updateTodayTrafficChart(data) {
            if (!todayTrafficChart) return;
            
            const mode = $("#chart-display-mode").val();
            let unit = $("#chart-unit-select").val() || "GB";
            
            // Generate complete time series to prevent chart discontinuity
            const labels = generateCompleteTimeSeriesForRealTimeChart("today");
            
            // Fill missing time points with zero values
            const timeData = {};
            labels.forEach(timeKey => {
                if (!timeData[timeKey]) {
                    timeData[timeKey] = { upload: 0, download: 0 };
                }
            });
            
            // Fill in actual data where available
            if (data.hourly_stats) {
                Object.keys(data.hourly_stats).forEach(hour => {
                    const timeKey = hour.toString().padStart(2, "0") + ":00";
                    if (timeData[timeKey]) {
                        const stats = data.hourly_stats[hour];
                        timeData[timeKey] = {
                            upload: stats.upload || 0,
                            download: stats.download || 0
                        };
                    }
                });
            }
            
            // Collect all data points for auto unit determination
            let allDataPoints = [];
            if (unit === "auto") {
                labels.forEach(timeKey => {
                    const stats = timeData[timeKey];
                    allDataPoints.push(stats.upload);
                    allDataPoints.push(stats.download);
                    allDataPoints.push(stats.upload + stats.download);
                });
                unit = getBestUnitForData(allDataPoints);
            }
            
            const unitDivisor = getUnitDivisor(unit);
            let datasets = [];
            
            switch(mode) {
                case "separate":
                    datasets = [
                        getStandardDatasetConfig("upload", "' . v2raysocks_traffic_lang('upload') . ' (" + unit + ")", labels.map(timeKey => {
                            const stats = timeData[timeKey];
                            return stats.upload / unitDivisor;
                        }), {fill: true}),
                        getStandardDatasetConfig("download", "' . v2raysocks_traffic_lang('download') . ' (" + unit + ")", labels.map(timeKey => {
                            const stats = timeData[timeKey];
                            return stats.download / unitDivisor;
                        }), {fill: true})
                    ];
                    break;
                case "total":
                    datasets = [
                        getStandardDatasetConfig("total", "' . v2raysocks_traffic_lang('total_traffic') . ' (" + unit + ")", labels.map(timeKey => {
                            const stats = timeData[timeKey];
                            return (stats.upload + stats.download) / unitDivisor;
                        }), {fill: true})
                    ];
                    break;
                case "cumulative":
                    let cumulativeUpload = 0;
                    let cumulativeDownload = 0;
                    datasets = [
                        getStandardDatasetConfig("upload", "' . v2raysocks_traffic_lang('cumulative_upload') . ' (" + unit + ")", labels.map(timeKey => {
                            const stats = timeData[timeKey];
                            cumulativeUpload += stats.upload;
                            return cumulativeUpload / unitDivisor;
                        }), {fill: true}),
                        getStandardDatasetConfig("download", "' . v2raysocks_traffic_lang('cumulative_download') . ' (" + unit + ")", labels.map(timeKey => {
                            const stats = timeData[timeKey];
                            cumulativeDownload += stats.download;
                            return cumulativeDownload / unitDivisor;
                        }), {fill: true})
                    ];
                    break;
                case "total_cumulative":
                    let cumulativeTotal = 0;
                    datasets = [
                        getStandardDatasetConfig("total", "' . v2raysocks_traffic_lang('total_cumulative_traffic') . ' (" + unit + ")", labels.map(timeKey => {
                            const stats = timeData[timeKey];
                            cumulativeTotal += stats.upload + stats.download;
                            return cumulativeTotal / unitDivisor;
                        }), {fill: true})
                    ];
                    break;
            }
            
            todayTrafficChart.data.labels = labels;
            todayTrafficChart.data.datasets = datasets;
            
            // Update axis title with current unit
            todayTrafficChart.options.scales.y.title.text = "' . v2raysocks_traffic_lang('traffic') . ' (" + unit + ")";
            
            todayTrafficChart.update();
        }
        
        // Initialize everything on document ready
        $(document).ready(function() {
            // Load module configuration first, then initialize everything
            loadModuleConfig().done(function() {
                const realtimeRefreshMs = parseInt(moduleConfig.realtime_refresh_interval || 5) * 1000;
                const chartRefreshMs = Math.max(realtimeRefreshMs * 2, 30000); // Chart refreshes at least every 30 seconds, or 2x real-time interval
                
                // Initialize today traffic chart first
                initTodayTrafficChart();
                
                // Load initial data
                refreshAllData();
                
                // Set up intervals with configured values for unified refresh
                refreshInterval = setInterval(refreshAllData, realtimeRefreshMs);
                chartRefreshInterval = setInterval(loadTodayTrafficData, chartRefreshMs);
                
                // Load today data by default
                loadCustomTimeRangeData();
            }).fail(function() {
                // Fallback to default intervals if config loading fails
                // Initialize today traffic chart first
                initTodayTrafficChart();
                
                refreshAllData();
                refreshInterval = setInterval(refreshAllData, 5000);
                chartRefreshInterval = setInterval(loadTodayTrafficData, 30000);
            });
            
            // Chart mode change handler
            $("#chart-display-mode").on("change", function() {
                loadTodayTrafficData();
            });
            
            // Chart unit change handler
            $("#chart-unit-select").on("change", function() {
                loadTodayTrafficData();
            });
            
            // Custom time range form submission
            $("#custom-time-filter").on("submit", function(e) {
                e.preventDefault();
                loadCustomTimeRangeData();
            });
            
            // Time range change handler
            $("#rt-time-range").on("change", function() {
                const value = $(this).val();
                if (value === "custom") {
                    $("#rt-custom-dates, #rt-custom-dates-end").show();
                    $("#rt-custom-times, #rt-custom-times-end").hide();
                } else if (value === "time_range") {
                    $("#rt-custom-times, #rt-custom-times-end").show();
                    $("#rt-custom-dates, #rt-custom-dates-end").hide();
                } else {
                    $("#rt-custom-dates, #rt-custom-dates-end").hide();
                    $("#rt-custom-times, #rt-custom-times-end").hide();
                }
                // Removed auto-load - data only updates when Apply button is clicked
            });
            
            // Today Traffic History event handlers
            $("#search-today-traffic").on("click", function() {
                todayCurrentPage = 1; // Reset to first page
                loadTodayTrafficHistory();
            });
            
            // Today time range change handler
            $("#today-time-range").on("change", function() {
                const value = $(this).val();
                if (value === "custom_time") {
                    $("#today-custom-times, #today-custom-times-end").show();
                } else {
                    $("#today-custom-times, #today-custom-times-end").hide();
                }
            });
            
            $("#export-today-data").on("click", function(e) {
                e.preventDefault();
                $("#export-modal").show();
            });
            
            $("#cancel-export").on("click", function() {
                $("#export-modal").hide();
            });
            
            // Export type change handlers
            $("input[name=\'export_type\']").on("change", function() {
                const type = $(this).val();
                $("#limit-options").toggle(type === "limited");
                $("#date-range-options").toggle(type === "date_range");
            });
            
            // Export form submission
            $("#export-form").on("submit", function(e) {
                e.preventDefault();
                
                const serviceId = $("#today-service-id").val().trim();
                const timeRange = $("#today-time-range").val();
                const exportType = $("input[name=\'export_type\']:checked").val();
                const format = $("#export_format").val();
                
                let exportParams = "time_range=" + timeRange + "&format=" + format + "&export_type=" + exportType;
                
                // Apply current search filters
                if (serviceId) {
                    exportParams += "&service_id=" + encodeURIComponent(serviceId);
                }
                
                // Add specific export options
                if (exportType === "limited") {
                    const limitCount = $("#limit_count").val();
                    exportParams += "&limit_count=" + limitCount;
                } else if (exportType === "date_range") {
                    // For real-time monitor, use time inputs to create todays date ranges
                    const startTime = $("#export_start_time").val();
                    const endTime = $("#export_end_time").val();
                    
                    if (startTime) {
                        // Convert time to today date + time using server local time
                        const today = new Date();
                        const todayStr = today.getFullYear() + "-" + 
                                        (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                        today.getDate().toString().padStart(2, "0");
                        const startDateTime = todayStr + " " + startTime;
                        const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                        exportParams += "&export_start_timestamp=" + startTimestamp;
                    }
                    
                    if (endTime) {
                        // Convert time to today date + time using server local time
                        const today = new Date();
                        const todayStr = today.getFullYear() + "-" + 
                                        (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                        today.getDate().toString().padStart(2, "0");
                        const endDateTime = todayStr + " " + endTime;
                        const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                        exportParams += "&export_end_timestamp=" + endTimestamp;
                    }
                }
                
                // Trigger download
                window.open("addonmodules.php?module=v2raysocks_traffic&action=export_data&" + exportParams);
                
                // Hide modal
                $("#export-modal").hide();
            });
            
            // Today traffic pagination event handlers
            $("#today-records-per-page").on("change", function() {
                todayRecordsPerPage = parseInt($(this).val());
                todayCurrentPage = 1;
                updateTodayPaginatedTable();
            });
            
            $("#today-first-page").on("click", function() {
                todayCurrentPage = 1;
                updateTodayPaginatedTable();
            });
            
            $("#today-prev-page").on("click", function() {
                if (todayCurrentPage > 1) {
                    todayCurrentPage--;
                    updateTodayPaginatedTable();
                }
            });
            
            $("#today-next-page").on("click", function() {
                if (todayCurrentPage < todayTotalPages) {
                    todayCurrentPage++;
                    updateTodayPaginatedTable();
                }
            });
            
            $("#today-last-page").on("click", function() {
                todayCurrentPage = todayTotalPages;
                updateTodayPaginatedTable();
            });
        });
        
        function formatBytes(bytes, forceUnit = null) {
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
            
            // Use force unit from parameter or module config
            const unit = forceUnit || (moduleConfig.default_unit !== "auto" ? moduleConfig.default_unit : "auto");
            
            // If specific unit is requested, use it
            if (unit !== "auto") {
                switch (unit.toUpperCase()) {
                    case "MB":
                        return (size / 1000000).toFixed(2) + " MB";
                    case "GB":
                        return (size / 1000000000).toFixed(2) + " GB";
                    case "TB":
                        return (size / 1000000000000).toFixed(2) + " TB";
                    case "PB":
                        return (size / 1000000000000000).toFixed(2) + " PB";
                    case "EB":
                        return (size / 1000000000000000000).toFixed(2) + " EB";
                    case "KB":
                        return (size / 1000).toFixed(2) + " KB";
                }
            }
            
            // Auto unit selection (existing logic)
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
        
        function formatBytesToUnit(bytes, unit) {
            if (bytes === null || bytes === undefined || bytes === "" || isNaN(bytes)) {
                return "0 " + unit;
            }
            
            const size = Math.abs(parseFloat(bytes));
            if (size === 0) {
                return "0 " + unit;
            }
            
            const divisor = getUnitDivisor(unit);
            const value = size / divisor;
            return value.toFixed(2) + " " + unit;
        }
        
        function getUnitDivisor(unit) {
            switch (unit.toUpperCase()) {
                case "KB": return 1000;
                case "MB": return 1000000;
                case "GB": return 1000000000;
                case "TB": return 1000000000000;
                case "PB": return 1000000000000000;
                case "EB": return 1000000000000000000;
                default: return 1000000000; // Default to GB
            }
        }
        
        function getBestUnitForData(dataArray) {
            if (!dataArray || dataArray.length === 0) return "GB";
            
            const maxValue = Math.max(...dataArray.filter(v => v && !isNaN(v)));
            
            if (maxValue < 1000000) return "KB";
            else if (maxValue < 1000000000) return "MB";
            else if (maxValue < 1000000000000) return "GB";
            else if (maxValue < 1000000000000000) return "TB";
            else if (maxValue < 1000000000000000000) return "PB";
            else return "EB";
        }
        
        // Today Traffic History Table functionality
        let allTodayData = [];
        let todayCurrentPage = 1;
        let todayRecordsPerPage = 50;
        let todayTotalPages = 1;
        
        function loadTodayTrafficHistory() {
            const serviceId = $("#today-service-id").val().trim();
            const timeRange = $("#today-time-range").val();
            
            let params = {
                time_range: timeRange
            };
            
            if (serviceId) {
                params.service_id = serviceId;
            }
            
            // Handle custom time option by adding timestamp parameters
            if (timeRange === "custom_time") {
                const startTime = $("#today-start-time").val();
                const endTime = $("#today-end-time").val();
                
                if (startTime && endTime) {
                    // Convert time to today date + time for timestamp calculation
                    const today = new Date();
                    const todayStr = today.getFullYear() + "-" + 
                                    (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                    today.getDate().toString().padStart(2, "0");
                    
                    const startDateTime = todayStr + " " + startTime;
                    const endDateTime = todayStr + " " + endTime;
                    
                    const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                    const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                    
                    params.start_timestamp = startTimestamp;
                    params.end_timestamp = endTimestamp;
                }
            }
            
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_traffic_data",
                type: "GET",
                data: params,
                dataType: "json",
                timeout: 15000,
                success: function(response) {
                    if (response.status === "success") {
                        allTodayData = response.data || [];
                        updateTodayPaginatedTable();
                    } else {
                        $("#today-traffic-data").html("<tr><td colspan=\\"11\\" class=\\"loading\\">Error: " + (response.message || "Unknown error") + "</td></tr>");
                        $("#today-pagination-controls").hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Today traffic history load error:", error);
                    $("#today-traffic-data").html("<tr><td colspan=\\"11\\" class=\\"loading\\">Error loading today traffic data</td></tr>");
                    $("#today-pagination-controls").hide();
                }
            });
        }
        
        function updateTodayPaginatedTable() {
            let html = "";
            
            if (allTodayData.length === 0) {
                html = "<tr><td colspan=\\"11\\" class=\\"no-data\\">No traffic data found for today</td></tr>";
                $("#today-pagination-controls").hide();
            } else {
                // Calculate pagination
                todayTotalPages = Math.ceil(allTodayData.length / todayRecordsPerPage);
                const startIndex = (todayCurrentPage - 1) * todayRecordsPerPage;
                const endIndex = Math.min(startIndex + todayRecordsPerPage, allTodayData.length);
                const pageData = allTodayData.slice(startIndex, endIndex);
                
                // Generate table rows for current page
                pageData.forEach(function(row) {
                    html += `<tr>
                        <td>${formatDateTime(row.t)}</td>
                        <td>${row.service_id || "-"}</td>
                        <td>${row.user_id || "-"}</td>
                        <td class="uuid-column" title="${row.uuid || "-"}">${row.uuid || "-"}</td>
                        <td>${row.node_name || "-"}</td>
                        <td>${formatBytes(row.u || 0)}</td>
                        <td>${formatBytes(row.d || 0)}</td>
                        <td>${formatBytes((row.u || 0) + (row.d || 0))}</td>
                        <td>${row.speedlimitss || "-"}</td>
                        <td>${row.speedlimitother || "-"}</td>
                        <td>${row.illegal || "0"}</td>
                    </tr>`;
                });
                
                // Update pagination controls
                $("#today-pagination-info").text("' . v2raysocks_traffic_lang('showing_records') . '".replace("{start}", startIndex + 1).replace("{end}", endIndex).replace("{total}", allTodayData.length));
                $("#today-page-info").text("' . v2raysocks_traffic_lang('page_info') . '".replace("{current}", todayCurrentPage).replace("{total}", todayTotalPages));
                
                // Enable/disable pagination buttons
                $("#today-first-page, #today-prev-page").prop("disabled", todayCurrentPage === 1);
                $("#today-next-page, #today-last-page").prop("disabled", todayCurrentPage === todayTotalPages);
                
                $("#today-pagination-controls").show();
            }
            
            $("#today-traffic-data").html(html);
        }
        
        // Global refresh function that updates all data on the page
        function refreshAllData() {
            loadRealTimeStats();
            loadCustomTimeRangeData();
            loadTodayTrafficData();
            loadTodayTrafficHistory(); // Add today traffic history refresh
        }
    </script>
    
        <!-- Export Options Modal -->
        <div id="export-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 400px;">
                <h4>' . v2raysocks_traffic_lang('export_data') . '</h4>
                <form id="export-form">
                    <div style="margin-bottom: 15px;">
                        <label>' . v2raysocks_traffic_lang('export_type') . '</label><br>
                        <label><input type="radio" name="export_type" value="all" checked> ' . v2raysocks_traffic_lang('all_filtered_data') . '</label><br>
                        <label><input type="radio" name="export_type" value="limited"> ' . v2raysocks_traffic_lang('limited_number_of_records') . '</label><br>
                        <label><input type="radio" name="export_type" value="date_range"> ' . v2raysocks_traffic_lang('custom_time_range') . '</label>
                    </div>
                    
                    <div id="limit-options" style="margin-bottom: 15px; display: none;">
                        <label for="limit_count">' . v2raysocks_traffic_lang('number_of_records') . '</label>
                        <input type="number" id="limit_count" name="limit_count" value="1000" min="1" max="10000">
                    </div>
                    
                    <div id="date-range-options" style="margin-bottom: 15px; display: none;">
                        <label for="export_start_time">' . v2raysocks_traffic_lang('start_time_label') . ':</label>
                        <input type="time" id="export_start_time" name="export_start_time" step="1"><br><br>
                        <label for="export_end_time">' . v2raysocks_traffic_lang('end_time_label') . ':</label>
                        <input type="time" id="export_end_time" name="export_end_time" step="1">
                        <br><small style="color: #6c757d; margin-top: 5px; display: block;">' . v2raysocks_traffic_lang('time_range_today_only') . '</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="export_format">' . v2raysocks_traffic_lang('format') . '</label>
                        <select id="export_format" name="format">
                            <option value="excel" selected>' . v2raysocks_traffic_lang('excel') . '</option>
                            <option value="csv">' . v2raysocks_traffic_lang('csv') . '</option>
                            <option value="json">' . v2raysocks_traffic_lang('json') . '</option>
                        </select>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" id="cancel-export" class="btn" style="margin-right: 10px;">' . v2raysocks_traffic_lang('cancel') . '</button>
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('export') . '</button>
                    </div>
                </form>
            </div>
        </div>
</body>
</html>';
?>