<?php
/**
 * Logger
 *
 * @package TPFMediaOrganizer\Core
 */

namespace TPFMediaOrganizer\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple logging class for debugging
 */
class Logger {

    /**
     * Log file path
     *
     * @var string
     */
    private static $log_file = null;

    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private static $enabled = null;

    /**
     * Get log file path
     *
     * @return string
     */
    private static function get_log_file() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            self::$log_file = $upload_dir['basedir'] . '/tpf-media-organizer-debug.log';
        }
        return self::$log_file;
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    private static function is_enabled() {
        if (self::$enabled === null) {
            self::$enabled = defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        }
        return self::$enabled;
    }

    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level   Log level (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        if (!self::is_enabled()) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $level = strtoupper($level);
        $formatted = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        error_log($formatted, 3, self::get_log_file());
    }

    /**
     * Log info message
     *
     * @param string $message
     */
    public static function info($message) {
        self::log($message, 'info');
    }

    /**
     * Log warning message
     *
     * @param string $message
     */
    public static function warning($message) {
        self::log($message, 'warning');
    }

    /**
     * Log error message
     *
     * @param string $message
     */
    public static function error($message) {
        self::log($message, 'error');
    }

    /**
     * Clear the log file
     */
    public static function clear() {
        $log_file = self::get_log_file();
        if (file_exists($log_file)) {
            unlink($log_file);
        }
    }

    /**
     * Get log contents
     *
     * @param int $lines Number of lines to return (0 = all)
     * @return string
     */
    public static function get_contents($lines = 100) {
        $log_file = self::get_log_file();
        if (!file_exists($log_file)) {
            return '';
        }

        if ($lines === 0) {
            return file_get_contents($log_file);
        }

        $contents = file($log_file);
        $contents = array_slice($contents, -$lines);
        return implode('', $contents);
    }
}
