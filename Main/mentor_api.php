<?php
session_start();
require_once 'db_connection.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Mentor ID is required']);
    exit();
}

$mentor_id = intval($_GET['id']);

// Fetch basic user info
$q_user = "SELECT full_name, level, xp, profile_pic FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $q_user);
mysqli_stmt_bind_param($stmt, "i", $mentor_id);
mysqli_stmt_execute($stmt);
$res_user = mysqli_stmt_get_result($stmt);

if (!$user = mysqli_fetch_assoc($res_user)) {
    echo json_encode(['success' => false, 'message' => 'Mentor not found']);
    exit();
}

// Fetch total active courses uploaded
$q_uploaded = "SELECT COUNT(*) as uploaded_count FROM courses WHERE creator_id = ? AND status = 'active'";
$stmt2 = mysqli_prepare($conn, $q_uploaded);
mysqli_stmt_bind_param($stmt2, "i", $mentor_id);
mysqli_stmt_execute($stmt2);
$uploaded = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2))['uploaded_count'];

// Fetch total students across all their courses
$q_students = "SELECT COUNT(*) as student_count FROM enrollments e JOIN courses c ON e.course_id = c.course_id WHERE c.creator_id = ?";
$stmt3 = mysqli_prepare($conn, $q_students);
mysqli_stmt_bind_param($stmt3, "i", $mentor_id);
mysqli_stmt_execute($stmt3);
$students = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3))['student_count'];

// Fetch total courses purchased by this mentor
$q_purchased = "SELECT COUNT(*) as purchased_count FROM enrollments WHERE student_id = ?";
$stmt4 = mysqli_prepare($conn, $q_purchased);
mysqli_stmt_bind_param($stmt4, "i", $mentor_id);
mysqli_stmt_execute($stmt4);
$purchased = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4))['purchased_count'];

// Fetch top 3 active courses
$q_top_courses = "SELECT course_id, title, price_tokens FROM courses WHERE creator_id = ? AND status = 'active' ORDER BY price_tokens DESC LIMIT 3";
$stmt5 = mysqli_prepare($conn, $q_top_courses);
if (!$stmt5) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_bind_param($stmt5, "i", $mentor_id);
mysqli_stmt_execute($stmt5);
$res_top = mysqli_stmt_get_result($stmt5);
$top_courses = [];
while ($row = mysqli_fetch_assoc($res_top)) {
    $row['icon'] = 'fa-book'; // Default icon
    $top_courses[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => [
        'full_name' => $user['full_name'],
        'level' => $user['level'],
        'xp' => $user['xp'],
        'profile_pic' => $user['profile_pic'],
        'uploaded_courses' => $uploaded,
        'total_students' => $students,
        'purchased_courses' => $purchased,
        'top_courses' => $top_courses
    ]
]);
