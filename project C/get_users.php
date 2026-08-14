<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('GET');

// The user directory is only exposed to logged-in users.
$current_user_id = require_login();

$sql = "SELECT id, username, full_name, avatar, online_status FROM users WHERE id != ? ORDER BY full_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'name' => $row['full_name'],
        'avatar' => $row['avatar'],
        'status' => $row['online_status'] ? 'online' : 'offline'
    ];
}

echo json_encode(['success' => true, 'users' => $users]);

$stmt->close();
$conn->close();
