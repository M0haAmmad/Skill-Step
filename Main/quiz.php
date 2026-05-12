<?php
session_start();
require_once 'db_connection.php';
require_once 'auth_check.php';

$user = checkUserSession($conn);
$user_id = $user['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0) die('Invalid course ID');

// Get course details
$query = "SELECT creator_id, title, quiz_pass_score, has_quiz FROM courses WHERE course_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $course_id);
mysqli_stmt_execute($stmt);
$course = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$course) die('Course not found');
$is_creator = ($course['creator_id'] == $user_id);
$is_admin = (strpos($user['roles'], 'admin') !== false);

// Check enrollment if not creator/admin
if (!$is_creator && !$is_admin) {
    $chk_en = mysqli_query($conn, "SELECT 1 FROM enrollments WHERE student_id = $user_id AND course_id = $course_id AND is_active = 1");
    if (mysqli_num_rows($chk_en) == 0) {
        die('You must be enrolled to take the quiz.');
    }
}

// Get or create Quiz
$q_quiz = mysqli_query($conn, "SELECT * FROM quizzes WHERE course_id = $course_id");
$quiz = mysqli_fetch_assoc($q_quiz);

if (!$quiz && $is_creator) {
    // Creator accesses quiz page for first time
    mysqli_query($conn, "INSERT INTO quizzes (course_id) VALUES ($course_id)");
    mysqli_query($conn, "UPDATE courses SET has_quiz = 1, quiz_pass_score = 70 WHERE course_id = $course_id");
    header("Refresh:0");
    exit;
} elseif (!$quiz) {
    die('This course does not have a quiz yet.');
}

$quiz_id = $quiz['quiz_id'];
$pass_score = $course['quiz_pass_score'] ?? 70;
$show_retry = false;

// Handle Student Quiz Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $score = 0;
    $total_q = 0;
    $correct_count = 0;
    $answers = $_POST['answers'] ?? [];
    
    // Calculate Score
    $q_ans = mysqli_query($conn, "SELECT q.question_id, c.choice_id FROM quiz_questions q JOIN quiz_choices c ON q.question_id = c.question_id WHERE q.quiz_id = $quiz_id AND c.is_correct = 1");
    $correct_map = [];
    while ($row = mysqli_fetch_assoc($q_ans)) {
        $correct_map[$row['question_id']] = $row['choice_id'];
        $total_q++;
    }
    
    if ($total_q > 0) {
        foreach ($answers as $qid => $cid) {
            if (isset($correct_map[$qid]) && $correct_map[$qid] == $cid) {
                $correct_count++;
            }
        }
        $score = ($correct_count / $total_q) * 100;
    }
    
    $passed = $score >= $pass_score ? 1 : 0;
    
    // Check attempts today
    $chk_att = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM quiz_attempts WHERE user_id = $user_id AND quiz_id = $quiz_id AND DATE(taken_at) = CURDATE()");
    $attempts_today = mysqli_fetch_assoc($chk_att)['cnt'];
    
    if ($attempts_today >= 3) {
        $error = "You have exhausted your three attempts for today. Try again tomorrow.";
    } else {
        $att_no = $attempts_today + 1;
        $ins_att = "INSERT INTO quiz_attempts (user_id, quiz_id, attempt_no, score, passed) VALUES (?, ?, ?, ?, ?)";
        $stmt_att = mysqli_prepare($conn, $ins_att);
        mysqli_stmt_bind_param($stmt_att, "iiidi", $user_id, $quiz_id, $att_no, $score, $passed);
        mysqli_stmt_execute($stmt_att);
        
        $success = "You answered " . $correct_count . " out of " . $total_q . " questions correctly. Final score: " . round($score, 2) . "%. " . ($passed ? "Congratulations, you passed!" : "Unfortunately, you did not reach the required passing score ($pass_score%).");
        if (!$passed) {
            $success .= " You can reload the page to shuffle the questions and try again.";
            $show_retry = true;
        }

        if ($passed) {
            $course_prog_q = mysqli_query($conn, "SELECT COUNT(l.lesson_id) as total, SUM(IF(p.is_complete = 1, 1, 0)) as completed
                FROM lessons l
                LEFT JOIN progress p ON l.lesson_id = p.lesson_id AND p.student_id = $user_id
                WHERE l.course_id = $course_id");
            $course_prog = mysqli_fetch_assoc($course_prog_q);
            $course_complete = ($course_prog['total'] > 0 && $course_prog['total'] == $course_prog['completed']);

            if ($course_complete) {
                $chk_cert = mysqli_query($conn, "SELECT 1 FROM certificates WHERE user_id = $user_id AND course_id = $course_id");
                if (mysqli_num_rows($chk_cert) == 0) {
                    $qr_token = bin2hex(random_bytes(16));
                    $ins_cert = "INSERT INTO certificates (user_id, course_id, qr_token) VALUES (?, ?, ?)";
                    $stmt_cert = mysqli_prepare($conn, $ins_cert);
                    mysqli_stmt_bind_param($stmt_cert, "iis", $user_id, $course_id, $qr_token);
                    mysqli_stmt_execute($stmt_cert);
                }
            }

            mysqli_query($conn, "INSERT INTO notifications (user_id, type, title, body) VALUES ($user_id, 'certificate_issued', 'New Certificate', 'A certificate of completion has been issued for the course: {$course['title']}')");
        }
    }
}

