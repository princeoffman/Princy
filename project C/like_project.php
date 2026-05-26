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

    // Check if already liked
    $check_sql = "SELECT id FROM likes WHERE user_id = ? AND project_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $project_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Already liked, remove it (unlike)
        $delete_sql = "DELETE FROM likes WHERE user_id = ? AND project_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $project_id);

        if ($delete_stmt->execute()) {
            // Get updated like count
            $count_sql = "SELECT COUNT(*) as like_count FROM likes WHERE project_id = ?";
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param("i", $project_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'message' => 'Project unliked successfully',
                'liked' => false,
                'like_count' => $count_row['like_count']
            ]);
            $count_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unlike project']);
        }
        $delete_stmt->close();
    } else {
        // Not liked, add it
        $insert_sql = "INSERT INTO likes (user_id, project_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $project_id);

        if ($insert_stmt->execute()) {
            // Get updated like count
            $count_sql = "SELECT COUNT(*) as like_count FROM likes WHERE project_id = ?";
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param("i", $project_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'message' => 'Project liked successfully',
                'liked' => true,
                'like_count' => $count_row['like_count']
            ]);
            $count_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to like project']);
        }
        $insert_stmt->close();
    }

    $check_stmt->close();
}

$conn->close();
?>
