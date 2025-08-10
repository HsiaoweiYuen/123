<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include unified navigation component
require_once(__DIR__ . '/navigation_component.php');

$serviceSearchHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('service_search') . '</title>
    <style>
        ' . v2raysocks_traffic_getNavigationCSS() . '
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
        .chart-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
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
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 500px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1.2em; font-weight: bold; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
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
            .search-form, .filter-panel, .navigation-bar {
                padding: 15px;
            }
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            .form-group {
                min-width: auto;
                width: 100%;
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
            .dashboard-container {
                padding: 5px;
            }
            .search-form, .filter-panel, .navigation-bar {
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
        
        // Function to get main page time range for export validation
        function getMainPageTimeRange() {
            const timeRange = document.getElementById("time_range").value;
            const today = new Date();
            let startDate, endDate;
            
            switch(timeRange) {
                case "today":
                    startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "week":
                    startDate = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    startDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "halfmonth":
                    startDate = new Date(today.getTime() - 15 * 24 * 60 * 60 * 1000);
                    startDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "month_including_today":
                    startDate = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                    startDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                    endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                    break;
                case "custom":
                    const startDateInput = document.getElementById("start_date").value;
                    const endDateInput = document.getElementById("end_date").value;
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
                alert("No usage records found for the specified criteria.");
                return false;
            }
            
            return true;
        }
    </script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        ' . v2raysocks_traffic_getNavigationHTML('service_search') . '

        <h1>' . v2raysocks_traffic_lang('service_search') . '</h1>
        
        <div class="search-form">
            <form id="service-search-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="search_type">' . v2raysocks_traffic_lang('search_type') . ':</label>
                        <select id="search_type" name="search_type">
                            <option value="service_id" selected>' . v2raysocks_traffic_lang('service_id') . '</option>
                            <option value="user_id">' . v2raysocks_traffic_lang('user_id') . '</option>
                            <option value="uuid">' . v2raysocks_traffic_lang('uuid') . '</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="search_value" id="search_value_label">' . v2raysocks_traffic_lang('service_id') . ':</label>
                        <input type="text" id="search_value" name="search_value" placeholder="' . v2raysocks_traffic_lang('enter_service_id') . '" required>
                    </div>
                    <div class="form-group">
                        <label for="time_range">' . v2raysocks_traffic_lang('time_range') . ':</label>
                        <select id="time_range" name="time_range">
                            <option value="today" selected>' . v2raysocks_traffic_lang('today') . '</option>
                            <option value="week">' . v2raysocks_traffic_lang('last_7_days') . '</option>
                            <option value="halfmonth">' . v2raysocks_traffic_lang('last_15_days') . '</option>
                            <option value="month_including_today">' . v2raysocks_traffic_lang('last_30_days') . '</option>
                            <option value="custom">' . v2raysocks_traffic_lang('custom_range') . '</option>
                        </select>
                    </div>
                    <div class="form-group" id="custom-dates" style="display: none;">
                        <label for="start_date">' . v2raysocks_traffic_lang('start_date') . ':</label>
                        <input type="date" id="start_date" name="start_date" style="width: 120px;">
                    </div>
                    <div class="form-group" id="custom-dates-end" style="display: none;">
                        <label for="end_date">' . v2raysocks_traffic_lang('end_date') . ':</label>
                        <input type="date" id="end_date" name="end_date" style="width: 120px;">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('search_traffic') . '</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div id="search-results" style="display: none;">
            <!-- Traffic Chart -->
            <div class="chart-container">
                <h3>' . v2raysocks_traffic_lang('traffic_chart') . '</h3>
                <div class="chart-mode-controls" style="margin-bottom: 15px;">
                    <label for="service-chart-display-mode" style="margin-right: 10px; font-weight: 500;">' . v2raysocks_traffic_lang('display_mode') . ':</label>
                    <select id="service-chart-display-mode" style="padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; margin-right: 20px;">
                        <option value="separate">' . v2raysocks_traffic_lang('upload_and_download') . '</option>
                        <option value="total">' . v2raysocks_traffic_lang('total_traffic') . '</option>
                        <option value="cumulative">' . v2raysocks_traffic_lang('cumulative_traffic') . '</option>
                        <option value="total_cumulative">' . v2raysocks_traffic_lang('total_cumulative_traffic') . '</option>
                    </select>
                    <label for="service-chart-unit" style="margin-right: 10px; font-weight: 500;">' . v2raysocks_traffic_lang('chart_unit') . '</label>
                    <select id="service-chart-unit" style="padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                        <option value="auto" selected>Auto</option>
                        <option value="MB">MB</option>
                        <option value="GB">GB</option>
                        <option value="TB">TB</option>
                    </select>
                </div>
                <canvas id="service-traffic-chart" width="400" height="200"></canvas>
            </div>
            
            <!-- Traffic Data Table -->
            <div class="chart-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;">' . v2raysocks_traffic_lang('service_traffic_records') . '</h3>
                    <button id="export-service-data" class="btn btn-success">' . v2raysocks_traffic_lang('export_data') . '</button>
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
                        <tbody id="service-traffic-data">
                            <tr>
                                <td colspan="11" class="loading">' . v2raysocks_traffic_lang('search_to_view_data') . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <div id="service-pagination-controls" style="margin-top: 15px; display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span id="service-pagination-info">' . v2raysocks_traffic_lang('showing_records') . ' 0 ' . v2raysocks_traffic_lang('to') . ' 0 ' . v2raysocks_traffic_lang('of') . ' 0 ' . v2raysocks_traffic_lang('records') . '</span>
                        </div>
                        <div>
                            <label for="service-records-per-page" style="margin-right: 10px;">' . v2raysocks_traffic_lang('records_per_page') . '</label>
                            <select id="service-records-per-page" style="margin-right: 15px; padding: 5px;">
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </select>
                            
                            <button id="service-first-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('first') . '</button>
                            <button id="service-prev-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('previous') . '</button>
                            <span id="service-page-info" style="margin: 0 10px;">' . v2raysocks_traffic_lang('page') . ' 1 ' . v2raysocks_traffic_lang('of_pages') . ' 1 ' . v2raysocks_traffic_lang('pages') . '</span>
                            <button id="service-next-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('next') . '</button>
                            <button id="service-last-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('last') . '</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export Options Modal -->
        <!-- Service Export Modal -->
        <div id="service-export-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 400px;">
                <h4>' . v2raysocks_traffic_lang('export_data') . '</h4>
                <form id="service-export-form">
                    <div style="margin-bottom: 15px;">
                        <label>' . v2raysocks_traffic_lang('export_type') . '</label><br>
                        <label><input type="radio" name="service_export_type" value="all" checked> ' . v2raysocks_traffic_lang('all_filtered_data') . '</label><br>
                        <label><input type="radio" name="service_export_type" value="limited"> ' . v2raysocks_traffic_lang('limited_number_of_records') . '</label><br>
                        <label><input type="radio" name="service_export_type" value="date_range"> ' . v2raysocks_traffic_lang('custom_date_range') . '</label><br>
                        <label><input type="radio" name="service_export_type" value="time_range"> ' . v2raysocks_traffic_lang('custom_time_range') . '</label>
                    </div>
                    
                    <div id="service-limit-options" style="margin-bottom: 15px; display: none;">
                        <label for="service_limit_count">' . v2raysocks_traffic_lang('number_of_records') . '</label>
                        <input type="number" id="service_limit_count" name="limit_count" value="1000" min="1" max="10000">
                    </div>
                    
                    <div id="service-date-range-options" style="margin-bottom: 15px; display: none;">
                        <label for="service_export_start_date">' . v2raysocks_traffic_lang('start_date_label') . '</label>
                        <input type="date" id="service_export_start_date" name="export_start_date"><br><br>
                        <label for="service_export_end_date">' . v2raysocks_traffic_lang('end_date_label') . '</label>
                        <input type="date" id="service_export_end_date" name="export_end_date">
                    </div>
                    
                    <div id="service-time-range-options" style="margin-bottom: 15px; display: none;">
                        <label for="service_export_start_time">' . v2raysocks_traffic_lang('start_time_label') . '</label>
                        <input type="time" id="service_export_start_time" name="export_start_time" step="1" style="width: 120px; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; margin-bottom: 10px;"><br>
                        <label for="service_export_end_time">' . v2raysocks_traffic_lang('end_time_label') . '</label>
                        <input type="time" id="service_export_end_time" name="export_end_time" step="1" style="width: 120px; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                        <br><small style="color: #6c757d; margin-top: 5px; display: block;">' . v2raysocks_traffic_lang('time_range_today_only') . '</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="service_export_format">' . v2raysocks_traffic_lang('format') . '</label>
                        <select id="service_export_format" name="format">
                            <option value="excel" selected>' . v2raysocks_traffic_lang('excel') . '</option>
                            <option value="csv">' . v2raysocks_traffic_lang('csv') . '</option>
                            <option value="json">' . v2raysocks_traffic_lang('json') . '</option>
                        </select>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" id="service-cancel-export" class="btn" style="margin-right: 10px;">' . v2raysocks_traffic_lang('cancel') . '</button>
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('export') . '</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let serviceChart;
        let currentSearchParams = {};
        let allServiceData = []; // Store all service data for pagination
        let serviceCurrentPage = 1;
        let serviceRecordsPerPage = 50;
        let serviceTotalPages = 1;
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
                            $("#service-chart-unit").val(moduleConfig.chart_unit);
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
            
            // Time range change handler
            $("#time_range").on("change", function() {
                if ($(this).val() === "custom") {
                    $("#custom-dates, #custom-dates-end").show();
                } else {
                    $("#custom-dates, #custom-dates-end").hide();
                }
            });
            
            // Chart mode change handler
            $("#service-chart-display-mode, #service-chart-unit").on("change", function() {
                // Re-run the search with the current parameters to update the chart
                if (Object.keys(currentSearchParams).length > 0) {
                    searchServiceTraffic();
                }
            });
            
            // Search type change handler
            $("#search_type").on("change", function() {
                const searchType = $(this).val();
                const label = $("#search_value_label");
                const input = $("#search_value");
                
                switch(searchType) {
                    case "service_id":
                        label.text("' . v2raysocks_traffic_lang('service_id') . ':");
                        input.attr("placeholder", "' . v2raysocks_traffic_lang('enter_service_id') . '");
                        break;
                    case "user_id":
                        label.text("' . v2raysocks_traffic_lang('user_id') . ':");
                        input.attr("placeholder", "' . v2raysocks_traffic_lang('enter_user_id') . '");
                        break;
                    case "uuid":
                        label.text("' . v2raysocks_traffic_lang('uuid') . ':");
                        input.attr("placeholder", "' . v2raysocks_traffic_lang('enter_uuid') . '");
                        break;
                }
                input.val(""); // Clear the input when switching types
            });
            
            // Service search form submission
            $("#service-search-form").on("submit", function(e) {
                e.preventDefault();
                searchServiceTraffic();
            });
            
            // Export data handler - now opens modal instead of direct export
            $("#export-service-data").on("click", function(e) {
                e.preventDefault();
                if (Object.keys(currentSearchParams).length > 0) {
                    $("#service-export-modal").show();
                } else {
                    alert("Please perform a search first before exporting data.");
                }
            });
            
            // Export modal handlers
            $("#service-cancel-export").on("click", function() {
                $("#service-export-modal").hide();
            });
            
            // Close modal when clicking outside
            $("#service-export-modal").on("click", function(event) {
                if (event.target === document.getElementById("service-export-modal")) {
                    $("#service-export-modal").hide();
                }
            });
            
            // Export type change handlers
            $("input[name=\'service_export_type\']").on("change", function() {
                const type = $(this).val();
                $("#service-limit-options").toggle(type === "limited");
                $("#service-date-range-options").toggle(type === "date_range");
                $("#service-time-range-options").toggle(type === "time_range");
            });
            
            // Export form submission
            $("#service-export-form").on("submit", function(e) {
                e.preventDefault();
                
                // Get current search parameters
                const searchParams = $.param(currentSearchParams);
                
                // Get export options
                const exportType = $("input[name=\'service_export_type\']:checked").val();
                const format = $("#service_export_format").val();
                
                let exportParams = searchParams + "&export_type=" + exportType + "&format=" + format;
                
                if (exportType === "limited") {
                    const limitCount = $("#service_limit_count").val();
                    exportParams += "&limit_count=" + limitCount;
                } else if (exportType === "date_range") {
                    const startDate = $("#service_export_start_date").val();
                    const endDate = $("#service_export_end_date").val();
                    
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
                } else if (exportType === "time_range") {
                    const startTime = $("#service_export_start_time").val();
                    const endTime = $("#service_export_end_time").val();
                    
                    if (startTime && endTime) {
                        // Convert time to todays date + time for timestamp calculation
                        const today = new Date().toISOString().split("T")[0]; // YYYY-MM-DD
                        const startDateTime = today + " " + startTime;
                        const endDateTime = today + " " + endTime;
                        const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                        const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                        
                        // Validate time range against main page bounds
                        const exportStartDate = new Date(startDateTime);
                        const exportEndDate = new Date(endDateTime);
                        if (!validateExportTimeRange(exportStartDate, exportEndDate)) {
                            return;
                        }
                        
                        exportParams += "&export_start_timestamp=" + startTimestamp + "&export_end_timestamp=" + endTimestamp;
                    } else {
                        alert("Please select both start and end times");
                        return;
                    }
                }
                
                // Trigger download
                window.open("addonmodules.php?module=v2raysocks_traffic&action=export_data&" + exportParams);
                
                // Hide modal
                $("#service-export-modal").hide();
            });
            
            // Service search pagination event handlers
            $("#service-records-per-page").on("change", function() {
                serviceRecordsPerPage = parseInt($(this).val());
                serviceCurrentPage = 1;
                updateServicePaginatedTable();
            });
            
            $("#service-first-page").on("click", function() {
                serviceCurrentPage = 1;
                updateServicePaginatedTable();
            });
            
            $("#service-prev-page").on("click", function() {
                if (serviceCurrentPage > 1) {
                    serviceCurrentPage--;
                    updateServicePaginatedTable();
                }
            });
            
            $("#service-next-page").on("click", function() {
                if (serviceCurrentPage < serviceTotalPages) {
                    serviceCurrentPage++;
                    updateServicePaginatedTable();
                }
            });
            
            $("#service-last-page").on("click", function() {
                serviceCurrentPage = serviceTotalPages;
                updateServicePaginatedTable();
            });
        });
        
        function searchServiceTraffic() {
            const searchType = $("#search_type").val();
            const searchValue = $("#search_value").val().trim();
            const timeRange = $("#time_range").val();
            const startDate = $("#start_date").val();
            const endDate = $("#end_date").val();
            
            if (!searchValue) {
                alert("Please enter a search value");
                return;
            }
            
            // Build request parameters based on search type
            let requestData = {
                time_range: timeRange
            };
            
            if (startDate) requestData.start_date = startDate;
            if (endDate) requestData.end_date = endDate;
            
            // Add search parameter based on type
            switch(searchType) {
                case "service_id":
                    requestData.service_id = searchValue;
                    break;
                case "user_id":
                    requestData.user_id = searchValue;
                    break;
                case "uuid":
                    requestData.uuid = searchValue;
                    break;
            }
            
            currentSearchParams = requestData;
            
            $("#search-results").show();
            $("#service-traffic-data").html("<tr><td colspan=\\"11\\" class=\\"loading\\">Searching traffic data...</td></tr>");
            
            // Try advanced search first for service_id
            if (searchType === "service_id") {
                $.ajax({
                    url: "addonmodules.php?module=v2raysocks_traffic&action=search_service_advanced",
                    type: "GET",
                    data: requestData,
                    dataType: "json",
                    timeout: 15000,
                    success: function(response) {
                        console.log("Advanced service search response:", response);
                        if (response.status === "success") {
                            if (response.data && response.data.length > 0) {
                                updateServiceTrafficTable(response.data);
                                updateServiceTrafficChart(response.data);
                            } else {
                                // Try fallback to regular search
                                searchServiceTrafficFallback(requestData);
                            }
                        } else {
                            console.error("Advanced service search error:", response);
                            // Try fallback to regular search
                            searchServiceTrafficFallback(requestData);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error in advanced service search:", status, error);
                        // Try fallback to regular search
                        searchServiceTrafficFallback(requestData);
                    }
                });
            } else {
                // For other search types, use regular search
                searchServiceTrafficFallback(requestData);
            }
        }
        
        function searchServiceTrafficFallback(formData) {
            console.log("Trying fallback search...");
            $.ajax({
                url: "addonmodules.php?module=v2raysocks_traffic&action=get_traffic_data",
                type: "GET",
                data: formData,
                dataType: "json",
                timeout: 15000,
                success: function(response) {
                    console.log("Fallback service search response:", response);
                    if (response.status === "success") {
                        updateServiceTrafficTable(response.data);
                        updateServiceTrafficChart(response.data);
                    } else {
                        console.error("Fallback service search error:", response);
                        $("#service-traffic-data").html("<tr><td colspan=\\"11\\" class=\\"loading\\">Error: " + (response.message || "Unknown error") + "</td></tr>");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error in fallback service search:", status, error);
                    let errorMsg = "Error loading traffic data";
                    if (status === "timeout") {
                        errorMsg = "Search timed out - please try again";
                    } else if (xhr.responseText) {
                        errorMsg = "Server error: " + xhr.status;
                    }
                    $("#service-traffic-data").html("<tr><td colspan=\\"11\\" class=\\"loading\\">" + errorMsg + "</td></tr>");
                }
            });
        }
        
        function updateServiceTrafficTable(data) {
            // Store all data for pagination
            allServiceData = data;
            updateServicePaginatedTable();
        }
        
        function updateServicePaginatedTable() {
            let html = "";
            
            if (allServiceData.length === 0) {
                html = "<tr><td colspan=\\"11\\" class=\\"no-data\\">No traffic data found for this search</td></tr>";
                $("#service-pagination-controls").hide();
            } else {
                // Calculate pagination
                serviceTotalPages = Math.ceil(allServiceData.length / serviceRecordsPerPage);
                const startIndex = (serviceCurrentPage - 1) * serviceRecordsPerPage;
                const endIndex = Math.min(startIndex + serviceRecordsPerPage, allServiceData.length);
                const pageData = allServiceData.slice(startIndex, endIndex);
                
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
                $("#service-pagination-info").text(`' . v2raysocks_traffic_lang('showing_records') . ' ${startIndex + 1} ' . v2raysocks_traffic_lang('to') . ' ${endIndex} ' . v2raysocks_traffic_lang('of') . ' ${allServiceData.length} ' . v2raysocks_traffic_lang('records') . '`);
                $("#service-page-info").text(`' . v2raysocks_traffic_lang('page') . ' ${serviceCurrentPage} ' . v2raysocks_traffic_lang('of_pages') . ' ${serviceTotalPages} ' . v2raysocks_traffic_lang('pages') . '`);
                
                // Enable/disable pagination buttons
                $("#service-first-page, #service-prev-page").prop("disabled", serviceCurrentPage === 1);
                $("#service-next-page, #service-last-page").prop("disabled", serviceCurrentPage === serviceTotalPages);
                
                $("#service-pagination-controls").show();
            }
            
            $("#service-traffic-data").html(html);
        }
        
        // Generate default time labels for empty charts
        function generateDefaultTimeLabels(timeRange = "today", points = 8) {
            const now = new Date();
            const labels = [];
            
            let start, interval;
            switch (timeRange) {
                case "today":
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    interval = (24 * 60 * 60 * 1000) / (points - 1);
                    break;
                case "week":
                case "halfmonth":
                case "month_including_today":
                    const days = timeRange === "week" ? 7 : timeRange === "halfmonth" ? 15 : 30;
                    start = new Date(now.getTime() - (days - 1) * 24 * 60 * 60 * 1000);
                    interval = (days * 24 * 60 * 60 * 1000) / (points - 1);
                    break;
                case "custom":
                default:
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    interval = (24 * 60 * 60 * 1000) / (points - 1);
                    break;
            }
            
            for (let i = 0; i < points; i++) {
                const timestamp = new Date(start.getTime() + (i * interval));
                if (timeRange === "today") {
                    labels.push(timestamp.toLocaleTimeString("zh-CN", { hour: "2-digit", minute: "2-digit" }));
                } else {
                    labels.push(timestamp.toLocaleDateString("zh-CN", { month: "2-digit", day: "2-digit" }));
                }
            }
            
            return labels;
        }
        
        function updateServiceTrafficChart(data) {
            // Handle empty data case - generate default time labels
            if (!data || data.length === 0) {
                const timeRange = $("#time_range").val();
                const defaultLabels = generateDefaultTimeLabels(timeRange, 8);
                const mode = $("#service-chart-display-mode").val();
                let unit = $("#service-chart-unit").val();
                
                if (unit === "auto") {
                    unit = "GB"; // Default unit for empty chart
                }
                
                let datasets = [];
                const emptyData = new Array(defaultLabels.length).fill(0);
                
                switch(mode) {
                    case "separate":
                        datasets = [
                            getStandardDatasetConfig("upload", `' . v2raysocks_traffic_lang('upload') . ' (${unit})`, emptyData),
                            getStandardDatasetConfig("download", `' . v2raysocks_traffic_lang('download') . ' (${unit})`, emptyData)
                        ];
                        break;
                    case "total":
                        datasets = [
                            getStandardDatasetConfig("total", `' . v2raysocks_traffic_lang('total_traffic') . ' (${unit})`, emptyData, {fill: true})
                        ];
                        break;
                    case "cumulative":
                        datasets = [
                            getStandardDatasetConfig("upload", `' . v2raysocks_traffic_lang('cumulative_traffic') . ' ' . v2raysocks_traffic_lang('upload') . ' (${unit})`, emptyData, {fill: false}),
                            getStandardDatasetConfig("download", `' . v2raysocks_traffic_lang('cumulative_traffic') . ' ' . v2raysocks_traffic_lang('download') . ' (${unit})`, emptyData, {fill: false})
                        ];
                        break;
                    case "total_cumulative":
                        datasets = [
                            getStandardDatasetConfig("total", `' . v2raysocks_traffic_lang('total_cumulative_traffic') . ' (${unit})`, emptyData, {fill: true})
                        ];
                        break;
                }
                
                if (serviceChart) {
                    serviceChart.destroy();
                }
                
                const ctx = document.getElementById("service-traffic-chart").getContext("2d");
                serviceChart = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: defaultLabels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: `' . v2raysocks_traffic_lang('traffic') . ' (${unit})`
                                },
                                ticks: {
                                    callback: function(value) {
                                        return parseFloat(value).toFixed(2);
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
                                callbacks: {
                                    label: function(context) {
                                        const label = context.dataset.label || "";
                                        const value = context.parsed.y;
                                        const unit = label.match(/\\(([^)]+)\\)/);
                                        const unitText = unit ? unit[1] : "GB";
                                        const cleanLabel = label.replace(/\\s*\\([^)]*\\)/, "");
                                        return cleanLabel + "：" + value.toFixed(2) + " " + unitText;
                                    }
                                }
                            }
                        }
                    }
                });
                return;
            }
            
            // Group data by time periods for chart
            const timeData = {};
            let allDataPoints = [];
            
            data.forEach(function(row) {
                const date = new Date(row.t * 1000);
                let timeKey;
                
                // Group by different time periods based on range
                const timeRange = $("#time_range").val();
                if (timeRange === "today") {
                    timeKey = date.getHours() + ":00";
                } else {
                    timeKey = date.toLocaleDateString();
                }
                
                if (!timeData[timeKey]) {
                    timeData[timeKey] = { upload: 0, download: 0 };
                }
                const upload = (row.u || 0);
                const download = (row.d || 0);
                timeData[timeKey].upload += upload;
                timeData[timeKey].download += download;
                
                // Collect all data points for auto unit detection
                allDataPoints.push(upload);
                allDataPoints.push(download);
                allDataPoints.push(upload + download);
            });
            
            const labels = Object.keys(timeData).sort();
            const mode = $("#service-chart-display-mode").val();
            let unit = $("#service-chart-unit").val();
            
            // Auto unit detection
            if (unit === "auto") {
                unit = getBestUnitForData(allDataPoints);
            }
            const unitDivisor = getUnitDivisor(unit);
            
            let datasets = [];
            
            switch(mode) {
                case "separate":
                    datasets = [
                        getStandardDatasetConfig("upload", `' . v2raysocks_traffic_lang('upload') . ' (${unit})`, labels.map(time => (timeData[time].upload / unitDivisor))),
                        getStandardDatasetConfig("download", `' . v2raysocks_traffic_lang('download') . ' (${unit})`, labels.map(time => (timeData[time].download / unitDivisor)))
                    ];
                    break;
                case "total":
                    datasets = [
                        getStandardDatasetConfig("total", `' . v2raysocks_traffic_lang('total_traffic') . ' (${unit})`, labels.map(time => ((timeData[time].upload + timeData[time].download) / unitDivisor)), {fill: true})
                    ];
                    break;
                case "cumulative":
                    let cumulativeUpload = 0;
                    let cumulativeDownload = 0;
                    datasets = [
                        getStandardDatasetConfig("upload", `' . v2raysocks_traffic_lang('cumulative_traffic') . ' ' . v2raysocks_traffic_lang('upload') . ' (${unit})`, labels.map(time => {
                            cumulativeUpload += timeData[time].upload;
                            return cumulativeUpload / unitDivisor;
                        }), {fill: false}),
                        getStandardDatasetConfig("download", `' . v2raysocks_traffic_lang('cumulative_traffic') . ' ' . v2raysocks_traffic_lang('download') . ' (${unit})`, labels.map(time => {
                            cumulativeDownload += timeData[time].download;
                            return cumulativeDownload / unitDivisor;
                        }), {fill: false})
                    ];
                    break;
                case "total_cumulative":
                    let cumulativeTotal = 0;
                    datasets = [
                        getStandardDatasetConfig("total", `' . v2raysocks_traffic_lang('total_cumulative_traffic') . ' (${unit})`, labels.map(time => {
                            cumulativeTotal += timeData[time].upload + timeData[time].download;
                            return cumulativeTotal / unitDivisor;
                        }), {fill: true})
                    ];
                    break;
            }
            
            if (serviceChart) {
                serviceChart.destroy();
            }
            
            const ctx = document.getElementById("service-traffic-chart").getContext("2d");
            serviceChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: `' . v2raysocks_traffic_lang('traffic') . ' (${unit})`
                            },
                            ticks: {
                                callback: function(value) {
                                    return parseFloat(value).toFixed(2);
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
    </script>
</body>
</html>';
?>