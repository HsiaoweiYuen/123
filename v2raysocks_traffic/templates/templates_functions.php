<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Use nodes module's traffic formatting approach for consistency
function v2raysocks_traffic_formatBytes($bytes, $precision = 2)
{
    // Align with nodes module approach: simple conversion to GB using / 1000000000
    $gb = $bytes / 1000000000;
    
    if ($gb < 0.01 && $bytes > 0) {
        // For very small values, show in MB
        $mb = $bytes / 1000000;
        return number_format($mb, 2) . ' MB';
    } elseif ($gb >= 1000) {
        // For very large values, show in TB
        $tb = $gb / 1000;
        return number_format($tb, 2) . ' TB';
    } else {
        // Default GB display like nodes module
        return number_format($gb, 2) . ' GB';
    }
}

function v2raysocks_traffic_formatBytesWithUnit($bytes, $unit, $precision = 2)
{
    // Simple unit conversion aligned with nodes module
    switch (strtoupper($unit)) {
        case 'KB':
            return number_format($bytes / 1000, $precision) . ' KB';
        case 'MB':
            return number_format($bytes / 1000000, $precision) . ' MB';
        case 'GB':
            return number_format($bytes / 1000000000, $precision) . ' GB';
        case 'TB':
            return number_format($bytes / 1000000000000, $precision) . ' TB';
        default:
            return v2raysocks_traffic_formatBytes($bytes, $precision);
    }
}

function v2raysocks_traffic_formatSpeed($bytesPerSecond)
{
    return v2raysocks_traffic_formatBytes($bytesPerSecond) . '/s';
}

function v2raysocks_traffic_timeAgo($timestamp)
{
    $time = time() - $timestamp;
    
    if ($time < 60) {
        return $time . 's ago';
    } elseif ($time < 3600) {
        return floor($time / 60) . 'm ago';
    } elseif ($time < 86400) {
        return floor($time / 3600) . 'h ago';
    } else {
        return floor($time / 86400) . 'd ago';
    }
}

function v2raysocks_traffic_getStatusBadge($isOnline, $LANG)
{
    if ($isOnline) {
        return '<span class="label label-success">Online</span>';
    } else {
        return '<span class="label label-danger">Offline</span>';
    }
}

function v2raysocks_traffic_makeProgressBar($current, $total, $type = 'primary')
{
    if ($total == 0) {
        $percentage = 0;
    } else {
        $percentage = min(100, ($current / $total) * 100);
    }
    
    $barClass = 'progress-bar-' . $type;
    if ($percentage > 80) {
        $barClass = 'progress-bar-warning';
    }
    if ($percentage > 95) {
        $barClass = 'progress-bar-danger';
    }
    
    return '
    <div class="progress" style="margin-bottom: 0;">
        <div class="progress-bar ' . $barClass . '" role="progressbar" style="width: ' . $percentage . '%">
            ' . round($percentage, 1) . '%
        </div>
    </div>';
}

// Add access to configurable unit conversion for templates
require_once(__DIR__ . '/../lib/Monitor_DB.php');