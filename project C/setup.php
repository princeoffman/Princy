<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Database configuration
$servername = "localhost";
$username = "root";       // Default XAMPP username
$password = "";           // Default XAMPP password (empty)

// Create connection without database first
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS projectconnect";
if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Database created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating database: ' . $conn->error]);
}

// Select the database
$conn->select_db('projectconnect');

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    avatar VARCHAR(255),
    bio TEXT,
    online_status BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Users table created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating users table: ' . $conn->error]);
}

// Create projects table
$sql = "CREATE TABLE IF NOT EXISTS projects (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Projects table created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating projects table: ' . $conn->error]);
}

// Create saved_projects table
$sql = "CREATE TABLE IF NOT EXISTS saved_projects (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    project_id INT(6) UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (user_id, project_id)
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Saved projects table created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating saved projects table: ' . $conn->error]);
}

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED,
    receiver_id INT(6) UNSIGNED,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Messages table created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating messages table: ' . $conn->error]);
}

// Create likes table for project likes
$sql = "CREATE TABLE IF NOT EXISTS likes (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    project_id INT(6) UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (user_id, project_id)
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Likes table created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating likes table: ' . $conn->error]);
}

// Create contacts table for user contacts
$sql = "CREATE TABLE IF NOT EXISTS contacts (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    contact_name VARCHAR(100) NOT NULL,
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    contact_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Contacts table created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating contacts table: ' . $conn->error]);
}

// Insert test user
$test_username = 'testuser';
$test_email = 'test@example.com';
$test_password = password_hash('password', PASSWORD_DEFAULT);
$test_full_name = 'Test User';

$check_sql = "SELECT id FROM users WHERE username = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $test_username);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows == 0) {
    $insert_sql = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ssss", $test_username, $test_email, $test_password, $test_full_name);

    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Test user created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating test user: ' . $insert_stmt->error]);
    }
    $insert_stmt->close();
} else {
    echo json_encode(['success' => true, 'message' => 'Test user already exists']);
}

$check_stmt->close();

// Download profile pictures for new users
$downloadResponse = file_get_contents('http://localhost/download_image.php?download=1');
$downloadData = json_decode($downloadResponse, true);

if ($downloadData && $downloadData['success']) {
    $kudaAvatar = $downloadData['kuda_avatar'];
    $tanatswaAvatar = $downloadData['tanatswa_avatar'];
} else {
    // Fallback avatars if download fails
    $kudaAvatar = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80';
    $tanatswaAvatar = 'https://images.unsplash.com/photo-1544725176-7c40e5a71c5e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80';
}

// Insert new users: Kuda Mapinga and Tanatswa Sibanda
$newUsers = [
    [
        'username' => 'kuda_mapinga',
        'email' => 'kuda.mapinga@example.com',
        'password' => password_hash('password', PASSWORD_DEFAULT),
        'full_name' => 'Kuda Mapinga',
        'avatar' => $kudaAvatar
    ],
    [
        'username' => 'tanatswa_sibanda',
        'email' => 'tanatswa.sibanda@example.com',
        'password' => password_hash('password', PASSWORD_DEFAULT),
        'full_name' => 'Tanatswa Sibanda',
        'avatar' => $tanatswaAvatar
    ]
];

foreach ($newUsers as $user) {
    $check_user_sql = "SELECT id FROM users WHERE username = ?";
    $check_user_stmt = $conn->prepare($check_user_sql);
    $check_user_stmt->bind_param("s", $user['username']);
    $check_user_stmt->execute();
    $check_user_result = $check_user_stmt->get_result();

    if ($check_user_result->num_rows == 0) {
        $insert_user_sql = "INSERT INTO users (username, email, password, full_name, avatar) VALUES (?, ?, ?, ?, ?)";
        $insert_user_stmt = $conn->prepare($insert_user_sql);
        $insert_user_stmt->bind_param("sssss", $user['username'], $user['email'], $user['password'], $user['full_name'], $user['avatar']);

        if ($insert_user_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User ' . $user['full_name'] . ' created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating user ' . $user['full_name'] . ': ' . $insert_user_stmt->error]);
        }
        $insert_user_stmt->close();
    } else {
        echo json_encode(['success' => true, 'message' => 'User ' . $user['full_name'] . ' already exists']);
    }

    $check_user_stmt->close();
}

// Insert sample projects
$sample_projects = [
    [
        'user_id' => 1,
        'title' => 'Sample Project 1',
        'description' => 'This is a sample project description.',
        'image' => 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'
    ],
    [
        'user_id' => 1,
        'title' => 'Sample Project 2',
        'description' => 'Another sample project with different content.',
        'image' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'
    ],
    [
        'user_id' => 1,
        'title' => 'Sample Project 3',
        'description' => 'Third sample project for testing.',
        'image' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'
    ],
    [
        'user_id' => 1,
        'title' => 'Sample Project 4',
        'description' => 'Fourth sample project to test pagination.',
        'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'
    ]
];

foreach ($sample_projects as $project) {
    $insert_project_sql = "INSERT INTO projects (user_id, title, description, image) VALUES (?, ?, ?, ?)";
    $insert_project_stmt = $conn->prepare($insert_project_sql);
    $insert_project_stmt->bind_param("isss", $project['user_id'], $project['title'], $project['description'], $project['image']);
    $insert_project_stmt->execute();
    $insert_project_stmt->close();
}

echo json_encode(['success' => true, 'message' => 'Setup completed successfully']);

$conn->close();
?>
