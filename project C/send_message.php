<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('POST');

// Messages are always sent as the logged-in user; a spoofed sender_id is ignored.
$sender_id = require_login();

$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($content_type, 'application/json') !== false) {
    $data = json_body();
} else {
    $data = $_POST;
}

$receiver_id = isset($data['receiver_id']) ? (int)$data['receiver_id'] : 0;
$message = isset($data['message']) ? trim((string)$data['message']) : '';

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

if (mb_strlen($message) > 5000) {
    echo json_encode(['success' => false, 'message' => 'Message is too long']);
    exit;
}

if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid receiver ID']);
    exit;
}

if ($receiver_id === $sender_id) {
    echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
    exit;
}

// Verify receiver exists
$check_receiver = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_receiver->bind_param("i", $receiver_id);
$check_receiver->execute();
$receiver_exists = $check_receiver->get_result()->num_rows > 0;
$check_receiver->close();

if (!$receiver_exists) {
    echo json_encode(['success' => false, 'message' => 'Receiver does not exist']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    error_log('Failed to send message: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}

$stmt->close();
$conn->close();
