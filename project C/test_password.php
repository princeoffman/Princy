<?php
$hash = '$2y$10$jxjFxPTXFfHiEsdbPCgamuPj1o/cDYp3hGs/CH4IqLsJeGWNCJeo6';
$password = 'password';
$result = password_verify($password, $hash);
echo $result ? 'true' : 'false';
?>
