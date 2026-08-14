<?php
include 'config.php';
require_once __DIR__ . '/lib/validation.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = decode_json_body(file_get_contents('php://input'));

    if (!$body['ok']) {
        echo json_encode(['success' => false, 'message' => $body['message']]);
        exit;
    }

    $input = validate_login_input($body['data']);

    if (!$input['ok']) {
        echo json_encode(['success' => false, 'message' => $input['message']]);
        exit;
    }

    $username = $input['username'];
    $password = $input['password'];
    
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Update online status
            $update_sql = "UPDATE users SET online_status = TRUE WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            echo json_encode([
                'success' => true, 
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'avatar' => $user['avatar'],
                    'bio' => $user['bio']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
    $stmt->close();
}
$conn->close();
?>