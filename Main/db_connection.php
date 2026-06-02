<?php
$host = "mysql-1e6a3a9d-skill-step.l.aivencloud.com";
$user = "avnadmin";
$pass = "AVNS_p5NpGhFdOk8dRo1Ek6f";
$db = "defaultdb";
$port = 21164;

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>