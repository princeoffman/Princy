<?php
// Database credentials come from the environment. Copy .env.example to .env and
// fill in real values; never commit credentials to the repository.
require_once __DIR__ . '/env.php';

load_env(__DIR__ . '/.env');

$servername = env('DB_HOST', 'localhost');
$username = env('DB_USER', '');
$password = env('DB_PASSWORD', '');
$dbname = env('DB_NAME', 'projectconnect');

if ($username === '') {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database is not configured']));
}

// Only allow browsers from trusted origins to read API responses. Credentialed
// requests cannot use a wildcard origin, so the origin is echoed when allowed.
$allowedOrigins = array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS', 'http://localhost'))));
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Set charset to utf8
$conn->set_charset("utf8mb4");
