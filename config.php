<?php
/**
 * Default Application Configuration
 * 
 * This file contains the default configuration settings for the application.
 * For local configuration overrides, create a `config.local.php` file in the same directory.
 * The local configuration will be merged with this default configuration.
 * 
 * WARNING: Do not store sensitive information in this file.
 * Use `config.local.php` and add it to your `.gitignore` file.
 */

return [
    /**
     * SMTP Configuration
     * 
     * These settings are used by PHPMailer to send emails.
     * For Gmail, you may need to enable "Less secure app access" or use an App Password.
     */
    'smtp' => [
        // SMTP server hostname
        'host' => 'smtp.gmail.com',
        
        // SMTP server port (587 for TLS, 465 for SSL)
        'port' => 587,
        
        // SMTP username (usually the email address)
        'username' => 'vaokello@gmail.com',
        
        // SMTP password (use an App Password for Gmail)
        'password' => 'odizryolxybtsgzf',
        
        // From email address (should match the username for most SMTP servers)
        'from_email' => 'vaokello@gmail.com',
        
        // From name (display name)
        'from_name' => 'Conference Registration',
        
        // Encryption: 'tls' or 'ssl'
        'secure' => 'tls',
        
        // Debug level: 0 = off, 1 = client messages, 2 = client and server messages
        'debug' => 0,
    ],
    
    /**
     * Application Settings
     */
    'app' => [
        // Environment: 'development' or 'production'
        'env' => 'production',
        
        // Debug mode: true for development, false for production
        'debug' => false,
        
        // Default timezone (see https://www.php.net/manual/en/timezones.php)
        'timezone' => 'UTC',
        
        // Maximum file upload size in bytes (5MB default)
        'max_upload_size' => 5 * 1024 * 1024,
    ],
    
    /**
     * Email CC Recipients
     * 
     * These email addresses will receive a carbon copy of all sent invoices.
     * Format: [['email' => 'email@example.com', 'name' => 'Name'], ...]
     */
    'cc_recipients' => [
        ['email' => 'vokello@mespt.org', 'name' => 'Victoria Okello'],
    ],
    
    /**
     * File Upload Settings
     */
    'upload' => [
        // Directory to store uploaded files (relative to the project root)
        'directory' => 'uploads',
        
        // Allowed file types for uploads
        'allowed_types' => ['pdf', 'png', 'jpg', 'jpeg'],
        
        // Maximum file size in bytes (overrides app.max_upload_size for specific uploads)
        'max_size' => 5 * 1024 * 1024, // 5MB
    ],
    
    /**
     * Security Settings
     */
    'security' => [
        // Enable CSRF protection for forms
        'csrf_protection' => true,
        
        // Allowed origins for CORS (empty array to allow all)
        'allowed_origins' => [],
        
        // Enable XSS protection headers
        'xss_protection' => true,
    ],
];
