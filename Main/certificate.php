<?php
session_start();
require_once 'db_connection.php';
require_once 'auth_check.php';

if (!isset($_GET['course_id'])) {
    die('Course ID is required.');
}

$course_id = intval($_GET['course_id']);
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id == 0) {
    die('Please login to view certificates.');
}

$is_creator = false;
$passed = false;

$q_course = mysqli_query($conn, "SELECT title, creator_id, (SELECT full_name FROM users WHERE user_id = courses.creator_id) as instructor_name FROM courses WHERE course_id = $course_id");
$course = mysqli_fetch_assoc($q_course);
if (!$course)
    die('Course not found');

// Fetch student name from database
$q_student = mysqli_query($conn, "SELECT full_name FROM users WHERE user_id = $user_id");
$student = mysqli_fetch_assoc($q_student);
$student_name = !empty($student['full_name']) ? $student['full_name'] : 'Student';

// Prevent course creator from getting certificate of their own course
if ($course['creator_id'] == $user_id) {
    die('Course creators cannot earn certificates from their own courses.');
}

$q_cert = mysqli_query($conn, "SELECT * FROM certificates WHERE user_id = $user_id AND course_id = $course_id");
$cert = mysqli_fetch_assoc($q_cert);

if ($cert) {
    $passed = true;
} else {
    $q_has_quiz = mysqli_query($conn, "SELECT has_quiz FROM courses WHERE course_id = $course_id");
    $has_quiz = mysqli_fetch_assoc($q_has_quiz)['has_quiz'];

    $q_quiz = mysqli_query($conn, "SELECT quiz_id FROM quizzes WHERE course_id = $course_id");
    $quiz = mysqli_fetch_assoc($q_quiz);

    $q_prog = mysqli_query($conn, "SELECT COUNT(l.lesson_id) as total, SUM(IF(p.is_complete = 1, 1, 0)) as completed
        FROM lessons l
        LEFT JOIN progress p ON l.lesson_id = p.lesson_id AND p.student_id = $user_id
        WHERE l.course_id = $course_id
    ");
    $prog = mysqli_fetch_assoc($q_prog);
    $course_complete = ($prog['total'] > 0 && $prog['total'] == $prog['completed']);

    $has_valid_quiz = false;
    if ($has_quiz && $quiz) {
        $q_count = mysqli_query($conn, "SELECT COUNT(*) as c FROM quiz_questions WHERE quiz_id = {$quiz['quiz_id']}");
        if (mysqli_fetch_assoc($q_count)['c'] > 0) {
            $has_valid_quiz = true;
        }
    }

    $passed = false;
    if ($has_valid_quiz) {
        $q_att = mysqli_query($conn, "SELECT passed FROM quiz_attempts WHERE user_id = $user_id AND quiz_id = {$quiz['quiz_id']} AND passed = 1 LIMIT 1");
        $quiz_passed = mysqli_num_rows($q_att) > 0;
        if ($course_complete && $quiz_passed) {
            $passed = true;
        }
    } else {
        if ($course_complete) {
            $passed = true;
        }
    }

    if (!$passed) {
        die('You have not met the requirements to earn this certificate.');
    }

    $qr_token = bin2hex(random_bytes(16));
    $ins = "INSERT INTO certificates (user_id, course_id, qr_token) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $ins);
    mysqli_stmt_bind_param($stmt, "iis", $user_id, $course_id, $qr_token);
    mysqli_stmt_execute($stmt);
    $cert = ['qr_token' => $qr_token, 'issued_at' => date('Y-m-d H:i:s')];
}
?>

<!DOCTYPE html>
<html lang="en" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion | <?php echo htmlspecialchars($course['title']); ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&family=Cinzel:wght@400;700&family=Great+Vibes&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #c5a059;
            --gold-light: #e6c98a;
            --dark: #0f172a;
            --glass: rgba(255, 255, 255, 0.03);
            --border: rgba(197, 160, 89, 0.3);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #020617;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Cairo', sans-serif;
            color: #f8fafc;
        }

        .controls {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            z-index: 100;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--glass);
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .btn:hover {
            background: var(--gold);
            color: var(--dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(197, 160, 89, 0.2);
        }

        .btn-primary {
            background: var(--gold);
            color: var(--dark);
            border: none;
        }

        .certificate-wrapper {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            width: 1100px;
            height: 780px;
            padding: 40px;
            position: relative;
            border: 2px solid var(--gold);
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }

        /* Decorative Corners */
        .corner {
            position: absolute;
            width: 150px;
            height: 150px;
            border: 4px solid var(--gold);
            z-index: 1;
        }

        .corner-tl {
            top: 20px;
            left: 20px;
            border-right: 0;
            border-bottom: 0;
        }

        .corner-tr {
            top: 20px;
            right: 20px;
            border-left: 0;
            border-bottom: 0;
        }

        .corner-bl {
            bottom: 20px;
            left: 20px;
            border-right: 0;
            border-top: 0;
        }

        .corner-br {
            bottom: 20px;
            right: 20px;
            border-left: 0;
            border-top: 0;
        }

        .inner-border {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            border: 1px solid rgba(197, 160, 89, 0.15);
            pointer-events: none;
        }

        .main-content {
            height: 100%;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 60px;
            text-align: center;
            background: radial-gradient(circle at center, rgba(197, 160, 89, 0.05) 0%, transparent 70%);
        }

        .header-award {
            font-size: 5rem;
            color: var(--gold);
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px rgba(197, 160, 89, 0.3));
        }

        .title-english {
            font-family: 'Cinzel', serif;
            font-size: 3.5rem;
            color: var(--gold-light);
            margin: 0;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .title-arabic {
            font-size: 1.8rem;
            color: #94a3b8;
            margin-bottom: 40px;
            font-weight: 600;
        }

        .presented-to {
            font-size: 1.2rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .student-name {
            font-family: 'Great Vibes', cursive;
            font-size: 4.5rem;
            color: white;
            margin-bottom: 30px;
            background: linear-gradient(to right, #fff, var(--gold-light), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .achievement-text {
            font-size: 1.3rem;
            line-height: 1.6;
            color: #94a3b8;
            max-width: 700px;
            margin-bottom: 40px;
        }

        .course-title {
            font-size: 2.2rem;
            color: var(--gold);
            font-weight: 800;
            margin-bottom: 50px;
        }

        .footer-signatures {
            width: 100%;
            display: flex;
            justify-content: space-around;
            margin-top: auto;
        }

        .sig-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .sig-line {
            width: 250px;
            height: 1px;
            background: var(--border);
            position: relative;
        }

        .sig-name {
            font-family: 'Great Vibes', cursive;
            font-size: 2rem;
            color: var(--gold-light);
            margin-bottom: -5px;
        }

        .sig-title {
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stamp {
            position: absolute;
            bottom: 60px;
            right: 80px;
            width: 140px;
            height: 140px;
            border: 4px double var(--gold);
            border-radius: 50%;
            display: grid;
            place-items: center;
            opacity: 0.2;
            transform: rotate(-15deg);
        }

        .cert-metadata {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: #475569;
            display: flex;
            gap: 30px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                display: block;
            }

            .controls {
                display: none;
            }

            .certificate-wrapper {
                width: 100%;
                height: 100vh;
                border: none;
                box-shadow: none;
                margin: 0;
                background: white !important;
            }
            
            .student-name, .title-english, .title-arabic, .course-title, .achievement-text, .presented-to, .sig-name, .sig-title, .cert-metadata {
                color: black !important;
                background: none !important;
                -webkit-text-fill-color: black !important;
            }
            .header-award {
                color: black !important;
            }
            .stamp {
                opacity: 0.1 !important;
                border-color: black !important;
            }
            .stamp i {
                color: black !important;
            }
            .corner {
                border-color: black !important;
            }
            .sig-line {
                background: black !important;
            }
        }
    </style>
</head>

<body>

    <div class="controls">
        <a href="../Main/index.php" class="btn"><i class="fas fa-home"></i> Home</a>
        <a href="../profile/profile.php" class="btn"><i class="fas fa-user"></i> Profile</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-download"></i> Download PDF / Print</button>
    </div>

    <div class="certificate-wrapper">
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>
        <div class="inner-border"></div>

        <div class="main-content">
            <i class="fas fa-award header-award"></i>

            <h1 class="title-english">Certificate</h1>
            <div class="title-arabic">Certificate of Completion</div>

            <div class="presented-to">This certificate is proudly presented to:</div>

            <div class="student-name"><?php echo htmlspecialchars($student_name); ?></div>

            <div class="achievement-text">
                for successfully completing all educational and skill requirements, and passing the final evaluation for the specialized training course:
            </div>

            <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>

            <div class="footer-signatures">
                <div class="sig-box">
                    <div class="sig-name"><?php echo htmlspecialchars($course['instructor_name']); ?></div>
                    <div class="sig-line"></div>
                    <div class="sig-title">Instructor</div>
                </div>
                <div class="sig-box">
                    <div class="sig-name">Skill-Step Admin</div>
                    <div class="sig-line"></div>
                    <div class="sig-title">Platform Director</div>
                </div>
            </div>

            <div class="stamp">
                <i class="fas fa-shield-halved" style="font-size: 3rem; color: var(--gold);"></i>
            </div>
        </div>

        <div class="cert-metadata">
            <span>Issue Date: <?php echo date('Y-m-d', strtotime($cert['issued_at'])); ?></span>
            <span>Certificate ID: <?php echo strtoupper(substr($cert['qr_token'], 0, 8)); ?></span>
            <span>Verify: skill-step.local/verify?id=<?php echo substr($cert['qr_token'], 0, 12); ?></span>
        </div>
    </div>

</body>

</html>