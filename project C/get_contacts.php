<?php
include 'config.php';

require_request_method('GET');

if (!isset($_GET['user_id'])) {
    json_fail('Missing required parameter: user_id', 400);
}

$user_id = require_positive_int($_GET['user_id'], 'user ID');

// Get contacts for the user
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

$stmt->close();

json_success(['contacts' => $contacts]);

$conn->close();
