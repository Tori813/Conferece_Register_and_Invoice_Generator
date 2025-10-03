<?php
/*
  Author: Victoria Okello
  App: Conference Registration System
  Created: 2025
  Authorship: Protected by copyright
*/

// Set app author header
header("X-App-Author: Victoria Okello");

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load bootstrap/configuration
require_once __DIR__ . '/bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set API headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// JSON response helper
function sendJsonResponse(array $data, int $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Invalid request method. Use POST.'], 405);
}

// Debug logging
if (config('app.debug')) {
    error_log("POST Data: " . json_encode($_POST));
    error_log("FILES Data: " . json_encode($_FILES));
}

// Check required fields
if (empty($_POST['email']) || empty($_FILES['pdf'])) {
    sendJsonResponse(['error' => 'Missing required fields: email and pdf'], 400);
}

// Sanitize email and name
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$name = !empty($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : 'User';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(['error' => 'Invalid email address format'], 400);
}

// File upload handling
$file = $_FILES['pdf'];
$allowedMimeTypes = ['application/pdf'];
$maxBytes = config('app.max_upload_size', 5 * 1024 * 1024);
$uploadDir = rtrim(config('upload.directory', __DIR__ . '/uploads/receipts'), '/');

// Ensure upload directory exists
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    sendJsonResponse(['error' => 'Failed to create upload directory'], 500);
}

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'Partial upload',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    $msg = $errorMessages[$file['error']] ?? 'Unknown upload error';
    sendJsonResponse(['error' => 'File upload failed: ' . $msg], 400);
}

// Validate size
if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
    $maxMB = round($maxBytes / (1024 * 1024), 1);
    sendJsonResponse(['error' => "File size must be between 1 byte and {$maxMB} MB"], 400);
}

// Validate MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMimeTypes, true)) {
    sendJsonResponse(['error' => 'Only PDF files are allowed', 'detected' => $mime], 400);
}

// Generate secure filename
$filename = 'receipt_' . bin2hex(random_bytes(8)) . '.pdf';
$targetPath = $uploadDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendJsonResponse(['error' => 'Failed to save uploaded file'], 500);
}

// Set file permissions
chmod($targetPath, 0644);

// Send email
try {
    $mail = new PHPMailer(true);

    // SMTP configuration
    $mail->isSMTP();
    $mail->Host = config('smtp.host');
    $mail->SMTPAuth = true;
    $mail->Username = config('smtp.username');
    $mail->Password = config('smtp.password');
    $secure = strtolower(config('smtp.secure', 'tls'));
    $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) config('smtp.port');

    if (config('app.debug')) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str) { error_log("PHPMailer: $str"); };
    }

    // Sender and recipient
    $mail->setFrom(config('smtp.from_email'), config('smtp.from_name'));
    $mail->addAddress($email, $name);

    // CC recipients
    foreach (config('cc_recipients', []) as $cc) {
        if (!empty($cc['email'])) {
            $mail->addCC($cc['email'], $cc['name'] ?? '');
        }
    }

    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'Your Conference Registration Receipt';
    $mail->Body = sprintf(
        'Hello <b>%s</b>,<br><br>Thank you for registering for the conference.<br>Please find your payment receipt attached.<br><br>Best regards,<br>%s',
        $name,
        config('smtp.from_name')
    );

    // Attach PDF
    $mail->addAttachment($targetPath, 'conference_receipt.pdf');

    // Send
    $mail->send();

    // Clean up file
    unlink($targetPath);

    sendJsonResponse(['success' => true, 'message' => "Receipt sent successfully to $email"]);
} catch (Exception $e) {
    if (file_exists($targetPath)) unlink($targetPath);

    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    error_log("Error sending receipt: " . print_r($errorDetails, true));

    if (config('app.debug')) {
        sendJsonResponse(['error' => 'Failed to send email', 'details' => $errorDetails], 500);
    } else {
        sendJsonResponse(['error' => 'Failed to send email. Please try again later.'], 500);
    }
}
