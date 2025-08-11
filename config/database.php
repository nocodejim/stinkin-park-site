<?php
declare(strict_types=1);

// Environment detection
$isProduction = $_SERVER['SERVER_NAME'] === 'buckeye90.com' 
                 || $_SERVER['SERVER_NAME'] === 'www.buckeye90.com';

// Database configuration
$dbConfig = [
    'host' => $isProduction ? 'localhost' : 'localhost',
    'database' => 'u937913451_stinkinpark',
    'username' => $isProduction ? 'u937913451_stinkin_user' : 'root',
    'password' => $isProduction ? 'suburbund3@thM@tch' : ''
];

// Initialize database
require_once __DIR__ . '/../includes/Database.php';
\StinkinPark\Database::init($dbConfig);

// Error reporting based on environment
if (!$isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ERROR | E_WARNING);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Set timezone
date_default_timezone_set('America/New_York');
