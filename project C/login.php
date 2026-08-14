<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

$data = json_body();

$username = isset($data['username']) ? trim((string)$data['username']) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing username or password']);
    exit;
}

$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->num_rows === 1 ? $result->fetch_assoc() : null;
$stmt->close();

// A single generic failure message avoids confirming which usernames exist.
if ($user === null || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    $conn->close();
    exit;
}

start_session();
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];

$update_stmt = $conn->prepare("UPDATE users SET online_status = TRUE WHERE id = ?");
$update_stmt->bind_param("i", $user['id']);
$update_stmt->execute();
$update_stmt->close();

echo json_encode([
    'success' => true,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'avatar' => $user['avatar'],
        'bio' => $user['bio']
    ]
]);

$conn->close();
