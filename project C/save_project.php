<?php
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['user_id']) || !isset($data['project_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $user_id = $data['user_id'];
    $project_id = $data['project_id'];

    // Check if already saved
    $check_sql = "SELECT id FROM saved_projects WHERE user_id = ? AND project_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $project_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Already saved, remove it (unsave)
        $delete_sql = "DELETE FROM saved_projects WHERE user_id = ? AND project_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $project_id);

        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project unsaved successfully', 'saved' => false]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unsave project']);
        }
        $delete_stmt->close();
    } else {
        // Not saved, add it
        $insert_sql = "INSERT INTO saved_projects (user_id, project_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $project_id);

        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project saved successfully', 'saved' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save project']);
        }
        $insert_stmt->close();
    }

    $check_stmt->close();
}

$conn->close();
?>
