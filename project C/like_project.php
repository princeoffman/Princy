<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

$user_id = require_login();

$data = json_body();

$project_id = isset($data['project_id']) ? (int)$data['project_id'] : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit;
}

function like_count($conn, $project_id)
{
    $count_stmt = $conn->prepare("SELECT COUNT(*) as like_count FROM likes WHERE project_id = ?");
    $count_stmt->bind_param("i", $project_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['like_count'];
    $count_stmt->close();
    return $count;
}

$check_stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND project_id = ?");
$check_stmt->bind_param("ii", $user_id, $project_id);
$check_stmt->execute();
$already_liked = $check_stmt->get_result()->num_rows > 0;
$check_stmt->close();

if ($already_liked) {
    $delete_stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND project_id = ?");
    $delete_stmt->bind_param("ii", $user_id, $project_id);

    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Project unliked successfully',
            'liked' => false,
            'like_count' => like_count($conn, $project_id)
        ]);
    } else {
        error_log('Failed to unlike project: ' . $delete_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to unlike project']);
    }
    $delete_stmt->close();
} else {
    $insert_stmt = $conn->prepare("INSERT INTO likes (user_id, project_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $project_id);

    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Project liked successfully',
            'liked' => true,
            'like_count' => like_count($conn, $project_id)
        ]);
    } else {
        error_log('Failed to like project: ' . $insert_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to like project']);
    }
    $insert_stmt->close();
}

$conn->close();
