<?php
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['sender_id']) || !isset($_GET['receiver_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }

    $sender_id = intval($_GET['sender_id']);
    $receiver_id = intval($_GET['receiver_id']);

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

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
