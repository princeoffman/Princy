<?php
include 'config.php';

require_request_method('POST');

$data = read_json_body();
require_fields($data, ['user_id', 'contact_name']);

$user_id = require_positive_int($data['user_id'], 'user ID');
$contact_name = trim($data['contact_name']);
$contact_email = isset($data['contact_email']) ? trim($data['contact_email']) : null;
$contact_phone = isset($data['contact_phone']) ? trim($data['contact_phone']) : null;
$contact_notes = isset($data['contact_notes']) ? trim($data['contact_notes']) : null;

if ($contact_email !== null && $contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    json_fail('Invalid contact email address', 400);
}

// Verify user exists
$check_user_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user_stmt->bind_param("i", $user_id);
$check_user_stmt->execute();
$user_exists = $check_user_stmt->get_result()->num_rows > 0;
$check_user_stmt->close();

if (!$user_exists) {
    json_fail('User does not exist', 404, 'user_id ' . $user_id);
}

// Insert new contact
$insert_sql = "INSERT INTO contacts (user_id, contact_name, contact_email, contact_phone, contact_notes) VALUES (?, ?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("issss", $user_id, $contact_name, $contact_email, $contact_phone, $contact_notes);
$insert_stmt->execute();
$contact_id = $conn->insert_id;
$insert_stmt->close();

json_success([
    'message' => 'Contact added successfully',
    'contact_id' => $contact_id
], 201);

$conn->close();
