<?php
include 'config.php';
require_once __DIR__ . '/lib/validation.php';

header('Content-Type: application/json');

// Check if user_id is provided
$param = validate_id_param($_GET['user_id'] ?? null);

if (!$param['ok']) {
    echo json_encode(['success' => false, 'message' => $param['message']]);
    exit;
}

$user_id = $param['id'];

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
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $saved_projects[] = $row;
    }
}

echo json_encode(['success' => true, 'projects' => $saved_projects]);

$stmt->close();
$conn->close();
?>
