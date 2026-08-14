<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('GET');

// The conversation is always read as the logged-in user, so a client cannot
// request someone else's inbox by changing sender_id.
$sender_id = require_login();
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;

if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

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
        'type' => ((int)$row['sender_id'] === $sender_id) ? 'sent' : 'received'
    ];
}

echo json_encode(['success' => true, 'messages' => $messages]);

$stmt->close();
$conn->close();
