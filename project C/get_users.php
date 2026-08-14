<?php
require_once __DIR__ . '/api_helpers.php';

require_method('GET');

// Get all users except the current user (if provided)
$current_user_id = int_param($_GET, 'current_user_id');

$sql = "SELECT id, username, full_name, avatar, online_status FROM users";
$types = "";
$params = [];

if ($current_user_id > 0) {
    $sql .= " WHERE id != ?";
    $types = "i";
    $params[] = $current_user_id;
}

$sql .= " ORDER BY full_name ASC";

$users = [];
foreach (fetch_rows($conn, $sql, $types, $params) as $row) {
    $users[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'name' => $row['full_name'],
        'avatar' => $row['avatar'],
        'status' => $row['online_status'] ? 'online' : 'offline'
    ];
}

json_success(['users' => $users]);

$conn->close();
?>
