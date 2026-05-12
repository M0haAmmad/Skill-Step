<?php
session_start();
header('Content-Type: application/json');

// Read raw input early so JSON requests are recognized correctly.
$raw_input = file_get_contents('php://input');
$is_json_request = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

// Check if post_max_size was exceeded (POST is empty but CONTENT_LENGTH > 0)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    empty($_POST) &&
    empty($_FILES) &&
    (!($is_json_request && strlen(trim($raw_input)) > 0)) &&
    isset($_SERVER['CONTENT_LENGTH']) &&
    $_SERVER['CONTENT_LENGTH'] > 0
) {
    $post_max = ini_get('post_max_size');
    $upload_max = ini_get('upload_max_filesize');
    echo json_encode(['success' => false, 'message' => "حجم الملفات كبير جداً. الحد الأقصى المسموح به حالياً هو post_max_size=$post_max و upload_max_filesize=$upload_max. يرجى رفع ملفات أصغر أو تعديل الإعدادات."]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once '../Main/db_connection.php';
require_once '../Main/level_helper.php';
$user_id = intval($_SESSION['user_id']);

$response = ['success' => false, 'message' => 'No action performed'];

// Unify JSON body and POST body
$data = json_decode(file_get_contents('php://input'), true);
$req = is_array($data) ? $data : $_POST;

// Handle Profile Picture Upload
if (isset($_FILES['profilePic']) && !isset($req['action'])) {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["profilePic"]["name"], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($file_extension, $allowed_types)) {
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["profilePic"]["tmp_name"], $target_file)) {
            $query = "UPDATE users SET profile_pic = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "si", $new_filename, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => 'Profile picture updated successfully', 'fileName' => $new_filename];
            } else {
                $response = ['success' => false, 'message' => 'Failed to save to database'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Error uploading file'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid file format. Only JPG, PNG, GIF, and WEBP are allowed.'];
    }
}
// Handle Name Update
else if (isset($req['firstName']) || isset($req['lastName'])) {
    $query = "SELECT full_name FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $currentName = explode(' ', trim($row['full_name']));
    $firstName = isset($req['firstName']) && !empty($req['firstName']) ? trim($req['firstName']) : $currentName[0];
    $lastName = isset($req['lastName']) && !empty($req['lastName']) ? trim($req['lastName']) : (isset($currentName[1]) ? $currentName[1] : '');
    
    $newFullName = trim($firstName . ' ' . $lastName);
    
    $updateQuery = "UPDATE users SET full_name = ? WHERE user_id = ?";
    $upStmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($upStmt, "si", $newFullName, $user_id);
    
    if (mysqli_stmt_execute($upStmt)) {
        $response = ['success' => true, 'message' => 'Name updated successfully', 'newName' => $newFullName];
    } else {
        $response = ['success' => false, 'message' => 'Error updating name in database: ' . mysqli_error($conn)];
    }
} 
// Handle Simulate Sale
else if (isset($req['action']) && $req['action'] === 'simulate_sale') {
    $earned_tokens = rand(50, 250); // E.g., someone bought a course!
    
    $query = "UPDATE wallet SET lifetime_earned = lifetime_earned + ?, token_balance = token_balance + ? WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iii", $earned_tokens, $earned_tokens, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_query($conn, "SELECT lifetime_earned FROM wallet WHERE user_id = $user_id");
        $new_balance = mysqli_fetch_assoc($res)['lifetime_earned'];
        $response = ['success' => true, 'new_balance' => $new_balance, 'earned' => $earned_tokens];
    } else {
        $response = ['success' => false, 'message' => 'Failed to simulate sale'];
    }
}
// Handle Mark Notification as Read
else if (isset($req['action']) && $req['action'] === 'mark_notif_read') {
    $notif_id = intval($req['notif_id'] ?? 0);
    if ($notif_id > 0) {
        $update = mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE notification_id = $notif_id AND user_id = $user_id");
        $response = ['success' => !!$update];
    } else {
        $response = ['success' => false, 'message' => 'Invalid notification ID'];
    }
}
// Handle Add New Course/Skill User Creator system
else if (isset($req['action']) && $req['action'] === 'add_skill') {
    $title = trim($req['title'] ?? '');
    $cat = trim($req['category'] ?? '');
    $icon = trim($req['icon'] ?? 'fa-solid fa-code');
    $price = intval($req['price'] ?? 0);
    $xp = intval($req['xp'] ?? 100);
    $support_type = trim($req['support_type'] ?? 'دعم 24/7');
    $free_lessons = intval($req['free_lessons_count'] ?? 0);
    $desc = trim($req['description'] ?? '');
    
    if ($price > 3000) {
        echo json_encode(['success' => false, 'message' => 'عذراً، أقصى سعر مسموح به هو 3000 توكن.']);
        exit();
    }
    
    $status = ($price < 1000) ? 'active' : 'pending_review';
    
    // Validate videos
    if (!isset($_FILES['videos']) || count($_FILES['videos']['name']) == 0 || $_FILES['videos']['error'][0] == UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'Pleae select at least one video']);
        exit();
    }
    
    $num_videos = count($_FILES['videos']['name']);
    
    if ($title) {
        // Map category string to Skill_id
        $skill_q = mysqli_query($conn, "SELECT skill_id FROM skills WHERE skill_name = '$cat' LIMIT 1");
        $skill_row = mysqli_fetch_assoc($skill_q);
        $db_skill_id = $skill_row ? $skill_row['skill_id'] : 1;

        // Insert Course First
        $query = "INSERT INTO courses (creator_id, skill_id, title, description, price_tokens, status, free_lessons_count) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iissisi", $user_id, $db_skill_id, $title, $desc, $price, $status, $free_lessons);
        
        if (mysqli_stmt_execute($stmt)) {
            $course_id = mysqli_insert_id($conn);

            // Save Quiz if provided
            if (isset($_POST['quiz_data'])) {
                $quiz_data = json_decode($_POST['quiz_data'], true);
                if ($quiz_data && is_array($quiz_data)) {
                    // Mark course as having a quiz
                    mysqli_query($conn, "UPDATE courses SET has_quiz = 1 WHERE course_id = $course_id");
                    
                    // Create Quiz record
                    mysqli_query($conn, "INSERT INTO quizzes (course_id) VALUES ($course_id)");
                    $quiz_id = mysqli_insert_id($conn);
                    
                    foreach ($quiz_data as $index => $q) {
                        $q_text = mysqli_real_escape_string($conn, $q['question']);
                        mysqli_query($conn, "INSERT INTO quiz_questions (quiz_id, question_text, order_index) VALUES ($quiz_id, '$q_text', $index)");
                        $question_id = mysqli_insert_id($conn);
                        
                        foreach ($q['choices'] as $c) {
                            $c_text = mysqli_real_escape_string($conn, $c['text']);
                            $is_correct = $c['is_correct'] ? 1 : 0;
                            mysqli_query($conn, "INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES ($question_id, '$c_text', $is_correct)");
                        }
                    }
                }
            }

            // Save videos
            $videos_dir = "uploads/videos/";
            if (!is_dir($videos_dir)) mkdir($videos_dir, 0777, true);
            
            // Upload Videos Loop
            for ($i = 0; $i < $num_videos; $i++) {
                $vid_name = $_FILES['videos']['name'][$i];
                $vid_tmp = $_FILES['videos']['tmp_name'][$i];
                $vid_error = $_FILES['videos']['error'][$i];
                
                if ($vid_error === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($vid_name, PATHINFO_EXTENSION));
                    $safe_vid_name = "course_" . $course_id . "_vid_" . time() . "_" . $i . "." . $ext;
                    $target_vid_file = $videos_dir . $safe_vid_name;
                    
                    if (move_uploaded_file($vid_tmp, $target_vid_file)) {
                        $v_title = isset($_POST['lesson_titles'][$i]) ? mysqli_real_escape_string($conn, $_POST['lesson_titles'][$i]) : "Lesson " . ($i + 1);
                        // Provide a default duration (e.g., 60 seconds) to satisfy the CHECK (duration_seconds > 0) constraint
                        $v_duration = 60; 
                        $vq = "INSERT INTO lessons (course_id, title, video_path, duration_seconds, order_index) VALUES (?, ?, ?, ?, ?)";
                        $vstmt = mysqli_prepare($conn, $vq);
                        mysqli_stmt_bind_param($vstmt, "issii", $course_id, $v_title, $safe_vid_name, $v_duration, $i);
                        mysqli_stmt_execute($vstmt);
                    }
                }
            }
            
            // Reward user for uploading course
            $response = [
                'success' => true, 
                'message' => 'Course published'
            ];
        } else {
            $response = ['success' => false, 'message' => 'Database error executing insert: ' . mysqli_error($conn)];
        }
    } else {
        $response = ['success' => false, 'message' => 'Missing title'];
    }
}
// Handle Delete Course
else if (isset($req['action']) && $req['action'] === 'delete_skill') {
    $skill_id = intval($req['skill_id'] ?? 0);
    $chq = "SELECT creator_id AS user_id FROM courses WHERE course_id = ?";
    $chst = mysqli_prepare($conn, $chq);
    mysqli_stmt_bind_param($chst, "i", $skill_id);
    mysqli_stmt_execute($chst);
    $cres = mysqli_stmt_get_result($chst);
    $srow = mysqli_fetch_assoc($cres);
    
    if($srow && ($srow['user_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        $vq = "SELECT video_path AS file_path FROM lessons WHERE course_id = ?";

        $vst = mysqli_prepare($conn, $vq);
        mysqli_stmt_bind_param($vst, "i", $skill_id);
        mysqli_stmt_execute($vst);
        $vRes = mysqli_stmt_get_result($vst);
        
        while($vFile = mysqli_fetch_assoc($vRes)) {
            $fp = "uploads/videos/" . $vFile['file_path'];
            if(file_exists($fp) && !empty($vFile['file_path'])) unlink($fp);
        }
        
        // Manual cleanup of dependent records to avoid foreign key errors
        mysqli_query($conn, "DELETE FROM quiz_choices WHERE question_id IN (SELECT question_id FROM quiz_questions WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = $skill_id))");
        mysqli_query($conn, "DELETE FROM quiz_questions WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = $skill_id)");
        mysqli_query($conn, "DELETE FROM quiz_attempts WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = $skill_id)");
        mysqli_query($conn, "DELETE FROM quizzes WHERE course_id = $skill_id");
        mysqli_query($conn, "DELETE FROM progress WHERE student_id IN (SELECT student_id FROM enrollments WHERE course_id = $skill_id)");
        mysqli_query($conn, "DELETE FROM progress WHERE lesson_id IN (SELECT lesson_id FROM lessons WHERE course_id = $skill_id)");
        mysqli_query($conn, "DELETE FROM lessons WHERE course_id = $skill_id");
        mysqli_query($conn, "DELETE FROM escrow WHERE payment_id IN (SELECT payment_id FROM payments WHERE course_id = $skill_id)");
        mysqli_query($conn, "DELETE FROM payments WHERE course_id = $skill_id");
        mysqli_query($conn, "DELETE FROM enrollments WHERE course_id = $skill_id");
        mysqli_query($conn, "DELETE FROM certificates WHERE course_id = $skill_id");

        $dq = "DELETE FROM courses WHERE course_id = ?";
        $dst = mysqli_prepare($conn, $dq);
        mysqli_stmt_bind_param($dst, "i", $skill_id);
        if(mysqli_stmt_execute($dst)) {
            $response = ['success' => true, 'message' => 'Course deleted'];
        } else {
            $response = ['success' => false, 'message' => 'DB error: ' . mysqli_error($conn)];
        }
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}
// Handle Edit Course
else if (isset($req['action']) && $req['action'] === 'edit_skill') {
    $skill_id = intval($req['skill_id'] ?? 0);
    $title = trim($req['title'] ?? '');
    $cat = trim($req['category'] ?? '');
    $icon = trim($req['icon'] ?? 'fa-solid fa-code');
    $price = intval($req['price'] ?? 0);
    $xp = intval($req['xp'] ?? 100);
    $support_type = trim($req['support_type'] ?? 'دعم 24/7');
    $desc = trim($req['description'] ?? '');
    
    $chq = "SELECT creator_id AS user_id FROM courses WHERE course_id = ?";
    $chst = mysqli_prepare($conn, $chq);
    mysqli_stmt_bind_param($chst, "i", $skill_id);
    mysqli_stmt_execute($chst);
    $srow = mysqli_fetch_assoc(mysqli_stmt_get_result($chst));
    
    if($title && $srow && ($srow['user_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        $skill_q = mysqli_query($conn, "SELECT skill_id FROM skills WHERE skill_name = '$cat' LIMIT 1");
        $skill_row = mysqli_fetch_assoc($skill_q);
        $db_skill_id = $skill_row ? $skill_row['skill_id'] : 1;

        $free_lessons = intval($req['free_lessons_count'] ?? 0);
        $status = ($price < 1000) ? 'active' : 'pending_review';

        $uq = "UPDATE courses SET title=?, skill_id=?, price_tokens=?, description=?, status=?, free_lessons_count=? WHERE course_id=?";
        $ust = mysqli_prepare($conn, $uq);
        mysqli_stmt_bind_param($ust, "siissii", $title, $db_skill_id, $price, $desc, $status, $free_lessons, $skill_id);
        
        if (mysqli_stmt_execute($ust)) {
            // Check if there are new videos
            if (isset($_FILES['videos']) && count($_FILES['videos']['name']) > 0 && $_FILES['videos']['error'][0] != UPLOAD_ERR_NO_FILE) {
                $num_videos = count($_FILES['videos']['name']);
                $videos_dir = "uploads/videos/";
                if (!is_dir($videos_dir)) {
                    mkdir($videos_dir, 0777, true);
                }
                
                // Get the next order_index
                $max_order_q = "SELECT COALESCE(MAX(order_index), -1) + 1 AS next_order FROM lessons WHERE course_id = ?";
                $max_stmt = mysqli_prepare($conn, $max_order_q);
                mysqli_stmt_bind_param($max_stmt, "i", $skill_id);
                mysqli_stmt_execute($max_stmt);
                $max_res = mysqli_stmt_get_result($max_stmt);
                $next_order = mysqli_fetch_assoc($max_res)['next_order'];
                
                for ($i = 0; $i < $num_videos; $i++) {
                    $vid_name = $_FILES['videos']['name'][$i];
                    $vid_tmp = $_FILES['videos']['tmp_name'][$i];
                    if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($vid_name, PATHINFO_EXTENSION));
                        $safe_vid_name = "course_" . $skill_id . "_vid_" . time() . "_" . $i . "." . $ext;
                        if (move_uploaded_file($vid_tmp, $videos_dir . $safe_vid_name)) {
                            $v_title = isset($_POST['lesson_titles'][$i]) ? mysqli_real_escape_string($conn, $_POST['lesson_titles'][$i]) : "Lesson " . ($next_order + $i + 1);
                            $order_index = $next_order + $i;
                            $duration_seconds = 60;
                            $vq = "INSERT INTO lessons (course_id, title, video_path, duration_seconds, order_index) VALUES (?, ?, ?, ?, ?)";
                            $vstmt = mysqli_prepare($conn, $vq);
                            mysqli_stmt_bind_param($vstmt, "issii", $skill_id, $v_title, $safe_vid_name, $duration_seconds, $order_index);
                            mysqli_stmt_execute($vstmt);
                        }
                    }
                }
            }
            $response = ['success' => true, 'message' => 'Course updated successfully'];
        } else {
            $response = ['success' => false, 'message' => 'DB error update'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized or invalid data'];
    }
}
// Handle Delete Single Video
else if (isset($req['action']) && $req['action'] === 'delete_single_video') {
    $video_id = intval($req['video_id'] ?? 0);
    $skill_id = intval($req['skill_id'] ?? 0);
    
    $chq = "SELECT creator_id AS user_id FROM courses WHERE course_id = ?";
    $chst = mysqli_prepare($conn, $chq);
    mysqli_stmt_bind_param($chst, "i", $skill_id);
    mysqli_stmt_execute($chst);
    $srow = mysqli_fetch_assoc(mysqli_stmt_get_result($chst));
    
    if($srow && ($srow['user_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        $vq = "SELECT video_path AS file_path FROM lessons WHERE lesson_id = ? AND course_id = ?";
        $vst = mysqli_prepare($conn, $vq);
        mysqli_stmt_bind_param($vst, "ii", $video_id, $skill_id);
        mysqli_stmt_execute($vst);
        $v_row = mysqli_fetch_assoc(mysqli_stmt_get_result($vst));
        
        if($v_row) {
            $fp = "uploads/videos/" . $v_row['file_path'];
            if(file_exists($fp) && !empty($v_row['file_path'])) unlink($fp);
            
            $dq = "DELETE FROM lessons WHERE lesson_id = ?";
            $dst = mysqli_prepare($conn, $dq);
            mysqli_stmt_bind_param($dst, "i", $video_id);
            mysqli_stmt_execute($dst);
            $response = ['success' => true, 'message' => 'Video deleted'];
        } else {
            $response = ['success' => false, 'message' => 'Video not found'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}
// Handle Quiz Actions
else if (isset($req['action']) && $req['action'] === 'delete_question') {
    $question_id = intval($req['question_id'] ?? 0);
    
    // Check ownership
    $chk_q = "SELECT q.quiz_id, z.course_id, c.creator_id FROM quiz_questions q JOIN quizzes z ON q.quiz_id = z.quiz_id JOIN courses c ON z.course_id = c.course_id WHERE q.question_id = ?";
    $chk_stmt = mysqli_prepare($conn, $chk_q);
    mysqli_stmt_bind_param($chk_stmt, "i", $question_id);
    mysqli_stmt_execute($chk_stmt);
    $chk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt));
    
    if ($chk_row && ($chk_row['creator_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        // Delete choices first
        mysqli_query($conn, "DELETE FROM quiz_choices WHERE question_id = $question_id");
        // Delete question
        mysqli_query($conn, "DELETE FROM quiz_questions WHERE question_id = $question_id");
        $response = ['success' => true, 'message' => 'Question deleted'];
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}
else if (isset($req['action']) && $req['action'] === 'delete_choice') {
    $choice_id = intval($req['choice_id'] ?? 0);
    
    // Check ownership
    $chk_q = "SELECT qc.choice_id, c.creator_id FROM quiz_choices qc JOIN quiz_questions qq ON qc.question_id = qq.question_id JOIN quizzes z ON qq.quiz_id = z.quiz_id JOIN courses c ON z.course_id = c.course_id WHERE qc.choice_id = ?";
    $chk_stmt = mysqli_prepare($conn, $chk_q);
    mysqli_stmt_bind_param($chk_stmt, "i", $choice_id);
    mysqli_stmt_execute($chk_stmt);
    $chk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt));
    
    if ($chk_row && ($chk_row['creator_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        mysqli_query($conn, "DELETE FROM quiz_choices WHERE choice_id = $choice_id");
        $response = ['success' => true, 'message' => 'Choice deleted'];
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}
else if (isset($req['action']) && $req['action'] === 'update_correct_choice') {
    $question_id = intval($req['question_id'] ?? 0);
    $choice_id = intval($req['choice_id'] ?? 0);
    
    // Check ownership
    $chk_q = "SELECT qq.question_id, c.creator_id FROM quiz_questions qq JOIN quizzes z ON qq.quiz_id = z.quiz_id JOIN courses c ON z.course_id = c.course_id WHERE qq.question_id = ?";
    $chk_stmt = mysqli_prepare($conn, $chk_q);
    mysqli_stmt_bind_param($chk_stmt, "i", $question_id);
    mysqli_stmt_execute($chk_stmt);
    $chk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt));
    
    if ($chk_row && ($chk_row['creator_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        // Reset all choices for this question
        mysqli_query($conn, "UPDATE quiz_choices SET is_correct = 0 WHERE question_id = $question_id");
        // Set the correct one
        mysqli_query($conn, "UPDATE quiz_choices SET is_correct = 1 WHERE choice_id = $choice_id");
        $response = ['success' => true, 'message' => 'Correct choice updated'];
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}
else if (isset($req['action']) && $req['action'] === 'save_quiz') {
    $quiz_id = intval($req['quiz_id'] ?? 0);
    $questions = $req['questions'] ?? [];
    
    // Check ownership
    $chk_q = "SELECT z.quiz_id, c.creator_id FROM quizzes z JOIN courses c ON z.course_id = c.course_id WHERE z.quiz_id = ?";
    $chk_stmt = mysqli_prepare($conn, $chk_q);
    mysqli_stmt_bind_param($chk_stmt, "i", $quiz_id);
    mysqli_stmt_execute($chk_stmt);
    $chk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt));
    
    if ($chk_row && ($chk_row['creator_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        // Delete existing questions and choices
        mysqli_query($conn, "DELETE FROM quiz_choices WHERE question_id IN (SELECT question_id FROM quiz_questions WHERE quiz_id = $quiz_id)");
        mysqli_query($conn, "DELETE FROM quiz_questions WHERE quiz_id = $quiz_id");
        
        // Insert new questions and choices
        foreach ($questions as $q) {
            $q_text = mysqli_real_escape_string($conn, $q['text']);
            mysqli_query($conn, "INSERT INTO quiz_questions (quiz_id, question_text) VALUES ($quiz_id, '$q_text')");
            $question_id = mysqli_insert_id($conn);
            
            foreach ($q['choices'] as $c) {
                $c_text = mysqli_real_escape_string($conn, $c['text']);
                $is_correct = $c['is_correct'] ? 1 : 0;
                mysqli_query($conn, "INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES ($question_id, '$c_text', $is_correct)");
            }
        }
        
        $response = ['success' => true, 'message' => 'Quiz saved successfully'];
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}
// Handle Add Quiz
else if (isset($req['action']) && $req['action'] === 'add_quiz') {
    $course_id = intval($req['course_id'] ?? 0);
    
    $chq = "SELECT creator_id AS user_id FROM courses WHERE course_id = ?";
    $chst = mysqli_prepare($conn, $chq);
    mysqli_stmt_bind_param($chst, "i", $course_id);
    mysqli_stmt_execute($chst);
    $srow = mysqli_fetch_assoc(mysqli_stmt_get_result($chst));
    
    if($srow && ($srow['user_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        // Check if quiz already exists
        $chk_q = mysqli_query($conn, "SELECT quiz_id FROM quizzes WHERE course_id = $course_id");
        if (mysqli_num_rows($chk_q) == 0) {
            mysqli_query($conn, "INSERT INTO quizzes (course_id) VALUES ($course_id)");
            mysqli_query($conn, "UPDATE courses SET has_quiz = 1 WHERE course_id = $course_id");
            $response = ['success' => true, 'message' => 'Quiz added successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Quiz already exists'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}
// Handle Delete Quiz
else if (isset($req['action']) && $req['action'] === 'delete_quiz') {
    $course_id = intval($req['course_id'] ?? 0);
    
    $chq = "SELECT creator_id AS user_id FROM courses WHERE course_id = ?";
    $chst = mysqli_prepare($conn, $chq);
    mysqli_stmt_bind_param($chst, "i", $course_id);
    mysqli_stmt_execute($chst);
    $srow = mysqli_fetch_assoc(mysqli_stmt_get_result($chst));
    
    if($srow && ($srow['user_id'] == $user_id || strpos($_SESSION['roles'], 'admin') !== false)) {
        // Delete quiz data
        mysqli_query($conn, "DELETE FROM quiz_choices WHERE question_id IN (SELECT question_id FROM quiz_questions WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = $course_id))");
        mysqli_query($conn, "DELETE FROM quiz_questions WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = $course_id)");
        mysqli_query($conn, "DELETE FROM quiz_attempts WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = $course_id)");
        mysqli_query($conn, "DELETE FROM quizzes WHERE course_id = $course_id");
        mysqli_query($conn, "UPDATE courses SET has_quiz = 0 WHERE course_id = $course_id");
        $response = ['success' => true, 'message' => 'Quiz deleted successfully'];
    } else {
        $response = ['success' => false, 'message' => 'Unauthorized'];
    }
}

echo json_encode($response);
?>
