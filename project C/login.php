<?php
require_once __DIR__ . '/api_helpers.php';

require_method('POST');

$data = read_request_data();
require_fields($data, ['username', 'password'], 'Missing username or password');

$username = $data['username'];
$password = $data['password'];

if (!$username || !$password) {
    json_fail('Missing username or password');
}

$user = fetch_row($conn, "SELECT * FROM users WHERE username = ?", "s", [$username]);

if ($user === null) {
    json_fail('User not found');
}

if (!password_verify($password, $user['password'])) {
    json_fail('Invalid password');
}

// Update online status
execute_write($conn, "UPDATE users SET online_status = TRUE WHERE id = ?", "i", [$user['id']]);

json_success([
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'avatar' => $user['avatar'],
        'bio' => $user['bio']
    ]
]);

$conn->close();
?>
