<?php
require_once __DIR__ . '/api_helpers.php';

require_method('POST');

$data = read_request_data();
require_fields($data, ['sender_id', 'receiver_id', 'message']);

$sender_id = intval($data['sender_id']);
$receiver_id = intval($data['receiver_id']);
$message = trim($data['message']);

if (empty($message)) {
    json_fail('Message cannot be empty');
}

if ($sender_id <= 0 || $receiver_id <= 0) {
    json_fail('Invalid sender or receiver ID');
}

if ($sender_id === $receiver_id) {
    json_fail('Cannot send message to yourself');
}

require_user($conn, $sender_id, 'Sender does not exist');
require_user($conn, $receiver_id, 'Receiver does not exist');

$sent = execute_write(
    $conn,
    "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)",
    "iis",
    [$sender_id, $receiver_id, $message]
);

if ($sent) {
    json_success(['message' => 'Message sent successfully']);
} else {
    json_error('Failed to send message');
}

$conn->close();
?>
