<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('GET');

// The contact owner is taken from the session so one user cannot read another's contacts.
$user_id = require_login();

$sql = "SELECT id, contact_name, contact_email, contact_phone, contact_notes, created_at
        FROM contacts
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$contacts = array();
while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
}

echo json_encode(['success' => true, 'contacts' => $contacts]);

$stmt->close();
$conn->close();
