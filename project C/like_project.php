<?php
include 'config.php';

require_request_method('POST');

$data = read_json_body();
require_fields($data, ['user_id', 'project_id']);

$user_id = require_positive_int($data['user_id'], 'user ID');
$project_id = require_positive_int($data['project_id'], 'project ID');

function count_likes(mysqli $conn, int $project_id): int
{
    $count_stmt = $conn->prepare("SELECT COUNT(*) as like_count FROM likes WHERE project_id = ?");
    $count_stmt->bind_param("i", $project_id);
    $count_stmt->execute();
    $row = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();

    return (int)($row['like_count'] ?? 0);
}

// Check if already liked
$check_stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND project_id = ?");
$check_stmt->bind_param("ii", $user_id, $project_id);
$check_stmt->execute();
$already_liked = $check_stmt->get_result()->num_rows > 0;
$check_stmt->close();

if ($already_liked) {
    // Already liked, remove it (unlike)
    $delete_stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND project_id = ?");
    $delete_stmt->bind_param("ii", $user_id, $project_id);
    $delete_stmt->execute();
    $deleted = $delete_stmt->affected_rows;
    $delete_stmt->close();

    if ($deleted === 0) {
        json_fail('Failed to unlike project', 500, "no rows deleted for user $user_id / project $project_id");
    }

    json_success([
        'message' => 'Project unliked successfully',
        'liked' => false,
        'like_count' => count_likes($conn, $project_id)
    ]);
} else {
    // Not liked, add it
    $insert_stmt = $conn->prepare("INSERT INTO likes (user_id, project_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $project_id);
    try {
        $insert_stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // 1452 is a foreign key violation: unknown user or project.
        if ($e->getCode() === 1452) {
            json_fail('User or project does not exist', 404, $e->getMessage());
        }
        throw $e;
    } finally {
        $insert_stmt->close();
    }

    json_success([
        'message' => 'Project liked successfully',
        'liked' => true,
        'like_count' => count_likes($conn, $project_id)
    ], 201);
}

$conn->close();
