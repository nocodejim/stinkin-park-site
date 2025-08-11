<?php
// includes/config.php
// Set environment and error reporting (Adheres to Development Standards)
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1'); // Set to '0' in production

// Database credentials (Placeholder values - MUST be updated)
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

// Define common paths
define('BASE_DIR', dirname(__DIR__));
define('AUDIO_DIR', BASE_DIR . '/audio/');
define('MEDIA_DIR', BASE_DIR . '/assets/media/');
