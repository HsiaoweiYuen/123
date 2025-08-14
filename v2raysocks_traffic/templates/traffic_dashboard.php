<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include unified navigation component
require_once(__DIR__ . '/navigation_component.php');

$trafficDashboardHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('traffic_dashboard') . '</title>
    <style>
        ' . v2raysocks_traffic_getNavigationCSS() . '
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            flex: 1;
            min-width: 250px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }
        .chart-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .filter-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }
        .filter-group {
            flex: 0 0 auto;
            min-width: 120px;
        }
        /* Compact layout for time inputs */
        .filter-group#custom-dates,
        .filter-group#custom-dates-end {
            flex: 1 1 auto;
            min-width: auto;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .filter-group input, .filter-group select {
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
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .table-responsive {
            overflow-x: auto;
            min-width: 100%;
        }
        .table {
            width: 100%;
            min-width: 800px; /* Ensure minimum table width for proper layout */
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
        .refresh-indicator {
            color: #28a745;
            margin-left: 10px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        /* Responsive styles for mobile devices */
        @media (max-width: 768px) {
            .stats-row {
                flex-direction: column;
                gap: 15px;
            }
            .stat-card {
                min-width: auto;
                margin: 0;
            }
            .filter-row {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-start;
            }
            .filter-group {
                min-width: auto;
                width: auto;
                flex: 0 0 auto;
            }
            /* Optimize filter layout for mobile - make inputs more compact */
            .filter-group:not(#custom-dates):not(#custom-dates-end) {
                flex: 1 1 calc(50% - 4px);
                min-width: 120px;
            }
            .filter-group#custom-dates,
            .filter-group#custom-dates-end {
                flex: 1 1 calc(50% - 4px);
                min-width: 140px;
            }
            /* Filter button should be full width on mobile */
            .filter-group:last-child {
                flex: 1 1 100%;
                margin-top: 5px;
            }
            .table-responsive {
                font-size: 0.9em;
            }
            .table {
                min-width: 300px; /* Reduced from 800px for mobile */
            }
            .table th, .table td {
                padding: 8px 4px;
                font-size: 0.9em;
            }
        }
        
        /* Responsive styles for very small devices */
        @media (max-width: 480px) {
            .stat-value {
                font-size: 1.5em;
            }
            .stat-card {
                padding: 15px;
            }
            .filter-panel, .navigation-bar {
                padding: 10px;
            }
            /* Stack filter elements vertically on very small screens */
            .filter-row {
                flex-direction: column;
                gap: 8px;
            }
            .filter-group {
                width: 100%;
                flex: 1 1 auto;
            }
            .filter-group#custom-dates,
            .filter-group#custom-dates-end {
                flex: 1 1 auto;
                min-width: auto;
            }
            .filter-group#custom-dates input,
            .filter-group#custom-dates-end input {
                width: 100%;
            }
            .table th, .table td {
                padding: 6px 2px;
                font-size: 0.8em;
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
        
        // Include unified chart utilities
        ' . file_get_contents(__DIR__ . '/unified_chart_utils.js') . '
        
        // Function to get main page time range for export validation
        function getMainPageTimeRange() {
            const timeRange = document.getElementById("time-range").value;
            const today = new Date();
            let startDate, endDate;
            
            switch(timeRange) {
                case "today":
                    startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "week":
                    startDate = new Date(today.getTime() - 6 * 24 * 60 * 60 * 1000);
                    startDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "halfmonth":
                    startDate = new Date(today.getTime() - 14 * 24 * 60 * 60 * 1000);
                    startDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "month_including_today":
                    startDate = new Date(today.getTime() - 29 * 24 * 60 * 60 * 1000);
                    startDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "custom":
                    const startDateInput = document.getElementById("start-date").value;
                    const endDateInput = document.getElementById("end-date").value;
                    if (startDateInput && endDateInput) {
                        startDate = new Date(startDateInput);
                        endDate = new Date(endDateInput + " 23:59:59");
                    } else {
                        return null; // Invalid custom range
                    }
                    break;
                default:
                    return null;
            }
            
            return { start: startDate, end: endDate };
        }
        
        // Function to validate export time range against main page bounds
        function validateExportTimeRange(exportStartDate, exportEndDate) {
            const mainRange = getMainPageTimeRange();
            if (!mainRange) {
                alert("Please set a valid time range on the main page before exporting.");
                return false;
            }
            
            if (exportStartDate < mainRange.start || exportEndDate > mainRange.end) {
                const mainStartStr = mainRange.start.getFullYear() + "/" + 
                                   String(mainRange.start.getMonth() + 1).padStart(2, "0") + "/" + 
                                   String(mainRange.start.getDate()).padStart(2, "0");
                const mainEndStr = mainRange.end.getFullYear() + "/" + 
                                 String(mainRange.end.getMonth() + 1).padStart(2, "0") + "/" + 
                                 String(mainRange.end.getDate()).padStart(2, "0");
                alert("Export time range must be within the main page search range (" + mainStartStr + " to " + mainEndStr + ").");
                return false;
            }
            
            return true;
        }
    </script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        ' . v2raysocks_traffic_getNavigationHTML('') . '

        <h1>' . v2raysocks_traffic_lang('traffic_dashboard') . ' <span id="refresh-indicator" class="refresh-indicator"></span></h1>
        
        <!-- Live Statistics -->
        <div class="stats-row" id="live-stats">
            <div class="stat-card">
                <div class="stat-value" id="total-users">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total_users') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="active-users-5min">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('active_users_5min') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="active-users-1hour">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('active_users_1hour') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="active-users-24h">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('active_users_24h') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="online-nodes">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('online_nodes') . '</div>
            </div>
        </div>
        
        <!-- Additional Traffic Stats -->
        <div class="stats-row" id="traffic-periods">
            <div class="stat-card">
                <div class="stat-value" id="traffic-5min">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('traffic_5min') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="traffic-1hour">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('traffic_1hour') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="today-traffic">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('today_traffic') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="traffic-monthly">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('traffic_monthly') . '</div>
            </div>
        </div>
        
        <!-- Total Traffic Since Launch -->
        <div class="stats-row" id="total-traffic-since-launch">
            <div class="stat-card">
                <div class="stat-value" id="total-upload-since-launch">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total_upload') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="total-download-since-launch">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total_download') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="total-traffic-since-launch-value">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total_traffic') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="site-launch-date">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('first_record') . '</div>
            </div>
        </div>
        
        <!-- Filter Panel -->
        <div class="filter-panel">
            <form id="traffic-filter">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="time-range">' . v2raysocks_traffic_lang('time_range') . ':</label>
                        <select id="time-range" name="time_range" style="width: 100%;">
                            <option value="today" selected>' . v2raysocks_traffic_lang('today') . '</option>
                            <option value="week">' . v2raysocks_traffic_lang('last_7_days') . '</option>
                            <option value="halfmonth">' . v2raysocks_traffic_lang('last_15_days') . '</option>
                            <option value="month_including_today">' . v2raysocks_traffic_lang('last_30_days') . '</option>
                            <option value="custom">' . v2raysocks_traffic_lang('custom_range') . '</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="service-id">' . v2raysocks_traffic_lang('service_id') . ':</label>
                        <input type="text" id="service-id" name="service_id" placeholder="' . v2raysocks_traffic_lang('enter_service_id') . '" style="width: 100%;">
                    </div>
                    <div class="filter-group" id="custom-dates" style="display: none;">
                        <label for="start-date">' . v2raysocks_traffic_lang('start_date') . ':</label>
                        <input type="date" id="start-date" name="start_date" style="width: 100%;">
                    </div>
                    <div class="filter-group" id="custom-dates-end" style="display: none;">
                        <label for="end-date">' . v2raysocks_traffic_lang('end_date') . ':</label>
                        <input type="date" id="end-date" name="end_date" style="width: 100%;">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('apply_filter') . '</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Custom Time Range Traffic Summary -->
        <div class="stats-row" id="custom-time-range-summary" style="display: none;">
            <div class="stat-card">
                <div class="stat-value" id="custom-range-upload">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('upload') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="custom-range-download">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('download') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="custom-range-total">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('total_traffic') . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="custom-range-records">--</div>
                <div class="stat-label">' . v2raysocks_traffic_lang('records_found') . '</div>
            </div>
        </div>
        
        <!-- Traffic Chart -->
        <div class="chart-container">
            <h3>' . v2raysocks_traffic_lang('traffic_usage_over_time') . '</h3>
            
            <!-- Chart Controls -->
            <div class="chart-controls" style="margin-bottom: 15px;">
                <div class="control-group" style="display: inline-block; margin-right: 20px;">
                    <label for="traffic-chart-type">' . v2raysocks_traffic_lang('chart_type') . ':</label>
                    <select id="traffic-chart-type" style="margin-left: 5px; padding: 5px;">
                        <option value="combined" selected>' . v2raysocks_traffic_lang('upload_and_download') . '</option>
                        <option value="total">' . v2raysocks_traffic_lang('total_traffic') . '</option>
                        <option value="cumulative">' . v2raysocks_traffic_lang('cumulative_traffic') . '</option>
                        <option value="total_cumulative">' . v2raysocks_traffic_lang('total_cumulative_traffic') . '</option>
                    </select>
                </div>
                <div class="control-group" style="display: inline-block;">
                    <label for="traffic-chart-unit">' . v2raysocks_traffic_lang('unit') . ':</label>
                    <select id="traffic-chart-unit" style="margin-left: 5px; padding: 5px;">
                        <option value="auto" selected>Auto</option>
                        <option value="MB">MB</option>
                        <option value="GB">GB</option>
                        <option value="TB">TB</option>
                    </select>
                </div>
            </div>
            
            <canvas id="traffic-chart" width="400" height="200"></canvas>
        </div>
        
        <!-- Recent Traffic Data -->
        <div class="chart-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">' . v2raysocks_traffic_lang('traffic_history') . '</h3>
                <button id="export-options" class="btn btn-success">' . v2raysocks_traffic_lang('export_data') . '</button>
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
                    <tbody id="traffic-data">
                        <tr>
                            <td colspan="11" class="loading">Loading traffic data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div id="pagination-controls" style="margin-top: 15px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span id="pagination-info">' . v2raysocks_traffic_lang('showing_records') . '</span>
                    </div>
                    <div>
                        <label for="records-per-page" style="margin-right: 10px;">' . v2raysocks_traffic_lang('records_per_page_label') . '</label>
                        <select id="records-per-page" style="margin-right: 15px; padding: 5px;">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        
                        <button id="first-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('first_page') . '</button>
                        <button id="prev-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('previous_page') . '</button>
                        <span id="page-info" style="margin: 0 10px;">' . v2raysocks_traffic_lang('page_info') . '</span>
                        <button id="next-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('next_page') . '</button>
                        <button id="last-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('last_page') . '</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export Options Modal -->
        <div id="export-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 400px;">
                <h4>' . v2raysocks_traffic_lang('export_data') . '</h4>
                <form id="export-form">
                    <div style="margin-bottom: 15px;">
                        <label>' . v2raysocks_traffic_lang('export_type') . '</label><br>
                        <label><input type="radio" name="export_type" value="all" checked> ' . v2raysocks_traffic_lang('all_filtered_data') . '</label><br>
                        <label><input type="radio" name="export_type" value="limited"> ' . v2raysocks_traffic_lang('limited_number_of_records') . '</label><br>
                        <label><input type="radio" name="export_type" value="date_range"> ' . v2raysocks_traffic_lang('custom_date_range') . '</label>
                    </div>
                    
                    <div id="limit-options" style="margin-bottom: 15px; display: none;">
                        <label for="limit_count">' . v2raysocks_traffic_lang('number_of_records') . '</label>
                        <input type="number" id="limit_count" name="limit_count" value="1000" min="1" max="10000">
                    </div>
                    
                    <div id="date-range-options" style="margin-bottom: 15px; display: none;">
                        <label for="export_start_date">' . v2raysocks_traffic_lang('start_date_label') . '</label>
                        <input type="date" id="export_start_date" name="export_start_date"><br><br>
                        <label for="export_end_date">' . v2raysocks_traffic_lang('end_date_label') . '</label>
                        <input type="date" id="export_end_date" name="export_end_date">
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
    </div>

    <script>
        let trafficChart;
        let refreshInterval;
        let currentTrafficData = []; // Store current data for chart type changes
        let currentGroupedData = null; // Store current grouped data for chart type changes
        let allTrafficData = []; // Store all filtered data for pagination
        let currentPage = 1;
        let recordsPerPage = 50;
        let totalPages = 1;
        let moduleConfig = {
            chart_unit: "auto"
        };
        
        // Load module configuration
        function loadModuleConfig() {
            return $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_module_config",
                type: "GET",
                dataType: "json",
                success: function(response) {
                    if (response.status === "success" && response.data) {
                        moduleConfig = response.data;
                        // Set chart unit selector to module configuration
                        if (moduleConfig.chart_unit) {
                            $("#traffic-chart-unit").val(moduleConfig.chart_unit);
                        }
                    }
                },
                error: function() {
                    console.warn("Failed to load module configuration, using defaults");
                }
            });
        }
        
        $(document).ready(function() {
            // Load module configuration first
            loadModuleConfig();
            
            initializeChart();
            loadLiveStats();
            loadTrafficData();
            loadTotalTrafficSinceLaunch();
            
            // Auto-refresh every 5 minutes (300 seconds) for WHMCS sync
            refreshInterval = setInterval(function() {
                loadLiveStats();
                loadTotalTrafficSinceLaunch(); // Refresh total stats too
                if ($("#time-range").val() === "today") {
                    loadTrafficData();
                }
            }, 300000);
            
            // Filter form submission
            $("#traffic-filter").on("submit", function(e) {
                e.preventDefault();
                loadTrafficData();
            });
            
            // Time range change handler
            $("#time-range").on("change", function() {
                if ($(this).val() === "custom") {
                    $("#custom-dates, #custom-dates-end").show();
                } else {
                    $("#custom-dates, #custom-dates-end").hide();
                    // Note: Removed auto-refresh on time range change
                    // Users must click Apply Filter button to refresh data
                }
            });
            
            // Service ID field change handler for real-time updates
            $("#service-id").on("input", debounce(function() {
                if ($(this).val().trim() !== "" || $("#time-range").val() !== "month_including_today") {
                    loadTrafficData();
                }
            }, 1000)); // Wait 1 second after user stops typing
            
            // Chart type and unit change handlers
            $("#traffic-chart-type, #traffic-chart-unit").on("change", function() {
                if (currentTrafficData && currentTrafficData.length > 0) {
                    updateTrafficChart(currentTrafficData, currentGroupedData);
                }
            });
            
            // Export options handler
            $("#export-options").on("click", function(e) {
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
                
                // Get current filter parameters
                const filterParams = $("#traffic-filter").serialize();
                
                // Get export options
                const exportType = $("input[name=\'export_type\']:checked").val();
                const format = $("#export_format").val();
                
                let exportParams = filterParams + "&export_type=" + exportType + "&format=" + format;
                
                if (exportType === "limited") {
                    const limitCount = $("#limit_count").val();
                    exportParams += "&limit_count=" + limitCount;
                } else if (exportType === "date_range") {
                    const startDate = $("#export_start_date").val();
                    const endDate = $("#export_end_date").val();
                    
                    // Validate export date range against main page search range
                    if (startDate && endDate) {
                        const exportStart = new Date(startDate);
                        const exportEnd = new Date(endDate);
                        
                        if (!validateExportTimeRange(exportStart, exportEnd)) {
                            return; // Stop submission if validation fails
                        }
                    }
                    
                    if (startDate) exportParams += "&export_start_date=" + startDate;
                    if (endDate) exportParams += "&export_end_date=" + endDate;
                }
                
                // Trigger download
                window.open("addonmodules.php?module=v2raysocks_traffic&action=export_data&" + exportParams);
                
                // Hide modal
                $("#export-modal").hide();
            });
            
            // Pagination event handlers
            $("#records-per-page").on("change", function() {
                recordsPerPage = parseInt($(this).val());
                currentPage = 1;
                updatePaginatedTable();
            });
            
            $("#first-page").on("click", function() {
                currentPage = 1;
                updatePaginatedTable();
            });
            
            $("#prev-page").on("click", function() {
                if (currentPage > 1) {
                    currentPage--;
                    updatePaginatedTable();
                }
            });
            
            $("#next-page").on("click", function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    updatePaginatedTable();
                }
            });
            
            $("#last-page").on("click", function() {
                currentPage = totalPages;
                updatePaginatedTable();
            });
        });
        
        function initializeChart() {
            const ctx = document.getElementById("traffic-chart").getContext("2d");
            const options = getUnifiedChartOptions("GB");
            options.plugins.title = {
                display: true,
                text: "' . v2raysocks_traffic_lang('traffic_usage_over_time') . '"
            };
            
            trafficChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: [],
                    datasets: [
                        getStandardDatasetConfig("upload", "' . v2raysocks_traffic_lang('upload') . '", []),
                        getStandardDatasetConfig("download", "' . v2raysocks_traffic_lang('download') . '", [])
                    ]
                },
                options: options
            });
        }
        
        function loadLiveStats() {
            $("#refresh-indicator").text("🔄");
            
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_live_stats",
                type: "GET",
                dataType: "json",
                success: function(response) {
                    if (response.status === "success") {
                        const data = response.data;
                        $("#total-users").text(data.total_users || 0);
                        
                        // Handle multiple active user timeframes
                        if (data.active_users) {
                            $("#active-users-5min").text(data.active_users["5min"] || 0);
                            $("#active-users-1hour").text(data.active_users["1hour"] || 0);
                            $("#active-users-24h").text(data.active_users["24hours"] || 0);
                        }
                        
                        $("#online-nodes").text((data.online_nodes || 0) + "/" + (data.total_nodes || 0));
                        
                        const totalTraffic = (data.today_upload || 0) + (data.today_download || 0);
                        $("#today-traffic").text(formatBytes(totalTraffic));
                        
                        // Handle traffic periods
                        if (data.traffic_periods) {
                            $("#traffic-5min").text(formatBytes(data.traffic_periods["5min"]?.total || 0));
                            $("#traffic-1hour").text(formatBytes(data.traffic_periods["1hour"]?.total || 0));
                            $("#traffic-monthly").text(formatBytes(data.traffic_periods.monthly?.total || 0));
                        }
                    }
                    $("#refresh-indicator").text("✓");
                    setTimeout(() => $("#refresh-indicator").text(""), 2000);
                },
                error: function() {
                    $("#refresh-indicator").text("❌");
                    setTimeout(() => $("#refresh-indicator").text(""), 2000);
                }
            });
        }
        
        function loadTrafficData() {
            const params = $("#traffic-filter").serialize() + "&grouped=true";
            
            // Add loading indicator without clearing existing data
            const loadingRow = "<tr id=\'loading-indicator\'><td colspan=\'11\' class=\'loading\' style=\'background-color: #f8f9fa; color: #007bff;\'>🔄 Loading traffic data...</td></tr>";
            $("#traffic-data").prepend(loadingRow);
            
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_traffic_data",
                type: "GET",
                data: params,
                dataType: "json",
                timeout: 30000, // 30 second timeout
                success: function(response) {
                    console.log("Traffic data response:", response);
                    $("#loading-indicator").remove();
                    if (response.status === "success") {
                        updateTrafficTable(response.data);
                        updateTrafficChart(response.data, response.grouped_data);
                    } else {
                        console.error("Traffic data error:", response);
                        $("#traffic-data").html("<tr><td colspan=\'11\' class=\'loading\'>Error: " + (response.message || "Unknown error") + "</td></tr>");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error loading traffic data:", status, error);
                    $("#loading-indicator").remove();
                    let errorMsg = "Error loading traffic data";
                    if (status === "timeout") {
                        errorMsg = "Request timed out - please try again";
                    } else if (xhr.responseText) {
                        errorMsg = "Server error: " + xhr.status;
                    }
                    $("#traffic-data").html("<tr><td colspan=\'11\' class=\'loading\'>" + errorMsg + "</td></tr>");
                }
            });
        }
        
        function updateTrafficTable(data) {
            // Store all data for pagination
            allTrafficData = data;
            updatePaginatedTable();
        }
        
        function updatePaginatedTable() {
            let html = "";
            
            if (allTrafficData.length === 0) {
                html = "<tr><td colspan=\\"11\\" class=\\"loading\\">No traffic data found</td></tr>";
                $("#pagination-controls").hide();
            } else {
                // Calculate pagination
                totalPages = Math.ceil(allTrafficData.length / recordsPerPage);
                const startIndex = (currentPage - 1) * recordsPerPage;
                const endIndex = Math.min(startIndex + recordsPerPage, allTrafficData.length);
                const pageData = allTrafficData.slice(startIndex, endIndex);
                
                // Generate table rows for current page
                pageData.forEach(function(row) {
                    html += `<tr>
                        <td>${new Date(row.t * 1000).toLocaleString()}</td>
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
                $("#pagination-info").text("' . v2raysocks_traffic_lang('showing_records') . '".replace("{start}", startIndex + 1).replace("{end}", endIndex).replace("{total}", allTrafficData.length));
                $("#page-info").text("' . v2raysocks_traffic_lang('page_info') . '".replace("{current}", currentPage).replace("{total}", totalPages));
                
                // Enable/disable pagination buttons
                $("#first-page, #prev-page").prop("disabled", currentPage === 1);
                $("#next-page, #last-page").prop("disabled", currentPage === totalPages);
                
                $("#pagination-controls").show();
            }
            
            $("#traffic-data").html(html);
        }
        
        function updateTrafficChart(data, groupedData) {
            // Store data for chart type changes
            currentTrafficData = data;
            currentGroupedData = groupedData;
            
            // Get chart settings
            const timeRange = $("#time-range").val();
            const chartType = $("#traffic-chart-type").val();
            let unit = $("#traffic-chart-unit").val();
            
            // Handle empty data case
            if (!data || !Array.isArray(data) || data.length === 0) {
                console.log("No traffic data available for chart");
                // Use unified utilities to create empty chart with continuous time axis
                updateChartWithContinuousTimeAxis(trafficChart, [], timeRange, "combined", unit || "GB", null);
                hideCustomTimeRangeSummary();
                return;
            }
            
            // Calculate totals for custom time range summary
            let totalUpload = 0;
            let totalDownload = 0;
            data.forEach(function(row) {
                totalUpload += parseFloat(row.u) || 0;
                totalDownload += parseFloat(row.d) || 0;
            });
            
            // Auto unit detection
            if (unit === "auto") {
                const allDataPoints = data.flatMap(row => [parseFloat(row.u) || 0, parseFloat(row.d) || 0, (parseFloat(row.u) || 0) + (parseFloat(row.d) || 0)]);
                unit = getBestUnitForData(allDataPoints);
            }
            
            // Update custom time range summary
            updateCustomTimeRangeSummary(totalUpload, totalDownload, data.length);
            
            // Use unified chart utilities to update chart with continuous time axis
            updateChartWithContinuousTimeAxis(trafficChart, data, timeRange, chartType, unit, groupedData);
            
            console.log("Traffic chart updated with continuous time axis in", chartType, "mode using", unit, "units");
        }
        
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
            
            // Use force unit from parameter if provided
            if (forceUnit && forceUnit !== "auto") {
                switch (forceUnit.toUpperCase()) {
                    case "KB":
                        return (size / 1000).toFixed(2) + " KB";
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
                }
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
        
        function updateCustomTimeRangeSummary(totalUpload, totalDownload, recordCount) {
            $("#custom-range-upload").text(formatBytes(totalUpload));
            $("#custom-range-download").text(formatBytes(totalDownload));
            $("#custom-range-total").text(formatBytes(totalUpload + totalDownload));
            $("#custom-range-records").text(recordCount.toLocaleString());
            $("#custom-time-range-summary").show();
        }
        
        function hideCustomTimeRangeSummary() {
            $("#custom-time-range-summary").hide();
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
        
        function getUnitDivisor(unit) {
            // Use consistent decimal (1000-based) conversion like formatBytes function
            switch (unit) {
                case "KB": return 1000;
                case "MB": return 1000000;
                case "GB": return 1000000000;
                case "TB": return 1000000000000;
                case "PB": return 1000000000000000;
                case "EB": return 1000000000000000000;
                case "auto": return 1000000000; // Default to GB for auto
                default: return 1000000000; // Default to GB
            }
        }
        
        // Debounce function for real-time updates
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        function loadTotalTrafficSinceLaunch() {
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_total_traffic_since_launch",
                type: "GET",
                dataType: "json",
                timeout: 15000,
                success: function(response) {
                    console.log("Total traffic since launch response:", response);
                    if (response.status === "success" && response.data) {
                        const data = response.data;
                        $("#total-upload-since-launch").text(formatBytes(data.total_upload));
                        $("#total-download-since-launch").text(formatBytes(data.total_download));
                        $("#total-traffic-since-launch-value").text(formatBytes(data.total_traffic));
                        $("#site-launch-date").text(data.first_record ? data.first_record.split(" ")[0] : "No data");
                    } else {
                        console.error("Total traffic since launch error:", response);
                        $("#total-upload-since-launch").text("Error");
                        $("#total-download-since-launch").text("Error");
                        $("#total-traffic-since-launch-value").text("Error");
                        $("#site-launch-date").text("Error");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error loading total traffic since launch:", status, error);
                    $("#total-upload-since-launch").text("Error");
                    $("#total-download-since-launch").text("Error");
                    $("#total-traffic-since-launch-value").text("Error");
                    $("#site-launch-date").text("Error");
                }
            });
        }
    </script>
</body>
</html>';
?>