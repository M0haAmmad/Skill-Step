<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "skill_step";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Failed to connect to database: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");
?>