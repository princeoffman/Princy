<?php
require_once __DIR__ . '/config.php';

/**
 * Shared helpers for the JSON endpoints.
 */

function json_response(array $payload) {
    echo json_encode($payload);
}

function json_success(array $data = array()) {
    json_response(array_merge(array('success' => true), $data));
}

function json_error($message, array $extra = array()) {
    json_response(array_merge(array('success' => false, 'message' => $message), $extra));
}

/** Send an error response and stop processing. */
function json_fail($message, array $extra = array()) {
    json_error($message, $extra);
    exit;
}

function require_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_fail('Invalid request method');
    }
}

/**
 * Read the request body as an array, accepting either a JSON body or form data.
 */
function read_request_data() {
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($content_type, 'application/json') !== false || empty($_POST)) {
        $raw_input = file_get_contents('php://input');
        if ($raw_input === '' || $raw_input === false) {
            return $_POST;
        }
        $data = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            json_fail('Invalid JSON');
        }
        return $data;
    }

    return $_POST;
}

/** Fail with a "Missing required fields" response unless every field is present. */
function require_fields(array $data, array $fields, $message = 'Missing required fields') {
    foreach ($fields as $field) {
        if (!isset($data[$field])) {
            json_fail($message);
        }
    }
}

function int_param(array $source, $key, $default = 0) {
    return isset($source[$key]) ? intval($source[$key]) : $default;
}

/** Read a positive integer parameter, failing with $message when absent or invalid. */
function require_positive_int(array $source, $key, $message) {
    $value = int_param($source, $key);
    if ($value <= 0) {
        json_fail($message);
    }
    return $value;
}

/** Prepare a statement and bind the given parameters. */
function prepare_query($conn, $sql, $types = '', array $params = array()) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        json_fail('Database error: ' . $conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    return $stmt;
}

/** Execute an INSERT/UPDATE/DELETE and report whether it succeeded. */
function execute_write($conn, $sql, $types = '', array $params = array()) {
    $stmt = prepare_query($conn, $sql, $types, $params);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/** Run a SELECT and return all rows as an array. */
function fetch_rows($conn, $sql, $types = '', array $params = array()) {
    $stmt = prepare_query($conn, $sql, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/** Run a SELECT and return the first row, or null. */
function fetch_row($conn, $sql, $types = '', array $params = array()) {
    $rows = fetch_rows($conn, $sql, $types, $params);
    return count($rows) > 0 ? $rows[0] : null;
}

function row_exists($conn, $sql, $types = '', array $params = array()) {
    return fetch_row($conn, $sql, $types, $params) !== null;
}

function user_exists($conn, $user_id) {
    return row_exists($conn, 'SELECT id FROM users WHERE id = ?', 'i', array($user_id));
}

/** Fail unless the user exists. */
function require_user($conn, $user_id, $message = 'User does not exist') {
    if (!user_exists($conn, $user_id)) {
        json_fail($message);
    }
}

/**
 * Insert a (user_id, project_id) row in $table, or delete it when already present.
 * Returns true when the row was created, false when it was removed and null on failure.
 */
function toggle_project_link($conn, $table, $user_id, $project_id) {
    $exists = row_exists(
        $conn,
        "SELECT id FROM $table WHERE user_id = ? AND project_id = ?",
        'ii',
        array($user_id, $project_id)
    );

    $sql = $exists
        ? "DELETE FROM $table WHERE user_id = ? AND project_id = ?"
        : "INSERT INTO $table (user_id, project_id) VALUES (?, ?)";

    if (!execute_write($conn, $sql, 'ii', array($user_id, $project_id))) {
        return null;
    }
    return !$exists;
}
