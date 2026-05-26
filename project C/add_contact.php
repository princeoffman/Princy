<?php
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['user_id']) || !isset($data['contact_name'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $user_id = $data['user_id'];
    $contact_name = trim($data['contact_name']);
    $contact_email = isset($data['contact_email']) ? trim($data['contact_email']) : null;
    $contact_phone = isset($data['contact_phone']) ? trim($data['contact_phone']) : null;
    $contact_notes = isset($data['contact_notes']) ? trim($data['contact_notes']) : null;

    if (empty($contact_name)) {
        echo json_encode(['success' => false, 'message' => 'Contact name cannot be empty']);
        exit;
    }

    // Verify user exists
    $check_user_sql = "SELECT id FROM users WHERE id = ?";
    $check_user_stmt = $conn->prepare($check_user_sql);
    $check_user_stmt->bind_param("i", $user_id);
    $check_user_stmt->execute();
    $check_user_result = $check_user_stmt->get_result();

    if ($check_user_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User does not exist']);
        exit;
    }
    $check_user_stmt->close();

    // Insert new contact
    $insert_sql = "INSERT INTO contacts (user_id, contact_name, contact_email, contact_phone, contact_notes) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("issss", $user_id, $contact_name, $contact_email, $contact_phone, $contact_notes);

    if ($insert_stmt->execute()) {
        $contact_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Contact added successfully',
            'contact_id' => $contact_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add contact: ' . $insert_stmt->error]);
    }

    $insert_stmt->close();
}

$conn->close();
?>
