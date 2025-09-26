<?php
/**
 * Example Configuration
 * 
 * Copy this file to config.local.php and update the values as needed.
 * The config.local.php file is ignored by Git for security.
 */

return [
    // SMTP Settings
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'your-email@example.com',
        'password' => 'your-email-password',
        'from_email' => 'noreply@example.com',
        'from_name' => 'Conference Registration',
        'secure' => 'tls', // tls or ssl
        'debug' => 0, // 0 = off, 1 = client messages, 2 = client and server messages
    ],
    
    // Application Settings
    'app' => [
        'env' => 'production', // 'development' or 'production'
        'debug' => false, // Set to false in production
        'timezone' => 'UTC',
        'max_upload_size' => 5 * 1024 * 1024, // 5MB in bytes
    ],
    
    // Email CC (Carbon Copy) recipients
    'cc_recipients' => [
        ['email' => 'vokello@mespt.org', 'name' => 'Victoria Okello'],
        // Add more recipients as needed
    ]
];
