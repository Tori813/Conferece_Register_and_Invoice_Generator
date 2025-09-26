<?php
// Debug mode - show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
header('Content-Type: text/plain'); // force plain text for debugging

// Debug output - show POST and FILES data
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "=== DEBUG OUTPUT ===\n\n";
    echo "POST data:\n";
    var_dump($_POST);
    echo "\nFILES data:\n";
    var_dump($_FILES);
    
    // Uncomment the line below to stop execution here and see the debug output
    // exit;
}

echo "\n\n=== NORMAL OUTPUT ===\n\n";

// Ensure no output before headers
if (ob_get_level()) ob_end_clean();

// Enable CORS and set JSON headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

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

// Set error handler to catch all errors and exceptions
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno] $errstr in $errfile on line $errline");
    sendJsonResponse(['error' => 'An internal server error occurred'], 500);
});

set_exception_handler(function($e) {
    error_log("PHP Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendJsonResponse(['error' => 'An unexpected error occurred'], 500);
});

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Try to load environment variables if Dotenv is available
$envFile = __DIR__ . '/.env';
$envVars = [];

// Check if .env file exists
if (file_exists($envFile)) {
    // Parse .env file manually as a fallback
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse name=value pairs
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $envVars[$name] = $value;
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Verify required environment variables are set
$requiredEnvVars = [
    'SMTP_HOST', 
    'SMTP_PORT', 
    'SMTP_USERNAME', 
    'SMTP_PASSWORD', 
    'SMTP_FROM_EMAIL', 
    'SMTP_FROM_NAME'
];

$missingVars = [];
foreach ($requiredEnvVars as $var) {
    $value = getenv($var);
    if ($value === false || $value === '') {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    sendJsonResponse([
        'error' => 'Missing required environment variables',
        'missing' => $missingVars,
        'available_vars' => array_keys($envVars)
    ], 500);
}

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

    $code = $_FILES['pdf']['error'];
    $msg = $errorMessages[$code] ?? "Unknown upload error (code $code)";

    sendJsonResponse([
        'error' => 'File upload failed',
        'code' => $code,
        'message' => $msg,
        'debug' => $_FILES,
        'php_ini' => [
            'file_uploads' => ini_get('file_uploads'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_tmp_dir' => ini_get('upload_tmp_dir')
        ]
    ], 400);
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        sendJsonResponse(['error' => 'Failed to create uploads directory'], 500);
    }
}

// Validate and move uploaded file
$fileName = basename($_FILES['pdf']['name']);
$targetPath = $uploadDir . '/' . $fileName;

if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
    sendJsonResponse(['error' => 'Failed to save uploaded file'], 500);
}

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

// Load environment variables from .env if present
try {
    // Check if .env file exists
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        throw new Exception('Configuration file (.env) not found. Please create one based on .env.example');
    }
    
    // Load .env file
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    // Load environment variables
    $dotenv->required([
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
        'SMTP_FROM_EMAIL',
        'SMTP_FROM_NAME'
    ]);
    
    // Validate required environment variables
    $requiredEnvVars = [
        'SMTP_HOST' => 'SMTP server host',
        'SMTP_PORT' => 'SMTP server port',
        'SMTP_USERNAME' => 'SMTP username',
        'SMTP_PASSWORD' => 'SMTP password',
        'SMTP_FROM_EMAIL' => 'Sender email address',
        'SMTP_FROM_NAME' => 'Sender name'
    ];
    
    $missingVars = [];
    $invalidVars = [];
    
    foreach ($requiredEnvVars as $var => $description) {
        $value = $_ENV[$var] ?? getenv($var);
        if ($value === false || $value === '') {
            $missingVars[] = "$var ($description)";
        } elseif ($var === 'SMTP_PORT' && (!is_numeric($value) || $value <= 0 || $value > 65535)) {
            $invalidVars[] = "$var: Port must be between 1 and 65535";
        } elseif ($var === 'SMTP_FROM_EMAIL' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $invalidVars[] = "$var: Invalid email format";
        }
    }
    
    $errors = [];
    if (!empty($missingVars)) {
        $errors[] = 'Missing required environment variables: ' . implode(', ', $missingVars);
    }
    if (!empty($invalidVars)) {
        $errors[] = 'Invalid environment variables: ' . implode('; ', $invalidVars);
    }
    
    if (!empty($errors)) {
        throw new Exception(implode('; ', $errors));
    }
    
} catch (Exception $e) {
    error_log('Configuration error: ' . $e->getMessage());
    sendJsonResponse([
        'error' => 'Server configuration error',
        'details' => $e->getMessage()
    ], 500);
}

// File size validation
$maxBytes = (int)(getenv('MAX_UPLOAD_BYTES') ?: 5 * 1024 * 1024); // default 5MB
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
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'];
    $mail->Password = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = strtolower($_ENV['SMTP_SECURE'] ?? 'tls');
    $mail->Port = (int)$_ENV['SMTP_PORT'];
    
    // Enable debug output
    $mail->SMTPDebug = 2; // 2 = client and server messages
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer: $str");
    };
    
    // Sender and recipient
    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress($_POST['email'], $_POST['name'] ?? '');
    
    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'Your Conference Registration Invoice';
    $mail->Body = sprintf(
        'Hello <b>%s</b>,<br><br>Thank you for registering for the conference.<br>Your invoice is attached.<br><br>Best regards,<br>%s',
        htmlspecialchars($_POST['name'] ?? 'there', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($_ENV['SMTP_FROM_NAME'], ENT_QUOTES, 'UTF-8')
    );
    
    // Attach the PDF
    $mail->addAttachment($targetPath, 'invoice.pdf');
    
    $mail->SMTPDebug = 3; // or 4 for even more detail
$mail->Debugoutput = function($str, $level) {
    error_log("SMTP Debug: $str");
};
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
