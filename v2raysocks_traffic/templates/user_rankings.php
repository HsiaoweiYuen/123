<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include unified navigation component
require_once(__DIR__ . '/navigation_component.php');

$userRankingsHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('user_rankings_title') . '</title>
    <style>
        ' . v2raysocks_traffic_getNavigationCSS() . '
        ' . v2raysocks_traffic_getUnifiedStyles() . '
        
        /* Search form styles from service_search.php */
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
            gap: 10px;
            align-items: end;
            margin-bottom: 15px;
        }
        .form-group { 
            flex: 1;
            min-width: 120px;
        }
        /* Compact layout for time inputs and search button */
        .form-group#custom-dates,
        .form-group#custom-dates-end {
            flex: 1 1 auto;
            min-width: auto;
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
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
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
        
        /* Modal styles for user details */
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
        .user-info {
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
                flex-direction: row;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-start;
            }
            .form-group {
                min-width: auto;
                width: auto;
                flex: 0 0 auto;
            }
            /* Optimize form layout for mobile - make inputs more compact */
            .form-group:not(#custom-dates):not(#custom-dates-end) {
                flex: 1 1 calc(50% - 4px);
                min-width: 140px;
            }
            .form-group#custom-dates,
            .form-group#custom-dates-end {
                flex: 1 1 calc(50% - 4px);
                min-width: 140px;
            }
            /* Search button should be full width on mobile */
            .form-group:last-child {
                flex: 1 1 100%;
                margin-top: 5px;
            }
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
            
            /* Maintain numeric alignment on mobile */
            .numeric-cell {
                padding-left: 8px !important;
                padding-right: 4px !important;
            }
            
            /* Adjust UUID column for mobile */
            .uuid-column {
                max-width: 240px; /* 缩小手机版UUID列宽度但保持可读性 */
            }
            
            /* Custom date range styling for mobile */
            .form-group#custom-dates,
            .form-group#custom-dates-end {
                flex-direction: row !important;
                align-items: center !important;
                flex-wrap: wrap !important;
            }
            .form-group#custom-dates label,
            .form-group#custom-dates-end label {
                margin-bottom: 0;
                margin-right: 8px;
                white-space: nowrap;
            }
            .form-group#custom-dates input,
            .form-group#custom-dates-end input {
                width: auto;
                margin-bottom: 0;
                margin-right: 10px !important;
                min-width: 120px;
            }
            
            /* Mobile responsive search controls for user records */
            .usage-records-section div[style*="background: #f8f9fa"] div[style*="display: flex"] {
                flex-direction: column !important;
                gap: 10px !important;
            }
            .usage-records-section div[style*="flex: 0 0"] {
                flex: 1 1 100% !important;
                min-width: 100% !important;
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
            /* Stack form elements vertically on very small screens */
            .form-row {
                flex-direction: column;
                gap: 8px;
            }
            .form-group {
                width: 100%;
                flex: 1 1 auto;
            }
            .form-group#custom-dates,
            .form-group#custom-dates-end {
                flex: 1 1 auto;
                min-width: auto;
            }
            .form-group#custom-dates input,
            .form-group#custom-dates-end input {
                width: 100%;
            }
            .btn {
                padding: 6px 12px;
                font-size: 0.9em;
            }
            /* Further adjust UUID column for very small devices */
            .uuid-column {
                max-width: 200px; /* 超小屏幕进一步缩小UUID列宽度 */
            }
            .table th, .table td {
                padding: 6px 2px;
                font-size: 0.8em;
            }
        }
        
        /* Ensure charts stay within bounds */
        .chart-container canvas {
            max-width: 100% !important;
            height: auto !important;
        }
        
        /* Numeric column alignment for better readability */
        .numeric-cell {
            text-align: right;
            padding-left: 15px !important;
            padding-right: 8px !important;
        }
        
        /* UUID column styling for 40-character limit */
        .uuid-column {
            max-width: 320px; /* 40字符 × 8px平均字符宽度 */
            white-space: nowrap; /* 禁止换行 */
            overflow: hidden; /* 隐藏超出内容 */
            text-overflow: ellipsis; /* 显示省略号 */
            font-family: monospace; /* 等宽字体便于查看 */
        }
        
        /* Standard styles for export modal inputs */
        #user-export-modal input[type="date"], 
        #user-export-modal input[type="time"], 
        #user-export-modal input[type="number"] {
            width: 200px;
            padding: 5px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        #user-export-modal label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        #user-export-modal .form-group {
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
            "select_start_end_dates": "' . v2raysocks_traffic_lang('select_start_end_dates') . '",
            "date_format_incorrect": "' . v2raysocks_traffic_lang('date_format_incorrect') . '", 
            "date_invalid": "' . v2raysocks_traffic_lang('date_invalid') . '",
            "start_date_after_end_date": "' . v2raysocks_traffic_lang('start_date_after_end_date') . '",
            "loading_user_rankings": "' . v2raysocks_traffic_lang('loading_user_rankings') . '",
            "no_data": "' . v2raysocks_traffic_lang('no_data') . '",
            "loading_failed": "' . v2raysocks_traffic_lang('loading_failed') . '",
            "unknown_error": "' . v2raysocks_traffic_lang('unknown_error') . '",
            "network_error_retry": "' . v2raysocks_traffic_lang('network_error_retry') . '",
            "loading": "' . v2raysocks_traffic_lang('loading') . '",
            "user_id_label": "' . v2raysocks_traffic_lang('user_id_label') . '",
            "time_range_label": "' . v2raysocks_traffic_lang('time_range_label') . '",
            "upload_traffic": "' . v2raysocks_traffic_lang('upload_traffic') . '",
            "download_traffic": "' . v2raysocks_traffic_lang('download_traffic') . '",
            "total_traffic_label": "' . v2raysocks_traffic_lang('total_traffic_label') . '",
            "recent_5min_traffic_label": "' . v2raysocks_traffic_lang('recent_5min_traffic_label') . '",
            "recent_1hour_traffic_label": "' . v2raysocks_traffic_lang('recent_1hour_traffic_label') . '",
            "recent_4hour_traffic_label": "' . v2raysocks_traffic_lang('recent_4hour_traffic_label') . '",
            "peak_time": "' . v2raysocks_traffic_lang('peak_time') . '",
            "idle_time": "' . v2raysocks_traffic_lang('idle_time') . '",
            "peak_traffic": "' . v2raysocks_traffic_lang('peak_traffic') . '",
            "idle_traffic": "' . v2raysocks_traffic_lang('idle_traffic') . '",
            "today_range": "' . v2raysocks_traffic_lang('today_range') . '",
            "last_7_days": "' . v2raysocks_traffic_lang('last_7_days') . '",
            "last_15_days": "' . v2raysocks_traffic_lang('last_15_days') . '",
            "last_30_days": "' . v2raysocks_traffic_lang('last_30_days') . '",
            "custom_date_range": "' . v2raysocks_traffic_lang('custom_date_range') . '",
            "upload_traffic_unit": "' . v2raysocks_traffic_lang('upload_traffic_unit') . '",
            "download_traffic_unit": "' . v2raysocks_traffic_lang('download_traffic_unit') . '",
            "total_traffic_unit": "' . v2raysocks_traffic_lang('total_traffic_unit') . '",
            "cumulative_upload_unit": "' . v2raysocks_traffic_lang('cumulative_upload_unit') . '",
            "cumulative_download_unit": "' . v2raysocks_traffic_lang('cumulative_download_unit') . '",
            "total_cumulative_traffic_unit": "' . v2raysocks_traffic_lang('total_cumulative_traffic_unit') . '",
            "traffic_unit": "' . v2raysocks_traffic_lang('traffic_unit') . '",
            "time_axis": "' . v2raysocks_traffic_lang('time_axis') . '",
            "user_traffic_usage_trends": "' . v2raysocks_traffic_lang('user_traffic_usage_trends') . '",
            "no_user_selected": "' . v2raysocks_traffic_lang('no_user_selected') . '",
            "no_usage_records": "' . v2raysocks_traffic_lang('no_usage_records') . '",
            "failed_load_usage_records": "' . v2raysocks_traffic_lang('failed_load_usage_records') . '",
            "no_traffic_data": "' . v2raysocks_traffic_lang('no_traffic_data') . '",
            "no_traffic_records_period": "' . v2raysocks_traffic_lang('no_traffic_records_period') . '",
            "network_connection_error": "' . v2raysocks_traffic_lang('network_connection_error') . '",
            "enabled": "' . v2raysocks_traffic_lang('active') . '",
            "disabled": "' . v2raysocks_traffic_lang('inactive') . '",
            "seconds_ago": "' . v2raysocks_traffic_lang('per_second') . '",
            "minutes_ago": "' . v2raysocks_traffic_lang('minutes_ago') . '",
            "hours_ago": "' . v2raysocks_traffic_lang('hours_ago') . '",
            "days_ago": "' . v2raysocks_traffic_lang('days_ago') . '",
            "showing_records": "' . v2raysocks_traffic_lang('showing_records') . '",
            "page_info": "' . v2raysocks_traffic_lang('page_info') . '",
            "to": "' . v2raysocks_traffic_lang('to') . '",
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
        ' . v2raysocks_traffic_getNavigationHTML('user_rankings') . '

        <h1>' . v2raysocks_traffic_lang('user_rankings_title') . '</h1>
        
        <!-- Time Search Form -->
        <div class="search-form">
            <form id="user-rankings-filter">
                <div class="form-row">
                    <div class="form-group">
                        <label for="time-range">' . v2raysocks_traffic_lang('time_range') . ':</label>
                        <select id="time-range" name="time_range">
                            <option value="today" selected>' . v2raysocks_traffic_lang('today') . '</option>
                            <option value="week">' . v2raysocks_traffic_lang('last_7_days') . '</option>
                            <option value="15days">' . v2raysocks_traffic_lang('last_15_days') . '</option>
                            <option value="month">' . v2raysocks_traffic_lang('last_30_days') . '</option>
                            <option value="custom">' . v2raysocks_traffic_lang('custom_date_range') . '</option>
                        </select>
                    </div>
                    <div class="form-group" id="custom-dates" style="display: none;">
                        <label for="start-date">' . v2raysocks_traffic_lang('start_date') . ':</label>
                        <input type="date" id="start-date" name="start_date">
                    </div>
                    <div class="form-group" id="custom-dates-end" style="display: none;">
                        <label for="end-date">' . v2raysocks_traffic_lang('end_date') . ':</label>
                        <input type="date" id="end-date" name="end_date">
                    </div>
                    <div class="form-group">
                        <label for="service-id-search">' . v2raysocks_traffic_lang('service_id') . ':</label>
                        <input type="text" id="service-id-search" name="service_id_search" placeholder="' . v2raysocks_traffic_lang('enter_service_id') . '">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('refresh_rankings') . '</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Rankings Container -->
        <div class="rankings-container">
            <div class="rankings-header">
                <h3 class="rankings-title">' . v2raysocks_traffic_lang('user_rankings_table_title') . '</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th style="min-width: 60px;" class="sortable-header" data-sort="rank">
                                ' . v2raysocks_traffic_lang('ranking') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px;" class="sortable-header" data-sort="service_id">
                                ' . v2raysocks_traffic_lang('service_id') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px;" class="sortable-header" data-sort="user_id">
                                ' . v2raysocks_traffic_lang('user_id') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 160px;" class="sortable-header uuid-column" data-sort="uuid">
                                ' . v2raysocks_traffic_lang('uuid') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="total_traffic">
                                ' . v2raysocks_traffic_lang('total_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="used_traffic">
                                ' . v2raysocks_traffic_lang('used_traffic_statistics') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="remaining_traffic">
                                ' . v2raysocks_traffic_lang('remaining_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="period_traffic">
                                ' . v2raysocks_traffic_lang('used_traffic') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 120px;" class="sortable-header" data-sort="usage_rate">
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
                            <th style="min-width: 80px; white-space: nowrap;" class="sortable-header" data-sort="used_nodes">
                                ' . v2raysocks_traffic_lang('used_nodes') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="ss_speed_limit">
                                ' . v2raysocks_traffic_lang('ss_speed_limit') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 100px;" class="sortable-header" data-sort="other_speed_limit">
                                ' . v2raysocks_traffic_lang('other_speed_limit') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px;" class="sortable-header" data-sort="record_count">
                                ' . v2raysocks_traffic_lang('record_count') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 80px;" class="sortable-header" data-sort="status">
                                ' . v2raysocks_traffic_lang('status') . '
                                <span class="sort-indicator"></span>
                            </th>
                            <th style="min-width: 120px;" class="sortable-header" data-sort="last_active">
                                ' . v2raysocks_traffic_lang('last_active') . '
                                <span class="sort-indicator"></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="rankings-tbody">
                        <tr>
                            <td colspan="18" class="loading">' . v2raysocks_traffic_lang('user_rankings_loading') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div id="rankings-pagination-controls" style="margin-top: 15px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span id="rankings-pagination-info">' . v2raysocks_traffic_lang('showing_records') . '</span>
                    </div>
                    <div>
                        <label for="rankings-records-per-page" style="margin-right: 10px;">' . v2raysocks_traffic_lang('records_per_page_label') . '</label>
                        <select id="rankings-records-per-page" style="margin-right: 15px; padding: 5px;">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        
                        <button id="rankings-first-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('first_page') . '</button>
                        <button id="rankings-prev-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('previous_page') . '</button>
                        <span id="rankings-page-info" style="margin: 0 10px;">' . v2raysocks_traffic_lang('page_info') . '</span>
                        <button id="rankings-next-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('next_page') . '</button>
                        <button id="rankings-last-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('last_page') . '</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">' . v2raysocks_traffic_lang('user_details') . '</h3>
                <span class="close" onclick="closeUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="user-info" class="user-info">
                    <div class="loading">' . v2raysocks_traffic_lang('loading') . '</div>
                </div>
                
                <!-- Chart Controls Panel -->
                <div class="chart-controls-panel" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                        <div class="control-group">
                            <label for="user-chart-unit" style="font-weight: bold; margin-right: 8px;">' . v2raysocks_traffic_lang('chart_unit') . ':</label>
                            <select id="user-chart-unit" style="padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="auto" selected>Auto</option>
                                <option value="MB">MB</option>
                                <option value="GB">GB</option>
                                <option value="TB">TB</option>
                            </select>
                        </div>
                        <div class="control-group">
                            <label for="user-chart-mode" style="font-weight: bold; margin-right: 8px;">' . v2raysocks_traffic_lang('display_mode') . ':</label>
                            <select id="user-chart-mode" style="padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="separate" selected>' . v2raysocks_traffic_lang('upload_download') . '</option>
                                <option value="total">' . v2raysocks_traffic_lang('total_traffic') . '</option>
                                <option value="cumulative">' . v2raysocks_traffic_lang('cumulative_traffic') . '</option>
                                <option value="total_cumulative">' . v2raysocks_traffic_lang('total_cumulative_traffic') . '</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="user-traffic-chart"></canvas>
                </div>
                
                <!-- Unified Usage Records Container -->
                <div class="usage-records-section" style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px;">
                    <!-- Node-based Search Area -->
                    <div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                            <div style="flex: 0 0 200px; min-width: 150px;">
                                <label for="user-node-search" style="display: block; margin-bottom: 5px; font-weight: 500;">' . v2raysocks_traffic_lang('node_search_label') . '</label>
                                <input type="text" id="user-node-search" placeholder="' . v2raysocks_traffic_lang('node_search_placeholder') . '" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button id="search-user-records" class="btn btn-primary" style="padding: 8px 16px;">' . v2raysocks_traffic_lang('search') . '</button>
                                <button id="clear-user-search" class="btn btn-secondary" style="padding: 8px 16px;">' . v2raysocks_traffic_lang('clear') . '</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="usage-records-header">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0;">' . v2raysocks_traffic_lang('traffic_history') . '</h4>
                            <button class="btn btn-success" onclick="exportUserUsageRecords()" style="padding: 6px 12px;">' . v2raysocks_traffic_lang('export_data') . '</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>' . v2raysocks_traffic_lang('time_column') . '</th>
                                    <th>' . v2raysocks_traffic_lang('node_column') . '</th>
                                    <th>' . v2raysocks_traffic_lang('upload') . '</th>
                                    <th>' . v2raysocks_traffic_lang('download') . '</th>
                                    <th>' . v2raysocks_traffic_lang('total') . '</th>
                                    <th>' . v2raysocks_traffic_lang('rate_column') . '</th>
                                </tr>
                            </thead>
                            <tbody id="user-records-tbody">
                                <tr>
                                    <td colspan="6" class="loading">' . v2raysocks_traffic_lang('loading_usage_records') . '</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Pagination for usage records -->
                        <div id="user-usage-pagination" style="margin-top: 15px; display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span id="user-pagination-info">' . v2raysocks_traffic_lang('showing_records') . '</span>
                                </div>
                                <div>
                                    <label for="user-records-per-page" style="margin-right: 10px;">' . v2raysocks_traffic_lang('records_per_page_label') . '</label>
                                    <select id="user-records-per-page" style="margin-right: 15px; padding: 5px;">
                                        <option value="25">25</option>
                                        <option value="50" selected>50</option>
                                        <option value="100">100</option>
                                        <option value="200">200</option>
                                    </select>
                                    
                                    <button id="user-first-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('first_page') . '</button>
                                    <button id="user-prev-page" class="btn btn-sm" style="margin-right: 5px;">' . v2raysocks_traffic_lang('previous_page') . '</button>
                                    <span id="user-page-info" style="margin: 0 10px;">' . v2raysocks_traffic_lang('page_info') . '</span>
                                    <button id="user-next-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('next_page') . '</button>
                                    <button id="user-last-page" class="btn btn-sm" style="margin-left: 5px;">' . v2raysocks_traffic_lang('last_page') . '</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentUserChart = null;
        let currentUserId = null;
        let allUserUsageRecords = [];
        let currentUserUsagePage = 1;
        let userUsageRecordsPerPage = 50;
        let totalUserUsagePages = 1;
        let currentSort = { field: "rank", direction: "asc" };
        let allUserRankings = [];
        
        // Main rankings pagination variables
        let currentRankingsPage = 1;
        let rankingsRecordsPerPage = 50;
        let totalRankingsPages = 1;
        let filteredRankings = [];
        
        // Load user rankings on page load
        $(document).ready(function() {
            loadUserRankings();
            
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
                if (allUserRankings.length > 0) {
                    sortAndDisplayUserRankings();
                } else {
                    // Load fresh data if no data is cached
                    loadUserRankings();
                }
            });
            
            // Add event listener for time range change
            document.getElementById("time-range").addEventListener("change", function() {
                const timeRange = this.value;
                const customDates = document.getElementById("custom-dates");
                const customDatesEnd = document.getElementById("custom-dates-end");
                
                if (timeRange === "custom") {
                    customDates.style.display = "block";
                    customDatesEnd.style.display = "block";
                } else {
                    customDates.style.display = "none";
                    customDatesEnd.style.display = "none";
                }
            });
            
            // Form submission handler (similar to traffic dashboard)
            $("#user-rankings-filter").on("submit", function(e) {
                e.preventDefault();
                loadUserRankings();
            });
            
            // Service ID search event handlers
            $("#service-id-search").on("input", function() {
                if (allUserRankings.length > 0) {
                    displayUserRankings(allUserRankings);
                }
            });
            
            // Enter key support for search
            $("#service-id-search").on("keypress", function(e) {
                if (e.which === 13) {
                    if (allUserRankings.length > 0) {
                        displayUserRankings(allUserRankings);
                    }
                }
            });
            
            // Rankings pagination event handlers
            $("#rankings-records-per-page").on("change", function() {
                currentRankingsPage = 1;
                updateRankingsPagination();
            });
            
            $("#rankings-first-page").on("click", function() {
                currentRankingsPage = 1;
                updateRankingsPagination();
            });
            
            $("#rankings-prev-page").on("click", function() {
                if (currentRankingsPage > 1) {
                    currentRankingsPage--;
                    updateRankingsPagination();
                }
            });
            
            $("#rankings-next-page").on("click", function() {
                if (currentRankingsPage < totalRankingsPages) {
                    currentRankingsPage++;
                    updateRankingsPagination();
                }
            });
            
            $("#rankings-last-page").on("click", function() {
                currentRankingsPage = totalRankingsPages;
                updateRankingsPagination();
            });
        });
        
        function loadUserRankings() {
            const timeRange = document.getElementById("time-range").value;
            
            // Validate custom date range if selected
            if (timeRange === "custom") {
                const startDate = document.getElementById("start-date").value;
                const endDate = document.getElementById("end-date").value;
                
                // Validate date format using regex (YYYY-MM-DD)
                const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                
                if (!startDate || !endDate) {
                    alert(t("select_start_end_dates"));
                    return;
                }
                
                if (!dateRegex.test(startDate) || !dateRegex.test(endDate)) {
                    alert(t("date_format_incorrect"));
                    return;
                }
                
                // Validate date range logic
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (isNaN(start.getTime()) || isNaN(end.getTime())) {
                    alert(t("date_invalid"));
                    return;
                }
                
                if (start > end) {
                    alert(t("start_date_after_end_date"));
                    return;
                }
            }
            
            // Use form serialization approach but remove sort_by parameter
            const formData = $("#user-rankings-filter").serialize();
            const url = "addonmodules.php?module=v2raysocks_traffic&action=get_user_traffic_rankings&" + formData + "&sort_by=traffic_desc";
            
            const tbody = document.getElementById("rankings-tbody");
            tbody.innerHTML = `<tr><td colspan="18" class="loading">${t("loading_user_rankings")}</td></tr>`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        allUserRankings = data.data || [];
                        sortAndDisplayUserRankings();
                    } else {
                        tbody.innerHTML = `<tr><td colspan="18" class="no-data">${t("loading_failed")} ${data.message || t("unknown_error")}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error("Error loading user rankings:", error);
                    tbody.innerHTML = `<tr><td colspan="18" class="no-data">${t("network_error_retry")}</td></tr>`;
                });
        }
        
        function updateSortIndicators() {
            // Remove all sort indicators
            $(".sortable-header").removeClass("sort-asc sort-desc");
            
            // Add indicator to current sort field
            $(`.sortable-header[data-sort="${currentSort.field}"]`).addClass(`sort-${currentSort.direction}`);
        }
        
        function sortAndDisplayUserRankings() {
            if (!allUserRankings || allUserRankings.length === 0) {
                displayUserRankings([]);
                return;
            }
            
            // Sort the data
            const sortedData = [...allUserRankings].sort((a, b) => {
                let aValue, bValue;
                
                switch (currentSort.field) {
                    case "rank":
                        // For rank, we use the original array index (0-based) + 1
                        aValue = allUserRankings.indexOf(a) + 1;
                        bValue = allUserRankings.indexOf(b) + 1;
                        break;
                    case "service_id":
                        aValue = parseInt(a.sid) || 0;
                        bValue = parseInt(b.sid) || 0;
                        break;
                    case "user_id":
                        aValue = parseInt(a[currentSort.field]) || 0;
                        bValue = parseInt(b[currentSort.field]) || 0;
                        break;
                    case "uuid":
                        aValue = (a.uuid || "").toLowerCase();
                        bValue = (b.uuid || "").toLowerCase();
                        break;
                    case "total_traffic":
                        aValue = a.transfer_enable || 0;
                        bValue = b.transfer_enable || 0;
                        break;
                    case "used_traffic":
                        aValue = a.used_traffic || 0;
                        bValue = b.used_traffic || 0;
                        break;
                    case "remaining_traffic":
                        aValue = a.remaining_quota || 0;
                        bValue = b.remaining_quota || 0;
                        break;
                    case "usage_rate":
                        aValue = a.quota_utilization || 0;
                        bValue = b.quota_utilization || 0;
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
                    case "used_nodes":
                        aValue = a.nodes_used || 0;
                        bValue = b.nodes_used || 0;
                        break;
                    case "record_count":
                        aValue = a.usage_records || 0;
                        bValue = b.usage_records || 0;
                        break;
                    case "status":
                        aValue = a.enable ? 1 : 0;
                        bValue = b.enable ? 1 : 0;
                        break;
                    case "last_active":
                        aValue = a.last_usage || 0;
                        bValue = b.last_usage || 0;
                        break;
                    case "ss_speed_limit":
                        aValue = (a.speedlimitss || "").toLowerCase();
                        bValue = (b.speedlimitss || "").toLowerCase();
                        break;
                    case "other_speed_limit":
                        aValue = (a.speedlimitother || "").toLowerCase();
                        bValue = (b.speedlimitother || "").toLowerCase();
                        break;
                    default:
                        aValue = a.period_traffic || 0;
                        bValue = b.period_traffic || 0;
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
            
            displayUserRankings(sortedData);
            updateSortIndicators();
        }
        
        function displayUserRankings(users) {
            // Store filtered data and apply pagination
            applyServiceIdFilter(users);
        }
        
        function applyServiceIdFilter(users) {
            if (!users || users.length === 0) {
                filteredRankings = [];
                updateRankingsPagination();
                return;
            }
            
            const serviceIdSearch = document.getElementById("service-id-search").value.trim();
            
            if (!serviceIdSearch) {
                // No search term, show all users
                filteredRankings = [...users];
            } else {
                // Filter and prioritize by service ID
                const exactMatches = [];
                const partialMatches = [];
                const otherUsers = [];
                
                users.forEach(user => {
                    const serviceId = (user.sid || "").toString();
                    if (serviceId === serviceIdSearch) {
                        exactMatches.push(user);
                    } else if (serviceId.includes(serviceIdSearch)) {
                        partialMatches.push(user);
                    } else {
                        otherUsers.push(user);
                    }
                });
                
                // Combine results: exact matches first, then partial matches, then others
                filteredRankings = [...exactMatches, ...partialMatches, ...otherUsers];
            }
            
            // Reset to first page when filter changes
            currentRankingsPage = 1;
            updateRankingsPagination();
        }
        
        function updateRankingsPagination() {
            const tbody = document.getElementById("rankings-tbody");
            const paginationDiv = document.getElementById("rankings-pagination-controls");
            
            if (filteredRankings.length === 0) {
                tbody.innerHTML = `<tr><td colspan="18" class="no-data">${t("no_data")}</td></tr>`;
                paginationDiv.style.display = "none";
                return;
            }
            
            // Calculate pagination
            rankingsRecordsPerPage = parseInt(document.getElementById("rankings-records-per-page").value);
            totalRankingsPages = Math.ceil(filteredRankings.length / rankingsRecordsPerPage);
            const startIndex = (currentRankingsPage - 1) * rankingsRecordsPerPage;
            const endIndex = Math.min(startIndex + rankingsRecordsPerPage, filteredRankings.length);
            const pageData = filteredRankings.slice(startIndex, endIndex);
            
            // Generate table rows for current page
            let html = "";
            pageData.forEach((user, index) => {
                const globalRank = startIndex + index + 1; // Global ranking position
                const rankClass = globalRank === 1 ? "rank-1" : globalRank === 2 ? "rank-2" : globalRank === 3 ? "rank-3" : "rank-other";
                
                const utilizationPercent = user.quota_utilization || 0;
                const progressWidth = Math.min(100, utilizationPercent); // Cap visual width at 100%
                
                // Determine color class based on utilization
                let colorClass = "normal";
                if (utilizationPercent >= 100) {
                    colorClass = "danger";
                } else if (utilizationPercent >= 80) {
                    colorClass = "warning";
                }
                
                const statusClass = user.enable ? "status-active" : "status-inactive";
                const statusText = user.enable ? t("enabled") : t("disabled");
                
                const lastUsageText = user.last_usage ? 
                    formatTimeAgo(user.last_usage) : t("no_data");
                
                html += `
                    <tr onclick="showUserDetails(${user.user_id})">
                        <td><span class="rank-badge ${rankClass}">${globalRank}</span></td>
                        <td class="numeric-cell">${user.sid || "N/A"}</td>
                        <td>${user.user_id}</td>
                        <td class="uuid-column" title="${user.uuid || "N/A"}">${user.uuid || "N/A"}</td>
                        <td>${formatBytes(user.transfer_enable)}</td>
                        <td>${formatBytes(user.used_traffic || 0)}</td>
                        <td>${formatBytes(user.remaining_quota)}</td>
                        <td>${formatBytes(user.period_traffic)}</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill ${colorClass}" style="width: ${progressWidth}%"></div>
                                <div class="progress-text">${utilizationPercent.toFixed(1)}%</div>
                            </div>
                        </td>
                        <td class="numeric-cell">${formatBytes(user.traffic_5min || 0)}</td>
                        <td class="numeric-cell">${formatBytes(user.traffic_1hour || 0)}</td>
                        <td class="numeric-cell">${formatBytes(user.traffic_4hour || 0)}</td>
                        <td class="numeric-cell">${user.nodes_used}</td>
                        <td>${user.speedlimitss || "-"}</td>
                        <td>${user.speedlimitother || "-"}</td>
                        <td>${user.usage_records}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${lastUsageText}</td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            // Update pagination info
            document.getElementById("rankings-pagination-info").textContent = t("showing_records", {
                start: startIndex + 1,
                end: endIndex,
                total: filteredRankings.length
            });
            document.getElementById("rankings-page-info").textContent = t("page_info", {
                current: currentRankingsPage,
                total: totalRankingsPages
            });
            
            // Enable/disable pagination buttons
            document.getElementById("rankings-first-page").disabled = currentRankingsPage === 1;
            document.getElementById("rankings-prev-page").disabled = currentRankingsPage === 1;
            document.getElementById("rankings-next-page").disabled = currentRankingsPage === totalRankingsPages;
            document.getElementById("rankings-last-page").disabled = currentRankingsPage === totalRankingsPages;
            
            paginationDiv.style.display = "block";
        }
        
        function showUserDetails(userId) {
            currentUserId = userId;
            const modal = document.getElementById("user-modal");
            const userInfo = document.getElementById("user-info");
            const recordsTbody = document.getElementById("user-records-tbody");
            
            modal.style.display = "block";
            userInfo.innerHTML = `<div class="loading">${t("loading")}</div>`;
            recordsTbody.innerHTML = `<tr><td colspan="6" class="loading">${t("loading_usage_records")}</td></tr>`;
            
            // Reset pagination
            currentUserUsagePage = 1;
            allUserUsageRecords = [];
            document.getElementById("user-usage-pagination").style.display = "none";
            
            // Add event listeners for chart controls
            document.getElementById("user-chart-unit").addEventListener("change", updateUserChart);
            document.getElementById("user-chart-mode").addEventListener("change", updateUserChart);
            
            // Load all modal data atomically to prevent race conditions
            loadUserModalData();
        }
        
        function loadUserModalData() {
            const timeRange = document.getElementById("time-range").value;
            let chartUrlParams = "addonmodules.php?module=v2raysocks_traffic&action=get_user_traffic_chart&user_id=" + currentUserId + "&time_range=" + timeRange;
            let usageUrlParams = `addonmodules.php?module=v2raysocks_traffic&action=get_usage_records&user_id=${currentUserId}&time_range=${timeRange}&limit=1000`;
            
            // Add custom date range parameters if applicable
            if (timeRange === "custom") {
                const startDate = document.getElementById("start-date").value;
                const endDate = document.getElementById("end-date").value;
                
                // Validate date format and values
                const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                
                if (startDate && endDate && dateRegex.test(startDate) && dateRegex.test(endDate)) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    
                    // Only add dates if they are valid and start <= end
                    if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && start <= end) {
                        const dateParams = "&start_date=" + startDate + "&end_date=" + endDate;
                        chartUrlParams += dateParams;
                        usageUrlParams += dateParams;
                    }
                }
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
                    displayUserChart(chartResponse.data);
                    updateUserInfoWithChartData(chartResponse.data);
                } else {
                    console.log("Chart API returned error:", chartResponse);
                    const userInfo = document.getElementById("user-info");
                    userInfo.innerHTML = `<div class="no-data">${t("no_traffic_data")} ${chartResponse.message || t("no_traffic_records_period")}</div>`;
                    
                    // Display empty chart
                    displayUserChart({
                        labels: [],
                        upload: [],
                        download: [],
                        total: [],
                        user_id: currentUserId
                    });
                }
                
                // Process usage records
                if (usageResponse.status === "success") {
                    allUserUsageRecords = usageResponse.data || [];
                    filterAndUpdateUserUsageRecords();
                } else {
                    const recordsTbody = document.getElementById("user-records-tbody");
                    recordsTbody.innerHTML = `<tr><td colspan="6" class="no-data">${t("failed_load_usage_records")} ${usageResponse.message}</td></tr>`;
                }
            })
            .catch(error => {
                console.error("Error loading user modal data:", error);
                const userInfo = document.getElementById("user-info");
                const recordsTbody = document.getElementById("user-records-tbody");
                
                userInfo.innerHTML = `<div class="no-data">${t("loading_failed")} ${error.message || t("network_connection_error")}</div>`;
                recordsTbody.innerHTML = `<tr><td colspan="6" class="no-data">${t("network_error_retry")}</td></tr>`;
                
                // Display empty chart on error
                displayUserChart({
                    labels: [],
                    upload: [],
                    download: [],
                    total: [],
                    user_id: currentUserId
                });
            });
        }
        
        function updateUserInfoWithChartData(chartData) {
            const userInfo = document.getElementById("user-info");
            const timeRange = document.getElementById("time-range").value;
            
            // Calculate totals from chart data (already in GB from API)
            const totalUpload = chartData.upload ? chartData.upload.reduce((sum, val) => sum + (val || 0), 0) : 0;
            const totalDownload = chartData.download ? chartData.download.reduce((sum, val) => sum + (val || 0), 0) : 0;
            const totalTraffic = totalUpload + totalDownload;
            
            // Convert GB to bytes for display
            const totalUploadBytes = totalUpload * 1000000000;
            const totalDownloadBytes = totalDownload * 1000000000;
            const totalTrafficBytes = totalTraffic * 1000000000;
            
            // Get the display text for time range
            const timeRangeDisplayText = getTimeRangeDisplayText(timeRange);
            
            // First, render the basic info
            userInfo.innerHTML = `
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">${t("user_id_label")}</div>
                        <div class="info-value">${currentUserId}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("time_range_label")}</div>
                        <div class="info-value">${timeRangeDisplayText}</div>
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
                        <div class="info-value text-warning" id="recent-5min-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("recent_1hour_traffic_label")}</div>
                        <div class="info-value text-warning" id="recent-1hour-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("recent_4hour_traffic_label")}</div>
                        <div class="info-value text-warning" id="recent-4hour-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("peak_time")}</div>
                        <div class="info-value text-info" id="user-peak-time">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("idle_time")}</div>
                        <div class="info-value text-info" id="user-idle-time">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("peak_traffic")}</div>
                        <div class="info-value text-warning" id="user-peak-traffic">-</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">${t("idle_traffic")}</div>
                        <div class="info-value text-warning" id="user-idle-traffic">-</div>
                    </div>
                </div>
            `;
            
            // Then fetch and update recent traffic data
            fetchUserRecentTrafficData();
            
            // Also fetch peak/idle statistics
            fetchUserPeakIdleStats();
        }
        
        function fetchUserRecentTrafficData() {
            // Fetch user ranking data to get recent traffic information
            const rankingsUrl = `addonmodules.php?module=v2raysocks_traffic&action=get_user_traffic_rankings&time_range=today&limit=10000`;
            
            fetch(rankingsUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Rankings API HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(rankingsResponse => {
                    if (rankingsResponse.status === "success" && rankingsResponse.data) {
                        // Find the current user in the rankings data
                        const userData = rankingsResponse.data.find(user => user.user_id == currentUserId);
                        if (userData) {
                            // Update recent traffic data with actual values
                            document.getElementById("recent-5min-traffic").innerHTML = formatBytes(userData.traffic_5min);
                            document.getElementById("recent-1hour-traffic").innerHTML = formatBytes(userData.traffic_1hour);
                            document.getElementById("recent-4hour-traffic").innerHTML = formatBytes(userData.traffic_4hour);
                        }
                    }
                })
                .catch(error => {
                    console.error("Error loading user recent traffic data:", error);
                    // Keep "-" values on error
                });
        }
        
        function fetchUserPeakIdleStats() {
            // Fetch detailed traffic data for peak/idle calculation
            const timeRange = document.getElementById("time-range").value;
            let apiUrl = `addonmodules.php?module=v2raysocks_traffic&action=get_traffic_data&user_id=${currentUserId}&time_range=${timeRange}&grouped=true&enhanced=true`;
            
            // Add custom date range parameters if applicable
            if (timeRange === "custom") {
                const startDate = document.getElementById("start-date").value;
                const endDate = document.getElementById("end-date").value;
                
                // Validate date format and values
                const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                
                if (startDate && endDate && dateRegex.test(startDate) && dateRegex.test(endDate)) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    
                    // Only add dates if they are valid and start <= end
                    if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && start <= end) {
                        apiUrl += "&start_date=" + startDate + "&end_date=" + endDate;
                    }
                }
            }
            
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Traffic data API HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(response => {
                    if (response.status === "success" && response.grouped_data) {
                        // Calculate peak time and idle time using grouped data (PR#37 pattern)
                        let peakTime = "";
                        let peakTraffic = 0;
                        let idleTime = "";
                        let idleTraffic = Number.MAX_VALUE;
                        
                        // Use grouped data for peak/idle calculation
                        Object.keys(response.grouped_data).forEach(function(timeKey) {
                            const groupData = response.grouped_data[timeKey];
                            const totalTraffic = groupData.total || 0;
                            
                            if (totalTraffic > peakTraffic) {
                                peakTraffic = totalTraffic;
                                peakTime = timeKey;
                            }
                            if (totalTraffic < idleTraffic && totalTraffic > 0) {
                                idleTraffic = totalTraffic;
                                idleTime = timeKey;
                            }
                        });
                        
                        // If no valid idle traffic found, set to 0
                        if (idleTraffic === Number.MAX_VALUE) {
                            idleTraffic = 0;
                        }
                        
                        // Update the display elements
                        document.getElementById("user-peak-time").textContent = peakTime || "-";
                        document.getElementById("user-idle-time").textContent = idleTime || "-";
                        document.getElementById("user-peak-traffic").innerHTML = formatBytes(peakTraffic);
                        document.getElementById("user-idle-traffic").innerHTML = formatBytes(idleTraffic);
                    } else {
                        // No data available, keep default "-" values
                        console.log("No grouped traffic data available for peak/idle calculation");
                    }
                })
                .catch(error => {
                    console.error("Error loading user peak/idle statistics:", error);
                    // Keep "-" values on error
                });
        }
        
        function loadUserUsageRecords() {
            const timeRange = document.getElementById("time-range").value;
            let urlParams = `addonmodules.php?module=v2raysocks_traffic&action=get_usage_records&user_id=${currentUserId}&time_range=${timeRange}&limit=1000`;
            
            // Add custom date range parameters if applicable
            if (timeRange === "custom") {
                const startDate = document.getElementById("start-date").value;
                const endDate = document.getElementById("end-date").value;
                
                // Validate date format and values
                const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                
                if (startDate && endDate && dateRegex.test(startDate) && dateRegex.test(endDate)) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    
                    // Only add dates if they are valid and start <= end
                    if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && start <= end) {
                        urlParams += "&start_date=" + startDate + "&end_date=" + endDate;
                    }
                }
            }
            
            fetch(urlParams)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        allUserUsageRecords = data.data || [];
                        filterAndUpdateUserUsageRecords();
                    } else {
                        const recordsTbody = document.getElementById("user-records-tbody");
                        recordsTbody.innerHTML = `<tr><td colspan="6" class="no-data">${t("failed_load_usage_records")} ${data.message}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error("Error loading usage records:", error);
                    const recordsTbody = document.getElementById("user-records-tbody");
                    recordsTbody.innerHTML = `<tr><td colspan="6" class="no-data">${t("network_error_retry")}</td></tr>`;
                });
        }
        
        function filterAndUpdateUserUsageRecords() {
            const nodeSearchTerm = document.getElementById("user-node-search").value.trim().toLowerCase();
            
            let filteredRecords = allUserUsageRecords;
            
            // Filter by node name if search term is provided
            if (nodeSearchTerm) {
                filteredRecords = allUserUsageRecords.filter(record => {
                    const nodeName = (record.node_name || `节点 ${record.node}`).toLowerCase();
                    return nodeName.includes(nodeSearchTerm);
                });
            }
            
            // Update pagination with filtered data
            updateUserUsagePaginationWithData(filteredRecords);
        }
        
        function updateUserChart() {
            // Reload all modal data to ensure consistency
            loadUserModalData();
        }
        
        function updateUserUsagePagination() {
            filterAndUpdateUserUsageRecords();
        }
        
        function updateUserUsagePaginationWithData(records) {
            const tbody = document.getElementById("user-records-tbody");
            const paginationDiv = document.getElementById("user-usage-pagination");
            
            if (!records || records.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="no-data">${t("no_usage_records")}</td></tr>`;
                paginationDiv.style.display = "none";
                return;
            }
            
            // Calculate pagination
            userUsageRecordsPerPage = parseInt(document.getElementById("user-records-per-page").value);
            totalUserUsagePages = Math.ceil(records.length / userUsageRecordsPerPage);
            const startIndex = (currentUserUsagePage - 1) * userUsageRecordsPerPage;
            const endIndex = Math.min(startIndex + userUsageRecordsPerPage, records.length);
            const pageData = records.slice(startIndex, endIndex);
            
            // Generate table rows
            let html = "";
            pageData.forEach(record => {
                html += `
                    <tr>
                        <td>${record.formatted_time}</td>
                        <td>${record.node_name || `节点 ${record.node}`}</td>
                        <td>${record.formatted_upload}</td>
                        <td>${record.formatted_download}</td>
                        <td>${record.formatted_total}</td>
                        <td>${record.count_rate}x</td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            // Update pagination info
            document.getElementById("user-pagination-info").textContent = t("showing_records", {
                start: startIndex + 1,
                end: endIndex,
                total: records.length
            });
            document.getElementById("user-page-info").textContent = t("page_info", {
                current: currentUserUsagePage,
                total: totalUserUsagePages
            });
            
            // Enable/disable pagination buttons
            document.getElementById("user-first-page").disabled = currentUserUsagePage === 1;
            document.getElementById("user-prev-page").disabled = currentUserUsagePage === 1;
            document.getElementById("user-next-page").disabled = currentUserUsagePage === totalUserUsagePages;
            document.getElementById("user-last-page").disabled = currentUserUsagePage === totalUserUsagePages;
            
            paginationDiv.style.display = "block";
        }
        
        // Generate default time labels for empty charts - using server local time (not UTC)
        function generateDefaultTimeLabels(timeRange = "today", points = 8) {
            // For empty charts, create minimal consistent placeholder labels
            const labels = [];
            
            switch (timeRange) {
                case "today":
                    // Generate hour labels for today - only up to current hour
                    const currentHour = new Date().getHours();
                    const maxHours = Math.min(currentHour + 1, 24);
                    for (let i = 0; i < Math.min(points, maxHours); i++) {
                        labels.push(String(i).padStart(2, "0") + ":00");
                    }
                    break;
                case "week":
                case "7days":
                case "15days":
                case "month":
                case "30days":
                    // Generate date labels for multi-day ranges
                    const today = new Date();
                    for (let i = points - 1; i >= 0; i--) {
                        const date = new Date(today.getTime() - i * 24 * 60 * 60 * 1000);
                        const timeKey = date.getFullYear() + "-" + 
                                       String(date.getMonth() + 1).padStart(2, "0") + "-" + 
                                       String(date.getDate()).padStart(2, "0"); // Standardized yyyy-mm-dd format
                        labels.push(timeKey);
                    }
                    break;
                default:
                    // Fallback: simple numeric labels
                    for (let i = 1; i <= points; i++) {
                        labels.push(String(i));
                    }
                    break;
            }
            
            return labels;
        }

        // Generate complete time series to prevent chart discontinuity
        function generateCompleteTimeSeriesForUserChart(timeRange) {
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
                    
                case "week":
                case "7days":
                    // Generate all 7 days for the past week
                    for (let i = 6; i >= 0; i--) {
                        const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                        const timeKey = date.getFullYear() + "-" + 
                                       String(date.getMonth() + 1).padStart(2, "0") + "-" + 
                                       String(date.getDate()).padStart(2, "0"); // Standardized yyyy-mm-dd format
                        labels.push(timeKey);
                    }
                    break;
                    
                case "15days":
                    // Generate all 15 days for the past 15 days
                    for (let i = 14; i >= 0; i--) {
                        const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                        const timeKey = date.getFullYear() + "-" + 
                                       String(date.getMonth() + 1).padStart(2, "0") + "-" + 
                                       String(date.getDate()).padStart(2, "0"); // Standardized yyyy-mm-dd format
                        labels.push(timeKey);
                    }
                    break;
                    
                case "month":
                case "30days":
                    // Generate all 30 days for the past month
                    for (let i = 29; i >= 0; i--) {
                        const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                        const timeKey = date.getFullYear() + "-" + 
                                       String(date.getMonth() + 1).padStart(2, "0") + "-" + 
                                       String(date.getDate()).padStart(2, "0"); // Standardized yyyy-mm-dd format
                        labels.push(timeKey);
                    }
                    break;
                    
                case "custom":
                    // For custom ranges, default to 30 days or use date picker values
                    const startDate = document.getElementById("start-date").value;
                    const endDate = document.getElementById("end-date").value;
                    
                    if (startDate && endDate) {
                        const start = new Date(startDate);
                        const end = new Date(endDate);
                        const current = new Date(start);
                        
                        while (current <= end) {
                            const timeKey = current.getFullYear() + "-" + 
                                           String(current.getMonth() + 1).padStart(2, "0") + "-" + 
                                           String(current.getDate()).padStart(2, "0"); // Standardized yyyy-mm-dd format
                            labels.push(timeKey);
                            current.setDate(current.getDate() + 1);
                        }
                    } else {
                        // Fallback to 30 days
                        for (let i = 29; i >= 0; i--) {
                            const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                            const timeKey = date.getFullYear() + "-" + 
                                           String(date.getMonth() + 1).padStart(2, "0") + "-" + 
                                           String(date.getDate()).padStart(2, "0"); // Standardized yyyy-mm-dd format
                            labels.push(timeKey);
                        }
                    }
                    break;
                    
                default:
                    // Default to 7 days
                    for (let i = 6; i >= 0; i--) {
                        const date = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
                        const timeKey = date.getFullYear() + "-" + 
                                       String(date.getMonth() + 1).padStart(2, "0") + "-" + 
                                       String(date.getDate()).padStart(2, "0"); // Standardized yyyy-mm-dd format
                        labels.push(timeKey);
                    }
                    break;
            }
            
            return labels;
        }

        function displayUserChart(chartData) {
            const ctx = document.getElementById("user-traffic-chart").getContext("2d");
            
            if (currentUserChart) {
                currentUserChart.destroy();
            }
            
            // Handle empty data case with proper fallback labels
            if (!chartData.labels || chartData.labels.length === 0) {
                const timeRange = document.getElementById("time-range").value;
                const defaultLabels = generateDefaultTimeLabels(timeRange, 8);
                chartData = {
                    labels: defaultLabels,
                    upload: new Array(defaultLabels.length).fill(0),
                    download: new Array(defaultLabels.length).fill(0), 
                    total: new Array(defaultLabels.length).fill(0),
                    user_id: chartData.user_id || "Unknown"
                };
            } else {
                // Ensure complete time series to prevent gaps
                const timeRange = document.getElementById("time-range").value;
                const completeLabels = generateCompleteTimeSeriesForUserChart(timeRange);
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
            }
            
            // Get current unit and mode settings
            const unit = document.getElementById("user-chart-unit").value;
            const mode = document.getElementById("user-chart-mode").value;
            
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
                unitMultiplier = getUserUnitMultiplier(unit);
                unitLabel = unit;
            }
            
            // Prepare datasets based on mode
            let datasets = [];
            
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
            
            currentUserChart = new Chart(ctx, {
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
                            text: t("user_traffic_usage_trends", {user_id: currentUserId}),
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
                                    const formattedValue = Number(value.toFixed(2));
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
                                    return Number(value.toFixed(2)) + " " + unitLabel;
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
        
        function getUserUnitMultiplier(unit) {
            switch (unit) {
                case "auto":
                case "MB": return 1000000;
                case "GB": return 1000000000;
                case "TB": return 1000000000000;
                default: return 1000000000; // Default to GB
            }
        }
        
        function exportUserUsageRecords() {
            if (!currentUserId) {
                alert(t("no_user_selected"));
                return;
            }
            
            // Get current node search filter value
            const nodeSearchValue = document.getElementById("user-node-search").value.trim();
            
            // Store the search value globally for use in export
            window.currentUserNodeSearchFilter = nodeSearchValue;
            
            // Show the enhanced export modal instead of direct export
            document.getElementById("user-export-modal").style.display = "block";
        }
        
        function closeUserModal() {
            document.getElementById("user-modal").style.display = "none";
            if (currentUserChart) {
                currentUserChart.destroy();
                currentUserChart = null;
            }
            currentUserId = null;
            allUserUsageRecords = [];
        }
        
        function exportUserRankings() {
            // Show export confirmation dialog instead of direct export
            document.getElementById("user-export-modal").style.display = "block";
        }
        
        // Utility functions
        function formatBytes(bytes) {
            if (bytes === 0) return "0&nbsp;B";
            const k = 1000;
            const sizes = ["B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB"];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            const value = bytes / Math.pow(k, i);
            // Use Number.prototype.toFixed() to match PHP number_format() behavior
            return Number(value.toFixed(2)) + "&nbsp;" + sizes[i];
        }
        
        function formatTimeAgo(timestamp) {
            const now = Math.floor(Date.now() / 1000);
            const diff = now - timestamp;
            
            if (diff < 60) return `${diff}${t("seconds_ago")}`;
            if (diff < 3600) return `${Math.floor(diff / 60)}${t("minutes_ago")}`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}${t("hours_ago")}`;
            return `${Math.floor(diff / 86400)}${t("days_ago")}`;
        }
        
        function getTimeRangeText(timeRange) {
            switch (timeRange) {
                case "today": return t("today_range");
                case "week": return t("last_7_days");
                case "15days": return t("last_15_days");
                case "month": return t("last_30_days");
                case "custom": return t("custom_date_range");
                default: return timeRange;
            }
        }
        
        function getTimeRangeDisplayText(timeRange) {
            if (timeRange === "custom") {
                const startDate = document.getElementById("start-date").value;
                const endDate = document.getElementById("end-date").value;
                
                if (startDate && endDate) {
                    return `${startDate} ${t("to")} ${endDate}`;
                } else {
                    return t("custom_date_range");
                }
            }
            
            return getTimeRangeText(timeRange);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById("user-modal");
            if (event.target === modal) {
                closeUserModal();
            }
        }
        
        // Export modal functions
        function closeUserExportModal() {
            document.getElementById("user-export-modal").style.display = "none";
        }
        
        // Export type change handlers for user modal
        $(document).ready(function() {
            $("input[name=\'user_export_type\']").on("change", function() {
                const type = $(this).val();
                $("#user-limit-options").toggle(type === "limited");
                $("#user-date-range-options").toggle(type === "date_range");
                $("#user-time-range-options").toggle(type === "time_range");
            });
            
            // Pagination event listeners for user usage records
            $("#user-records-per-page").on("change", function() {
                currentUserUsagePage = 1;
                updateUserUsagePagination();
            });
            
            $("#user-first-page").on("click", function() {
                currentUserUsagePage = 1;
                updateUserUsagePagination();
            });
            
            $("#user-prev-page").on("click", function() {
                if (currentUserUsagePage > 1) {
                    currentUserUsagePage--;
                    updateUserUsagePagination();
                }
            });
            
            $("#user-next-page").on("click", function() {
                if (currentUserUsagePage < totalUserUsagePages) {
                    currentUserUsagePage++;
                    updateUserUsagePagination();
                }
            });
            
            $("#user-last-page").on("click", function() {
                currentUserUsagePage = totalUserUsagePages;
                updateUserUsagePagination();
            });
            
            // Node search event handlers
            $("#search-user-records").on("click", function() {
                currentUserUsagePage = 1;
                filterAndUpdateUserUsageRecords();
            });
            
            $("#clear-user-search").on("click", function() {
                document.getElementById("user-node-search").value = "";
                currentUserUsagePage = 1;
                filterAndUpdateUserUsageRecords();
            });
            
            // Enter key support for search
            $("#user-node-search").on("keypress", function(e) {
                if (e.which === 13) {
                    currentUserUsagePage = 1;
                    filterAndUpdateUserUsageRecords();
                }
            });
            
            // Helper function to get main page time range bounds
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
                    case "15days":
                        startDate = new Date(today.getTime() - 14 * 24 * 60 * 60 * 1000);
                        startDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                        endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
                        break;
                    case "month":
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
                    const mainStartStr = mainRange.start.toLocaleDateString();
                    const mainEndStr = mainRange.end.toLocaleDateString();
                    alert("Export time range must be within the main page search range (" + mainStartStr + " to " + mainEndStr + ").");
                    return false;
                }
                
                return true;
            }
            
            // Export form submission for users
            $("#user-export-form").on("submit", function(e) {
                e.preventDefault();
                
                const timeRange = document.getElementById("time-range").value;
                const exportType = $("input[name=\'user_export_type\']:checked").val();
                const format = $("#user_export_format").val();
                
                let exportParams;
                
                // If we have a current user ID, export that user usage records
                // Otherwise, export the user rankings list
                if (currentUserId) {
                    exportParams = "export_type=usage_records&user_id=" + currentUserId + "&time_range=" + timeRange + "&format=" + format;
                    
                    // Add node search filter if available
                    if (window.currentUserNodeSearchFilter) {
                        exportParams += "&node_search=" + encodeURIComponent(window.currentUserNodeSearchFilter);
                    }
                } else {
                    const limit = document.getElementById("limit").value;
                    exportParams = `export_type=user_rankings&time_range=${timeRange}&sort_by=${currentSort.field}_${currentSort.direction}&limit=${limit}&format=${format}`;
                }
                
                // Add custom date range parameters if applicable  
                if (timeRange === "custom" && exportType !== "date_range") {
                    const startDate = document.getElementById("start-date").value;
                    const endDate = document.getElementById("end-date").value;
                    
                    // Validate date format and values
                    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                    
                    if (startDate && endDate && dateRegex.test(startDate) && dateRegex.test(endDate)) {
                        const start = new Date(startDate);
                        const end = new Date(endDate);
                        
                        // Only add dates if they are valid and start <= end
                        if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && start <= end) {
                            exportParams += "&start_date=" + startDate + "&end_date=" + endDate;
                        }
                    }
                }
                
                // Add specific export options
                if (exportType === "limited") {
                    const limitCount = $("#user_limit_count").val();
                    exportParams += "&limit_count=" + limitCount;
                } else if (exportType === "date_range") {
                    const startDate = $("#user_export_start_date").val();
                    const endDate = $("#user_export_end_date").val();
                    
                    // Validate export modal dates
                    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                    
                    if (startDate && endDate && dateRegex.test(startDate) && dateRegex.test(endDate)) {
                        const start = new Date(startDate);
                        const end = new Date(endDate + " 23:59:59");
                        
                        // Only add dates if they are valid and start <= end  
                        if (!isNaN(start.getTime()) && !isNaN(end.getTime()) && start <= end) {
                            // Validate against main page time range
                            if (!validateExportTimeRange(start, end)) {
                                return;
                            }
                            
                            // Override timeRange to custom and use standard date parameters
                            exportParams = exportParams.replace(/time_range=[^&]*/, "time_range=custom");
                            exportParams += "&start_date=" + startDate + "&end_date=" + endDate;
                        } else {
                            alert("Invalid date range. Please check your start and end dates.");
                            return;
                        }
                    } else {
                        alert("Please select both start and end dates for custom date range export.");
                        return;
                    }
                } else if (exportType === "time_range") {
                    const startTime = $("#user_export_start_time").val();
                    const endTime = $("#user_export_end_time").val();
                    
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
                closeUserExportModal();
            });
        });
    </script>

    <!-- User Export Confirmation Modal -->
    <div id="user-export-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 400px;">
            <h4>' . v2raysocks_traffic_lang('export_data') . '</h4>
            <form id="user-export-form">
                <div style="margin-bottom: 15px;">
                    <label>' . v2raysocks_traffic_lang('export_type') . '</label><br>
                    <label><input type="radio" name="user_export_type" value="all" checked> ' . v2raysocks_traffic_lang('all_filtered_data') . '</label><br>
                    <label><input type="radio" name="user_export_type" value="limited"> ' . v2raysocks_traffic_lang('limited_number_of_records') . '</label><br>
                    <label><input type="radio" name="user_export_type" value="date_range"> ' . v2raysocks_traffic_lang('custom_date_range') . '</label><br>

                    <label><input type="radio" name="user_export_type" value="time_range"> ' . v2raysocks_traffic_lang('custom_time_range') . '</label>
                </div>
                
                <div id="user-limit-options" style="margin-bottom: 15px; display: none;">
                    <label for="user_limit_count">' . v2raysocks_traffic_lang('number_of_records') . '</label>
                    <input type="number" id="user_limit_count" name="limit_count" value="1000" min="1" max="10000">
                </div>
                
                <div id="user-date-range-options" style="margin-bottom: 15px; display: none;">
                    <label for="user_export_start_date">' . v2raysocks_traffic_lang('start_date_label') . '</label>
                    <input type="date" id="user_export_start_date" name="export_start_date"><br><br>
                    <label for="user_export_end_date">' . v2raysocks_traffic_lang('end_date_label') . '</label>
                    <input type="date" id="user_export_end_date" name="export_end_date">
                </div>

                
                <div id="user-time-range-options" style="margin-bottom: 15px; display: none;">
                    <label for="user_export_start_time">' . v2raysocks_traffic_lang('start_time_label') . '</label>
                    <input type="time" id="user_export_start_time" name="export_start_time" step="1" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; margin-bottom: 10px;"><br>
                    <label for="user_export_end_time">' . v2raysocks_traffic_lang('end_time_label') . '</label>
                    <input type="time" id="user_export_end_time" name="export_end_time" step="1" style="width: 100%; padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px;">
                    <br><small style="color: #6c757d; margin-top: 5px; display: block;">' . v2raysocks_traffic_lang('time_range_today_only') . '</small>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="user_export_format">' . v2raysocks_traffic_lang('format') . '</label>
                    <select id="user_export_format" name="format">
                        <option value="excel" selected>' . v2raysocks_traffic_lang('excel') . '</option>
                        <option value="csv">' . v2raysocks_traffic_lang('csv') . '</option>
                        <option value="json">' . v2raysocks_traffic_lang('json') . '</option>
                    </select>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" onclick="closeUserExportModal()" class="btn" style="margin-right: 10px;">' . v2raysocks_traffic_lang('cancel') . '</button>
                    <button type="submit" class="btn btn-primary">' . v2raysocks_traffic_lang('export') . '</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>';

return $userRankingsHtml;