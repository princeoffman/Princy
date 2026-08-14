<?php
include 'config.php';

require_request_method('POST');

$data = read_json_body();
require_fields($data, ['user_id', 'title', 'description']);

$user_id = require_positive_int($data['user_id'], 'user ID');
$title = trim($data['title']);
$description = trim($data['description']);
$image = isset($data['image']) && trim($data['image']) !== ''
    ? trim($data['image'])
    : 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';

// Verify user exists
$check_user_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user_stmt->bind_param("i", $user_id);
$check_user_stmt->execute();
$user_exists = $check_user_stmt->get_result()->num_rows > 0;
$check_user_stmt->close();

if (!$user_exists) {
    json_fail('User does not exist', 404, 'user_id ' . $user_id);
}

// Insert new project
$insert_stmt = $conn->prepare("INSERT INTO projects (user_id, title, description, image) VALUES (?, ?, ?, ?)");
$insert_stmt->bind_param("isss", $user_id, $title, $description, $image);
$insert_stmt->execute();
$project_id = $conn->insert_id;
$insert_stmt->close();

json_success([
    'message' => 'Project added successfully',
    'project_id' => $project_id
], 201);

$conn->close();
