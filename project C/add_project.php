<?php
require_once __DIR__ . '/api_helpers.php';

require_method('POST');

$data = read_request_data();
require_fields($data, ['user_id', 'title', 'description']);

$user_id = $data['user_id'];
$title = trim($data['title']);
$description = trim($data['description']);
$image = isset($data['image']) ? trim($data['image']) : 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';

if (empty($title) || empty($description)) {
    json_fail('Title and description cannot be empty');
}

require_user($conn, $user_id);

$inserted = execute_write(
    $conn,
    "INSERT INTO projects (user_id, title, description, image) VALUES (?, ?, ?, ?)",
    "isss",
    [$user_id, $title, $description, $image]
);

if ($inserted) {
    json_success([
        'message' => 'Project added successfully',
        'project_id' => $conn->insert_id
    ]);
} else {
    json_error('Failed to add project: ' . $conn->error);
}

$conn->close();
?>
