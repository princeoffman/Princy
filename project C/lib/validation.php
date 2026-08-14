<?php
/**
 * Pure input-validation helpers shared by the JSON endpoints.
 *
 * Every function is side-effect free: it returns an array with an `ok` flag so
 * that callers decide how to respond, which keeps the rules unit testable.
 */

const MAX_AVATAR_SIZE = 5 * 1024 * 1024;
const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const DEFAULT_PROJECT_IMAGE = 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';

/**
 * Decode a raw JSON request body.
 *
 * @return array{ok: bool, data?: array, message?: string}
 */
function decode_json_body($raw)
{
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return ['ok' => false, 'message' => 'Invalid JSON'];
    }

    return ['ok' => true, 'data' => $data];
}

/**
 * @return array{ok: bool, username?: string, password?: string, message?: string}
 */
function validate_login_input($data)
{
    if (!is_array($data)) {
        return ['ok' => false, 'message' => 'Invalid JSON'];
    }

    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    if (!$username || !$password) {
        return ['ok' => false, 'message' => 'Missing username or password'];
    }

    return ['ok' => true, 'username' => $username, 'password' => $password];
}

/**
 * @return array{ok: bool, username?: string, email?: string, password?: string, full_name?: string, message?: string}
 */
function validate_register_input($data)
{
    if (!is_array($data)) {
        return ['ok' => false, 'message' => 'Invalid JSON'];
    }

    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $full_name = trim($data['full_name'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $full_name === '') {
        return ['ok' => false, 'message' => 'All fields are required'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Password must be at least 6 characters'];
    }

    return [
        'ok' => true,
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'full_name' => $full_name,
    ];
}

/**
 * @return array{ok: bool, sender_id?: int, receiver_id?: int, message?: string}
 */
function validate_message_input($data)
{
    if (!is_array($data)
        || !isset($data['sender_id'])
        || !isset($data['receiver_id'])
        || !isset($data['message'])
    ) {
        return ['ok' => false, 'message' => 'Missing required fields'];
    }

    $sender_id = intval($data['sender_id']);
    $receiver_id = intval($data['receiver_id']);
    $text = trim($data['message']);

    if ($text === '') {
        return ['ok' => false, 'message' => 'Message cannot be empty'];
    }

    if ($sender_id <= 0 || $receiver_id <= 0) {
        return ['ok' => false, 'message' => 'Invalid sender or receiver ID'];
    }

    if ($sender_id === $receiver_id) {
        return ['ok' => false, 'message' => 'Cannot send message to yourself'];
    }

    return [
        'ok' => true,
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'text' => $text,
    ];
}

/**
 * @return array{ok: bool, user_id?: mixed, contact?: array, message?: string}
 */
function validate_contact_input($data)
{
    if (!is_array($data) || !isset($data['user_id']) || !isset($data['contact_name'])) {
        return ['ok' => false, 'message' => 'Missing required fields'];
    }

    $contact_name = trim($data['contact_name']);

    if ($contact_name === '') {
        return ['ok' => false, 'message' => 'Contact name cannot be empty'];
    }

    return [
        'ok' => true,
        'user_id' => $data['user_id'],
        'contact' => [
            'contact_name' => $contact_name,
            'contact_email' => isset($data['contact_email']) ? trim($data['contact_email']) : null,
            'contact_phone' => isset($data['contact_phone']) ? trim($data['contact_phone']) : null,
            'contact_notes' => isset($data['contact_notes']) ? trim($data['contact_notes']) : null,
        ],
    ];
}

/**
 * @return array{ok: bool, user_id?: mixed, project?: array, message?: string}
 */
function validate_project_input($data)
{
    if (!is_array($data)
        || !isset($data['user_id'])
        || !isset($data['title'])
        || !isset($data['description'])
    ) {
        return ['ok' => false, 'message' => 'Missing required fields'];
    }

    $title = trim($data['title']);
    $description = trim($data['description']);

    if ($title === '' || $description === '') {
        return ['ok' => false, 'message' => 'Title and description cannot be empty'];
    }

    $image = isset($data['image']) ? trim($data['image']) : '';

    return [
        'ok' => true,
        'user_id' => $data['user_id'],
        'project' => [
            'title' => $title,
            'description' => $description,
            'image' => $image !== '' ? $image : DEFAULT_PROJECT_IMAGE,
        ],
    ];
}

/**
 * Normalise a `user_id`-style request parameter.
 *
 * @return array{ok: bool, id?: int, message?: string}
 */
function validate_id_param($value, $error_message = 'Invalid user ID')
{
    $id = intval($value ?? 0);

    if ($id <= 0) {
        return ['ok' => false, 'message' => $error_message];
    }

    return ['ok' => true, 'id' => $id];
}

/**
 * @param array|null $file entry taken from $_FILES
 * @return array{ok: bool, message?: string}
 */
function validate_avatar_upload($file)
{
    if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'No file uploaded or upload error'];
    }

    if (!isset($file['type']) || !in_array($file['type'], ALLOWED_AVATAR_TYPES, true)) {
        return [
            'ok' => false,
            'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.',
        ];
    }

    if (isset($file['size']) && $file['size'] > MAX_AVATAR_SIZE) {
        return ['ok' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }

    return ['ok' => true];
}

/**
 * Build the on-disk name for an uploaded avatar.
 */
function avatar_filename($user_id, $original_name, $timestamp)
{
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $name = 'user_' . intval($user_id) . '_' . intval($timestamp);

    return $extension === '' ? $name : $name . '.' . $extension;
}
