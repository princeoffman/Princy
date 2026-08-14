<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

// The avatar owner comes from the session: a client can no longer overwrite
// another account's picture by posting a different user_id.
$user_id = require_login();

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['profile_picture'];

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// The client supplied MIME type and filename are untrusted: the real image type
// decides the extension, so a .php upload can never land in the web root.
$image_info = @getimagesize($file['tmp_name']);
$allowed_types = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

if ($image_info === false || !isset($allowed_types[$image_info[2]])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
    exit;
}

$extension = $allowed_types[$image_info[2]];
$new_filename = 'user_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$upload_path = 'uploads/' . $new_filename;

if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

@chmod(__DIR__ . '/' . $upload_path, 0644);

$stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmt->bind_param("si", $upload_path, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'avatar_url' => $upload_path
    ]);
} else {
    error_log('Failed to update avatar: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
}

$stmt->close();
$conn->close();
