<?php
include 'config.php';
require_once __DIR__ . '/lib/validation.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $input = validate_project_input($data);

    if (!$input['ok']) {
        echo json_encode(['success' => false, 'message' => $input['message']]);
        exit;
    }

    $user_id = $input['user_id'];
    $title = $input['project']['title'];
    $description = $input['project']['description'];
    $image = $input['project']['image'];

    // Verify user exists
    $check_user_sql = "SELECT id FROM users WHERE id = ?";
    $check_user_stmt = $conn->prepare($check_user_sql);
    $check_user_stmt->bind_param("i", $user_id);
    $check_user_stmt->execute();
    $check_user_result = $check_user_stmt->get_result();

    if ($check_user_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User does not exist']);
        exit;
    }
    $check_user_stmt->close();

    // Insert new project
    $insert_sql = "INSERT INTO projects (user_id, title, description, image) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("isss", $user_id, $title, $description, $image);

    if ($insert_stmt->execute()) {
        $project_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Project added successfully',
            'project_id' => $project_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add project: ' . $insert_stmt->error]);
    }

    $insert_stmt->close();
}

$conn->close();
?>
