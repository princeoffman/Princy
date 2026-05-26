<?php
include 'config.php';

header('Content-Type: application/json');

// Get all messages
$result = $conn->query('SELECT * FROM messages ORDER BY sent_at DESC LIMIT 10');

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);

$conn->close();
?>