// Handle Creator adding question
if ($is_creator && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $q_text = trim($_POST['question_text']);
    $choices = $_POST['choices'] ?? [];
    $correct_idx = isset($_POST['correct']) ? intval($_POST['correct']) : 0;
    
    if (!empty($q_text) && count($choices) >= 2) {
        mysqli_begin_transaction($conn);
        try {
            $q_order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(order_index), 0) + 1 as nxt FROM quiz_questions WHERE quiz_id = $quiz_id"))['nxt'];
            $ins_q = "INSERT INTO quiz_questions (quiz_id, question_text, order_index) VALUES (?, ?, ?)";
            $stmt_q = mysqli_prepare($conn, $ins_q);
            mysqli_stmt_bind_param($stmt_q, "isi", $quiz_id, $q_text, $q_order);
            mysqli_stmt_execute($stmt_q);
            $qid = mysqli_insert_id($conn);
            
            $ins_c = "INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)";
            $stmt_c = mysqli_prepare($conn, $ins_c);
            foreach ($choices as $idx => $ctext) {
                $trimmed_ctext = trim($ctext);
                if (empty($trimmed_ctext)) continue;
                $isc = ($idx == $correct_idx) ? 1 : 0;
                mysqli_stmt_bind_param($stmt_c, "isi", $qid, $trimmed_ctext, $isc);
                mysqli_stmt_execute($stmt_c);
            }
            mysqli_commit($conn);
            header("Refresh:0");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to add question.";
        }
    }
}

