<?php
include 'config.php';

header('Content-Type: application/json');

// Check if user_id is provided
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

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
