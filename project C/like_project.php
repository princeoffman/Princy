<?php
require_once __DIR__ . '/api_helpers.php';

require_method('POST');

$data = read_request_data();
require_fields($data, ['user_id', 'project_id']);

$project_id = $data['project_id'];
$liked = toggle_project_link($conn, 'likes', $data['user_id'], $project_id);

if ($liked === null) {
    json_error('Failed to like project');
} else {
    $count_row = fetch_row(
        $conn,
        "SELECT COUNT(*) as like_count FROM likes WHERE project_id = ?",
        "i",
        [$project_id]
    );

    json_success([
        'message' => $liked ? 'Project liked successfully' : 'Project unliked successfully',
        'liked' => $liked,
        'like_count' => $count_row['like_count']
    ]);
}

$conn->close();
?>
