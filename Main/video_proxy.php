<?php
session_start();
require_once 'db_connection.php';
require_once 'auth_check.php';

$user = checkUserSession($conn);
$user_id = $user['user_id'];
$is_admin = (strpos($user['roles'], 'admin') !== false);

if (!isset($_GET['lesson_id'])) {
    header("HTTP/1.0 404 Not Found");
    exit('Lesson ID missing');
}

$lesson_id = intval($_GET['lesson_id']);

// Fetch lesson and course details
$query = "
    SELECT l.video_path, l.course_id, c.creator_id, l.order_index, c.free_lessons_count, c.price_tokens
    FROM lessons l 
    JOIN courses c ON l.course_id = c.course_id 
    WHERE l.lesson_id = ?
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $lesson_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$lesson = mysqli_fetch_assoc($res);

if (!$lesson) {
    header("HTTP/1.0 404 Not Found");
    exit('Lesson not found');
}

$course_id = $lesson['course_id'];
$creator_id = $lesson['creator_id'];
$is_price_zero = (intval($lesson['price_tokens'] ?? 0) === 0);
$free_count = intval($lesson['free_lessons_count'] ?? 0);

// Calculate the lesson's 0-based position among course lessons in a deterministic order
$pos_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS lesson_position FROM lessons WHERE course_id = ? AND (order_index < ? OR (order_index = ? AND lesson_id < ?))");
mysqli_stmt_bind_param($pos_stmt, "iiii", $course_id, $lesson['order_index'], $lesson['order_index'], $lesson_id);
mysqli_stmt_execute($pos_stmt);
$pos_res = mysqli_stmt_get_result($pos_stmt);
$lesson_position = 0;
if ($pos_row = mysqli_fetch_assoc($pos_res)) {
    $lesson_position = intval($pos_row['lesson_position']);
}

// Check access rights: Admin, Creator, Free Course, Free Preview, or Enrolled Student
$has_access = false;

if ($is_admin || $user_id == $creator_id || $is_price_zero) {
    $has_access = true;
} elseif ($lesson_position < $free_count) {
    // Within the free preview limit
    $has_access = true;
} else {
    $check_en = "SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?";
    $en_stmt = mysqli_prepare($conn, $check_en);
    mysqli_stmt_bind_param($en_stmt, "ii", $user_id, $course_id);
    mysqli_stmt_execute($en_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($en_stmt)) > 0) {
        $has_access = true;
    }
}

if (!$has_access) {
    header("HTTP/1.0 403 Forbidden");
    exit('Access denied. You must enroll in this course to view its content.');
}

// Serve the video file
$video_path = '../profile/uploads/videos/' . $lesson['video_path'];

if (!file_exists($video_path)) {
    header("HTTP/1.0 404 Not Found");
    exit('Video file not found on server');
}

$mime_type = mime_content_type($video_path);
$file_size = filesize($video_path);

// Partial content handling for seeking (byte-range requests)
$start = 0;
$end = $file_size - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    $c_start = $start;
    $c_end = $end;
    
    list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
    if (strpos($range, ',') !== false) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes $start-$end/$file_size");
        exit;
    }
    
    if ($range == '-') {
        $c_start = $file_size - substr($range, 1);
    } else {
        $range = explode('-', $range);
        $c_start = $range[0];
        $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $file_size - 1;
    }
    $c_end = ($c_end > $end) ? $end : $c_end;
    
    if ($c_start > $c_end || $c_start > $file_size - 1 || $c_end >= $file_size) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes $start-$end/$file_size");
        exit;
    }
    
    $start = $c_start;
    $end = $c_end;
    $length = $end - $start + 1;
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$file_size");
} else {
    $length = $file_size;
}

header("Content-Type: $mime_type");
header("Accept-Ranges: bytes");
header("Content-Length: " . $length);

$file = @fopen($video_path, 'rb');
if (!$file) {
    header("HTTP/1.0 500 Internal Server Error");
    exit('Cannot open file');
}

fseek($file, $start);
$buffer_size = 1024 * 8; // 8KB chunks
$bytes_sent = 0;

while (!feof($file) && ($bytes_sent < $length) && (connection_status() == 0)) {
    $read_length = min($buffer_size, $length - $bytes_sent);
    $buffer = fread($file, $read_length);
    echo $buffer;
    flush();
    $bytes_sent += $read_length;
}

fclose($file);
exit();
?>
