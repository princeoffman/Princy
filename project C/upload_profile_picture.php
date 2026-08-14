<?php
include 'config.php';
require_once __DIR__ . '/lib/validation.php';

header('Content-Type: application/json');

// Check if user is logged in (you might want to implement proper session management)
session_start();

// For testing purposes, allow upload without session if user_id is provided in POST data
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
} else {
    $user_id = $_SESSION['user_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['profile_picture'] ?? null;
    $upload = validate_avatar_upload($file);

    if (!$upload['ok']) {
        echo json_encode(['success' => false, 'message' => $upload['message']]);
        exit;
    }

    $upload_path = 'uploads/' . avatar_filename($user_id, $file['name'], time());

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Update database with new avatar URL
        $avatar_url = $upload_path;
        $sql = "UPDATE users SET avatar = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $avatar_url, $user_id);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'avatar_url' => $avatar_url
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
