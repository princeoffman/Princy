<?php
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['user_id']) || !isset($data['title']) || !isset($data['description'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $user_id = $data['user_id'];
    $title = trim($data['title']);
    $description = trim($data['description']);
    $image = isset($data['image']) ? trim($data['image']) : 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';

    if (empty($title) || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Title and description cannot be empty']);
        exit;
    }

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
