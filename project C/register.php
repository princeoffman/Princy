<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

$data = json_body();

$username = isset($data['username']) ? trim((string)$data['username']) : '';
$email = isset($data['email']) ? trim((string)$data['email']) : '';
$raw_password = isset($data['password']) ? (string)$data['password'] : '';
$full_name = isset($data['full_name']) ? trim((string)$data['full_name']) : '';

if ($username === '' || $email === '' || $raw_password === '' || $full_name === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 3-30 characters (letters, numbers, . _ -)']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

if (strlen($raw_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

if (mb_strlen($full_name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Full name is too long']);
    exit;
}

$password = password_hash($raw_password, PASSWORD_DEFAULT);

// Check if user already exists
$check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ss", $username, $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Insert new user
$sql = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $username, $email, $password, $full_name);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Registration successful']);
} else {
    error_log('Registration failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Registration failed']);
}

$stmt->close();
$conn->close();
