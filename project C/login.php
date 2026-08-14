<?php
include 'config.php';

require_request_method('POST');

$data = read_json_body();
require_fields($data, ['username', 'password']);

$username = $data['username'];
$password = $data['password'];

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user === null) {
    json_fail('Invalid username or password', 401, 'user not found: ' . $username);
}

if (!password_verify($password, $user['password'])) {
    json_fail('Invalid username or password', 401, 'password mismatch for user id ' . $user['id']);
}

// Update online status. A failure here must not silently pass as a clean login,
// but it also must not lose the successful authentication.
$online_status_updated = true;
try {
    $update_stmt = $conn->prepare("UPDATE users SET online_status = TRUE WHERE id = ?");
    $update_stmt->bind_param("i", $user['id']);
    $update_stmt->execute();
    $update_stmt->close();
} catch (mysqli_sql_exception $e) {
    $online_status_updated = false;
    error_log('login.php: failed to update online_status for user ' . $user['id'] . ': ' . $e->getMessage());
}

json_success([
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'avatar' => $user['avatar'],
        'bio' => $user['bio']
    ],
    'online_status_updated' => $online_status_updated
]);

$conn->close();
