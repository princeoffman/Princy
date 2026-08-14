<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('GET');

// Saved projects are private to the logged-in user.
$user_id = require_login();

$sql = "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar, sp.created_at as saved_at
        FROM saved_projects sp
        JOIN projects p ON sp.project_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE sp.user_id = ?
        ORDER BY sp.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$saved_projects = array();
while ($row = $result->fetch_assoc()) {
    $saved_projects[] = $row;
}

echo json_encode(['success' => true, 'projects' => $saved_projects]);

$stmt->close();
$conn->close();