// Fetch Questions
$questions = [];
$order_questions = $is_creator ? 'ORDER BY order_index ASC' : 'ORDER BY RAND()';
$res_q = mysqli_query($conn, "SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id $order_questions");
while ($rq = mysqli_fetch_assoc($res_q)) {
    $choice_order = $is_creator ? 'ORDER BY choice_id ASC' : 'ORDER BY RAND()';
    $c_res = mysqli_query($conn, "SELECT * FROM quiz_choices WHERE question_id = {$rq['question_id']} $choice_order");
    $rq['choices'] = [];
    while ($rc = mysqli_fetch_assoc($c_res)) {
        $rq['choices'][] = $rc;
    }
    $questions[] = $rq;
}

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Course Quiz: <?php echo htmlspecialchars($course['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #0f172a; color: white; padding: 40px; }
        .quiz-container { max-width: 800px; margin: auto; background: rgba(255,255,255,0.05); padding: 30px; border-radius: 15px; }
        .question-block { margin-bottom: 25px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 10px; }
        .choice-label { display: block; margin: 10px 0; cursor: pointer; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .choice-label:hover { background: rgba(255,255,255,0.2); }
        input[type="radio"] { margin-left: 10px; }
        .btn { padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: bold; }
        .btn-primary { background: #3b82f6; }
        .btn-retry { background: #ef4444; }
        .result-card { padding: 20px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.35); backdrop-filter: blur(12px); }
        .result-card-success { background: linear-gradient(180deg, rgba(16,185,129,0.18), rgba(16,185,129,0.08)); border: 1px solid rgba(16,185,129,0.4); }
        .result-card-fail { background: linear-gradient(180deg, rgba(239,68,68,0.16), rgba(239,68,68,0.08)); border: 1px solid rgba(239,68,68,0.4); }
        .result-card-error { background: rgba(248, 113, 113, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171; }
        .result-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 12px; }
        .result-body { font-size: 1rem; line-height: 1.7; margin-bottom: 15px; color: rgba(255,255,255,0.9); }
        .result-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .creator-panel { border: 2px dashed #f59e0b; padding: 20px; margin-bottom: 30px; border-radius: 10px; }
        .creator-panel input[type="text"] { width: 100%; padding: 10px; margin-bottom: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="quiz-container">
        <h1>Quiz: <?php echo htmlspecialchars($course['title']); ?></h1>
        
        <?php if (isset($error)): ?>
            <div class="result-card result-card-error">
                <div class="result-title">Error</div>
                <div class="result-body"><?php echo $error; ?></div>
                <?php if (!empty($show_retry)): ?>
                    <div class="result-actions">
                        <button type="button" onclick="window.location.href='quiz.php?course_id=<?php echo $course_id; ?>';" class="btn btn-retry">Try Again</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="result-card <?php echo $passed ? 'result-card-success' : 'result-card-fail'; ?>">
                <div class="result-title"><?php echo $passed ? 'Passed the quiz!' : 'Failed passing score'; ?></div>
                <div class="result-body"><?php echo $success; ?></div>
                <div class="result-actions">
                    <?php if (isset($passed) && $passed): ?>
                        <a href="certificate.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">View Certificate</a>
                    <?php else: ?>
                        <button type="button" onclick="window.location.href='quiz.php?course_id=<?php echo $course_id; ?>';" class="btn btn-retry">Retake Quiz</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_creator): ?>
            <div class="creator-panel">
                <h3><span style="color:#f59e0b;">⚡ Instructor Panel:</span> Add New Question</h3>
                <form method="POST">
                    <input type="text" name="question_text" placeholder="Question text..." required>
                    <?php for($i=0; $i<4; $i++): ?>
                        <div style="display:flex; align-items:center;">
                            <input type="radio" name="correct" value="<?php echo $i; ?>" <?php echo $i==0?'checked':''; ?> title="Correct Answer">
                            <input type="text" name="choices[<?php echo $i; ?>]" placeholder="Choice <?php echo $i+1; ?>" <?php echo $i<2?'required':''; ?>>
                        </div>
                    <?php endfor; ?>
                    <button type="submit" name="add_question" class="btn" style="background:#f59e0b;">Add Question</button>
                </form>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php if (empty($questions)): ?>
                <p>There are no questions in this quiz yet.</p>
            <?php else: ?>
                <?php foreach ($questions as $index => $q): ?>
                    <div class="question-block">
                        <h4><?php echo ($index + 1) . ". " . htmlspecialchars($q['question_text']); ?></h4>
                        <?php foreach ($q['choices'] as $c): ?>
                            <label class="choice-label">
                                <input type="radio" name="answers[<?php echo $q['question_id']; ?>]" value="<?php echo $c['choice_id']; ?>" required>
                                <?php echo htmlspecialchars($c['choice_text']); ?>
                                <?php if ($is_creator && $c['is_correct']) echo ' <span style="color:#10b981;font-size:0.8em;">(Correct Answer)</span>'; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$is_creator || $is_admin): ?>
                    <button type="submit" name="submit_quiz" class="btn">Submit Answers</button>
                <?php endif; ?>
            <?php endif; ?>
            <a href="course_player.php?id=<?php echo $course_id; ?>" class="btn" style="background:transparent; border:1px solid white; display:inline-block; margin-right:10px;">Back to Course</a>
        </form>
    </div>
</body>
</html>
