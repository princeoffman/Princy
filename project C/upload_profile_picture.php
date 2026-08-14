<?php
include 'config.php';

require_request_method('POST');

session_start();

// For testing purposes, allow upload without session if user_id is provided in POST data
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
} elseif (isset($_POST['user_id'])) {
    $user_id = require_positive_int($_POST['user_id'], 'user ID');
} else {
    json_fail('Not logged in', 401);
}

if (!isset($_FILES['profile_picture'])) {
    // An oversized POST body is discarded by PHP before it reaches $_FILES, so
    // report that case explicitly instead of "no file uploaded".
    if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
        json_fail('Upload exceeded the server POST size limit', 413, 'post_max_size=' . ini_get('post_max_size'));
    }
    json_fail('No file uploaded', 400);
}

$file = $_FILES['profile_picture'];

// Report the actual upload failure reason instead of a single generic message.
if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => ['File too large (server limit)', 413],
        UPLOAD_ERR_FORM_SIZE => ['File too large (form limit)', 413],
        UPLOAD_ERR_PARTIAL => ['File was only partially uploaded', 400],
        UPLOAD_ERR_NO_FILE => ['No file uploaded', 400],
        UPLOAD_ERR_NO_TMP_DIR => ['Server is missing a temporary folder', 500],
        UPLOAD_ERR_CANT_WRITE => ['Server failed to write the file to disk', 500],
        UPLOAD_ERR_EXTENSION => ['Upload blocked by a server extension', 500],
    ];
    [$message, $status] = $upload_errors[$file['error']] ?? ['Upload failed', 400];
    json_fail($message, $status, 'UPLOAD_ERR code ' . $file['error']);
}

// Validate the real file type rather than the client-supplied one
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

$mime_type = null;
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detected = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($detected !== false) {
            $mime_type = $detected;
        }
    }
}
if ($mime_type === null) {
    error_log('upload_profile_picture.php: could not detect MIME type, falling back to client-reported type');
    $mime_type = $file['type'];
}

if (!isset($allowed_types[$mime_type])) {
    json_fail('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.', 415, 'detected ' . $mime_type);
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    json_fail('File too large. Maximum size is 5MB.', 413, 'size ' . $file['size']);
}

$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir) && !mkdir($uploads_dir, 0755, true) && !is_dir($uploads_dir)) {
    json_fail('Failed to save file', 500, 'could not create ' . $uploads_dir);
}

// Generate unique filename from the detected type, not the client filename
$new_filename = 'user_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_types[$mime_type];
$avatar_url = 'uploads/' . $new_filename;

if (!move_uploaded_file($file['tmp_name'], $uploads_dir . '/' . $new_filename)) {
    json_fail('Failed to save file', 500, 'move_uploaded_file failed for ' . $file['tmp_name']);
}

// Update database with new avatar URL. If this fails the stored file is removed
// so we do not leave an orphan upload behind a failed request.
try {
    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("si", $avatar_url, $user_id);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    unlink($uploads_dir . '/' . $new_filename);
    json_fail('Failed to update profile picture', 500, $e->getMessage());
}

if ($updated === 0) {
    unlink($uploads_dir . '/' . $new_filename);
    json_fail('User does not exist', 404, 'user_id ' . $user_id);
}

json_success([
    'message' => 'Profile picture updated successfully',
    'avatar_url' => $avatar_url
]);

$conn->close();
