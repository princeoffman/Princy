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

// Check if already saved
$check_stmt = $conn->prepare("SELECT id FROM saved_projects WHERE user_id = ? AND project_id = ?");
$check_stmt->bind_param("ii", $user_id, $project_id);
$check_stmt->execute();
$already_saved = $check_stmt->get_result()->num_rows > 0;
$check_stmt->close();

if ($already_saved) {
    $delete_stmt = $conn->prepare("DELETE FROM saved_projects WHERE user_id = ? AND project_id = ?");
    $delete_stmt->bind_param("ii", $user_id, $project_id);

    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Project unsaved successfully', 'saved' => false]);
    } else {
        error_log('Failed to unsave project: ' . $delete_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to unsave project']);
    }
    $delete_stmt->close();
} else {
    $insert_stmt = $conn->prepare("INSERT INTO saved_projects (user_id, project_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $project_id);

    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Project saved successfully', 'saved' => true]);
    } else {
        error_log('Failed to save project: ' . $insert_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to save project']);
    }
    $insert_stmt->close();
}

$conn->close();
