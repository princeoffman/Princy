<?php
include 'config.php';
require_once __DIR__ . '/lib/presenters.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get sort parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$searchTerm = empty($search) ? '' : $conn->real_escape_string($search);
$sql = build_projects_query($sort, $searchTerm);

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
