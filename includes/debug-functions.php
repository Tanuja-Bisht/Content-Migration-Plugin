<?php
/**
 * Debug Functions
 * 
 * Centralized debug functions for the Content Migrator plugin.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Log debug information to a file
 * 
 * @param string $message The message to log
 * @return void
 */
function wcm_debug_log($message) {
    $debug_file = CONTENT_MIGRATOR_PLUGIN_DIR . 'debug-log.txt';
    $timestamp = date('[Y-m-d H:i:s] ');
    file_put_contents($debug_file, $timestamp . $message . "\n", FILE_APPEND);
} 