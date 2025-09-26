<?php
// Load configuration and bootstrap
require_once __DIR__ . '/bootstrap.php';

// Set headers for API response
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Debug output - show POST and FILES data (only in development)
if (config('app.debug') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== DEBUG OUTPUT ===\n");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
}

// Set error handler to catch all errors and exceptions
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno] $errstr in $errfile on line $errline");
    if (config('app.debug')) {
        sendJsonResponse([
            'error' => 'An internal server error occurred',
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ], 500);
    } else {
        sendJsonResponse(['error' => 'An internal server error occurred'], 500);
    }
});

set_exception_handler(function($e) {
    error_log("PHP Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendJsonResponse(['error' => 'An unexpected error occurred'], 500);
});
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuration is now handled by bootstrap.php

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Invalid request method. Use POST.'], 405);
}

// Debug: Log raw request data
error_log('Request Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);

// Debug: Log all headers
$headers = [];
foreach ($_SERVER as $name => $value) {
    if (substr($name, 0, 5) === 'HTTP_') {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
        $headers[$name] = $value;
    }
}
error_log('Request Headers: ' . json_encode($headers));

// Debug: Log POST data
error_log('POST Data: ' . json_encode($_POST));

// Debug: Log FILES data
error_log('FILES Data: ' . json_encode($_FILES));

// Check if file was uploaded
if (empty($_FILES['pdf'])) {
    sendJsonResponse([
        'error' => 'No file received in the request',
        'debug' => $_FILES
    ], 400);
}

// Check for upload errors
if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload'
    ];

    $errorMessage = $errorMessages[$_FILES['pdf']['error']] ?? 'Unknown file upload error';
    sendJsonResponse(['error' => 'File upload failed: ' . $errorMessage], 400);
}

// Get upload configuration
$maxBytes = config('upload.max_size', config('app.max_upload_size', 5 * 1024 * 1024));
$allowedTypes = config('upload.allowed_types', ['pdf']);
$uploadDir = rtrim(config('upload.directory', 'uploads'), '/');

// Ensure the upload directory exists
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    sendJsonResponse(['error' => 'Failed to create upload directory'], 500);
}

// Verify the upload directory is writable
if (!is_writable($uploadDir)) {
    sendJsonResponse(['error' => 'Upload directory is not writable'], 500);
}

// File size validation
$size = (int)($_FILES['pdf']['size'] ?? 0);
if ($size <= 0 || $size > $maxBytes) {
    $maxSizeMB = round($maxBytes / (1024 * 1024), 1);
    sendJsonResponse([
        'error' => sprintf('File size must be between 1 byte and %s MB', $maxSizeMB)
    ], 400);
}

// File type validation
$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($_FILES['pdf']['tmp_name']);

// Map MIME types to file extensions
$mimeToExt = [
    'application/pdf' => 'pdf',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
];

// Get file extension from MIME type
$fileExt = $mimeToExt[$mimeType] ?? null;

// Check if the file type is allowed
if (!$fileExt || !in_array($fileExt, $allowedTypes, true)) {
    sendJsonResponse([
        'error' => sprintf('Invalid file type. Allowed types: %s', implode(', ', $allowedTypes))
    ], 400);
}

// Generate a secure filename
$filename = sprintf(
    '%s_%s.%s',
    'invoice',
    bin2hex(random_bytes(8)),
    $fileExt
);

$targetPath = $uploadDir . '/' . $filename;

// Move the uploaded file to the target location
if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
    sendJsonResponse(['error' => 'Failed to save uploaded file'], 500);
}

// Set proper permissions on the uploaded file
chmod($targetPath, 0644);

// Check for required fields
$required = ['email', 'pdf'];
$missing = [];
foreach ($required as $field) {
    if (empty($_POST[$field]) && empty($_FILES[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
}

// Sanitize and validate inputs
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : 'User';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(['error' => 'Invalid email address format'], 400);
}

// File size validation
$maxBytes = config('app.max_upload_size', 5 * 1024 * 1024); // default 5MB
$size = (int)($_FILES['pdf']['size'] ?? 0);

if ($size <= 0 || $size > $maxBytes) {
    $maxSizeMB = round($maxBytes / (1024 * 1024), 1);
    sendJsonResponse([
        'error' => sprintf('File size must be between 1 byte and %s MB', $maxSizeMB)
    ], 400);
}

// File type validation (use $_FILES['pdf']['type'] as fallback if tmp file is missing)
$allowedMimeTypes = ['application/pdf'];

$mime = null;
if (is_uploaded_file($_FILES['pdf']['tmp_name'])) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['pdf']['tmp_name']);
}

if (!$mime) {
    // fallback to browser-provided type if finfo fails
    $mime = $_FILES['pdf']['type'] ?? '';
}

if (!in_array($mime, $allowedMimeTypes, true)) {
    sendJsonResponse([
        'error' => 'Only PDF files are allowed',
        'detected' => $mime
    ], 400);
}

// Additional security check
$extension = pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION);
if (strtolower($extension) !== 'pdf') {
    sendJsonResponse(['error' => 'Invalid file extension. Only .pdf files are allowed'], 400);
}

try {
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    // Configure SMTP
    $mail->isSMTP();
    $mail->Host = config('smtp.host');
    $mail->SMTPAuth = true;
    $mail->Username = config('smtp.username');
    $mail->Password = config('smtp.password');
    $mail->SMTPSecure = strtolower(config('smtp.secure', 'tls'));
    $mail->Port = (int)config('smtp.port');
    
    // Enable debug output if debug mode is on
    if (config('app.debug')) {
        $mail->SMTPDebug = 2;  // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer: $str");
        };
    }
    
    // Sender
    $mail->setFrom(
        config('smtp.from_email'),
        config('smtp.from_name')
    );
    
    // Recipient
    $mail->addAddress($email, $name);
    
    // Add CC recipients from config
    $ccRecipients = config('cc_recipients', []);
    foreach ($ccRecipients as $recipient) {
        if (is_array($recipient) && !empty($recipient['email'])) {
            $mail->addCC(
                $recipient['email'],
                $recipient['name'] ?? ''
            );
        }
    }
    
    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'Your Conference Registration Invoice';
    $mail->Body = sprintf(
        'Hello <b>%s</b>,<br><br>Thank you for registering for the conference.<br>Your invoice is attached.<br><br>Best regards,<br>%s',
        htmlspecialchars($name ?? 'there', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(config('smtp.from_name'), ENT_QUOTES, 'UTF-8')
    );
    
    // Attach the PDF
    $mail->addAttachment($targetPath, 'invoice.pdf');
    // Send the email
    if ($mail->send()) {
        // Clean up the uploaded file after sending
        unlink($targetPath);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Invoice sent successfully to ' . $_POST['email']
        ]);
    } else {
        throw new Exception('Failed to send email: ' . $mail->ErrorInfo);
    }
} catch (Exception $e) {
    // Clean up the uploaded file if it exists
    if (isset($targetPath) && file_exists($targetPath)) {
        unlink($targetPath);
    }
    
    error_log('Mailer Error: ' . $e->getMessage());
    sendJsonResponse([
        'error' => 'Failed to send email: ' . $e->getMessage(),
        'details' => $e->getMessage()
    ], 500);
}
