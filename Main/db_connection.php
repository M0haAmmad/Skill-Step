<?php
$host = "mysql-1e6a3a9d-skill-step.l.aivencloud.com";
$user = "avnadmin";
$pass = "AVNS_p5NpGhFdOk8dRo1Ek6f"; // ضع الباسورد الحقيقي الخاص بك هنا
$db   = "defaultdb"; // هذا اسم الداتابيز الافتراضية في Aiven التي رفعنا عليها الجداول
$port = 21164;

// إنشاء الاتصال الأونلاين
$conn = mysqli_connect($host, $user, $pass, $db, $port);

// فحص الاتصال
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// دعم اللغة العربية
mysqli_set_charset($conn, "utf8mb4");
?>