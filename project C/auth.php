<?php
// Session handling and authorization helpers shared by every API endpoint.
require_once __DIR__ . '/env.php';

load_env(__DIR__ . '/.env');

function start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function current_user_id()
{
    start_session();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// Ends the request with 401 unless a session-backed user is logged in.
function require_login()
{
    $user_id = current_user_id();
    if ($user_id === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    return $user_id;
}

function require_method($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
}

function json_body()
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    return $data;
}
