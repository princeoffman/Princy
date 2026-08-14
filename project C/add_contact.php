<?php
include 'config.php';
require_once __DIR__ . '/lib/validation.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $input = validate_contact_input($data);

    if (!$input['ok']) {
        echo json_encode(['success' => false, 'message' => $input['message']]);
        exit;
    }

    $user_id = $input['user_id'];
    $contact_name = $input['contact']['contact_name'];
    $contact_email = $input['contact']['contact_email'];
    $contact_phone = $input['contact']['contact_phone'];
    $contact_notes = $input['contact']['contact_notes'];

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
