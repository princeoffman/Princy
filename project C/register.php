<?php
include 'config.php';
require_once __DIR__ . '/lib/validation.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decoded = decode_json_body(file_get_contents('php://input'));

    if (!$decoded['ok']) {
        echo json_encode(['success' => false, 'message' => $decoded['message']]);
        exit;
    }

    $validated = validate_register_input($decoded['data']);

    if (!$validated['ok']) {
        echo json_encode(['success' => false, 'message' => $validated['message']]);
        exit;
    }

    $username = $validated['username'];
    $email = $validated['email'];
    $password = password_hash($validated['password'], PASSWORD_DEFAULT);
    $full_name = $validated['full_name'];

    // Check if user already exists
    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit;
    }

    // Insert new user
    $sql = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $email, $password, $full_name);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }

    $stmt->close();
}
$conn->close();
?>
