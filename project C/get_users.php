<?php
include 'config.php';

require_request_method('GET');

// Get all users except the current user (if provided)
$current_user_id = isset($_GET['current_user_id']) ? require_positive_int($_GET['current_user_id'], 'current user ID') : null;

$sql = "SELECT id, username, full_name, avatar, online_status FROM users";
if ($current_user_id !== null) {
    $sql .= " WHERE id != ?";
}
$sql .= " ORDER BY full_name ASC";

$stmt = $conn->prepare($sql);
if ($current_user_id !== null) {
    $stmt->bind_param("i", $current_user_id);
}
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

$stmt->close();

json_success(['users' => $users]);

$conn->close();
