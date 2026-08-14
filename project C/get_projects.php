<?php
require_once __DIR__ . '/api_helpers.php';

// Get sort parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query
$sql = "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar
        FROM projects p
        JOIN users u ON p.user_id = u.id";

$types = "";
$params = [];

// Add search condition if provided
if (!empty($search)) {
    $sql .= " WHERE (p.title LIKE ? OR p.description LIKE ? OR u.full_name LIKE ?)";
    $like = '%' . $search . '%';
    $types = "sss";
    $params = [$like, $like, $like];
}

// Add sorting. Trending uses a random order for demo purposes (views not implemented).
$sql .= ($sort === 'trending')
    ? " ORDER BY RAND(), p.created_at DESC"
    : " ORDER BY p.created_at DESC";

$projects = fetch_rows($conn, $sql, $types, $params);

json_success([
    'projects' => $projects,
    'sort' => $sort,
    'search' => $search
]);

$conn->close();
?>
