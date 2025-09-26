<?php
// Simple test script to send an email using App\Mailer (PHPMailer)
// Usage (from project directory):
//   c:\xampp\\php\\php.exe send_test_email.php recipient@example.com "Recipient Name"

require __DIR__ . '/vendor/autoload.php';

use App\Mailer;

$toEmail = $argv[1] ?? null;
$toName  = $argv[2] ?? '';
if (!$toEmail) {
    fwrite(STDERR, "Usage: php send_test_email.php <toEmail> [toName]\n");
    exit(1);
}

// TODO: Replace these with your real SMTP settings
$config = [
    'host'       => 'smtp.gmail.com',   // e.g. smtp.gmail.com
    'port'       => 587,                  // 587 for TLS, 465 for SSL
    'username'   => 'vaokello@gmail.com',   // SMTP username
    'password'   => 'odizryolxybtsgzf',       // SMTP password or App Password
    'encryption' => 'tls',                // 'tls', 'ssl', or null for none
    'from_email' => 'vaokello@gmail.com',
    'from_name'  => 'Conference App'
];

$mailer = new Mailer($config);

$subject  = 'Test Email from Conference App';
$htmlBody = '<h1>Hello</h1><p>This is a <strong>test email</strong> sent using PHPMailer via Composer.</p>';
$textBody = "Hello\nThis is a test email sent using PHPMailer via Composer.";

try {
    $ok = $mailer->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    if ($ok) {
        echo "Sent test email to {$toEmail}\n";
        exit(0);
    }
    echo "Failed to send email to {$toEmail}\n";
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(3);
}
