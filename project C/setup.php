<?php
require_once __DIR__ . '/api_response.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_request_method('GET', 'POST');

// Database configuration
$servername = "localhost";
$username = "root";       // Default XAMPP username
$password = "";           // Default XAMPP password (empty)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Each step appends its outcome here so the response is a single JSON document
// listing every step, instead of a stream of concatenated JSON objects.
$steps = [];

function step(string $name, callable $action): bool
{
    global $steps;

    try {
        $message = $action();
        $steps[] = ['step' => $name, 'success' => true, 'message' => $message];
        return true;
    } catch (Throwable $e) {
        error_log("setup.php: $name failed: " . $e->getMessage());
        $steps[] = ['step' => $name, 'success' => false, 'message' => $e->getMessage()];
        return false;
    }
}

function fail_setup(string $message): void
{
    global $steps;

    json_response([
        'success' => false,
        'message' => $message,
        'steps' => $steps
    ], 500);
    exit;
}

// Create connection without database first
try {
    $conn = new mysqli($servername, $username, $password);
} catch (mysqli_sql_exception $e) {
    json_fail('Database unavailable', 503, 'Connection failed: ' . $e->getMessage());
}

if (!step('create_database', function () use ($conn) {
    $conn->query("CREATE DATABASE IF NOT EXISTS projectconnect");
    return 'Database ready';
})) {
    fail_setup('Setup aborted: could not create the database');
}

if (!step('select_database', function () use ($conn) {
    $conn->select_db('projectconnect');
    $conn->set_charset('utf8mb4');
    return 'Database selected';
})) {
    fail_setup('Setup aborted: could not select the database');
}

$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL UNIQUE,
        email VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        avatar VARCHAR(255),
        bio TEXT,
        online_status BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    'projects' => "CREATE TABLE IF NOT EXISTS projects (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(6) UNSIGNED,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    'saved_projects' => "CREATE TABLE IF NOT EXISTS saved_projects (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(6) UNSIGNED,
        project_id INT(6) UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        UNIQUE KEY unique_save (user_id, project_id)
    ) ENGINE=InnoDB",
    'messages' => "CREATE TABLE IF NOT EXISTS messages (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_id INT(6) UNSIGNED,
        receiver_id INT(6) UNSIGNED,
        message TEXT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    'likes' => "CREATE TABLE IF NOT EXISTS likes (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(6) UNSIGNED NOT NULL,
        project_id INT(6) UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (user_id, project_id)
    ) ENGINE=InnoDB",
    'contacts' => "CREATE TABLE IF NOT EXISTS contacts (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(6) UNSIGNED,
        contact_name VARCHAR(100) NOT NULL,
        contact_email VARCHAR(100),
        contact_phone VARCHAR(20),
        contact_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
];

foreach ($tables as $table => $ddl) {
    if (!step("create_table_$table", function () use ($conn, $ddl, $table) {
        $conn->query($ddl);
        return "Table $table ready";
    })) {
        // Later steps depend on the schema, so stop instead of continuing on a
        // half-created database and still reporting overall success.
        fail_setup("Setup aborted: could not create the $table table");
    }
}

// Download profile pictures for the seeded users
$kudaAvatar = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80';
$tanatswaAvatar = 'https://images.unsplash.com/photo-1544725176-7c40e5a71c5e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80';

step('download_avatars', function () use (&$kudaAvatar, &$tanatswaAvatar) {
    $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $response = @file_get_contents('http://localhost/download_image.php?download=1', false, $context);
    if ($response === false) {
        throw new RuntimeException('Avatar download request failed; using fallback avatars');
    }

    $downloadData = json_decode($response, true);
    if (!is_array($downloadData) || empty($downloadData['success'])) {
        $reason = is_array($downloadData) && isset($downloadData['message']) ? $downloadData['message'] : 'unexpected response';
        throw new RuntimeException("Avatar download failed ($reason); using fallback avatars");
    }

    $kudaAvatar = $downloadData['kuda_avatar'] ?? $kudaAvatar;
    $tanatswaAvatar = $downloadData['tanatswa_avatar'] ?? $tanatswaAvatar;

    return 'Avatars downloaded';
});

// Seed users
$seed_users = [
    [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'full_name' => 'Test User',
        'avatar' => null
    ],
    [
        'username' => 'kuda_mapinga',
        'email' => 'kuda.mapinga@example.com',
        'full_name' => 'Kuda Mapinga',
        'avatar' => $kudaAvatar
    ],
    [
        'username' => 'tanatswa_sibanda',
        'email' => 'tanatswa.sibanda@example.com',
        'full_name' => 'Tanatswa Sibanda',
        'avatar' => $tanatswaAvatar
    ]
];

$seed_failed = false;
foreach ($seed_users as $user) {
    $ok = step('seed_user_' . $user['username'], function () use ($conn, $user) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $user['username']);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();

        if ($exists) {
            return 'User ' . $user['full_name'] . ' already exists';
        }

        $hashed = password_hash('password', PASSWORD_DEFAULT);
        $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, avatar) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssss", $user['username'], $user['email'], $hashed, $user['full_name'], $user['avatar']);
        $insert_stmt->execute();
        $insert_stmt->close();

        return 'User ' . $user['full_name'] . ' created successfully';
    });

    $seed_failed = $seed_failed || !$ok;
}

// Insert sample projects for the first user, if they have none yet
$sample_projects = [
    ['Sample Project 1', 'This is a sample project description.', 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
    ['Sample Project 2', 'Another sample project with different content.', 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
    ['Sample Project 3', 'Third sample project for testing.', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
    ['Sample Project 4', 'Fourth sample project to test pagination.', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
];

$sample_ok = step('seed_sample_projects', function () use ($conn, $sample_projects) {
    $owner = $conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetch_assoc();
    if ($owner === null) {
        throw new RuntimeException('No user available to own the sample projects');
    }
    $user_id = (int)$owner['id'];

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM projects WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    if ($total > 0) {
        return 'Sample projects already present';
    }

    $insert_stmt = $conn->prepare("INSERT INTO projects (user_id, title, description, image) VALUES (?, ?, ?, ?)");
    foreach ($sample_projects as [$title, $description, $image]) {
        $insert_stmt->bind_param("isss", $user_id, $title, $description, $image);
        $insert_stmt->execute();
    }
    $insert_stmt->close();

    return 'Sample projects created';
});

if ($seed_failed || !$sample_ok) {
    json_response([
        'success' => false,
        'message' => 'Schema is ready but seeding did not fully succeed',
        'steps' => $steps
    ], 500);
    $conn->close();
    exit;
}

json_success(['message' => 'Setup completed successfully', 'steps' => $steps]);

$conn->close();
