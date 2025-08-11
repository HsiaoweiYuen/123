<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once(__DIR__ . '/lib/Monitor_DB.php');

/**
 * Standalone User Rankings Page
 * This provides a dedicated, independent PHP file for user rankings
 * as requested in the requirements, while maintaining WHMCS integration
 */
function v2raysocks_traffic_userRankingsStandalone()
{
    require(__DIR__ . '/templates/templates_functions.php');
    
    // Load language settings
    $LANG = v2raysocks_traffic_loadLanguage();
    
    // Handle form data if this is a POST request
    $formData = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formData = $_POST;
    }
    
    // Display user rankings using the existing template function
    return v2raysocks_traffic_displayUserRankings($LANG, $formData);
}

// This file can be included/required by the main module or accessed through routing
// It provides the standalone user rankings functionality as requested