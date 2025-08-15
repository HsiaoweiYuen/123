<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include unified navigation component
require_once(__DIR__ . '/navigation_component.php');

$performanceDashboardHtml = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . v2raysocks_traffic_lang('performance_dashboard') . '</title>
    <style>
        ' . v2raysocks_traffic_getNavigationCSS() . '
        .performance-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .performance-card {
            flex: 1;
            min-width: 280px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }
        .performance-value {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .performance-label {
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .performance-excellent { color: #28a745; }
        .performance-good { color: #17a2b8; }
        .performance-warning { color: #ffc107; }
        .performance-critical { color: #dc3545; }
        
        .memory-info {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .memory-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 10px 0;
        }
        .memory-bar-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        .cache-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .cache-stat {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }
        .optimization-actions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .action-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        .action-button:hover {
            background: #0056b3;
        }
        .key-analysis {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .key-pattern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        ' . v2raysocks_traffic_getNavigationHTML() . '
        
        <h1>' . v2raysocks_traffic_lang('performance_dashboard') . '</h1>
        
        <div id="performance-content">
            <div class="performance-row">
                <div class="performance-card">
                    <div class="performance-label">' . v2raysocks_traffic_lang('cache_hit_rate') . '</div>
                    <div class="performance-value" id="hit-rate">-</div>
                    <div class="performance-label">' . v2raysocks_traffic_lang('total_requests') . ': <span id="total-requests">-</span></div>
                </div>
                
                <div class="performance-card">
                    <div class="performance-label">' . v2raysocks_traffic_lang('memory_fragmentation') . '</div>
                    <div class="performance-value" id="fragmentation-ratio">-</div>
                    <div class="performance-label" id="fragmentation-status">-</div>
                </div>
                
                <div class="performance-card">
                    <div class="performance-label">' . v2raysocks_traffic_lang('total_cache_keys') . '</div>
                    <div class="performance-value" id="total-keys">-</div>
                    <div class="performance-label">' . v2raysocks_traffic_lang('redis_status') . ': <span id="redis-status">-</span></div>
                </div>
            </div>
            
            <div class="memory-info">
                <h3>' . v2raysocks_traffic_lang('memory_usage') . '</h3>
                <div>
                    <div class="performance-label">' . v2raysocks_traffic_lang('used_memory') . ': <span id="used-memory">-</span></div>
                    <div class="memory-bar">
                        <div class="memory-bar-fill" id="memory-usage-bar"></div>
                    </div>
                </div>
                <div>
                    <div class="performance-label">' . v2raysocks_traffic_lang('peak_memory') . ': <span id="peak-memory">-</span></div>
                </div>
            </div>
            
            <div class="cache-stats">
                <div class="cache-stat">
                    <div class="performance-label">' . v2raysocks_traffic_lang('cache_hits') . '</div>
                    <div class="performance-value" id="cache-hits">-</div>
                </div>
                <div class="cache-stat">
                    <div class="performance-label">' . v2raysocks_traffic_lang('cache_misses') . '</div>
                    <div class="performance-value" id="cache-misses">-</div>
                </div>
                <div class="cache-stat">
                    <div class="performance-label">' . v2raysocks_traffic_lang('cache_sets') . '</div>
                    <div class="performance-value" id="cache-sets">-</div>
                </div>
                <div class="cache-stat">
                    <div class="performance-label">' . v2raysocks_traffic_lang('cache_errors') . '</div>
                    <div class="performance-value" id="cache-errors">-</div>
                </div>
            </div>
            
            <div class="optimization-actions">
                <h3>' . v2raysocks_traffic_lang('optimization_actions') . '</h3>
                <button class="action-button" onclick="prewarmCache()">' . v2raysocks_traffic_lang('prewarm_cache') . '</button>
                <button class="action-button" onclick="optimizeCache()">' . v2raysocks_traffic_lang('optimize_cache') . '</button>
                <button class="action-button" onclick="smartClearCache()">' . v2raysocks_traffic_lang('smart_clear_cache') . '</button>
                <button class="action-button" onclick="refreshStats()">' . v2raysocks_traffic_lang('refresh_stats') . '</button>
                <div id="action-result" style="margin-top: 10px; display: none;"></div>
            </div>
            
            <div class="key-analysis">
                <h3>' . v2raysocks_traffic_lang('cache_key_analysis') . '</h3>
                <div id="key-patterns">
                    <div class="performance-label">' . v2raysocks_traffic_lang('loading') . '...</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function updatePerformanceStats() {
            fetch("?action=cache_performance_stats")
                .then(response => response.json())
                .then(data => {
                    if (data.redis_available) {
                        // Update basic stats
                        document.getElementById("hit-rate").textContent = data.hit_rate + "%";
                        document.getElementById("total-requests").textContent = data.total_requests;
                        document.getElementById("cache-hits").textContent = data.hits;
                        document.getElementById("cache-misses").textContent = data.misses;
                        document.getElementById("cache-sets").textContent = data.sets;
                        document.getElementById("cache-errors").textContent = data.errors;
                        document.getElementById("total-keys").textContent = data.key_analysis?.total_keys || 0;
                        document.getElementById("redis-status").textContent = "' . v2raysocks_traffic_lang('connected') . '";
                        
                        // Update hit rate color
                        const hitRateElement = document.getElementById("hit-rate");
                        const hitRate = parseFloat(data.hit_rate);
                        if (hitRate >= 80) {
                            hitRateElement.className = "performance-value performance-excellent";
                        } else if (hitRate >= 60) {
                            hitRateElement.className = "performance-value performance-good";
                        } else if (hitRate >= 40) {
                            hitRateElement.className = "performance-value performance-warning";
                        } else {
                            hitRateElement.className = "performance-value performance-critical";
                        }
                        
                        // Update memory info
                        if (data.memory_info) {
                            const memInfo = data.memory_info;
                            document.getElementById("fragmentation-ratio").textContent = memInfo.mem_fragmentation_ratio;
                            document.getElementById("used-memory").textContent = memInfo.used_memory_human || "N/A";
                            document.getElementById("peak-memory").textContent = memInfo.used_memory_peak_human || "N/A";
                            
                            // Update fragmentation status
                            const fragElement = document.getElementById("fragmentation-ratio");
                            const fragStatus = document.getElementById("fragmentation-status");
                            const fragRatio = parseFloat(memInfo.mem_fragmentation_ratio);
                            
                            if (data.fragmentation_status) {
                                fragStatus.textContent = data.fragmentation_status.message;
                                fragElement.className = "performance-value performance-" + data.fragmentation_status.status;
                            }
                            
                            // Update memory usage bar
                            if (memInfo.maxmemory > 0) {
                                const usagePercent = (memInfo.used_memory / memInfo.maxmemory) * 100;
                                const memoryBar = document.getElementById("memory-usage-bar");
                                memoryBar.style.width = usagePercent + "%";
                                
                                if (usagePercent >= 90) {
                                    memoryBar.style.backgroundColor = "#dc3545";
                                } else if (usagePercent >= 70) {
                                    memoryBar.style.backgroundColor = "#ffc107";
                                } else {
                                    memoryBar.style.backgroundColor = "#28a745";
                                }
                            }
                        }
                        
                        // Update key analysis
                        if (data.key_analysis && data.key_analysis.patterns) {
                            const keyPatternsDiv = document.getElementById("key-patterns");
                            keyPatternsDiv.innerHTML = "";
                            
                            Object.entries(data.key_analysis.patterns).forEach(([pattern, info]) => {
                                const patternDiv = document.createElement("div");
                                patternDiv.className = "key-pattern";
                                patternDiv.innerHTML = `
                                    <span><strong>${pattern}</strong></span>
                                    <span>${info.count} keys</span>
                                `;
                                keyPatternsDiv.appendChild(patternDiv);
                            });
                        }
                    } else {
                        document.getElementById("redis-status").textContent = "' . v2raysocks_traffic_lang('disconnected') . '";
                        document.getElementById("hit-rate").textContent = "N/A";
                    }
                })
                .catch(error => {
                    console.error("Error fetching performance stats:", error);
                });
        }
        
        function prewarmCache() {
            showActionResult("' . v2raysocks_traffic_lang('prewarming_cache') . '...", "info");
            fetch("?action=prewarm_cache")
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showActionResult("' . v2raysocks_traffic_lang('cache_prewarmed') . '", "success");
                        updatePerformanceStats();
                    } else {
                        showActionResult("' . v2raysocks_traffic_lang('prewarm_failed') . '", "error");
                    }
                });
        }
        
        function optimizeCache() {
            showActionResult("' . v2raysocks_traffic_lang('optimizing_cache') . '...", "info");
            fetch("?action=optimize_cache")
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showActionResult("' . v2raysocks_traffic_lang('cache_optimized') . '", "success");
                        updatePerformanceStats();
                    } else {
                        showActionResult("' . v2raysocks_traffic_lang('optimize_failed') . '", "error");
                    }
                });
        }
        
        function smartClearCache() {
            showActionResult("' . v2raysocks_traffic_lang('clearing_cache') . '...", "info");
            fetch("?action=smart_clear_cache")
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showActionResult("' . v2raysocks_traffic_lang('cache_cleared') . '", "success");
                        updatePerformanceStats();
                    } else {
                        showActionResult("' . v2raysocks_traffic_lang('clear_failed') . '", "error");
                    }
                });
        }
        
        function refreshStats() {
            updatePerformanceStats();
            showActionResult("' . v2raysocks_traffic_lang('stats_refreshed') . '", "success");
        }
        
        function showActionResult(message, type) {
            const resultDiv = document.getElementById("action-result");
            resultDiv.style.display = "block";
            resultDiv.textContent = message;
            resultDiv.className = "performance-" + type;
            
            setTimeout(() => {
                resultDiv.style.display = "none";
            }, 3000);
        }
        
        // Auto-refresh stats every 30 seconds
        setInterval(updatePerformanceStats, 30000);
        
        // Initial load
        updatePerformanceStats();
    </script>
</body>
</html>';

echo $performanceDashboardHtml;