<?php
include 'config.php';

require_request_method('GET');

if (!isset($_GET['user_id'])) {
    json_fail('Missing required parameter: user_id', 400);
}

$user_id = require_positive_int($_GET['user_id'], 'user ID');

// Get saved projects with project and author information
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

$stmt->close();

json_success(['projects' => $saved_projects]);

$conn->close();
