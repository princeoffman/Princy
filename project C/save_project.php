<?php
require_once __DIR__ . '/api_helpers.php';

require_method('POST');

$data = read_request_data();
require_fields($data, ['user_id', 'project_id']);

$saved = toggle_project_link($conn, 'saved_projects', $data['user_id'], $data['project_id']);

if ($saved === null) {
    json_error('Failed to save project');
} else {
    json_success([
        'message' => $saved ? 'Project saved successfully' : 'Project unsaved successfully',
        'saved' => $saved
    ]);
}

$conn->close();
?>
