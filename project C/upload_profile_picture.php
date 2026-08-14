<?php
require_once __DIR__ . '/api_helpers.php';

// Check if user is logged in (you might want to implement proper session management)
session_start();

// For testing purposes, allow upload without session if user_id is provided in POST data
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
} else {
    json_fail('Not logged in');
}

require_method('POST');

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    json_fail('No file uploaded or upload error');
}

$file = $_FILES['profile_picture'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    json_fail('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $max_size) {
    json_fail('File too large. Maximum size is 5MB.');
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
$avatar_url = 'uploads/' . $new_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $avatar_url)) {
    json_fail('Failed to save file');
}

// Update database with new avatar URL
if (execute_write($conn, "UPDATE users SET avatar = ? WHERE id = ?", "si", [$avatar_url, $user_id])) {
    json_success([
        'message' => 'Profile picture updated successfully',
        'avatar_url' => $avatar_url
    ]);
} else {
    json_error('Failed to update database');
}

$conn->close();
?>
