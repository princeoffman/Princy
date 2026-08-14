<?php
include 'config.php';

require_request_method('GET');

if (!isset($_GET['sender_id']) || !isset($_GET['receiver_id'])) {
    json_fail('Missing required parameters: sender_id, receiver_id', 400);
}

$sender_id = require_positive_int($_GET['sender_id'], 'sender ID');
$receiver_id = require_positive_int($_GET['receiver_id'], 'receiver ID');

// Get messages between the two users (both directions)
$sql = "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.sent_at,
               u.full_name as sender_name, u.avatar as sender_avatar
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'message' => $row['message'],
        'sent_at' => $row['sent_at'],
        'sender_name' => $row['sender_name'],
        'sender_avatar' => $row['sender_avatar'],
        'type' => ($row['sender_id'] == $sender_id) ? 'sent' : 'received'
    ];
}

$stmt->close();

json_success(['messages' => $messages]);

$conn->close();
