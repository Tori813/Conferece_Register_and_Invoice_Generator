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
    if ($registration['email'] === $email) {
        $registration['payment_status'] = $status;
        $updated = true;
        break;
    } elseif ($registration['type'] === 'multiple' && $registration['primary']['email'] === $email) {
        $registration['primary']['payment_status'] = $status;
        $updated = true;
        break;
    } elseif ($registration['type'] === 'multiple' && !empty($registration['additional'])) {
        foreach ($registration['additional'] as &$additional) {
            if ($additional['email'] === $email) {
                $additional['payment_status'] = $status;
                $updated = true;
                break 2;
            }
        }
    }
}

if ($updated) {
    // Save the updated registrations
    file_put_contents($registrationsFile, json_encode($registrations, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Registration not found']);
}
