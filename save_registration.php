<?php
// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// File where registrations will be stored
$file = __DIR__ . DIRECTORY_SEPARATOR . "registrations.json";

// Get POST data
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "No data received"
    ]);
    exit;
}

// Load existing registrations
if (file_exists($file)) {
    $registrations = json_decode(file_get_contents($file), true);
    if (!is_array($registrations)) {
        $registrations = [];
    }
} else {
    $registrations = [];
}

// Normalize helper: get emails from any registration record
function collect_emails_from_record($rec) {
    $out = [];
    if (!is_array($rec)) return $out;
    if (isset($rec['type']) && $rec['type'] === 'single') {
        if (!empty($rec['email'])) $out[] = strtolower(trim($rec['email']));
    } elseif (isset($rec['type']) && $rec['type'] === 'multiple') {
        if (!empty($rec['primary']['email'])) $out[] = strtolower(trim($rec['primary']['email']));
        if (!empty($rec['additional']) && is_array($rec['additional'])) {
            foreach ($rec['additional'] as $a) {
                if (!empty($a['email'])) $out[] = strtolower(trim($a['email']));
            }
        }
    }
    return $out;
}

// Build a set of existing emails
$existingEmails = [];
foreach ($registrations as $rec) {
    foreach (collect_emails_from_record($rec) as $em) {
        $existingEmails[$em] = true;
    }
}

// Collect submitted emails and normalize
$submittedEmails = collect_emails_from_record($data);

// Guard: require at least one email
if (empty($submittedEmails)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "No email provided in submission."
    ]);
    exit;
}

// Check duplicates within the same submission
if (count($submittedEmails) !== count(array_unique($submittedEmails))) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Duplicate emails found within this submission. Each participant must have a unique email."
    ]);
    exit;
}

// Check if any submitted email already exists
$already = [];
foreach ($submittedEmails as $em) {
    if (isset($existingEmails[$em])) $already[] = $em;
}
if (!empty($already)) {
    http_response_code(409); // Conflict
    echo json_encode([
        "success" => false,
        "error" => "The following email(s) are already registered: " . implode(", ", $already)
    ]);
    exit;
}

// Add timestamp
$data["created_at"] = date("Y-m-d H:i:s");

// Append new registration
$registrations[] = $data;

// Save back to file
if (file_put_contents($file, json_encode($registrations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    http_response_code(201); // Created
    echo json_encode([
        "success" => true,
        "message" => "Registration saved successfully!"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to save registration. Please try again."
    ]);
}
?>
