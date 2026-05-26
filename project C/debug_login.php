<?php
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    echo "Raw input: " . file_get_contents('php://input') . "\n";
    echo "Decoded data: " . json_encode($data) . "\n";

    if ($data === null) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    echo "Username: $username\n";
    echo "Password: $password\n";

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Missing username or password']);
        exit;
    }

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "Query executed, rows: " . $result->num_rows . "\n";

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        echo "User found: " . json_encode($user) . "\n";

        if (password_verify($password, $user['password'])) {
            echo json_encode(['success' => true, 'message' => 'Login successful']);
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
