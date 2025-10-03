<?php
/**
 * Bootstrap file for app configuration
 * Prevents multiple inclusions and redeclaration errors
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (defined('BOOTSTRAP_ALREADY_LOADED')) {
    return;
}
define('BOOTSTRAP_ALREADY_LOADED', true);

// Load Composer autoload if available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Import PHPMailer classes after autoloader is available
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


/**
 * Global config() helper
 * Loads config.php once and caches values
 */
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        static $settings = null;

        if ($settings === null) {
            $configFile = __DIR__ . '/config.php';
            if (!file_exists($configFile)) {
                throw new Exception("Missing config.php in " . __DIR__);
            }
            $settings = require $configFile;
            
            // Load local config if exists
            $localConfigFile = __DIR__ . '/config.local.php';
            if (file_exists($localConfigFile)) {
                $localConfig = require $localConfigFile;
                $settings = array_replace_recursive($settings, $localConfig);
            }
            
            if (!is_array($settings)) {
                throw new Exception("config.php must return an array");
            }
            
            // Set error reporting based on debug mode
            $debug = $settings['app']['debug'] ?? false;
            if ($debug) {
                error_reporting(E_ALL);
                ini_set('display_errors', 1);
                ini_set('log_errors', 1);
                ini_set('error_log', __DIR__ . '/php_errors.log');
            } else {
                error_reporting(0);
                ini_set('display_errors', 0);
            }
            
            // Set default timezone
            date_default_timezone_set($settings['app']['timezone'] ?? 'UTC');
        }

        $keys = explode('.', $key);
        $value = $settings;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}

// Set environment variables for backward compatibility
if (!getenv('SMTP_HOST')) {
    putenv('SMTP_HOST=' . config('smtp.host'));
    putenv('SMTP_PORT=' . config('smtp.port'));
    putenv('SMTP_USERNAME=' . config('smtp.username'));
    putenv('SMTP_PASSWORD=' . config('smtp.password'));
    putenv('SMTP_FROM_EMAIL=' . config('smtp.from_email'));
    putenv('SMTP_FROM_NAME=' . config('smtp.from_name'));
    putenv('SMTP_SECURE=' . (config('smtp.secure') ?: 'tls'));
    putenv('SMTP_DEBUG=' . (config('smtp.debug') ? '1' : '0'));
    putenv('APP_ENV=' . (config('app.env') ?: 'production'));
    putenv('APP_DEBUG=' . (config('app.debug') ? 'true' : 'false'));
    putenv('TIMEZONE=' . (config('app.timezone') ?: 'UTC'));
    putenv('MAX_UPLOAD_BYTES=' . (config('app.max_upload_size') ?: '5242880'));
}
