<?php
// Test script for save_project.php
$_SERVER['REQUEST_METHOD'] = 'POST';

// Simulate php://input by creating a stream
$stream = fopen('php://memory', 'r+');
fwrite($stream, '{"user_id":1,"project_id":1}');
rewind($stream);

// Override file_get_contents for this test
function file_get_contents($filename) {
    if ($filename === 'php://input') {
        global $stream;
        return stream_get_contents($stream);
    }
    return \file_get_contents($filename);
}

include 'save_project.php';

// Clean up
fclose($stream);
?>
