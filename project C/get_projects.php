<?php
include 'config.php';

require_request_method('GET');

// Get sort parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$allowed_sorts = ['default', 'trending', 'new'];
if (!in_array($sort, $allowed_sorts, true)) {
    json_fail('Invalid sort option. Allowed: ' . implode(', ', $allowed_sorts), 400);
}

// Base query
$sql = "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar
        FROM projects p
        JOIN users u ON p.user_id = u.id";

// Add search condition if provided
$params = [];
$types = '';
if ($search !== '') {
    $sql .= " WHERE (p.title LIKE ? OR p.description LIKE ? OR u.full_name LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}

// Add sorting. For trending we use a random score for demo (views not implemented).
$sql .= $sort === 'trending' ? " ORDER BY RAND(), p.created_at DESC" : " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$projects = array();
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

$stmt->close();

json_success([
    'projects' => $projects,
    'sort' => $sort,
    'search' => $search
]);

$conn->close();
