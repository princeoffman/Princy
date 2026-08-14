<?php
// Schema installer. Run from the command line only:
//   php setup.php
// It is intentionally not reachable over HTTP because it creates the database
// and seed accounts.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/env.php';

load_env(__DIR__ . '/.env');

$servername = env('DB_HOST', 'localhost');
$username = env('DB_USER');
$password = env('DB_PASSWORD', '');
$dbname = env('DB_NAME', 'projectconnect');

if ($username === null) {
    fwrite(STDERR, "DB_USER is not set. Copy .env.example to .env and fill it in.\n");
    exit(1);
}

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    fwrite(STDERR, 'Connection failed: ' . $conn->connect_error . "\n");
    exit(1);
}

function run($conn, $sql, $label)
{
    if ($conn->query($sql) === TRUE) {
        echo "$label: ok\n";
    } else {
        fwrite(STDERR, "$label: " . $conn->error . "\n");
    }
}

run($conn, "CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $dbname) . "`", 'database');
$conn->select_db($dbname);

run($conn, "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    avatar VARCHAR(255),
    bio TEXT,
    online_status BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB", 'users table');

run($conn, "CREATE TABLE IF NOT EXISTS projects (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB", 'projects table');

run($conn, "CREATE TABLE IF NOT EXISTS saved_projects (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    project_id INT(6) UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (user_id, project_id)
) ENGINE=InnoDB", 'saved_projects table');

run($conn, "CREATE TABLE IF NOT EXISTS messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED,
    receiver_id INT(6) UNSIGNED,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB", 'messages table');

run($conn, "CREATE TABLE IF NOT EXISTS likes (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    project_id INT(6) UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (user_id, project_id)
) ENGINE=InnoDB", 'likes table');

run($conn, "CREATE TABLE IF NOT EXISTS contacts (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    contact_name VARCHAR(100) NOT NULL,
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    contact_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB", 'contacts table');

// Demo accounts are only seeded when an explicit password is supplied, so no
// installation ends up with well known credentials.
$seed_password = env('SEED_DEMO_PASSWORD');

if ($seed_password === null) {
    echo "SEED_DEMO_PASSWORD not set: skipping demo users and sample projects.\n";
    echo "Setup completed.\n";
    $conn->close();
    exit(0);
}

if (strlen($seed_password) < 8) {
    fwrite(STDERR, "SEED_DEMO_PASSWORD must be at least 8 characters.\n");
    exit(1);
}

$demo_users = [
    ['testuser', 'test@example.com', 'Test User', null],
    ['kuda_mapinga', 'kuda.mapinga@example.com', 'Kuda Mapinga', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'],
    ['tanatswa_sibanda', 'tanatswa.sibanda@example.com', 'Tanatswa Sibanda', 'https://images.unsplash.com/photo-1544725176-7c40e5a71c5e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80'],
];

$first_user_id = null;

foreach ($demo_users as list($demo_username, $demo_email, $demo_full_name, $demo_avatar)) {
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $demo_username);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing) {
        $first_user_id = $first_user_id ?? (int)$existing['id'];
        echo "user $demo_username: already exists\n";
        continue;
    }

    $hash = password_hash($seed_password, PASSWORD_DEFAULT);
    $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, avatar) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("sssss", $demo_username, $demo_email, $hash, $demo_full_name, $demo_avatar);

    if ($insert_stmt->execute()) {
        $first_user_id = $first_user_id ?? $conn->insert_id;
        echo "user $demo_username: created\n";
    } else {
        fwrite(STDERR, "user $demo_username: " . $insert_stmt->error . "\n");
    }
    $insert_stmt->close();
}

$sample_projects = [
    ['Sample Project 1', 'This is a sample project description.', 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
    ['Sample Project 2', 'Another sample project with different content.', 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
    ['Sample Project 3', 'Third sample project for testing.', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
    ['Sample Project 4', 'Fourth sample project to test pagination.', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'],
];

if ($first_user_id !== null) {
    $count = $conn->query("SELECT COUNT(*) AS c FROM projects")->fetch_assoc()['c'];
    if ((int)$count === 0) {
        foreach ($sample_projects as list($title, $description, $image)) {
            $stmt = $conn->prepare("INSERT INTO projects (user_id, title, description, image) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $first_user_id, $title, $description, $image);
            $stmt->execute();
            $stmt->close();
        }
        echo "sample projects: created\n";
    }
}

echo "Setup completed.\n";

$conn->close();
