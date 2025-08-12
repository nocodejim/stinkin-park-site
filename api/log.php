<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Logger;

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    $logger = Logger::getInstance();
    
    // Log the frontend event
    $level = strtoupper($data['level'] ?? 'INFO');
    $message = $data['message'] ?? 'Frontend log';
    $context = [
        'frontend' => true,
        'page' => $data['page'] ?? 'unknown',
        'station' => $data['station'] ?? null,
        'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
        'data' => $data['data'] ?? null
    ];
    
    $logger->log($level, "[FRONTEND] " . $message, $context, 'FRONTEND');
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}