<?php
require_once __DIR__ . '/api_helpers.php';

require_method('GET');
require_fields($_GET, ['sender_id', 'receiver_id'], 'Missing required parameters');

$sender_id = int_param($_GET, 'sender_id');
$receiver_id = int_param($_GET, 'receiver_id');

// Get messages between the two users (both directions)
$rows = fetch_rows(
    $conn,
    "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.sent_at,
            u.full_name as sender_name, u.avatar as sender_avatar
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE (m.sender_id = ? AND m.receiver_id = ?)
        OR (m.sender_id = ? AND m.receiver_id = ?)
     ORDER BY m.sent_at ASC",
    "iiii",
    [$sender_id, $receiver_id, $receiver_id, $sender_id]
);

$messages = [];
foreach ($rows as $row) {
    $row['type'] = ($row['sender_id'] == $sender_id) ? 'sent' : 'received';
    $messages[] = $row;
}

json_success(['messages' => $messages]);

$conn->close();
?>
