<?php
// Simple test endpoint - NO moodle required
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

echo json_encode([
    'success' => true,
    'message' => 'AJAX works!',
    'received' => $input,
    'time' => date('Y-m-d H:i:s')
]);
