<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'db_connection.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lesson_id']) || !isset($data['watched_pct'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$student_id = intval($_SESSION['user_id']);
$lesson_id = intval($data['lesson_id']);
$watched_pct = intval($data['watched_pct']);
$is_complete = $watched_pct >= 90 ? 1 : 0;

// Fetch course_id from lesson to ensure valid enrollment
$q_course = mysqli_query($conn, "SELECT course_id FROM lessons WHERE lesson_id = $lesson_id");
if (!$q_course || mysqli_num_rows($q_course) == 0) {
    echo json_encode(['success' => false, 'message' => 'Lesson not found']);
    exit();
}
$course_id = mysqli_fetch_assoc($q_course)['course_id'];

// Verify course creator cannot update progress on their own course
$q_creator = mysqli_query($conn, "SELECT creator_id FROM courses WHERE course_id = $course_id");
$creator = mysqli_fetch_assoc($q_creator);
if ($creator && $creator['creator_id'] == $student_id) {
    echo json_encode(['success' => false, 'message' => 'Course creators cannot track progress on their own courses']);
    exit();
}

// Upsert progress
$query = "
    INSERT INTO progress (student_id, lesson_id, watched_pct, is_complete, completed_at) 
    VALUES (?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
    watched_pct = GREATEST(watched_pct, VALUES(watched_pct)),
    is_complete = GREATEST(is_complete, VALUES(is_complete)),
    completed_at = IF(VALUES(is_complete) = 1 AND is_complete = 0, VALUES(completed_at), completed_at)
";

$stmt = mysqli_prepare($conn, $query);
$completed_time = $is_complete ? date('Y-m-d H:i:s') : null;
mysqli_stmt_bind_param($stmt, "iiiis", $student_id, $lesson_id, $watched_pct, $is_complete, $completed_time);

if (mysqli_stmt_execute($stmt)) {
    // Check if course is now 100% complete
    $total_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM lessons WHERE course_id = $course_id");
    $total_lessons = mysqli_fetch_assoc($total_q)['total'];

    $comp_q = mysqli_query($conn, "SELECT COUNT(*) as comp FROM progress WHERE student_id = $student_id AND lesson_id IN (SELECT lesson_id FROM lessons WHERE course_id = $course_id) AND is_complete = 1");
    $comp_lessons = mysqli_fetch_assoc($comp_q)['comp'];

    $course_finished = ($total_lessons > 0 && $comp_lessons == $total_lessons);
    
    // Check if course has quiz and if it's passed
    $course_info_q = mysqli_query($conn, "SELECT has_quiz FROM courses WHERE course_id = $course_id");
    $course_info = mysqli_fetch_assoc($course_info_q);
    
    if ($course_info['has_quiz']) {
        // If course has quiz, check if student passed it
        $quiz_q = mysqli_query($conn, "SELECT quiz_id FROM quizzes WHERE course_id = $course_id");
        $quiz = mysqli_fetch_assoc($quiz_q);
        if ($quiz) {
            $passed_q = mysqli_query($conn, "SELECT 1 FROM quiz_attempts WHERE user_id = $student_id AND quiz_id = {$quiz['quiz_id']} AND passed = 1 LIMIT 1");
            $course_finished = $course_finished && (mysqli_num_rows($passed_q) > 0);
        }
    }
    
    $new_course_progress = ($total_lessons > 0) ? round(($comp_lessons / $total_lessons) * 100) : 0;

    if ($course_finished) {
        // Check if certificate already exists
        $chk_cert = mysqli_query($conn, "SELECT 1 FROM certificates WHERE user_id = $student_id AND course_id = $course_id");
        if (mysqli_num_rows($chk_cert) == 0) {
            $qr_token = md5($student_id . "_" . $course_id . "_" . time());
            mysqli_query($conn, "INSERT INTO certificates (user_id, course_id, qr_token) VALUES ($student_id, $course_id, '$qr_token')");
            
            // Send notification
            $c_title_q = mysqli_query($conn, "SELECT title FROM courses WHERE course_id = $course_id");
            $c_title = mysqli_fetch_assoc($c_title_q)['title'];
            $notif_title = "Congratulations! You've earned a certificate";
            $notif_body = "You have successfully completed the course \"" . $c_title . "\" and earned your Certificate of Completion.";
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, body) VALUES ($student_id, '$notif_title', '$notif_body')");
        }
    }

    echo json_encode([
        'success' => true, 
        'is_complete' => $is_complete, 
        'course_finished' => $course_finished,
        'new_course_progress' => $new_course_progress
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
