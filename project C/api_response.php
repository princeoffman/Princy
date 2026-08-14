<?php
// Shared helpers for JSON responses, request validation and error propagation.
// Included by config.php, so every endpoint that includes config.php can use them.

if (!defined('API_RESPONSE_LOADED')) {
    define('API_RESPONSE_LOADED', true);

    // Never render PHP notices/warnings into the response body: they corrupt the
    // JSON the client tries to parse. They are logged instead.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    function json_response(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
        }

        $body = json_encode($payload);
        if ($body === false) {
            error_log('json_encode failed: ' . json_last_error_msg());
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo '{"success":false,"message":"Failed to encode response"}';
            return;
        }

        echo $body;
    }

    function json_success(array $payload = [], int $status = 200): void
    {
        json_response(['success' => true] + $payload, $status);
    }

    // Sends a client-safe error and logs the underlying cause, so failures are
    // never swallowed silently on the server side either.
    function json_error(string $message, int $status = 400, ?string $internal = null): void
    {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? 'unknown');
        error_log(sprintf('%s: %d %s%s', $script, $status, $message, $internal !== null ? ' | ' . $internal : ''));
        json_response(['success' => false, 'message' => $message], $status);
    }

    function json_fail(string $message, int $status = 400, ?string $internal = null): void
    {
        json_error($message, $status, $internal);
        exit;
    }

    // Answers CORS preflight requests and rejects unsupported verbs instead of
    // falling through to an empty 200 response.
    function require_request_method(string ...$allowed): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        if ($method === 'OPTIONS') {
            if (!headers_sent()) {
                http_response_code(204);
            }
            exit;
        }

        if (!in_array($method, $allowed, true)) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', $allowed));
            }
            json_fail('Method not allowed. Use ' . implode(' or ', $allowed) . '.', 405, 'received ' . $method);
        }
    }

    // Decodes a JSON request body, failing loudly on malformed input.
    function read_json_body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false) {
            json_fail('Could not read request body', 400);
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            json_fail('Invalid JSON', 400, json_last_error_msg());
        }

        if (!is_array($data)) {
            json_fail('Request body must be a JSON object', 400);
        }

        return $data;
    }

    function require_fields(array $data, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }

        if ($missing) {
            json_fail('Missing required fields: ' . implode(', ', $missing), 400);
        }
    }

    function require_positive_int($value, string $name): int
    {
        if (!is_numeric($value) || (int)$value <= 0) {
            json_fail("Invalid $name", 400, 'got ' . var_export($value, true));
        }

        return (int)$value;
    }

    // Turns uncaught throwables (including mysqli_sql_exception, enabled by
    // MYSQLI_REPORT_ERROR in config.php) into a logged 500 JSON response
    // instead of an HTML fatal error the client cannot parse.
    set_exception_handler(function (Throwable $e): void {
        json_error('Internal server error', 500, get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    });

    // Promote warnings (failed mkdir, unreadable file, ...) to exceptions so
    // they propagate rather than leaving code running on invalid state.
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    // A fatal error bypasses the exception handler; make sure the client still
    // receives JSON with a 500 status rather than a truncated 200 body.
    register_shutdown_function(function (): void {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        error_log(sprintf('Fatal error: %s at %s:%d', $error['message'], $error['file'], $error['line']));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo '{"success":false,"message":"Internal server error"}';
        }
    });
}
