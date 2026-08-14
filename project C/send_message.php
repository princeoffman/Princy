<?php
include 'config.php';
require_once __DIR__ . '/lib/validation.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = null;

    // Check if input is JSON
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($content_type, 'application/json') !== false) {
        $raw_input = file_get_contents('php://input');
        error_log("Raw JSON input: " . $raw_input); // Debug log
        $data = json_decode($raw_input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg()); // Debug log
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }
    } else {
        // Handle form data
        error_log("Form data received: " . print_r($_POST, true)); // Debug log
        $data = $_POST;
    }

    $input = validate_message_input($data);

    if (!$input['ok']) {
        error_log("Invalid message payload: " . print_r($data, true)); // Debug log
        echo json_encode(['success' => false, 'message' => $input['message']]);
        exit;
    }

    $sender_id = $input['sender_id'];
    $receiver_id = $input['receiver_id'];
    $message = $input['text'];

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
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }

    $stmt->close();
}

$conn->close();
?>
