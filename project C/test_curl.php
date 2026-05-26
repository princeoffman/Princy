<?php
echo 'Testing save_project.php with curl' . PHP_EOL;

$ch = curl_init('http://localhost/project%20C/save_project.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user_id' => 1, 'project_id' => 1]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

echo $response . PHP_EOL;
?>
