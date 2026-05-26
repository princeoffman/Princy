<?php
include 'config.php';

header('Content-Type: application/json');

// Test data
$sender_id = 1;
$receiver_id = 2;
$message = 'Test message from PHP script at ' . date('Y-m-d H:i:s');

echo "Testing message sending...\n";
echo "Sender ID: $sender_id\n";
echo "Receiver ID: $receiver_id\n";
echo "Message: $message\n\n";

// Simulate the send_message.php logic
if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

// Validate sender and receiver IDs
if ($sender_id <= 0 || $receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid sender or receiver ID']);
    exit;
}

// Check if sender and receiver are different users
if ($sender_id === $receiver_id) {
    echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
    exit;
}

// Verify sender exists
$check_sender = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_sender->bind_param("i", $sender_id);
$check_sender->execute();
if ($check_sender->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sender does not exist']);
    exit;
}
$check_sender->close();

// Verify receiver exists
$check_receiver = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_receiver->bind_param("i", $receiver_id);
$check_receiver->execute();
if ($check_receiver->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Receiver does not exist']);
    exit;
}
$check_receiver->close();

// Insert message into database
$sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);

if ($stmt->execute()) {
    $message_id = $conn->insert_id;
    echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'message_id' => $message_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $stmt->error]);
}

$stmt->close();

$conn->close();
?>
