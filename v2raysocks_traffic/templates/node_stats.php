<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include unified navigation component
require_once(__DIR__ . '/navigation_component.php');

$nodeStatsHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('node_statistics') . '</title>
    <style>
        ' . v2raysocks_traffic_getNavigationCSS() . '
        ' . v2raysocks_traffic_getUnifiedStyles() . '
        
        .rank-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            min-width: 30px;
            text-align: center;
        }
        .rank-1 { background: #ffd700; color: #333; }
        .rank-2 { background: #c0c0c0; color: #333; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-other { background: #6c757d; color: white; }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-online { background: #d4edda; color: #155724; }
        .status-offline { background: #f8d7da; color: #721c24; }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .progress-fill.normal {
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        .progress-fill.warning {
            background: linear-gradient(90deg, #ffc107, #e0a800);
        }
        .progress-fill.danger {
            background: linear-gradient(90deg, #dc3545, #c82333);
        }
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.8em;
            font-weight: bold;
            color: #333;
            text-shadow: 0 0 3px rgba(255,255,255,0.8);
        }
        
        .loading, .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .no-data { font-style: italic; }
        
        /* Modal styles for node details */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 1000px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .modal-title {
            margin: 0;
            font-size: 1.3em;
            text-align: center;
        }
        .close {
            color: #333;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            padding: 0 5px;
            background: transparent;
            border: none;
            border-radius: 50%;
            transition: all 0.2s ease;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close:hover { 
            color: #000; 
            background: #f0f0f0;
            transform: translateY(-50%) scale(1.1);
        }
        .modal-body {
            padding: 20px;
        }
        .chart-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            height: 400px;
            position: relative;
            max-width: 100%;
            overflow: hidden;
        }
        
        /* Fixed usage records and export section */
        .usage-records-section {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
            margin-top: 20px;
        }
        
        .usage-records-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 11;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 15px;
        }
        .node-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        .info-label {
            font-weight: bold;
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        .info-value {
            font-size: 1.2em;
            color: #495057;
        }
        .text-success { color: #28a745 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }
        .text-info { color: #17a2b8 !important; }
        .text-primary { color: #007bff !important; }
        
        /* Sortable table headers */
        .sortable-header {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 20px !important;
            transition: background-color 0.2s ease;
            text-align: center;
            vertical-align: middle;
        }
        .sortable-header:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        .sort-indicator {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            color: #6c757d;
            opacity: 0.7;
            line-height: 1;
        }
        .sortable-header.sort-asc .sort-indicator::after {
            content: "▲";
            color: #007bff;
            opacity: 1;
        }
        .sortable-header.sort-desc .sort-indicator::after {
            content: "▼";
            color: #007bff;
            opacity: 1;
        }
        .sortable-header:not(.sort-asc):not(.sort-desc) .sort-indicator::after {
            content: "⇅";
        }
        
        /* Chart controls panel */
        .chart-controls-panel .control-group {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
        }
        .chart-controls-panel select {
            min-width: 120px;
        }
        
        /* Responsive styles for mobile devices */
        @media (max-width: 768px) {
            .rank-badge {
                min-width: 25px;
                padding: 2px 4px;
                font-size: 0.8em;
            }
            .progress-bar {
                height: 16px;
            }
            .progress-text {
                font-size: 0.7em;
            }
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .chart-controls-panel {
                padding: 10px !important;
            }
            .chart-controls-panel > div {
                flex-direction: column;
                gap: 10px !important;
            }
            .chart-controls-panel .control-group {
                width: 100%;
                justify-content: space-between;
            }
            .chart-controls-panel select {
                min-width: 100px;
                flex: 1;
                margin-left: 8px;
            }
            .chart-container {
                padding: 10px;
            }
            .usage-records-section {
                margin-top: 10px;
            }
            
            /* Mobile responsive search controls for new structure */
            .usage-records-section div[style*="background: #f8f9fa"] div[style*="display: flex"] {
                flex-direction: column !important;
                gap: 10px !important;
            }
            .usage-records-section div[style*="flex: 0 0"] {
                flex: 1 1 100% !important;
                min-width: 100% !important;
            }
            div[style*="background: #f8f9fa"] div[style*="display: flex"] {
                flex-direction: column !important;
                gap: 10px !important;
            }
            div[style*="flex: 0 0 180px"],
            div[style*="flex: 0 0 200px"] {
                flex: 1 1 100% !important;
                min-width: 100% !important;
            }

        }
        
        /* Ensure charts stay within bounds */
        .chart-container canvas {
            max-width: 100% !important;
            height: auto !important;
        }
        
        /* UUID column font consistency */
        .uuid-column {
            font-family: inherit;
            font-size: inherit;
            font-weight: normal;
        }
        
        /* Standard styles for export modal inputs */
        #node-export-modal input[type="date"], 
        #node-export-modal input[type="time"], 
        #node-export-modal input[type="number"],
        #node-export-usage-modal input[type="date"], 
        #node-export-usage-modal input[type="time"], 
        #node-export-usage-modal input[type="number"] {
            width: 200px;
            padding: 5px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        #node-export-modal label,
        #node-export-usage-modal label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        #node-export-modal .form-group,
        #node-export-usage-modal .form-group {
            margin-bottom: 15px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Include standardized chart colors
        ' . file_get_contents(__DIR__ . '/chart_colors.js') . '
        
        // JavaScript translation function
        const translations = {
            "loading_node_rankings": "' . v2raysocks_traffic_lang('loading_node_rankings') . '",
            "no_data": "' . v2raysocks_traffic_lang('no_data') . '",
            "loading_failed": "' . v2raysocks_traffic_lang('loading_failed') . '",
            "unknown_error": "' . v2raysocks_traffic_lang('unknown_error') . '",
            "network_error_retry": "' . v2raysocks_traffic_lang('network_error_retry') . '",
            "loading": "' . v2raysocks_traffic_lang('loading') . '",
            "loading_usage_records": "' . v2raysocks_traffic_lang('loading_usage_records') . '",
            "node_name_label": "' . v2raysocks_traffic_lang('node_name_label') . '",
            "node_prefix": "' . v2raysocks_traffic_lang('node_prefix') . '",
            "today_range": "' . v2raysocks_traffic_lang('today_range') . '",
            "time_range_label": "' . v2raysocks_traffic_lang('time_range_label') . '",
            "upload_traffic": "' . v2raysocks_traffic_lang('upload') . '",
            "download_traffic": "' . v2raysocks_traffic_lang('download') . '",
            "total_traffic_label": "' . v2raysocks_traffic_lang('total_traffic_label') . '",
            "recent_5min_traffic_label": "' . v2raysocks_traffic_lang('recent_5min_traffic_label') . '",
            "recent_1hour_traffic_label": "' . v2raysocks_traffic_lang('recent_1hour_traffic_label') . '",
            "recent_4hour_traffic_label": "' . v2raysocks_traffic_lang('recent_4hour_traffic_label') . '",
            "peak_time": "' . v2raysocks_traffic_lang('peak_time') . '",
            "idle_time": "' . v2raysocks_traffic_lang('idle_time') . '",
            "peak_traffic": "' . v2raysocks_traffic_lang('peak_traffic') . '",
            "idle_traffic": "' . v2raysocks_traffic_lang('idle_traffic') . '",
            "no_traffic_data": "' . v2raysocks_traffic_lang('no_traffic_data') . '",
            "no_traffic_records_period": "' . v2raysocks_traffic_lang('no_traffic_records_period') . '",
            "network_connection_error": "' . v2raysocks_traffic_lang('network_connection_error') . '",
            "online": "' . v2raysocks_traffic_lang('online') . '",
            "offline": "' . v2raysocks_traffic_lang('offline') . '",
            "minutes_ago": "' . v2raysocks_traffic_lang('minutes_ago') . '",
            "hours_ago": "' . v2raysocks_traffic_lang('hours_ago') . '",
            "days_ago": "' . v2raysocks_traffic_lang('days_ago') . '",
            "no_usage_records": "' . v2raysocks_traffic_lang('no_usage_records') . '",
            "failed_load_usage_records": "' . v2raysocks_traffic_lang('failed_load_usage_records') . '",
            "showing_records": "' . v2raysocks_traffic_lang('showing_records') . '",
            "page_info": "' . v2raysocks_traffic_lang('page_info') . '",
            "upload_traffic_unit": "' . v2raysocks_traffic_lang('upload_traffic_unit') . '",
            "download_traffic_unit": "' . v2raysocks_traffic_lang('download_traffic_unit') . '",
            "total_traffic_unit": "' . v2raysocks_traffic_lang('total_traffic_unit') . '",
            "cumulative_upload_unit": "' . v2raysocks_traffic_lang('cumulative_upload_unit') . '",
            "cumulative_download_unit": "' . v2raysocks_traffic_lang('cumulative_download_unit') . '",
            "total_cumulative_traffic_unit": "' . v2raysocks_traffic_lang('total_cumulative_traffic_unit') . '",
            "traffic_unit": "' . v2raysocks_traffic_lang('traffic_unit') . '",
            "time_axis": "' . v2raysocks_traffic_lang('time_axis') . '",
            "node_today_usage_trends": "' . v2raysocks_traffic_lang('node_today_usage_trends') . '",
            "no_node_selected": "' . v2raysocks_traffic_lang('no_node_selected') . '",
            "select_start_end_times": "' . v2raysocks_traffic_lang('select_start_end_times') . '",
            "upload": "' . v2raysocks_traffic_lang('upload') . '",
            "download": "' . v2raysocks_traffic_lang('download') . '",
            "total_traffic": "' . v2raysocks_traffic_lang('total_traffic') . '",
            "cumulative_upload": "' . v2raysocks_traffic_lang('cumulative_upload') . '",
            "cumulative_download": "' . v2raysocks_traffic_lang('cumulative_download') . '",
            "total_cumulative_traffic": "' . v2raysocks_traffic_lang('total_cumulative_traffic') . '"
        };
        
        function t(key, replacements = {}) {
            let text = translations[key] || key;
            for (const [placeholder, value] of Object.entries(replacements)) {
                text = text.replace(new RegExp(`{${placeholder}}`, "g"), value);
            }
            return text;
        }
    </script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        ' . v2raysocks_traffic_getNavigationHTML('node_stats') . '

        <h1>' . v2raysocks_traffic_lang('node_rankings_title_today') . '</h1>
        
        <!-- Controls Panel -->
        <div class="controls-panel">
            <div class="controls-row">

                <div class="control-group">
                    <label for="show-offline">' . v2raysocks_traffic_lang('show_offline_nodes') . ':</label>
                    <select id="show-offline">
                        <option value="true" selected>' . v2raysocks_traffic_lang('yes') . '</option>
                        <option value="false">' . v2raysocks_traffic_lang('no') . '</option>
                    </select>
                </div>
                <div class="control-group">
                    <label for="node-rankings-time-range">' . v2raysocks_traffic_lang('time_range') . ':</label>
                    <select id="node-rankings-time-range">
                        <option value="today" selected>' . v2raysocks_traffic_lang('today') . '</option>
                        <option value="last_1_hour">' . v2raysocks_traffic_lang('last_1_hour') . '</option>
                        <option value="last_3_hours">' . v2raysocks_traffic_lang('last_3_hours') . '</option>
                        <option value="last_6_hours">' . v2raysocks_traffic_lang('last_6_hours') . '</option>
                        <option value="last_12_hours">' . v2raysocks_traffic_lang('last_12_hours') . '</option>
                        <option value="custom_range">' . v2raysocks_traffic_lang('custom_time_range') . '</option>
                    </select>
                </div>
                <div class="control-group">
                    <button class="btn btn-primary" onclick="loadNodeRankings()">' . v2raysocks_traffic_lang('refresh_rankings') . '</button>
                </div>
            </div>
            
            <!-- Custom Time Range Options -->
            <div id="node-rankings-custom-time-range" style="margin-top: 15px; display: none;">
                <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div style="flex: 0 0 140px;">
                        <label for="node-rankings-start-time" style="display: block; margin-bottom: 5px; font-weight: 500;">' . v2raysocks_traffic_lang('start_time_label') . ':</label>
                        <input type="time" id="node-rankings-start-time" style="width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 4px;" step="1">
                    </div>
                    <div style="flex: 0 0 140px;">
                        <label for="node-rankings-end-time" style="display: block; margin-bottom: 5px; font-weight: 500;">' . v2raysocks_traffic_lang('end_time_label') . ':</label>
                        <input type="time" id="node-rankings-end-time" style="width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 4px;" step="1">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rankings Container -->
        <div class="rankings-container">
            <div class="rankings-header">
                <h3 class="rankings-title">' . v2raysocks_traffic_lang('node_rankings_table_title_today') . '</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th style="min-width: 60px;" class="sortable-header" data-sort="rank">
                                ' . v2raysocks_traffic_lang('ranking') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px;" class="sortable-header" data-sort="node_id">
                                ' . v2raysocks_traffic_lang('node_id') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 160px;" class="sortable-header" data-sort="node_name">
                                ' . v2raysocks_traffic_lang('node_name') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="protocol">
                                ' . v2raysocks_traffic_lang('protocol') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 200px;" class="sortable-header" data-sort="address">
                                ' . v2raysocks_traffic_lang('address') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="total_traffic_limit">
                                ' . v2raysocks_traffic_lang('total_traffic_limit') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="used_traffic_statistics">
                                ' . v2raysocks_traffic_lang('used_traffic_statistics') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="remaining_traffic">
                                ' . v2raysocks_traffic_lang('remaining_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="today_traffic">
                                ' . v2raysocks_traffic_lang('today_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 120px;" class="sortable-header" data-sort="traffic_usage_rate">
                                ' . v2raysocks_traffic_lang('traffic_usage_rate') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 90px;" class="sortable-header" data-sort="traffic_5min">
                                ' . v2raysocks_traffic_lang('recent_5min_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 90px;" class="sortable-header" data-sort="traffic_1hour">
                                ' . v2raysocks_traffic_lang('recent_1hour_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 90px;" class="sortable-header" data-sort="traffic_4hour">
                                ' . v2raysocks_traffic_lang('recent_4hour_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="billing_rate">
                                ' . v2raysocks_traffic_lang('billing_rate') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px;" class="sortable-header" data-sort="user_count">
                                ' . v2raysocks_traffic_lang('user_count') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px;" class="sortable-header" data-sort="record_count">
                                ' . v2raysocks_traffic_lang('record_count') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 120px;" class="sortable-header" data-sort="excessive_speed_limit">
                                ' . v2raysocks_traffic_lang('excessive_speed_limit') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 120px;" class="sortable-header" data-sort="speed_limit">
                                ' . v2raysocks_traffic_lang('speed_limit') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="country">
                                ' . v2raysocks_traffic_lang('country') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px; white-space: nowrap;" class="sortable-header" data-sort="online_status">
                                ' . v2raysocks_traffic_lang('online_status') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 120px;" class="sortable-header" data-sort="last_online">
                                ' . v2raysocks_traffic_lang('last_online') . '
                                <span class="sort-indicator"></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="rankings-tbody">
                        <tr>
                            <td colspan="21" class="loading">' . v2raysocks_traffic_lang('node_rankings_loading') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Node Details Modal -->
    <div id="node-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">' . v2raysocks_traffic_lang('node_details_title') . '</h3>
                <span class="close" onclick="closeNodeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="node-info" class="node-info">
                    <div class="loading">' . v2raysocks_traffic_lang('node_info_loading') . '</div>
                </div>
                
                <!-- Chart Controls Panel -->
                <div class="chart-controls-panel" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                        <div class="control-group">
                            <label for="chart-unit" style="font-weight: bold; margin-right: 8px;">' . v2raysocks_traffic_lang('chart_unit') . ':</label>
                            <select id="chart-unit" style="padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="auto" selected>Auto</option>
                                <option value="MB">MB</option>
                                <option value="GB">GB</option>
                                <option value="TB">TB</option>
                            </select>
                        </div>
                        <div class="control-group">
                            <label for="chart-mode" style="font-weight: bold; margin-right: 8px;">' . v2raysocks_traffic_lang('display_mode') . ':</label>
                            <select id="chart-mode" style="padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="separate" selected>' . v2raysocks_traffic_lang('upload_download') . '</option>
                                <option value="total">' . v2raysocks_traffic_lang('total_traffic') . '</option>
                                <option value="cumulative">' . v2raysocks_traffic_lang('cumulative_traffic') . '</option>
                                <option value="total_cumulative">' . v2raysocks_traffic_lang('total_cumulative_traffic') . '</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="node-traffic-chart"></canvas>
                </div>
                
                <!-- Traffic History Data Container -->
                <div class="usage-records-section" style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px;">
                    <!-- Search Area moved inside container -->
                    <div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                            <div style="flex: 0 0 180px; min-width: 150px;">
                                <label for="node-search-type" style="display: block; margin-bottom: 5px; font-weight: 500;">' . v2raysocks_traffic_lang('search_type_label') . ':</label>
                                <select id="node-search-type" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                                    <option value="uuid">UUID</option>
                                    <option value="user_id">' . v2raysocks_traffic_lang('user_id') . '</option>
                                </select>
                            </div>
                            <div style="flex: 0 0 200px; min-width: 150px;">
                                <label for="node-search-value" style="display: block; margin-bottom: 5px; font-weight: 500;">' . v2raysocks_traffic_lang('search_value_label') . ':</label>
                                <input type="text" id="node-search-value" placeholder="' . v2raysocks_traffic_lang('search_value_placeholder') . '" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                            </div>

                            <div style="display: flex; gap: 10px;">
                                <button id="search-node-records" class="btn btn-primary" style="padding: 8px 16px;">' . v2raysocks_traffic_lang('search') . '</button>
                            </div>
                        </div>

                    </div>
                    
                    <div class="usage-records-header">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0;">' . v2raysocks_traffic_lang('traffic_history') . '</h4>
                            <button class="btn btn-success" onclick="exportNodeUsageRecords()" style="padding: 6px 12px;">' . v2raysocks_traffic_lang('export_data') . '</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>' . v2raysocks_traffic_lang('time_column') . '</th>
                                    <th>' . v2raysocks_traffic_lang('user_id') . '</th>
                                    <th>UUID</th>
                                    <th>' . v2raysocks_traffic_lang('upload') . '</th>
                                    <th>' . v2raysocks_traffic_lang('download') . '</th>
                                    <th>' . v2raysocks_traffic_lang('total') . '</th>
                                    <th>' . v2raysocks_traffic_lang('rate_column') . '</th>
                                </tr>
                            </thead>
                            <tbody id="node-records-tbody">
                                <tr>
                                    <td colspan="7" class="loading">' . v2raysocks_traffic_lang('loading_usage_records') . '</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Pagination for usage records -->
                        <div id="node-usage-pagination" style="margin-top: 15px; display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span id="node-pagination-info">' . v2raysocks_traffic_lang('showing_records') . '</span>
                                </div>
                                <div>
                                    <label for="node-records-per-page" style="margin-right: 10px;">' . v2raysocks_traffic_lang('records_per_page_label') . ':</label>
                                    <select id="node-records-per-page" style="margin-right: 15px; padding: 5px;">
                                        <option value="25">25</option>
                                        <option value="50" selected>50</option>
                                        <option value="100">100</option>
                                        <option value="200">200</option>
                                    </select>
                                    
                                    <button id="node-first-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('first_page') . '</button>
                                    <button id="node-prev-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('previous_page') . '</button>
                                    <span id="node-page-info" style="margin: 0 10px;">' . v2raysocks_traffic_lang('page_info') . '</span>
                                    <button id="node-next-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('next_page') . '</button>
                                    <button id="node-last-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('last_page') . '</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentNodeChart = null;
        let currentNodeId = null;
        let currentNodeName = null;
        let allNodeUsageRecords = [];
        let currentNodeUsagePage = 1;
        let nodeUsageRecordsPerPage = 50;
        let totalNodeUsagePages = 1;
        let currentSort = { field: "rank", direction: "asc" };
        let allNodeRankings = [];
        
        // Load node rankings on page load
        $(document).ready(function() {
            loadNodeRankings();
            
            // Add event listener for sortable headers
            $(".sortable-header").on("click", function() {
                const sortField = $(this).data("sort");
                
                // Toggle sort direction if clicking the same field
                if (currentSort.field === sortField) {
                    currentSort.direction = currentSort.direction === "asc" ? "desc" : "asc";
                } else {
                    currentSort.field = sortField;
                    currentSort.direction = "desc"; // Default to descending for new field
                }
                
                // Update sort indicators
                updateSortIndicators();
                
                // Apply sorting to current data
                if (allNodeRankings.length > 0) {
                    sortAndDisplayNodeRankings();
                } else {
                    // Load fresh data if no data is cached
                    loadNodeRankings();
                }
            });
            
            // Time range change handler for custom range
            $("#node-rankings-time-range").on("change", function() {
                const isCustomRange = $(this).val() === "custom_range";
                $("#node-rankings-custom-time-range").toggle(isCustomRange);
            });
        });
        
        function loadNodeRankings() {
            const showOffline = document.getElementById("show-offline").value === "true";
            const timeRange = document.getElementById("node-rankings-time-range").value;
            
            const tbody = document.getElementById("rankings-tbody");
            tbody.innerHTML = `<tr><td colspan="21" class="loading">${t("loading_node_rankings")}</td></tr>`;
            
            // Build API URL with time range parameters
            let apiUrl = "addonmodules.php?module=v2raysocks_traffic&action=get_node_traffic_rankings";
            
            if (timeRange === "custom_range") {
                const startTime = document.getElementById("node-rankings-start-time").value;
                const endTime = document.getElementById("node-rankings-end-time").value;
                
                if (startTime && endTime) {
                    // Convert time to today\'s date + time for timestamp calculation
                    const today = new Date();
                    const todayStr = today.getFullYear() + "-" + 
                                    (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                    today.getDate().toString().padStart(2, "0");
                    const startDateTime = todayStr + " " + startTime;
                    const endDateTime = todayStr + " " + endTime;
                    const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                    const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                    
                    apiUrl += `&time_range=custom&start_timestamp=${startTimestamp}&end_timestamp=${endTimestamp}`;
                } else {
                    alert("Please select both start and end times for custom range");
                    return;
                }
            } else {
                apiUrl += `&time_range=${timeRange}`;
            }
            
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        let nodes = data.data;
                        
                        // Filter offline nodes if needed
                        if (!showOffline) {
                            nodes = nodes.filter(node => node.is_online);
                        }
                        
                        allNodeRankings = nodes;
                        sortAndDisplayNodeRankings();
                    } else {
                        tbody.innerHTML = `<tr><td colspan="21" class="no-data">${t("loading_failed")} ${data.message || t("unknown_error")}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error("Error loading node rankings:", error);
                    tbody.innerHTML = `<tr><td colspan="21" class="no-data">${t("network_error_retry")}</td></tr>`;
                });
        }
        
        function updateSortIndicators() {
            // Remove all sort indicators
            $(".sortable-header").removeClass("sort-asc sort-desc");
            
            // Add indicator to current sort field
            $(`.sortable-header[data-sort="${currentSort.field}"]`).addClass(`sort-${currentSort.direction}`);
        }
        
        function sortAndDisplayNodeRankings() {
            if (!allNodeRankings || allNodeRankings.length === 0) {
                displayNodeRankings([]);
                return;
            }
            
            // Sort the data
            const sortedData = [...allNodeRankings].sort((a, b) => {
                let aValue, bValue;
                
                switch (currentSort.field) {
                    case "rank":
                        // For rank, we use the original array index (0-based) + 1
                        aValue = allNodeRankings.indexOf(a) + 1;
                        bValue = allNodeRankings.indexOf(b) + 1;
                        break;
                    case "node_id":
                        aValue = parseInt(a.id) || 0;
                        bValue = parseInt(b.id) || 0;
                        break;
                    case "node_name":
                        aValue = (a.name || "").toLowerCase();
                        bValue = (b.name || "").toLowerCase();
                        break;
                    case "address":
                        aValue = (a.address || "").toLowerCase();
                        bValue = (b.address || "").toLowerCase();
                        break;
                    case "total_traffic_limit":
                        aValue = a.max_traffic || 0;
                        bValue = b.max_traffic || 0;
                        break;
                    case "used_traffic_statistics":
                        aValue = a.statistics || 0;
                        bValue = b.statistics || 0;
                        break;
                    case "remaining_traffic":
                        aValue = a.remaining_traffic || 0;
                        bValue = b.remaining_traffic || 0;
                        break;
                    case "today_traffic":
                        aValue = a.total_traffic || 0;
                        bValue = b.total_traffic || 0;
                        break;
                    case "traffic_usage_rate":
                        aValue = a.traffic_utilization || 0;
                        bValue = b.traffic_utilization || 0;
                        break;
                    case "traffic_5min":
                        aValue = a.traffic_5min || 0;
                        bValue = b.traffic_5min || 0;
                        break;
                    case "traffic_1hour":
                        aValue = a.traffic_1hour || 0;
                        bValue = b.traffic_1hour || 0;
                        break;
                    case "traffic_4hour":
                        aValue = a.traffic_4hour || 0;
                        bValue = b.traffic_4hour || 0;
                        break;
                    case "user_count":
                        aValue = a.unique_users || 0;
                        bValue = b.unique_users || 0;
                        break;
                    case "record_count":
                        aValue = a.usage_records || 0;
                        bValue = b.usage_records || 0;
                        break;
                    case "excessive_speed_limit":
                        aValue = (a.excessive_speed_limit || "").toLowerCase();
                        bValue = (b.excessive_speed_limit || "").toLowerCase();
                        break;
                    case "speed_limit":
                        aValue = (a.speed_limit || "").toLowerCase();
                        bValue = (b.speed_limit || "").toLowerCase();
                        break;
                    case "country":
                        aValue = (a.country || "").toLowerCase();
                        bValue = (b.country || "").toLowerCase();
                        break;
                    case "online_status":
                        aValue = a.is_online ? 1 : 0;
                        bValue = b.is_online ? 1 : 0;
                        break;
                    case "last_online":
                        aValue = a.last_seen_minutes || 0;
                        bValue = b.last_seen_minutes || 0;
                        break;
                    case "protocol":
                        aValue = (a.type || "").toLowerCase();
                        bValue = (b.type || "").toLowerCase();
                        break;
                    case "billing_rate":
                        aValue = a.count_rate || 1.0;
                        bValue = b.count_rate || 1.0;
                        break;

                    default:
                        aValue = a.total_traffic || 0;
                        bValue = b.total_traffic || 0;
                }
                
                // Handle string comparisons
                if (typeof aValue === "string" && typeof bValue === "string") {
                    return currentSort.direction === "asc" ? 
                        aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                }
                
                // Handle numeric comparisons
                return currentSort.direction === "asc" ? 
                    (aValue - bValue) : (bValue - aValue);
            });
            
            displayNodeRankings(sortedData);
            updateSortIndicators();
        }
        
        function displayNodeRankings(nodes) {
            const tbody = document.getElementById("rankings-tbody");
            
            if (!nodes || nodes.length === 0) {
                tbody.innerHTML = `<tr><td colspan="21" class="no-data">${t("no_data")}</td></tr>`;
                return;
            }
            
            let html = "";
            nodes.forEach((node, index) => {
                const rank = index + 1;
                const rankClass = rank === 1 ? "rank-1" : rank === 2 ? "rank-2" : rank === 3 ? "rank-3" : "rank-other";
                
                const utilizationPercent = node.traffic_utilization || 0;
                const progressWidth = Math.min(100, utilizationPercent); // Cap visual width at 100%
                
                // Determine color class based on utilization
                let colorClass = \'normal\';
                if (utilizationPercent >= 100) {
                    colorClass = \'danger\';
                } else if (utilizationPercent >= 80) {
                    colorClass = \'warning\';
                }
                
                const statusClass = node.is_online ? "status-online" : "status-offline";
                const statusText = node.is_online ? t("online") : t("offline");
                
                const lastSeenText = node.is_online ? t("online") : 
                    (node.last_seen_minutes < 60 ? `${node.last_seen_minutes}${t("minutes_ago")}` :
                     node.last_seen_minutes < 1440 ? `${Math.floor(node.last_seen_minutes / 60)}${t("hours_ago")}` :
                     `${Math.floor(node.last_seen_minutes / 1440)}${t("days_ago")}`);
                
                html += `
                    <tr onclick="showNodeDetails(${node.id})">
                        <td><span class="rank-badge ${rankClass}">${rank}</span></td>
                        <td>${node.id}</td>
                        <td title="${node.name}">${node.name}</td>
                        <td>${node.type || "-"}</td>
                        <td title="${node.address}">${node.address ? (node.address.length > 40 ? node.address.substring(0, 40) + "..." : node.address) : "N/A"}</td>
                        <td>${formatBytes(node.max_traffic * 1000000000)}</td>
                        <td>${formatBytes(node.statistics)}</td>
                        <td>${formatBytes(node.remaining_traffic)}</td>
                        <td>${formatBytes(node.total_traffic)}</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill ${colorClass}" style="width: ${progressWidth}%"></div>
                                <div class="progress-text">${utilizationPercent.toFixed(1)}%</div>
                            </div>
                        </td>
                        <td class="numeric-cell">${formatBytes(node.traffic_5min || 0)}</td>
                        <td class="numeric-cell">${formatBytes(node.traffic_1hour || 0)}</td>
                        <td class="numeric-cell">${formatBytes(node.traffic_4hour || 0)}</td>
                        <td>${node.count_rate || "1.0"}x</td>
                        <td>${node.unique_users}</td>
                        <td>${node.usage_records}</td>
                        <td>${node.excessive_speed_limit || "-"}</td>
                        <td>${node.speed_limit || "-"}</td>
                        <td>${node.country || "N/A"}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${lastSeenText}</td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        function showNodeDetails(nodeId) {
            currentNodeId = nodeId;
            
            // Find the node name from the current data
            const nodeData = allNodeRankings.find(node => node.id == nodeId);
            currentNodeName = nodeData ? nodeData.name : null;
            
            const modal = document.getElementById("node-modal");
            const nodeInfo = document.getElementById("node-info");
            const recordsTbody = document.getElementById("node-records-tbody");
            
            modal.style.display = "block";
            nodeInfo.innerHTML = `<div class="loading">${t("loading")}</div>`;
            recordsTbody.innerHTML = `<tr><td colspan="7" class="loading">${t("loading_usage_records")}</td></tr>`;
            
            // Reset pagination
            currentNodeUsagePage = 1;
            allNodeUsageRecords = [];
            document.getElementById("node-usage-pagination").style.display = "none";
            
            // Add event listeners for chart controls
            document.getElementById("chart-unit").addEventListener("change", updateNodeChart);
            document.getElementById("chart-mode").addEventListener("change", updateNodeChart);
            
            // Load all modal data atomically to prevent race conditions
            loadNodeModalData();
        }
        
        function loadNodeModalData() {
            // Get current time range selection
            const timeRange = document.getElementById("node-rankings-time-range").value;
            let timeRangeParam = timeRange;
            let chartUrlParams = `addonmodules.php?module=v2raysocks_traffic&action=get_node_traffic_chart&node_id=${currentNodeId}`;
            let usageUrlParams = `addonmodules.php?module=v2raysocks_traffic&action=get_usage_records&node_id=${currentNodeId}&limit=1000`;
            
            if (timeRange === "custom_range") {
                const startTime = document.getElementById("node-rankings-start-time").value;
                const endTime = document.getElementById("node-rankings-end-time").value;
                
                if (startTime && endTime) {
                    // Convert time to today\'s date + time for timestamp calculation
                    const today = new Date();
                    const todayStr = today.getFullYear() + "-" + 
                                    (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                    today.getDate().toString().padStart(2, "0");
                    const startDateTime = todayStr + " " + startTime;
                    const endDateTime = todayStr + " " + endTime;
                    const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                    const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                    
                    chartUrlParams += `&time_range=custom&start_timestamp=${startTimestamp}&end_timestamp=${endTimestamp}`;
                    usageUrlParams += `&time_range=custom&start_timestamp=${startTimestamp}&end_timestamp=${endTimestamp}`;
                    timeRangeParam = "custom";
                } else {
                    // Fallback to today if custom time is not set
                    chartUrlParams += "&time_range=today";
                    usageUrlParams += "&time_range=today";
                    timeRangeParam = "today";
                }
            } else {
                chartUrlParams += `&time_range=${timeRange}`;
                usageUrlParams += `&time_range=${timeRange}`;
            }
            
            // Load chart data and usage records atomically using Promise.all
            Promise.all([
                fetch(chartUrlParams).then(response => {
                    if (!response.ok) {
                        throw new Error(`Chart API HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                }),
                fetch(usageUrlParams).then(response => {
                    if (!response.ok) {
                        throw new Error(`Usage API HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
            ])
            .then(([chartResponse, usageResponse]) => {
                // Process chart data
                if (chartResponse.status === "success" && chartResponse.data) {
                    displayNodeChart(chartResponse.data);
                    updateNodeInfoWithChartData(chartResponse.data);
                } else {
                    console.log("Chart API returned error:", chartResponse);
                    const nodeInfo = document.getElementById("node-info");
                    nodeInfo.innerHTML = `<div class="no-data">${t("no_traffic_data")} ${chartResponse.message || t("no_traffic_records_period")}</div>`;
                    
                    // Display empty chart
                    displayNodeChart({
                        labels: [],
                        upload: [],
                        download: [],
                        total: [],
                        node_id: currentNodeId
                    });
                }
                
                // Process usage records
                if (usageResponse.status === "success") {
                    allNodeUsageRecords = usageResponse.data || [];
                    updateNodeUsagePagination();
                } else {
                    const recordsTbody = document.getElementById("node-records-tbody");
                    recordsTbody.innerHTML = `<tr><td colspan="7" class="no-data">${t("failed_load_usage_records")} ${usageResponse.message}</td></tr>`;
                }
            })
            .catch(error => {
                console.error("Error loading node modal data:", error);
                const nodeInfo = document.getElementById("node-info");
                const recordsTbody = document.getElementById("node-records-tbody");
                
                nodeInfo.innerHTML = `<div class="no-data">${t("loading_failed")} ${error.message || t("network_connection_error")}</div>`;
                recordsTbody.innerHTML = `<tr><td colspan="7" class="no-data">${t("network_error_retry")}</td></tr>`;
                
                // Display empty chart on error
                displayNodeChart({
                    labels: [],
                    upload: [],
                    download: [],
                    total: [],
                    node_id: currentNodeId
                });
            });
        }
        
        function getTimeRangeDisplayText(timeRange) {
            const now = new Date();
            
            switch (timeRange) {
                case "today":
                    return t("today_range");
                case "last_1_hour":
                    const oneHourAgo = new Date(now.getTime() - 60 * 60 * 1000);
                    return oneHourAgo.toTimeString().slice(0, 5) + " - " + now.toTimeString().slice(0, 5);
                case "last_3_hours":
                    const threeHoursAgo = new Date(now.getTime() - 3 * 60 * 60 * 1000);
                    return threeHoursAgo.toTimeString().slice(0, 5) + " - " + now.toTimeString().slice(0, 5);
                case "last_6_hours":
                    const sixHoursAgo = new Date(now.getTime() - 6 * 60 * 60 * 1000);
                    return sixHoursAgo.toTimeString().slice(0, 5) + " - " + now.toTimeString().slice(0, 5);
                case "last_12_hours":
                    const twelveHoursAgo = new Date(now.getTime() - 12 * 60 * 60 * 1000);
                    return twelveHoursAgo.toTimeString().slice(0, 5) + " - " + now.toTimeString().slice(0, 5);
                case "custom":
                    const startTime = document.getElementById("node-rankings-start-time").value;
                    const endTime = document.getElementById("node-rankings-end-time").value;
                    if (startTime && endTime) {
                        return startTime + " - " + endTime;
                    }
                    return "Custom range";
                default:
                    return timeRange;
            }
        }
        
        function updateNodeInfoWithChartData(chartData) {
            const nodeInfo = document.getElementById("node-info");
            const timeRange = document.getElementById("node-rankings-time-range").value;
            const timeRangeParam = timeRange === "custom_range" ? "custom" : timeRange;
            
            // Calculate totals from chart data (already in GB from API)
            const totalUpload = chartData.upload ? chartData.upload.reduce((sum, val) => sum + (val || 0), 0) : 0;
            const totalDownload = chartData.download ? chartData.download.reduce((sum, val) => sum + (val || 0), 0) : 0;
            const totalTraffic = totalUpload + totalDownload;
            
            // Convert GB to bytes for display
            const totalUploadBytes = totalUpload * 1000000000;
            const totalDownloadBytes = totalDownload * 1000000000;
            const totalTrafficBytes = totalTraffic * 1000000000;
            
            nodeInfo.innerHTML = `
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">${t("node_name_label")}</div>
                        <div class="info-value">${currentNodeName || (t("node_prefix") + " " + currentNodeId)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("time_range_label")}</div>
                        <div class="info-value">${getTimeRangeDisplayText(timeRangeParam)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("upload_traffic")}</div>
                        <div class="info-value text-success">${formatBytes(totalUploadBytes)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("download_traffic")}</div>
                        <div class="info-value text-info">${formatBytes(totalDownloadBytes)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("total_traffic_label")}</div>
                        <div class="info-value text-primary">${formatBytes(totalTrafficBytes)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("recent_5min_traffic_label")}</div>
                        <div class="info-value text-warning" id="node-recent-5min-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("recent_1hour_traffic_label")}</div>
                        <div class="info-value text-warning" id="node-recent-1hour-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("recent_4hour_traffic_label")}</div>
                        <div class="info-value text-warning" id="node-recent-4hour-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("peak_time")}</div>
                        <div class="info-value text-info" id="node-peak-time">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("idle_time")}</div>
                        <div class="info-value text-info" id="node-idle-time">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("peak_traffic")}</div>
                        <div class="info-value text-warning" id="node-peak-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("idle_traffic")}</div>
                        <div class="info-value text-warning" id="node-idle-traffic">-</div>
                    </div>
                </div>
            `;
            
            // Then fetch and update recent traffic data
            fetchNodeRecentTrafficData();
            
            // Also fetch peak/idle statistics
            fetchNodePeakIdleStats();
        }
        
        function fetchNodeRecentTrafficData() {
            // Fetch node ranking data to get recent traffic information
            const rankingsUrl = `addonmodules.php?module=v2raysocks_traffic&action=get_node_traffic_rankings&sort_by=traffic_desc&only_today=true`;
            
            fetch(rankingsUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Node rankings API HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(rankingsResponse => {
                    if (rankingsResponse.status === "success" && rankingsResponse.data) {
                        // Find the current node in the rankings data
                        const nodeData = rankingsResponse.data.find(node => node.id == currentNodeId);
                        if (nodeData) {
                            // Update recent traffic data with actual values
                            document.getElementById("node-recent-5min-traffic").innerHTML = formatBytes(nodeData.traffic_5min);
                            document.getElementById("node-recent-1hour-traffic").innerHTML = formatBytes(nodeData.traffic_1hour);
                            document.getElementById("node-recent-4hour-traffic").innerHTML = formatBytes(nodeData.traffic_4hour);
                        }
                    }
                })
                .catch(error => {
                    console.error("Error loading node recent traffic data:", error);
                    // Keep "-" values on error
                });
        }
        
        function fetchNodePeakIdleStats() {
            // Fetch node traffic chart data for peak/idle calculation
            const apiUrl = `addonmodules.php?module=v2raysocks_traffic&action=get_node_traffic_chart&node_id=${currentNodeId}&time_range=today`;
            
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Node chart API HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(response => {
                    if (response.status === "success" && response.data && response.data.labels && response.data.total) {
                        // Calculate peak time and idle time using chart data arrays
                        let peakTime = "";
                        let peakTraffic = 0;
                        let idleTime = "";
                        let idleTraffic = Number.MAX_VALUE;
                        
                        // Iterate through the synchronized arrays
                        for (let i = 0; i < response.data.labels.length; i++) {
                            const timeLabel = response.data.labels[i];
                            const totalTraffic = response.data.total[i] || 0;
                            
                            if (totalTraffic > peakTraffic) {
                                peakTraffic = totalTraffic;
                                peakTime = timeLabel;
                            }
                            if (totalTraffic < idleTraffic && totalTraffic > 0) {
                                idleTraffic = totalTraffic;
                                idleTime = timeLabel;
                            }
                        }
                        
                        // If no valid idle traffic found, set to 0
                        if (idleTraffic === Number.MAX_VALUE) {
                            idleTraffic = 0;
                        }
                        
                        // Update the display elements
                        // API returns data in GB, so convert to bytes for formatBytes function
                        document.getElementById("node-peak-time").textContent = peakTime || "-";
                        document.getElementById("node-idle-time").textContent = idleTime || "-";
                        document.getElementById("node-peak-traffic").innerHTML = formatBytes(peakTraffic * 1000000000);
                        document.getElementById("node-idle-traffic").innerHTML = formatBytes(idleTraffic * 1000000000);
                    } else {
                        // No data available, keep default "-" values
                        console.log("No chart data available for peak/idle calculation");
                    }
                })
                .catch(error => {
                    console.error("Error loading node peak/idle statistics:", error);
                    // Keep "-" values on error
                });
        }
        
        function loadNodeUsageRecords() {
            // Get search parameters from search controls only
            const searchType = document.getElementById("node-search-type").value;
            const searchValue = document.getElementById("node-search-value").value.trim();
            
            // Use main page time range instead of modal time filter
            const timeFilter = document.getElementById("node-rankings-time-range").value;
            const startTime = document.getElementById("node-rankings-start-time").value;
            const endTime = document.getElementById("node-rankings-end-time").value;
            
            // Build query parameters
            let queryParams = `node_id=${currentNodeId}&limit=1000`;
            
            // Add search value based on selected type
            if (searchValue) {
                if (searchType === "uuid") {
                    queryParams += `&uuid=${encodeURIComponent(searchValue)}`;
                } else if (searchType === "user_id") {
                    queryParams += `&user_id=${encodeURIComponent(searchValue)}`;
                }
            }
            
            // Handle time filtering using main page selection
            if (timeFilter === "custom_range" && startTime && endTime) {
                // Convert time values to current date + time for timestamp calculation
                const today = new Date();
                const todayStr = today.getFullYear() + "-" + 
                                (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                today.getDate().toString().padStart(2, "0");
                const startDateTime = todayStr + " " + startTime;
                const endDateTime = todayStr + " " + endTime;
                const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                queryParams += `&export_start_timestamp=${startTimestamp}&export_end_timestamp=${endTimestamp}`;
            } else if (timeFilter !== "today") {
                queryParams += `&time_range=${timeFilter}`;
            } else {
                queryParams += `&time_range=today`;
            }
            
            fetch(`addonmodules.php?module=v2raysocks_traffic&action=get_usage_records&${queryParams}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        allNodeUsageRecords = data.data || [];
                        updateNodeUsagePagination();
                    } else {
                        const recordsTbody = document.getElementById("node-records-tbody");
                        recordsTbody.innerHTML = `<tr><td colspan="7" class="no-data">${t("failed_load_usage_records")} ${data.message}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error("Error loading usage records:", error);
                    const recordsTbody = document.getElementById("node-records-tbody");
                    recordsTbody.innerHTML = `<tr><td colspan="7" class="no-data">${t("network_error_retry")}</td></tr>`;
                });
        }
        
        function updateNodeChart() {
            // Reload all modal data to ensure consistency
            loadNodeModalData();
        }
        
        function updateNodeUsagePagination() {
            const tbody = document.getElementById("node-records-tbody");
            const paginationDiv = document.getElementById("node-usage-pagination");
            
            if (!allNodeUsageRecords || allNodeUsageRecords.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="no-data">${t("no_usage_records")}</td></tr>`;
                paginationDiv.style.display = "none";
                return;
            }
            
            // Calculate pagination
            nodeUsageRecordsPerPage = parseInt(document.getElementById("node-records-per-page").value);
            totalNodeUsagePages = Math.ceil(allNodeUsageRecords.length / nodeUsageRecordsPerPage);
            const startIndex = (currentNodeUsagePage - 1) * nodeUsageRecordsPerPage;
            const endIndex = Math.min(startIndex + nodeUsageRecordsPerPage, allNodeUsageRecords.length);
            const pageData = allNodeUsageRecords.slice(startIndex, endIndex);
            
            // Generate table rows
            let html = "";
            pageData.forEach(record => {
                html += `
                    <tr>
                        <td>${record.formatted_time}</td>
                        <td>${record.user_id}</td>
                        <td class="uuid-column" title="${record.uuid || "N/A"}">${record.uuid || "N/A"}</td>
                        <td>${record.formatted_upload}</td>
                        <td>${record.formatted_download}</td>
                        <td>${record.formatted_total}</td>
                        <td>${record.count_rate}x</td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            // Update pagination info
            document.getElementById("node-pagination-info").textContent = t("showing_records", {
                start: startIndex + 1,
                end: endIndex,
                total: allNodeUsageRecords.length
            });
            document.getElementById("node-page-info").textContent = t("page_info", {
                current: currentNodeUsagePage,
                total: totalNodeUsagePages
            });
            
            // Enable/disable pagination buttons
            document.getElementById("node-first-page").disabled = currentNodeUsagePage === 1;
            document.getElementById("node-prev-page").disabled = currentNodeUsagePage === 1;
            document.getElementById("node-next-page").disabled = currentNodeUsagePage === totalNodeUsagePages;
            document.getElementById("node-last-page").disabled = currentNodeUsagePage === totalNodeUsagePages;
            
            paginationDiv.style.display = "block";
        }
        
        // Generate default time labels for empty charts
        function generateDefaultTimeLabels(timeRange = "today", points = 10) {
            const now = new Date();
            const labels = [];
            
            let start, interval;
            switch (timeRange) {
                case "5min":
                    start = new Date(now.getTime() - 5 * 60 * 1000);
                    interval = (5 * 60 * 1000) / (points - 1);
                    break;
                case "10min":
                    start = new Date(now.getTime() - 10 * 60 * 1000);
                    interval = (10 * 60 * 1000) / (points - 1);
                    break;
                case "30min":
                    start = new Date(now.getTime() - 30 * 60 * 1000);
                    interval = (30 * 60 * 1000) / (points - 1);
                    break;
                case "1hour":
                    start = new Date(now.getTime() - 60 * 60 * 1000);
                    interval = (60 * 60 * 1000) / (points - 1);
                    break;
                case "today":
                default:
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    interval = (24 * 60 * 60 * 1000) / (points - 1);
                    break;
            }
            
            for (let i = 0; i < points; i++) {
                const timestamp = new Date(start.getTime() + (i * interval));
                
                // For "today" type ranges, do not generate future time points
                if (timeRange === "today" && timestamp > now) {
                    break;
                }
                
                if (timeRange === "today" || timeRange.includes("hour") || timeRange.includes("min")) {
                    // Use consistent time formatting like service_search.php
                    labels.push(timestamp.getHours().toString().padStart(2, "0") + ":00");
                } else {
                    // Use consistent date formatting - YYYY-MM-DD format
                    const year = timestamp.getFullYear();
                    const month = String(timestamp.getMonth() + 1).padStart(2, "0");
                    const day = String(timestamp.getDate()).padStart(2, "0");
                    labels.push(year + "-" + month + "-" + day);
                }
            }
            
            return labels;
        }

        // Generate complete time series to prevent chart discontinuity
        function generateCompleteTimeSeriesForNodeChart(timeRange) {
            const labels = [];
            const now = new Date();
            
            switch (timeRange) {
                case "today":
                default:
                    // Generate hours up to current time only
                    const currentHour = now.getHours();
                    for (let hour = 0; hour <= currentHour; hour++) {
                        labels.push(hour.toString().padStart(2, "0") + ":00");
                    }
                    break;
                    
                case "week":
                    // Generate all 7 days for the past week - use YYYY-MM-DD format  
                    for (let i = 6; i >= 0; i--) {
                        const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, "0");
                        const day = String(date.getDate()).padStart(2, "0");
                        labels.push(year + "-" + month + "-" + day);
                    }
                    break;
                    
                case "month":
                    // Generate all 30 days for the past month - use YYYY-MM-DD format
                    for (let i = 29; i >= 0; i--) {
                        const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, "0");
                        const day = String(date.getDate()).padStart(2, "0");
                        labels.push(year + "-" + month + "-" + day);
                    }
                    break;
                    
                case "custom":
                    // Generate hours for custom time range to show continuous curve
                    const startTimeInput = document.getElementById("node-rankings-start-time").value;
                    const endTimeInput = document.getElementById("node-rankings-end-time").value;
                    if (startTimeInput && endTimeInput) {
                        const [startHour, startMin] = startTimeInput.split(":").map(Number);
                        const [endHour, endMin] = endTimeInput.split(":").map(Number);
                        
                        // Generate hourly labels within the selected time range
                        // If end time is earlier than start time, assume next day
                        let currentHour = startHour;
                        const maxHour = endHour < startHour ? endHour + 24 : endHour;
                        
                        while (currentHour <= maxHour) {
                            const displayHour = currentHour % 24;
                            labels.push(displayHour.toString().padStart(2, "0") + ":00");
                            currentHour++;
                        }
                    } else {
                        // Fallback: return empty array if custom time inputs are not available
                        return [];
                    }
                    break;
            }
            
            return labels;
        }

        function displayNodeChart(chartData) {
            const ctx = document.getElementById("node-traffic-chart").getContext("2d");
            
            if (currentNodeChart) {
                currentNodeChart.destroy();
            }
            
            // Get the actual time range from chart data, fallback to "today"
            const actualTimeRange = chartData.time_range || "today";
            
            // Handle empty data case - use proper time labels instead of placeholder
            if (!chartData.labels || chartData.labels.length === 0) {
                const defaultLabels = generateDefaultTimeLabels("today", 8);
                chartData = {
                    labels: defaultLabels,
                    upload: new Array(defaultLabels.length).fill(0),
                    download: new Array(defaultLabels.length).fill(0), 
                    total: new Array(defaultLabels.length).fill(0),
                    node_id: chartData.node_id || "Unknown"
                };
            } else {
                // Ensure complete time series to prevent gaps
                const completeLabels = generateCompleteTimeSeriesForNodeChart(actualTimeRange);
                
                // If complete labels are available, use them to fill gaps
                if (completeLabels.length > 0) {
                    // Store original data for processing
                    const originalData = {
                        labels: [...chartData.labels],
                        upload: [...chartData.upload],
                        download: [...chartData.download],
                        total: [...chartData.total]
                    };
                    
                    // Reset arrays to match complete time series
                    chartData.labels = completeLabels;
                    chartData.upload = new Array(completeLabels.length).fill(0);
                    chartData.download = new Array(completeLabels.length).fill(0);
                    chartData.total = new Array(completeLabels.length).fill(0);
                    
                    // Fill in actual data where available
                    originalData.labels.forEach((label, index) => {
                        const completeIndex = completeLabels.indexOf(label);
                        if (completeIndex !== -1) {
                            chartData.upload[completeIndex] = originalData.upload[index] || 0;
                            chartData.download[completeIndex] = originalData.download[index] || 0;
                            chartData.total[completeIndex] = originalData.total[index] || 0;
                        }
                    });
                } else {
                    // Use the chart data as-is when no complete series is needed
                    // This preserves the exact time filtering applied by the backend
                }
            }
            
            // Get current unit and mode settings
            const unit = document.getElementById("chart-unit").value;
            const mode = document.getElementById("chart-mode").value;
            
            // Convert data based on unit - handle auto unit
            let unitMultiplier, unitLabel;
            if (unit === "auto") {
                // For auto, determine best fit based on data size
                const maxValue = Math.max(
                    ...chartData.upload,
                    ...chartData.download,
                    ...chartData.total
                ) * 1000000000; // Convert from GB to bytes
                
                if (maxValue >= 1000000000000) {
                    unitMultiplier = 1000000000000;
                    unitLabel = "TB";
                } else if (maxValue >= 1000000000) {
                    unitMultiplier = 1000000000;
                    unitLabel = "GB";
                } else {
                    unitMultiplier = 1000000;
                    unitLabel = "MB";
                }
            } else {
                unitMultiplier = getUnitMultiplier(unit);
                unitLabel = unit;
            }
            
            // Prepare datasets based on mode
            let datasets = [];
            let processedData = {};
            
            switch (mode) {
                case "separate":
                    // Show upload and download separately
                    datasets = [
                        {
                            label: t("upload_traffic_unit", {unit: unitLabel}),
                            data: chartData.upload.map(val => val * 1000000000 / unitMultiplier),
                            borderColor: CHART_COLORS.upload,
                            backgroundColor: CHART_COLORS.upload + "20",
                            tension: 0.4,
                            fill: false,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        },
                        {
                            label: t("download_traffic_unit", {unit: unitLabel}),
                            data: chartData.download.map(val => val * 1000000000 / unitMultiplier),
                            borderColor: CHART_COLORS.download,
                            backgroundColor: CHART_COLORS.download + "20",
                            tension: 0.4,
                            fill: false,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        }
                    ];
                    break;
                case "total":
                    // Show total traffic only
                    datasets = [
                        {
                            label: t("total_traffic_unit", {unit: unitLabel}),
                            data: chartData.total.map(val => val * 1000000000 / unitMultiplier),
                            borderColor: CHART_COLORS.total,
                            backgroundColor: CHART_COLORS.total + "20",
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        }
                    ];
                    break;
                case "cumulative":
                    // Show cumulative upload and download
                    let cumulativeUpload = [];
                    let cumulativeDownload = [];
                    let uploadSum = 0;
                    let downloadSum = 0;
                    
                    chartData.upload.forEach((val, index) => {
                        uploadSum += val;
                        downloadSum += chartData.download[index];
                        cumulativeUpload.push(uploadSum * 1000000000 / unitMultiplier);
                        cumulativeDownload.push(downloadSum * 1000000000 / unitMultiplier);
                    });
                    
                    datasets = [
                        {
                            label: t("cumulative_upload_unit", {unit: unitLabel}),
                            data: cumulativeUpload,
                            borderColor: CHART_COLORS.upload,
                            backgroundColor: CHART_COLORS.upload + "20",
                            tension: 0.4,
                            fill: false,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        },
                        {
                            label: t("cumulative_download_unit", {unit: unitLabel}),
                            data: cumulativeDownload,
                            borderColor: CHART_COLORS.download,
                            backgroundColor: CHART_COLORS.download + "20",
                            tension: 0.4,
                            fill: false,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        }
                    ];
                    break;
                case "total_cumulative":
                    // Show total cumulative traffic
                    let cumulativeTotal = [];
                    let totalSum = 0;
                    
                    chartData.total.forEach(val => {
                        totalSum += val;
                        cumulativeTotal.push(totalSum * 1000000000 / unitMultiplier);
                    });
                    
                    datasets = [
                        {
                            label: t("total_cumulative_traffic_unit", {unit: unitLabel}),
                            data: cumulativeTotal,
                            borderColor: CHART_COLORS.total,
                            backgroundColor: CHART_COLORS.total + "20",
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        }
                    ];
                    break;
            }
            
            currentNodeChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: chartData.labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: t("node_today_usage_trends", {node_name: currentNodeName || (t("node_prefix") + " " + currentNodeId)}),
                            font: {
                                size: 16,
                                weight: "bold"
                            }
                        },
                        legend: {
                            display: true,
                            position: "top"
                        },
                        tooltip: {
                            mode: "nearest",
                            intersect: true,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed.y;
                                    const formattedValue = value.toFixed(2);
                                    const label = context.dataset.label || "";
                                    
                                    // Use translation functions instead of regex replacement
                                    let cleanLabel;
                                    if (label.includes("upload") || label.includes("上传") || label.includes("上傳")) {
                                        if (label.includes("cumulative") || label.includes("累积") || label.includes("累積")) {
                                            cleanLabel = t("cumulative_upload");
                                        } else {
                                            cleanLabel = t("upload");
                                        }
                                    } else if (label.includes("download") || label.includes("下载") || label.includes("下載")) {
                                        if (label.includes("cumulative") || label.includes("累积") || label.includes("累積")) {
                                            cleanLabel = t("cumulative_download");
                                        } else {
                                            cleanLabel = t("download");
                                        }
                                    } else if (label.includes("total") || label.includes("总") || label.includes("總")) {
                                        if (label.includes("cumulative") || label.includes("累积") || label.includes("累積")) {
                                            cleanLabel = t("total_cumulative_traffic");
                                        } else {
                                            cleanLabel = t("total_traffic");
                                        }
                                    } else {
                                        // Fallback: remove unit parentheses as before
                                        cleanLabel = label.replace(/\\s*\\([^)]*\\)/, "");
                                    }
                                    
                                    return cleanLabel + "：" + formattedValue + " " + unitLabel;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: t("traffic_unit", {unit: unitLabel})
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(2) + " " + unitLabel;
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: t("time_axis")
                            }
                        }
                    },
                    interaction: {
                        mode: "nearest",
                        axis: "x",
                        intersect: true
                    }
                }
            });
        }
        
        function getUnitMultiplier(unit) {
            switch (unit) {
                case "auto":
                case "MB": return 1000000;
                case "GB": return 1000000000;
                case "TB": return 1000000000000;
                default: return 1000000000; // Default to GB
            }
        }
        
        function exportNodeUsageRecords() {
            if (!currentNodeId) {
                alert(t("no_node_selected"));
                return;
            }
            
            // Update export modal with current search conditions
            document.getElementById("export-node-name").textContent = currentNodeName || (t("node_prefix") + " " + currentNodeId);
            
            const searchType = document.getElementById("node-search-type").value;
            const searchValue = document.getElementById("node-search-value").value.trim();
            
            if (searchValue) {
                if (searchType === "uuid") {
                    document.getElementById("export-uuid").textContent = searchValue;
                    document.getElementById("export-user-id").textContent = "-";
                } else if (searchType === "user_id") {
                    document.getElementById("export-uuid").textContent = "-";
                    document.getElementById("export-user-id").textContent = searchValue;
                }
            } else {
                document.getElementById("export-uuid").textContent = "-";
                document.getElementById("export-user-id").textContent = "-";
            }
            
            // Calculate and display specific time range from main page
            const timeFilter = document.getElementById("node-rankings-time-range").value;
            const startTime = document.getElementById("node-rankings-start-time").value;
            const endTime = document.getElementById("node-rankings-end-time").value;
            
            let timeRangeText = "-";
            const now = new Date();
            
            if (timeFilter === "custom_range" && startTime && endTime) {
                // For custom range, show only the time range without date
                timeRangeText = startTime + " - " + endTime;
            } else {
                // Calculate actual time ranges for predefined periods
                let startDate, endDate;
                
                switch (timeFilter) {
                    case "today":
                        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                        endDate = new Date(now);
                        break;
                    case "last_1_hour":
                        startDate = new Date(now.getTime() - (1 * 60 * 60 * 1000));
                        endDate = new Date(now);
                        break;
                    case "last_3_hours":
                        startDate = new Date(now.getTime() - (3 * 60 * 60 * 1000));
                        endDate = new Date(now);
                        break;
                    case "last_6_hours":
                        startDate = new Date(now.getTime() - (6 * 60 * 60 * 1000));
                        endDate = new Date(now);
                        break;
                    case "last_12_hours":
                        startDate = new Date(now.getTime() - (12 * 60 * 60 * 1000));
                        endDate = new Date(now);
                        break;
                    default:
                        startDate = endDate = null;
                }
                
                if (startDate && endDate) {
                    const formatDateTime = (date) => {
                        const year = date.getFullYear();
                        const month = (date.getMonth() + 1).toString().padStart(2, \'0\');
                        const day = date.getDate().toString().padStart(2, \'0\');
                        const hours = date.getHours().toString().padStart(2, \'0\');
                        const minutes = date.getMinutes().toString().padStart(2, \'0\');
                        const seconds = date.getSeconds().toString().padStart(2, \'0\');
                        return year + "-" + month + "-" + day + " " + hours + ":" + minutes + ":" + seconds;
                    };
                    
                    timeRangeText = formatDateTime(startDate) + " - " + formatDateTime(endDate);
                }
            }
            
            document.getElementById("export-time-range").textContent = timeRangeText;
            
            // Show export confirmation dialog
            document.getElementById("node-export-usage-modal").style.display = "block";
        }
        
        function closeNodeModal() {
            document.getElementById("node-modal").style.display = "none";
            if (currentNodeChart) {
                currentNodeChart.destroy();
                currentNodeChart = null;
            }
            currentNodeId = null;
            currentNodeName = null;
            allNodeUsageRecords = [];
        }
        
        function exportNodeRankings() {
            // Show export confirmation dialog instead of direct export
            document.getElementById("node-export-modal").style.display = "block";
        }
        
        // Utility functions
        function formatBytes(bytes) {
            if (bytes === 0) return "0&nbsp;B";
            const k = 1000;
            const sizes = ["B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB"];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            const value = bytes / Math.pow(k, i);
            // Use Number.prototype.toFixed() to match PHP number_format() behavior
            return value.toFixed(2) + "&nbsp;" + sizes[i];
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById("node-modal");
            if (event.target === modal) {
                closeNodeModal();
            }
        }
        
        // Export modal functions
        function closeNodeExportModal() {
            document.getElementById("node-export-modal").style.display = "none";
        }
        
        function closeNodeExportUsageModal() {
            document.getElementById("node-export-usage-modal").style.display = "none";
        }
        
        // Export type change handlers for node modal
        $(document).ready(function() {
            $("input[name=\'node_export_type\']").on("change", function() {
                const type = $(this).val();
                $("#node-limit-options").toggle(type === "limited");
                $("#node-date-range-options").toggle(type === "date_range");
            });
            
            $("input[name=\'node_usage_export_type\']").on("change", function() {
                const type = $(this).val();
                $("#node-usage-limit-options").toggle(type === "limited");
                $("#node-usage-time-range-options").toggle(type === "time_range");
            });

            
            // Search handlers for new structure
            $("#search-node-records").on("click", function() {
                currentNodeUsagePage = 1;
                loadNodeUsageRecords();
            });
            
            // Pagination event listeners for node usage records
            $("#node-records-per-page").on("change", function() {
                currentNodeUsagePage = 1;
                updateNodeUsagePagination();
            });
            
            $("#node-first-page").on("click", function() {
                currentNodeUsagePage = 1;
                updateNodeUsagePagination();
            });
            
            $("#node-prev-page").on("click", function() {
                if (currentNodeUsagePage > 1) {
                    currentNodeUsagePage--;
                    updateNodeUsagePagination();
                }
            });
            
            $("#node-next-page").on("click", function() {
                if (currentNodeUsagePage < totalNodeUsagePages) {
                    currentNodeUsagePage++;
                    updateNodeUsagePagination();
                }
            });
            
            $("#node-last-page").on("click", function() {
                currentNodeUsagePage = totalNodeUsagePages;
                updateNodeUsagePagination();
            });
            
            // Export form submission for nodes
            $("#node-export-form").on("submit", function(e) {
                e.preventDefault();
                
                const showOffline = document.getElementById("show-offline").value;
                const exportType = $("input[name=\'node_export_type\']:checked").val();
                const format = $("#node_export_format").val();
                
                // Use current sorting state instead of just dropdown
                const currentSortParam = `${currentSort.field}_${currentSort.direction}`;
                const timeRange = document.getElementById("node-rankings-time-range").value;
                
                let exportParams = `export_type=node_rankings&sort_by=${currentSortParam}&show_offline=${showOffline}&format=${format}`;
                
                // Add time range parameters
                if (timeRange === "custom_range") {
                    const startTime = document.getElementById("node-rankings-start-time").value;
                    const endTime = document.getElementById("node-rankings-end-time").value;
                    
                    if (startTime && endTime) {
                        // Convert time to today\'s date + time for timestamp calculation
                        const today = new Date();
                        const todayStr = today.getFullYear() + "-" + 
                                        (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                        today.getDate().toString().padStart(2, "0");
                        const startDateTime = todayStr + " " + startTime;
                        const endDateTime = todayStr + " " + endTime;
                        const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                        const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                        
                        exportParams += `&time_range=custom&start_timestamp=${startTimestamp}&end_timestamp=${endTimestamp}`;
                    } else {
                        exportParams += "&time_range=today";
                    }
                } else {
                    exportParams += `&time_range=${timeRange}`;
                }
                
                // Add specific export options
                if (exportType === "limited") {
                    const limitCount = $("#node_limit_count").val();
                    exportParams += "&limit_count=" + limitCount;
                } else if (exportType === "date_range") {
                    const startDate = $("#node_export_start_date").val();
                    const endDate = $("#node_export_end_date").val();
                    
                    if (startDate) {
                        exportParams += "&export_start_date=" + startDate;
                    }
                    if (endDate) {
                        exportParams += "&export_end_date=" + endDate;
                    }
                }
                
                // Trigger download
                window.open("addonmodules.php?module=v2raysocks_traffic&action=export_data&" + exportParams);
                
                // Hide modal
                closeNodeExportModal();
            });
            
            // Export form submission for usage records
            $("#node-usage-export-form").on("submit", function(e) {
                e.preventDefault();
                
                if (!currentNodeId) {
                    alert(t("no_node_selected"));
                    return;
                }
                
                const exportType = $("input[name=\'node_usage_export_type\']:checked").val();
                const format = $("#node_usage_export_format").val();
                
                // Get current search parameters from search controls and main page time range
                const searchType = document.getElementById("node-search-type").value;
                const searchValue = document.getElementById("node-search-value").value.trim();
                const timeFilter = document.getElementById("node-rankings-time-range").value;
                const startTime = document.getElementById("node-rankings-start-time").value;
                const endTime = document.getElementById("node-rankings-end-time").value;
                
                let exportParams = `export_type=usage_records&node_id=${currentNodeId}&format=${format}`;
                
                // Add search filters based on selected type
                if (searchValue) {
                    if (searchType === "uuid") {
                        exportParams += "&uuid=" + encodeURIComponent(searchValue);
                    } else if (searchType === "user_id") {
                        exportParams += "&user_id=" + encodeURIComponent(searchValue);
                    }
                }
                
                // Handle time filtering
                if (timeFilter === "custom_range" && startTime && endTime) {
                    // Convert time values to current date + time for timestamp calculation
                    const today = new Date();
                    const todayStr = today.getFullYear() + "-" + 
                                    (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                    today.getDate().toString().padStart(2, "0");
                    const startDateTime = todayStr + " " + startTime;
                    const endDateTime = todayStr + " " + endTime;
                    const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                    const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                    exportParams += `&export_start_timestamp=${startTimestamp}&export_end_timestamp=${endTimestamp}`;
                } else if (timeFilter !== "today") {
                    exportParams += `&time_range=${timeFilter}`;
                } else {
                    exportParams += `&time_range=today`;
                }
                
                // Add specific export options
                if (exportType === "limited") {
                    const limitCount = $("#node_usage_limit_count").val();
                    exportParams += "&limit_count=" + limitCount;
                } else if (exportType === "time_range") {
                    const startTime = $("#node_usage_export_start_time").val();
                    const endTime = $("#node_usage_export_end_time").val();
                    
                    if (startTime && endTime) {
                        // Export modal time range overrides search filter time range
                        // Remove any existing time parameters first
                        exportParams = exportParams.replace(/&time_range=[^&]*/g, "");
                        exportParams = exportParams.replace(/&export_start_timestamp=[^&]*/g, "");
                        exportParams = exportParams.replace(/&export_end_timestamp=[^&]*/g, "");
                        
                        // Convert time to todays date + time for timestamp calculation
                        const today = new Date();
                        const todayStr = today.getFullYear() + "-" + 
                                        (today.getMonth() + 1).toString().padStart(2, "0") + "-" + 
                                        today.getDate().toString().padStart(2, "0");
                        const startDateTime = todayStr + " " + startTime;
                        const endDateTime = todayStr + " " + endTime;
                        const startTimestamp = Math.floor(new Date(startDateTime).getTime() / 1000);
                        const endTimestamp = Math.floor(new Date(endDateTime).getTime() / 1000);
                        exportParams += "&export_start_timestamp=" + startTimestamp + "&export_end_timestamp=" + endTimestamp;
                    } else {
                        alert(t("select_start_end_times"));
                        return;
                    }
                }
                
                // Trigger download
                window.open("addonmodules.php?module=v2raysocks_traffic&action=export_data&" + exportParams);
                
                // Hide modal
                closeNodeExportUsageModal();
            });
        });
    </script>

    <!-- Node Export Confirmation Modal -->
    <div id="node-export-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 400px;">
            <h4>' . v2raysocks_traffic_lang('export_data') . '</h4>
                <form id="node-export-form">
                    <div style="margin-bottom: 15px;">
                        <label>' . v2raysocks_traffic_lang('export_type_label') . ':</label><br>
                        <label><input type="radio" name="node_export_type" value="all" checked> ' . v2raysocks_traffic_lang('all_data_option') . '</label><br>
                        <label><input type="radio" name="node_export_type" value="limited"> ' . v2raysocks_traffic_lang('custom_quantity_option') . '</label><br>
                        <label><input type="radio" name="node_export_type" value="date_range"> ' . v2raysocks_traffic_lang('custom_date_range') . '</label>
                    </div>
                    
                    <div id="node-limit-options" style="margin-bottom: 15px; display: none;">
                        <label for="node_limit_count">' . v2raysocks_traffic_lang('number_of_records_label') . ':</label>
                        <input type="number" id="node_limit_count" name="limit_count" value="1000" min="1" max="10000">
                    </div>
                    
                    <div id="node-date-range-options" style="margin-bottom: 15px; display: none;">
                        <label for="node_export_start_date">' . v2raysocks_traffic_lang('start_date_label') . ':</label>
                        <input type="date" id="node_export_start_date" name="export_start_date"><br><br>
                        <label for="node_export_end_date">' . v2raysocks_traffic_lang('end_date_label') . ':</label>
                        <input type="date" id="node_export_end_date" name="export_end_date">
                        <br><small style="color: #6c757d; margin-top: 5px; display: block;">选择自定义日期范围进行导出</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="node_export_format">' . v2raysocks_traffic_lang('format_label') . ':</label>
                        <select id="node_export_format" name="format">
                            <option value="excel" selected>' . v2raysocks_traffic_lang('excel') . '</option>
                            <option value="csv">' . v2raysocks_traffic_lang('csv') . '</option>
                            <option value="json">' . v2raysocks_traffic_lang('json') . '</option>
                        </select>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" onclick="closeNodeExportModal()" class="btn" style="margin-right: 10px;">' . v2raysocks_traffic_lang('cancel_button') . '</button>
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('confirm_export') . '</button>
                    </div>
                </form>
            </div>
        </div>
    
    <!-- Node Usage Records Export Modal -->
    <div id="node-export-usage-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 400px;">
            <h4>' . v2raysocks_traffic_lang('export_usage_records') . '</h4>
                <form id="node-usage-export-form">
                    <div style="margin-bottom: 15px;">
                        <label>' . v2raysocks_traffic_lang('export_type_label') . ':</label><br>
                        <label><input type="radio" name="node_usage_export_type" value="all" checked> ' . v2raysocks_traffic_lang('all_data_option') . '</label><br>
                        <label><input type="radio" name="node_usage_export_type" value="limited"> ' . v2raysocks_traffic_lang('custom_quantity_option') . '</label><br>
                        <label><input type="radio" name="node_usage_export_type" value="time_range"> ' . v2raysocks_traffic_lang('custom_time_range') . '</label>
                    </div>
                    
                    <div id="node-usage-limit-options" style="margin-bottom: 15px; display: none;">
                        <label for="node_usage_limit_count">' . v2raysocks_traffic_lang('number_of_records_label') . ':</label>
                        <input type="number" id="node_usage_limit_count" name="limit_count" value="1000" min="1" max="10000">
                    </div>
                    
                    <div id="node-usage-time-range-options" style="margin-bottom: 15px; display: none;">
                        <label for="node_usage_export_start_time">' . v2raysocks_traffic_lang('start_time_label') . ':</label>
                        <input type="time" id="node_usage_export_start_time" name="export_start_time" step="1"><br>
                        <label for="node_usage_export_end_time">' . v2raysocks_traffic_lang('end_time_label') . ':</label>
                        <input type="time" id="node_usage_export_end_time" name="export_end_time" step="1">
                        <br><small style="color: #6c757d; margin-top: 5px; display: block;">' . v2raysocks_traffic_lang('time_range_today_only') . '</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="node_usage_export_format">' . v2raysocks_traffic_lang('format_label') . ':</label>
                        <select id="node_usage_export_format" name="format">
                            <option value="excel" selected>' . v2raysocks_traffic_lang('excel') . '</option>
                            <option value="csv">' . v2raysocks_traffic_lang('csv') . '</option>
                            <option value="json">' . v2raysocks_traffic_lang('json') . '</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 4px;">
                        <small style="color: #6c757d;">
                            <strong>' . v2raysocks_traffic_lang('current_search_conditions') . '</strong><br>
                            • ' . v2raysocks_traffic_lang('node_name_label') . ' <span id="export-node-name">-</span><br>
                            • ' . v2raysocks_traffic_lang('uuid_search_label') . ' <span id="export-uuid">-</span><br>
                            • ' . v2raysocks_traffic_lang('user_id') . ': <span id="export-user-id">-</span><br>
                            • ' . v2raysocks_traffic_lang('time_range_label') . ': <span id="export-time-range">-</span>
                        </small>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" onclick="closeNodeExportUsageModal()" class="btn" style="margin-right: 10px;">' . v2raysocks_traffic_lang('cancel_button') . '</button>
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('confirm_export') . '</button>
                    </div>
                </form>
            </div>
        </div>
</body>
</html>';

return $nodeStatsHtml;
