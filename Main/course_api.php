<?php
session_start();
require_once 'db_connection.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Course ID is required']);
    exit();
}

$course_id = intval($_GET['id']);

$query = "
    SELECT c.*, u.full_name as creator_name,
           (SELECT name FROM categories JOIN skills ON skills.category_id = categories.category_id WHERE skills.skill_id = c.skill_id LIMIT 1) as category_name
    FROM courses c
    JOIN users u ON c.creator_id = u.user_id
    WHERE c.course_id = ?
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $course_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$course = mysqli_fetch_assoc($res);

if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Course not found']);
    exit();
}

// Fetch lessons
$l_query = "SELECT title, order_index FROM lessons WHERE course_id = ? ORDER BY order_index ASC";
$l_stmt = mysqli_prepare($conn, $l_query);
mysqli_stmt_bind_param($l_stmt, "i", $course_id);
mysqli_stmt_execute($l_stmt);
$l_res = mysqli_stmt_get_result($l_stmt);
$lessons = [];
while ($row = mysqli_fetch_assoc($l_res)) {
    $lessons[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => [
        'title' => $course['title'],
        'creator_id' => $course['creator_id'],
        'creator_name' => $course['creator_name'],
        'category_name' => $course['category_name'],
        'price_tokens' => $course['price_tokens'],
        'description' => $course['description'] ?? 'لا يوجد وصف متاح.',
        'lessons' => $lessons
    ]
]);
