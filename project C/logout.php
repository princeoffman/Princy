<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

$user_id = current_user_id();

if ($user_id !== null) {
    $stmt = $conn->prepare("UPDATE users SET online_status = FALSE WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

start_session();
$_SESSION = [];
session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out']);

$conn->close();
