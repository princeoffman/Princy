<?php
include 'config.php';

header('Content-Type: application/json');

// Check if user_id is provided
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Get contacts for the user
$sql = "SELECT id, contact_name, contact_email, contact_phone, contact_notes, created_at
        FROM contacts
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$contacts = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
}

echo json_encode(['success' => true, 'contacts' => $contacts]);

$stmt->close();
$conn->close();
?>
