<?php
include 'config.php';
require_once __DIR__ . '/lib/presenters.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all users except the current user (if logged in)
    $current_user_id = isset($_GET['current_user_id']) ? intval($_GET['current_user_id']) : null;

    $sql = "SELECT id, username, full_name, avatar, online_status FROM users";
    $params = [];
    $types = "";

    if ($current_user_id) {
        $sql .= " WHERE id != ?";
        $params[] = $current_user_id;
        $types = "i";
    }

    $sql .= " ORDER BY full_name ASC";

    $stmt = $conn->prepare($sql);
    if ($current_user_id) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = format_user_row($row);
    }

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
