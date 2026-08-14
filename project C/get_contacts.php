<?php
require_once __DIR__ . '/api_helpers.php';

$user_id = require_positive_int($_GET, 'user_id', 'Invalid user ID');

$contacts = fetch_rows(
    $conn,
    "SELECT id, contact_name, contact_email, contact_phone, contact_notes, created_at
     FROM contacts
     WHERE user_id = ?
     ORDER BY created_at DESC",
    "i",
    [$user_id]
);

json_success(['contacts' => $contacts]);

$conn->close();
?>
