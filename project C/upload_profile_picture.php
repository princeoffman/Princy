<?php
include 'config.php';

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
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['profile_picture'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
        exit;
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
        exit;
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
    $upload_path = 'uploads/' . $new_filename;

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
