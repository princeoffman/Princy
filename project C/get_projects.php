<?php
include 'config.php';
require_once __DIR__ . '/auth.php';

require_method('GET');

$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'default';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$sql = "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar
        FROM projects p
        JOIN users u ON p.user_id = u.id";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE (p.title LIKE ? OR p.description LIKE ? OR u.full_name LIKE ?)";
    // Escape LIKE wildcards so a search term cannot widen the match.
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params = [$like, $like, $like];
    $types = "sss";
}

// Sort keys are mapped to fixed ORDER BY clauses instead of interpolated.
$orderByClauses = [
    'default' => " ORDER BY p.created_at DESC",
    'new' => " ORDER BY p.created_at DESC",
    'trending' => " ORDER BY RAND(), p.created_at DESC",
];
if (!isset($orderByClauses[$sort])) {
    $sort = 'default';
}
$sql .= $orderByClauses[$sort];

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$projects = array();
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

echo json_encode([
    'success' => true,
    'projects' => $projects,
    'sort' => $sort,
    'search' => $search
]);

$stmt->close();
$conn->close();
