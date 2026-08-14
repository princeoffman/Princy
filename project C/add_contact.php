<?php
require_once __DIR__ . '/api_helpers.php';

require_method('POST');

$data = read_request_data();
require_fields($data, ['user_id', 'contact_name']);

$user_id = $data['user_id'];
$contact_name = trim($data['contact_name']);
$contact_email = isset($data['contact_email']) ? trim($data['contact_email']) : null;
$contact_phone = isset($data['contact_phone']) ? trim($data['contact_phone']) : null;
$contact_notes = isset($data['contact_notes']) ? trim($data['contact_notes']) : null;

if (empty($contact_name)) {
    json_fail('Contact name cannot be empty');
}

require_user($conn, $user_id);

$inserted = execute_write(
    $conn,
    "INSERT INTO contacts (user_id, contact_name, contact_email, contact_phone, contact_notes) VALUES (?, ?, ?, ?, ?)",
    "issss",
    [$user_id, $contact_name, $contact_email, $contact_phone, $contact_notes]
);

if ($inserted) {
    json_success([
        'message' => 'Contact added successfully',
        'contact_id' => $conn->insert_id
    ]);
} else {
    json_error('Failed to add contact: ' . $conn->error);
}

$conn->close();
?>
