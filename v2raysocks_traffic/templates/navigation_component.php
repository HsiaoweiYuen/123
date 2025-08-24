<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Unified Navigation Component for V2RaySocks Traffic Module
 * 
 * This component provides consistent navigation styling and structure
 * across all traffic module pages to fix navigation menu positioning issues.
 */

function v2raysocks_traffic_getNavigationCSS() {
    return '
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
        }
        
        /* Responsive styles for very small devices */
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 5px;
            }
            .navigation-bar {
                padding: 10px;
            }
        }
    ';
}

function v2raysocks_traffic_getNavigationHTML($activeAction = '') {
    $navItems = [
        '' => [
            'url' => 'addonmodules.php?module=v2raysocks_traffic',
            'label' => v2raysocks_traffic_lang('traffic_dashboard')
        ],
        'real_time' => [
            'url' => 'addonmodules.php?module=v2raysocks_traffic&action=real_time',
            'label' => v2raysocks_traffic_lang('real_time_monitor')
        ],
        'node_stats' => [
            'url' => 'addonmodules.php?module=v2raysocks_traffic&action=node_stats',
            'label' => v2raysocks_traffic_lang('node_statistics')
        ],
        'user_rankings' => [
            'url' => 'addonmodules.php?module=v2raysocks_traffic&action=user_rankings',
            'label' => v2raysocks_traffic_lang('user_rankings')
        ],
        'service_search' => [
            'url' => 'addonmodules.php?module=v2raysocks_traffic&action=service_search',
            'label' => v2raysocks_traffic_lang('service_search')
        ]
    ];
    
    $html = '<div class="navigation-bar"><div class="nav-links">';
    
    foreach ($navItems as $action => $item) {
        $activeClass = ($action === $activeAction) ? ' active' : '';
        $html .= '<a href="' . $item['url'] . '" class="nav-link' . $activeClass . '">' . $item['label'] . '</a>';
    }
    
    $html .= '</div></div>';
    
    return $html;
}

function v2raysocks_traffic_getUnifiedStyles() {
    return '
        .controls-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .controls-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
            margin-bottom: 15px;
        }
        .control-group {
            flex: 1;
            min-width: auto;
        }
        /* Constrain time input controls to prevent them from growing too wide */
        #node-rankings-start-time-group,
        #node-rankings-end-time-group {
            flex: 0 0 140px;
            min-width: 140px;
        }
        .control-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .control-group select, .control-group input {
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
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .rankings-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .rankings-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .rankings-title {
            font-size: 1.2em;
            font-weight: bold;
            margin: 0;
        }
        .export-btn {
            padding: 6px 12px;
            font-size: 0.9em;
        }
        
        .table-responsive { 
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table-striped tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .table tbody tr:hover {
            background-color: #e3f2fd;
            cursor: pointer;
        }
        
        .loading, .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .no-data { font-style: italic; }
        
        /* UUID column styling for 40-character limit */
        .uuid-column {
            max-width: 320px; /* 40字符 × 8px平均字符宽度 */
            white-space: nowrap; /* 禁止换行 */
            overflow: hidden; /* 隐藏超出内容 */
            text-overflow: ellipsis; /* 显示省略号 */
            font-family: monospace; /* 等宽字体便于查看 */
        }
        
        /* Responsive styles for mobile devices */
        @media (max-width: 768px) {
            .controls-row {
                flex-direction: column;
                gap: 10px;
            }
            .control-group {
                min-width: auto;
                width: 100%;
            }
            /* Override time input constraints for mobile */
            #node-rankings-start-time-group,
            #node-rankings-end-time-group {
                flex: 1 1 auto;
                min-width: auto;
                width: 100%;
            }
            .table-responsive {
                font-size: 0.9em;
            }
            .table th, .table td {
                padding: 8px 4px;
                min-width: auto !important;
            }
            /* Adjust UUID column for mobile */
            .uuid-column {
                max-width: 240px; /* 缩小手机版UUID列宽度但保持可读性 */
            }
        }
        
        /* Responsive styles for very small devices */
        @media (max-width: 480px) {
            .controls-panel {
                padding: 10px;
            }
            .table th, .table td {
                padding: 6px 2px;
                font-size: 0.8em;
            }
            /* Further adjust UUID column for very small devices */
            .uuid-column {
                max-width: 200px; /* 超小屏幕进一步缩小UUID列宽度 */
            }
        }
    ';
}