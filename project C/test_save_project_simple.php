<?php
// Simple test script for save_project.php
$_SERVER['REQUEST_METHOD'] = 'POST';

// Create a temporary file to simulate php://input
$temp_file = tempnam(sys_get_temp_dir(), 'php_input');
file_put_contents($temp_file, '{"user_id":1,"project_id":1}');

// Create a wrapper script that includes save_project.php
$wrapper_code = '<?php
$_SERVER["REQUEST_METHOD"] = "POST";
function file_get_contents($filename) {
    if ($filename === "php://input") {
        return \'{"user_id":1,"project_id":1}\';
    }
    return \file_get_contents($filename);
}
include "save_project.php";
?>';

file_put_contents('temp_wrapper.php', $wrapper_code);
include 'temp_wrapper.php';

// Clean up
unlink('temp_wrapper.php');
unlink($temp_file);
?>
