<?php
include 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get sort parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query
$sql = "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar
        FROM projects p
        JOIN users u ON p.user_id = u.id";

// Add search condition if provided
$whereClause = "";
if (!empty($search)) {
    $searchTerm = $conn->real_escape_string($search);
    $whereClause = " WHERE (p.title LIKE '%$searchTerm%' OR p.description LIKE '%$searchTerm%' OR u.full_name LIKE '%$searchTerm%')";
}

// Add sorting
$orderBy = " ORDER BY p.created_at DESC";
if ($sort === 'trending') {
    // For trending, we'll use random score for demo (since views not implemented)
    $orderBy = " ORDER BY RAND(), p.created_at DESC";
} elseif ($sort === 'new') {
    $orderBy = " ORDER BY p.created_at DESC";
}

$sql .= $whereClause . $orderBy;

$result = $conn->query($sql);

$projects = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}

// Return success response
$response = array(
    'success' => true,
    'projects' => $projects,
    'sort' => $sort,
    'search' => $search
);

echo json_encode($response);
$conn->close();
?>
