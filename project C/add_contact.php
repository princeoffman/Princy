<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

$user_id = require_login();

$data = json_body();

$contact_name = isset($data['contact_name']) ? trim((string)$data['contact_name']) : '';
$contact_email = isset($data['contact_email']) ? trim((string)$data['contact_email']) : null;
$contact_phone = isset($data['contact_phone']) ? trim((string)$data['contact_phone']) : null;
$contact_notes = isset($data['contact_notes']) ? trim((string)$data['contact_notes']) : null;

if ($contact_name === '' || mb_strlen($contact_name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Contact name must be 1-100 characters']);
    exit;
}

if ($contact_email !== null && $contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid contact email']);
    exit;
}

if ($contact_phone !== null && $contact_phone !== '' && !preg_match('/^[0-9 +()-]{5,20}$/', $contact_phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid contact phone']);
    exit;
}

if ($contact_notes !== null && mb_strlen($contact_notes) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Contact notes are too long']);
    exit;
}

// Insert new contact
$insert_sql = "INSERT INTO contacts (user_id, contact_name, contact_email, contact_phone, contact_notes) VALUES (?, ?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("issss", $user_id, $contact_name, $contact_email, $contact_phone, $contact_notes);

if ($insert_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Contact added successfully',
        'contact_id' => $conn->insert_id
    ]);
} else {
    error_log('Failed to add contact: ' . $insert_stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to add contact']);
}

$insert_stmt->close();
$conn->close();
