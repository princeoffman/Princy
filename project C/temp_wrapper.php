<?php
$_SERVER["REQUEST_METHOD"] = "POST";
function file_get_contents($filename) {
    if ($filename === "php://input") {
        return '{"user_id":1,"project_id":1}';
    }
    return \file_get_contents($filename);
}
include "save_project.php";
?>