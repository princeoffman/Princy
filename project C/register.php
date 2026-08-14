<?php
require_once __DIR__ . '/api_helpers.php';

require_method('POST');

$data = read_request_data();
require_fields($data, ['username', 'email', 'password', 'full_name']);

$username = $data['username'];
$email = $data['email'];
$password = password_hash($data['password'], PASSWORD_DEFAULT);
$full_name = $data['full_name'];

// Check if user already exists
if (row_exists($conn, "SELECT id FROM users WHERE username = ? OR email = ?", "ss", [$username, $email])) {
    json_fail('Username or email already exists');
}

// Insert new user
$inserted = execute_write(
    $conn,
    "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)",
    "ssss",
    [$username, $email, $password, $full_name]
);

if ($inserted) {
    json_success(['message' => 'Registration successful']);
} else {
    json_error('Registration failed');
}

$conn->close();
?>
