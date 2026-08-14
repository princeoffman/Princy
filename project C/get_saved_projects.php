<?php
require_once __DIR__ . '/api_helpers.php';

$user_id = require_positive_int($_GET, 'user_id', 'Invalid user ID');

// Get saved projects with project and author information
$saved_projects = fetch_rows(
    $conn,
    "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar, sp.created_at as saved_at
     FROM saved_projects sp
     JOIN projects p ON sp.project_id = p.id
     JOIN users u ON p.user_id = u.id
     WHERE sp.user_id = ?
     ORDER BY sp.created_at DESC",
    "i",
    [$user_id]
);

json_success(['projects' => $saved_projects]);

$conn->close();
?>
