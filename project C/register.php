<?php
include 'config.php';

require_request_method('POST');

$data = read_json_body();
require_fields($data, ['username', 'email', 'password', 'full_name']);

$username = trim($data['username']);
$email = trim($data['email']);
$full_name = trim($data['full_name']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_fail('Invalid email address', 400);
}

$password = password_hash($data['password'], PASSWORD_DEFAULT);
if ($password === false) {
    json_fail('Could not process password', 500, 'password_hash returned false');
}

// Check if user already exists
$check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$check_stmt->bind_param("ss", $username, $email);
$check_stmt->execute();
$exists = $check_stmt->get_result()->num_rows > 0;
$check_stmt->close();

if ($exists) {
    json_fail('Username or email already exists', 409);
}

// Insert new user
try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $password, $full_name);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // 1062 is a duplicate key: the pre-check above can lose a race.
    if ($e->getCode() === 1062) {
        json_fail('Username or email already exists', 409, $e->getMessage());
    }
    throw $e;
}

json_success(['message' => 'Registration successful', 'user_id' => $user_id], 201);

$conn->close();
