<?php
/**
 * Bootstrap File
 * 
 * This file loads the configuration and defines helper functions.
 */

// Load base configuration
$config = require __DIR__ . '/config.php';

// Load local configuration if it exists
$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    $localConfig = require $localConfigFile;
    
    // Merge configurations recursively
    $config = array_replace_recursive($config, $localConfig);
    
    // Special handling for numeric arrays to avoid merging issues
    if (isset($localConfig['cc_recipients'])) {
        $config['cc_recipients'] = $localConfig['cc_recipients'];
    }
}

// Ensure required configuration sections exist
$requiredSections = ['smtp', 'app'];
foreach ($requiredSections as $section) {
    if (!isset($config[$section])) {
        throw new RuntimeException("Missing required configuration section: {$section}");
    }
}

// Set default values for required SMTP settings
$requiredSmtpSettings = ['host', 'port', 'username', 'password', 'from_email', 'from_name'];
foreach ($requiredSmtpSettings as $setting) {
    if (!isset($config['smtp'][$setting])) {
        throw new RuntimeException("Missing required SMTP setting: {$setting}");
    }
}

/**
 * Get a configuration value using dot notation
 * 
 * @param string $key Dot notation key (e.g., 'smtp.host')
 * @param mixed $default Default value if key doesn't exist
 * @return mixed
 */
function config($key, $default = null) {
    global $config;
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

// Set environment variables for backward compatibility
putenv('SMTP_HOST=' . config('smtp.host'));
putenv('SMTP_PORT=' . config('smtp.port'));
putenv('SMTP_USERNAME=' . config('smtp.username'));
putenv('SMTP_PASSWORD=' . config('smtp.password'));
putenv('SMTP_FROM_EMAIL=' . config('smtp.from_email'));
putenv('SMTP_FROM_NAME=' . config('smtp.from_name'));
putenv('SMTP_SECURE=' . config('smtp.secure'));
putenv('SMTP_DEBUG=' . config('smtp.debug'));
putenv('APP_ENV=' . config('app.env'));
putenv('APP_DEBUG=' . (config('app.debug') ? 'true' : 'false'));
putenv('TIMEZONE=' . config('app.timezone'));
putenv('MAX_UPLOAD_BYTES=' . config('app.max_upload_size'));

// Set default timezone
date_default_timezone_set(config('app.timezone', 'UTC'));

// Set error reporting based on debug mode
if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
