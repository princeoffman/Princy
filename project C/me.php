<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('GET');

$user_id = require_login();

$stmt = $conn->prepare("SELECT id, username, full_name, avatar, bio, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    start_session();
    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    $conn->close();
    exit;
}

echo json_encode(['success' => true, 'user' => $user]);

$conn->close();
