<?php
header('Content-Type: application/json');

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['email']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$email = $data['email'];
$status = $data['status'];
$registrationsFile = __DIR__ . '/registrations.json';

// Read current registrations
$registrations = [];
if (file_exists($registrationsFile)) {
    $registrations = json_decode(file_get_contents($registrationsFile), true) ?? [];
}

// Find and update the registration
$updated = false;
foreach ($registrations as &$registration) {
    // Handle single registration
    if ($registration['type'] === 'single' && $registration['email'] === $email) {
        $registration['payment_status'] = $status;
        $updated = true;
        break;
    } 
    // Handle multiple registrations
    elseif ($registration['type'] === 'multiple') {
        // Check primary registrant
        if (isset($registration['primary']) && $registration['primary']['email'] === $email) {
            $registration['primary']['payment_status'] = $status;
            $updated = true;
            break;
        }
        // Check additional registrants
        if (!empty($registration['additional'])) {
            foreach ($registration['additional'] as &$additional) {
                if ($additional['email'] === $email) {
                    $additional['payment_status'] = $status;
                    $updated = true;
                    break 2;
                }
            }
        }
    }
}

if ($updated) {
    // Save the updated registrations with file locking to prevent corruption
    $tempFile = tempnam(dirname($registrationsFile), 'reg');
    if (file_put_contents($tempFile, json_encode($registrations, JSON_PRETTY_PRINT)) !== false) {
        // Use rename for atomic update
        if (rename($tempFile, $registrationsFile)) {
            chmod($registrationsFile, 0666); // Ensure proper permissions
        } else {
            unlink($tempFile); // Clean up temp file if rename fails
            throw new Exception('Failed to update registrations file');
        }
    } else {
        unlink($tempFile); // Clean up temp file if write fails
        throw new Exception('Failed to write to temporary file');
    }
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Registration not found']);
}
