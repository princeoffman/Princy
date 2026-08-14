<?php
include 'config.php';

require_request_method('POST');

// Accept both JSON and form-encoded bodies.
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$data = strpos($content_type, 'application/json') !== false ? read_json_body() : $_POST;

require_fields($data, ['sender_id', 'receiver_id', 'message']);

$sender_id = require_positive_int($data['sender_id'], 'sender ID');
$receiver_id = require_positive_int($data['receiver_id'], 'receiver ID');
$message = trim($data['message']);

if ($message === '') {
    json_fail('Message cannot be empty', 400);
}

if ($sender_id === $receiver_id) {
    json_fail('Cannot send message to yourself', 400);
}

// Verify both participants exist
$check_users = $conn->prepare("SELECT id FROM users WHERE id IN (?, ?)");
$check_users->bind_param("ii", $sender_id, $receiver_id);
$check_users->execute();
$existing_ids = [];
$check_result = $check_users->get_result();
while ($row = $check_result->fetch_assoc()) {
    $existing_ids[] = (int)$row['id'];
}
$check_users->close();

if (!in_array($sender_id, $existing_ids, true)) {
    json_fail('Sender does not exist', 404, 'sender_id ' . $sender_id);
}

if (!in_array($receiver_id, $existing_ids, true)) {
    json_fail('Receiver does not exist', 404, 'receiver_id ' . $receiver_id);
}

// Insert message into database
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);
$stmt->execute();
$message_id = $conn->insert_id;
$stmt->close();

json_success(['message' => 'Message sent successfully', 'message_id' => $message_id], 201);

$conn->close();
