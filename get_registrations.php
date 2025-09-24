<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$file = __DIR__ . DIRECTORY_SEPARATOR . 'registrations.json';

if (!file_exists($file)) {
    echo json_encode([]);
    exit;
}

$registrations = json_decode(file_get_contents($file), true);
if (!is_array($registrations)) {
    $registrations = [];
}

echo json_encode($registrations);
