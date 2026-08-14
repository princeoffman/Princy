<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

// Projects are always created for the logged-in user.
$user_id = require_login();

$data = json_body();

$title = isset($data['title']) ? trim((string)$data['title']) : '';
$description = isset($data['description']) ? trim((string)$data['description']) : '';
$image = isset($data['image']) ? trim((string)$data['image']) : '';

$default_image = 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';

if ($title === '' || $description === '') {
    echo json_encode(['success' => false, 'message' => 'Title and description cannot be empty']);
    exit;
}

if (mb_strlen($title) > 255) {
    echo json_encode(['success' => false, 'message' => 'Title is too long']);
    exit;
}

if (mb_strlen($description) > 5000) {
    echo json_encode(['success' => false, 'message' => 'Description is too long']);
    exit;
}

// Only absolute http(s) URLs are accepted so the image cannot become a
// javascript: or data: payload when rendered.
if ($image === '') {
    $image = $default_image;
} else {
    $scheme = strtolower((string)parse_url($image, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || strlen($image) > 255) {
        echo json_encode(['success' => false, 'message' => 'Image must be an http(s) URL']);
        exit;
    }
}

$insert_stmt = $conn->prepare("INSERT INTO projects (user_id, title, description, image) VALUES (?, ?, ?, ?)");
$insert_stmt->bind_param("isss", $user_id, $title, $description, $image);

if ($insert_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Project added successfully',
        'project_id' => $conn->insert_id
    ]);
} else {
    error_log('Failed to add project: ' . $insert_stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to add project']);
}

$insert_stmt->close();
$conn->close();
