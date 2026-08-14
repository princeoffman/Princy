<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/api_response.php';

// Database configuration
$servername = "localhost";
$username = "root";       // Default XAMPP username
$password = "";           // Default XAMPP password (empty)
$dbname = "projectconnect";

// Make mysqli raise exceptions instead of returning false, so a failing
// prepare()/execute()/query() can no longer be ignored by callers.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create connection
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // The driver message can expose credentials/host details, so it is logged
    // instead of returned, and the client gets a parseable JSON error.
    json_fail('Database unavailable', 503, 'Connection failed: ' . $e->getMessage());
}
