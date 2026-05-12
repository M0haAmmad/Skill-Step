<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login/login.php");
    exit();
}

require_once 'db_connection.php';
$user_id = intval($_SESSION['user_id']);
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($course_id <= 0) {
    die("Invalid course ID.");
}

// Fetch course details
$query = "SELECT courses.*, courses.course_id AS skill_id, courses.creator_id AS user_id, 100 AS xp, (SELECT categories.name FROM categories JOIN skills ON skills.category_id = categories.category_id WHERE skills.skill_id = courses.skill_id LIMIT 1) AS category, u.full_name AS User_name, u.profile_pic FROM courses JOIN users u ON courses.creator_id = u.user_id WHERE courses.course_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $course_id);
mysqli_stmt_execute($stmt);
$course = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$course) {
    die("Course not found.");
}

// Check access
$is_owner = ($course['user_id'] == $user_id);
$is_unlocked = false;

$user_query = "SELECT u.profile_pic, w.token_balance FROM users u LEFT JOIN wallet w ON u.user_id = w.user_id WHERE u.user_id = $user_id";
$res_u = mysqli_query($conn, $user_query);
$u_data = mysqli_fetch_assoc($res_u);
$user_tokens = $u_data['token_balance'] ?? 0;

$enroll_q = mysqli_query($conn, "SELECT 1 FROM enrollments WHERE student_id = $user_id AND course_id = $course_id AND is_active = 1");
if ($enroll_q && mysqli_num_rows($enroll_q) > 0) {
    $is_unlocked = true;
}

// Allow entry for free preview if not owner or unlocked
$_is_free_course = (intval($course['price_tokens'] ?? 0) === 0);
$can_preview = ($course['free_lessons_count'] > 0) || $_is_free_course;

if (!$is_owner && !$is_unlocked && !$can_preview) {
    header("Location: ../Main/index.php?msg=access_denied");
    exit();
}

// Fetch videos
$vq = "SELECT lesson_id AS id, course_id AS skill_id, title, video_path AS file_path FROM lessons WHERE course_id = ? ORDER BY order_index ASC";
$vstmt = mysqli_prepare($conn, $vq);
mysqli_stmt_bind_param($vstmt, "i", $course_id);
mysqli_stmt_execute($vstmt);
$vRes = mysqli_stmt_get_result($vstmt);
$videos = [];
while ($row = mysqli_fetch_assoc($vRes)) {
    $videos[] = $row;
}

$_free_count = intval($course['free_lessons_count'] ?? 0);
$_price_zero = (intval($course['price_tokens'] ?? 0) === 0);
$_user_locked = (!$is_owner && !$is_unlocked && !$_price_zero);

// Determine requested lesson index from the URL
$requested_index = isset($_GET['v']) ? intval($_GET['v']) : 0;
$active_video_index = ($requested_index >= 0 && $requested_index < count($videos)) ? $requested_index : 0;


$current_video = count($videos) > 0 ? $videos[$active_video_index] : null;

$user_db_pic = $u_data['profile_pic'] ? $u_data['profile_pic'] : 'default.png';
$pic_path = $user_db_pic !== 'default.png' ? "../profile/uploads/" . htmlspecialchars($user_db_pic) : "";

// Course Progress calculation
$prog_q = mysqli_query($conn, "SELECT lesson_id FROM progress WHERE student_id = $user_id AND lesson_id IN (SELECT lesson_id FROM lessons WHERE course_id = $course_id) AND is_complete = 1");
$completed_lesson_ids = [];
while ($pr = mysqli_fetch_assoc($prog_q)) {
    $completed_lesson_ids[] = $pr['lesson_id'];
}
$completed_lessons_count = count($completed_lesson_ids);
$total_lessons = count($videos);
$course_progress = ($total_lessons > 0) ? round(($completed_lessons_count / $total_lessons) * 100) : 0;
$show_locked_toast = isset($_GET['locked']) && $_GET['locked'] == '1';

// Check if course has a VALID quiz (with questions)
$has_valid_quiz = false;
if ($course['has_quiz']) {
    $quiz_q = mysqli_query($conn, "SELECT quiz_id FROM quizzes WHERE course_id = $course_id");
    $quiz_data = mysqli_fetch_assoc($quiz_q);
    if ($quiz_data) {
        $q_count = mysqli_query($conn, "SELECT COUNT(*) as c FROM quiz_questions WHERE quiz_id = {$quiz_data['quiz_id']}");
        if (mysqli_fetch_assoc($q_count)['c'] > 0) {
            $has_valid_quiz = true;
        }
    }
}

// Check if certificate exists
$chk_cert = mysqli_query($conn, "SELECT 1 FROM certificates WHERE user_id = $user_id AND course_id = $course_id");
$has_certificate = $chk_cert && mysqli_num_rows($chk_cert) > 0;

if (!$has_certificate && $course_progress >= 100) {
    if (!$has_valid_quiz) {
        $has_certificate = true;
    } else if (isset($quiz_data)) {
        $passed_q = mysqli_query($conn, "SELECT 1 FROM quiz_attempts WHERE user_id = $user_id AND quiz_id = {$quiz_data['quiz_id']} AND passed = 1 LIMIT 1");
        if (mysqli_num_rows($passed_q) > 0) {
            $has_certificate = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Player - <?php echo htmlspecialchars($course['title']); ?></title>
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@400;700;800&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="course_player.css">
    <link rel="stylesheet" href="../Main/style.css?v=<?php echo time(); ?>">
</head>

<body>

    <nav>
        <a href="index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50" onerror="this.style.display='none';">
            Skill-Step
        </a>
        <a href="index.php" class="btn-action" style="width:auto; padding: 10px 20px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Platform
        </a>
    </nav>

    <?php if ($is_owner): ?>
        <!-- Owner Mode Banner -->
        <div
            style="background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(251,191,36,0.05)); border-bottom: 1px solid rgba(245,158,11,0.3); padding: 10px 20px; display:flex; align-items:center; gap:12px; font-size:0.9rem;">
            <i class="fa-solid fa-shield-halved" style="color:#f59e0b; font-size:1.1rem;"></i>
            <span style="color:#f59e0b; font-weight:600;">Creator Mode — </span>
            <span style="color:var(--text-muted);">You are viewing this course as the owner. All lessons are unlocked. Students who haven't purchased will see locked lessons.</span>
        </div>
    <?php endif; ?>

    <div class="player-container">
        <!-- Main Video Area -->
        <div class="video-section">
            <div class="video-wrapper">
                <?php
                $free_count = intval($course['free_lessons_count'] ?? 0);
                $is_price_zero = (intval($course['price_tokens'] ?? 0) === 0);
                $is_current_locked = (!$is_owner && !$is_unlocked && !$is_price_zero && $active_video_index >= $free_count);

                if ($current_video && !$is_current_locked): ?>
                    <video id="courseVideo" controls controlsList="nodownload" autoplay>
                        <source src="video_proxy.php?lesson_id=<?php echo htmlspecialchars($current_video['id']); ?>"
                            type="video/mp4">
                        Your browser does not support HTML video.
                    </video>
                <?php elseif ($is_current_locked): ?>
                    <div
                        style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.95); display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; backdrop-filter:blur(10px); z-index:10;">
                        <div
                            style="width:80px; height:80px; background:rgba(255,255,255,0.05); border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:20px; border:1px solid rgba(251, 191, 36, 0.3);">
                            <i class="fa-solid fa-lock" style="font-size:2.5rem; color:var(--accent-gold);"></i>
                        </div>
                        <h2 style="color:white; margin-bottom:10px; font-weight:800;">Premium Content</h2>
                        <p style="color:var(--text-muted); margin-bottom:25px; max-width:400px; font-size:1.05rem;">You have watched the free lessons. To unlock the full course and access all lessons, please confirm your payment to the mentor.</p>
                        <button onclick="openPlayerPurchaseModal()" class="btn-action"
                            style="width:auto; padding:15px 40px; background:linear-gradient(135deg, #fbbf24, #f59e0b); color:black; font-weight:800; border:none; box-shadow:0 10px 25px rgba(245, 158, 11, 0.3); border-radius:12px; font-size:1.1rem; cursor:pointer;">
                            <i class="fa-solid fa-unlock"></i> Confirm payment of <?php echo $course['price_tokens']; ?> tokens
                        </button>
                    </div>
                <?php else: ?>
                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center;">
                        <i class="fa-solid fa-video-slash"
                            style="font-size:3rem; color:rgba(255,255,255,0.2); margin-bottom:15px;"></i>
                        <h3 style="color:var(--text-muted);">No videos uploaded for this course yet</h3>
                    </div>
                <?php endif; ?>
            </div>

            <div class="course-info">
                <h1 class="course-title">
                    <?php echo htmlspecialchars($current_video ? $current_video['title'] : $course['title']); ?>
                </h1>

                <div class="course-meta">
                    <span><i class="fa-solid fa-folder"></i> <?php echo htmlspecialchars($course['category']); ?></span>
                    <span><i class="fa-solid fa-video"></i> <?php echo count($videos); ?> Lessons</span>
                    <span><i class="fa-solid fa-star" style="color:var(--accent-gold);"></i>
                        <?php echo $course['xp']; ?> XP</span>
                </div>

                <p style="color: var(--text-muted); line-height: 1.6;">
                    <?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?>
                </p>

                <div class="mentor-info">
                    <div class="mentor-avatar">
                        <?php if (!empty($course['profile_pic']) && $course['profile_pic'] !== 'default.png' && $course['profile_pic'] !== 'images/avatar1.png' && $course['profile_pic'] !== '../images/avatar1.png'): ?>
                            <img src="../profile/uploads/<?php echo htmlspecialchars($course['profile_pic']); ?>"
                                alt="Mentor"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div
                                style="background:#8b5cf6; width:100%; height:100%; display:none; align-items:center; justify-content:center; font-weight:bold; font-size:1.2rem; border-radius:50%; color:white;">
                                <?php echo mb_substr($course['User_name'], 0, 1); ?>
                            </div>
                        <?php else: ?>
                            <div
                                style="background:#8b5cf6; width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:1.2rem; border-radius:50%; color:white;">
                                <?php echo mb_substr($course['User_name'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="color:var(--text-muted); font-size:0.85rem;">Mentor</div>
                        <div class="mentor-name"><?php echo htmlspecialchars($course['User_name']); ?></div>
                    </div>
                    <?php if ($course['user_id'] != $user_id): ?>
                        <a href="chat.php?user_id=<?php echo $course['user_id']; ?>"
                            style="margin-right:auto; padding: 10px 20px; background: var(--accent-blue); color: white; text-decoration:none; border-radius:8px; font-size:0.95rem; font-weight:bold; transition:0.2s;"><i
                                class="fa-solid fa-comment-dots"></i> Message Mentor</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Playlist -->
        <div class="playlist-section">
            <div class="playlist-header">
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <span
                        style="font-size:0.85rem; background:var(--accent-teal); color:white; padding:4px 12px; border-radius:12px; font-weight:600; display: inline-block;" dir="ltr">
                        <i class="fa-solid fa-play" style="font-size: 0.7rem; margin-right: 4px;"></i> <?php echo count($videos); ?> Videos
                    </span>
                    <?php if ($course['user_id'] == $user_id): ?>
                        <a href="../profile/edit_quiz.php?course_id=<?php echo $course_id; ?>"
                            style="font-size:0.85rem; background:#3b82f6; color:white; padding:4px 12px; border-radius:12px; text-decoration:none; display: inline-flex; align-items: center; gap: 5px; font-weight:600;"
                            title="Edit Final Quiz">
                            <i class="fa-solid fa-pen-to-square"></i> Edit Quiz
                        </a>
                    <?php elseif ($has_valid_quiz && $course_progress >= 100): ?>
                        <a href="quiz.php?course_id=<?php echo $course_id; ?>"
                            style="font-size:0.85rem; background:#f59e0b; color:white; padding:4px 12px; border-radius:12px; text-decoration:none; display: inline-flex; align-items: center; gap: 5px; font-weight:600;"
                            title="Start Final Quiz">
                            <i class="fa-solid fa-clipboard-question"></i> Quiz
                        </a>
                    <?php elseif ($has_valid_quiz): ?>
                        <span
                            style="font-size:0.85rem; background:rgba(255,255,255,0.1); color:rgba(255,255,255,0.3); padding:4px 12px; border-radius:12px; cursor:not-allowed; display: inline-flex; align-items: center; gap: 5px; font-weight:600;"
                            title="Complete 100% to unlock quiz">
                            <i class="fa-solid fa-lock"></i> Quiz
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Bar Area -->
            <div style="padding: 15px; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.05);">
                <div
                    style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:8px; color:var(--text-muted);">
                    <span>Course Progress</span>
                    <span class="progress-percentage"
                        style="color:var(--accent-teal); font-weight:bold;"><?php echo $course_progress; ?>%</span>
                </div>
                <div
                    style="width:100%; height:6px; background:rgba(255,255,255,0.1); border-radius:10px; overflow:hidden;">
                    <div class="progress-bar-fill-inner"
                        style="width:<?php echo $course_progress; ?>%; height:100%; background:linear-gradient(90deg, var(--accent-teal), #2dd4bf); transition: width 0.5s;">
                    </div>
                </div>
            </div>
            <div class="playlist-items">
                    <?php foreach ($videos as $index => $vid):
                        $free_count = intval($course['free_lessons_count'] ?? 0);
                        $is_price_zero = (intval($course['price_tokens'] ?? 0) === 0);
                        $is_locked_video = (!$is_owner && !$is_unlocked && !$is_price_zero && $index >= $free_count);
                        $href = $is_locked_video ? "javascript:void(0)" : "?id={$course_id}&v={$index}";
                        $onclick = $is_locked_video ? "onclick='openPlayerPurchaseModal(event, $index); return false;'" : "";
                        $aria_disabled = $is_locked_video ? 'aria-disabled="true" tabindex="-1"' : '';
                        $lock_label = $is_locked_video ? '<span style="font-size:0.65rem; background:rgba(251, 191, 36, 0.18); color:#fbbf24; border:1px solid rgba(251, 191, 36, 0.3); padding:1px 5px; border-radius:4px; margin-right:5px; vertical-align:middle;">LOCKED</span>' : '';
                        ?>
                        <a href="<?php echo $href; ?>" <?php echo $onclick; ?> <?php echo $aria_disabled; ?>
                            class="playlist-item <?php echo $index === $active_video_index ? 'active' : ''; ?> <?php echo $is_locked_video ? 'locked-item' : ''; ?>"
                            style="<?php echo $is_locked_video ? 'opacity:0.7; cursor:not-allowed;' : ''; ?>">
                            <div class="play-icon"
                                style="<?php echo $is_locked_video ? 'background:rgba(0,0,0,0.3); border-color:rgba(255,255,255,0.1);' : ''; ?>">
                                <i class="fa-solid <?php echo $is_locked_video ? 'fa-lock' : ($index === $active_video_index ? 'fa-pause' : 'fa-play'); ?>"
                                    style="<?php echo $is_locked_video ? 'color:var(--accent-gold); font-size:0.8rem;' : ''; ?>"></i>
                            </div>
                            <div style="flex:1;">
                                <div
                                    style="font-weight:600; font-size:0.95rem; margin-bottom:5px; color: <?php echo $index === $active_video_index ? 'var(--accent-blue)' : 'var(--text-main)'; ?>;">
                                    <?php echo htmlspecialchars($vid['title']); ?>
                                    <?php if (in_array($vid['id'], $completed_lesson_ids)): ?>
                                        <i class="fa-solid fa-check-circle" style="color: #10b981; margin-left: 5px; font-size: 0.85rem;" title="Completed"></i>
                                    <?php endif; ?>
                                    <?php if ($is_locked_video): ?>
                                        <span
                                            style="font-size:0.65rem; background:rgba(251, 191, 36, 0.18); color:#fbbf24; border:1px solid rgba(251, 191, 36, 0.3); padding:1px 5px; border-radius:4px; margin-right:5px; vertical-align:middle;">LOCKED</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    Lesson <?php echo $index + 1; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($has_valid_quiz || ($is_owner && $course['has_quiz'])): ?>
                        <?php
                        $quiz_unlocked = ($is_owner || $course_progress >= 100);
                        $quiz_href = $quiz_unlocked ? ($is_owner ? "../profile/edit_quiz.php?course_id={$course_id}" : "quiz.php?course_id={$course_id}") : "javascript:void(0)";
                        $quiz_onclick = !$quiz_unlocked ? "onclick=\"showToast('Complete all lessons first to unlock the quiz', 'error'); return false;\"" : "";
                        ?>
                        <a href="<?php echo $quiz_href; ?>" <?php echo $quiz_onclick; ?>
                            class="playlist-item quiz-item"
                            style="
                                border-top: 1px solid rgba(245, 158, 11, 0.2);
                                margin-top: 5px;
                                background: <?php echo $quiz_unlocked ? 'rgba(245, 158, 11, 0.08)' : 'rgba(0,0,0,0.2)'; ?>;
                                opacity: <?php echo $quiz_unlocked ? '1' : '0.6'; ?>;
                                cursor: <?php echo $quiz_unlocked ? 'pointer' : 'not-allowed'; ?>;
                                transition: all 0.3s;
                            ">
                            <div class="play-icon" style="
                                background: <?php echo $quiz_unlocked ? 'rgba(245, 158, 11, 0.2)' : 'rgba(0,0,0,0.3)'; ?>;
                                border-color: <?php echo $quiz_unlocked ? 'rgba(245, 158, 11, 0.5)' : 'rgba(255,255,255,0.1)'; ?>;
                                min-width: 40px; height: 40px;
                            ">
                                <i class="fa-solid <?php echo $quiz_unlocked ? ($is_owner ? 'fa-pen-to-square' : 'fa-clipboard-question') : 'fa-lock'; ?>"
                                   style="color: <?php echo $quiz_unlocked ? ($is_owner ? '#3b82f6' : '#f59e0b') : 'rgba(255,255,255,0.3)'; ?>; font-size: 0.9rem;"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:700; font-size:0.95rem; margin-bottom:4px; color: <?php echo $quiz_unlocked ? ($is_owner ? '#60a5fa' : '#fbbf24') : 'rgba(255,255,255,0.4)'; ?>;">
                                    <i class="fa-solid fa-star" style="font-size:0.7rem; margin-left:4px; color:<?php echo $quiz_unlocked ? ($is_owner ? '#3b82f6' : '#f59e0b') : 'rgba(255,255,255,0.2)'; ?>;"></i>
                                    <?php echo $is_owner ? 'Edit Quiz' : 'Final Quiz'; ?>
                                </div>
                                <div style="font-size:0.78rem; color: <?php echo $quiz_unlocked ? ($is_owner ? 'rgba(59, 130, 246, 0.7)' : 'rgba(245, 158, 11, 0.7)') : 'var(--text-muted)'; ?>;">
                                    <?php echo $quiz_unlocked ? ($is_owner ? 'Click to edit quiz questions' : 'Click to start quiz ✓') : 'Complete all lessons to unlock'; ?>
                                </div>
                            </div>
                            <?php if (!$quiz_unlocked): ?>
                                <div style="font-size:0.65rem; background:rgba(255,255,255,0.05); color:rgba(255,255,255,0.3); border:1px solid rgba(255,255,255,0.1); padding:2px 7px; border-radius:4px; white-space:nowrap;">
                                    <?php echo $course_progress; ?>% Completed
                                </div>
                            <?php else: ?>
                                <div style="font-size:0.65rem; background:<?php echo $is_owner ? 'rgba(59,130,246,0.15)' : 'rgba(245,158,11,0.15)'; ?>; color:<?php echo $is_owner ? '#60a5fa' : '#fbbf24'; ?>; border:1px solid <?php echo $is_owner ? 'rgba(59,130,246,0.3)' : 'rgba(245,158,11,0.3)'; ?>; padding:2px 7px; border-radius:4px; white-space:nowrap;">
                                    <?php echo $is_owner ? 'Manage' : 'Available!'; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php if (isset($has_certificate) && $has_certificate): ?>
                        <div style="padding: 15px; text-align: center; margin-top: 10px; border-top: 1px solid rgba(16, 185, 129, 0.2);">
                            <a href="certificate.php?course_id=<?php echo $course_id; ?>"
                                style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size:1rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669); color:white; padding:12px 25px; border-radius:20px; text-decoration:none; width: 100%; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); transition: transform 0.2s, box-shadow 0.2s;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(16, 185, 129, 0.6)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(16, 185, 129, 0.4)';">
                                <i class="fa-solid fa-award" style="font-size: 1.2rem;"></i> View Certificate
                            </a>
                        </div>
                    <?php endif; ?>

            </div>
        </div>
        <!-- Course Completion Modal -->
        <div id="completionModal" class="completion-modal-overlay">
            <div class="completion-card">
                <div class="confetti-container" id="confettiContainer"></div>
                <div class="trophy-ring">
                    <i class="fa-solid fa-trophy"></i>
                </div>
                <h2 class="completion-title">🎉 Congratulations!</h2>
                <p class="completion-subtitle">You have successfully completed the course</p>
                <p class="completion-course-name"><?php echo htmlspecialchars($course['title']); ?></p>
                <div class="completion-badge">
                    <i class="fa-solid fa-certificate"></i> Certificate of Completion is ready
                </div>
                <div class="completion-actions">
                    <a href="certificate.php?course_id=<?php echo $course_id; ?>" class="btn-view-cert">
                        <i class="fa-solid fa-certificate"></i> View Certificate
                    </a>
                    <button onclick="closeCompletionModal()" class="btn-later-cert">
                        Later
                    </button>
                </div>
            </div>
        </div>

        <!-- Purchase Modal -->
        <div id="purchaseModal" class="modal">
            <div class="modal-content glass-card">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm payment to the mentor</h3>
                    <button class="close-modal" onclick="hidePurchaseModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="modal-body">
                    <p class="modal-message">Do you want to buy this course for
                        <strong><?php echo $course['price_tokens']; ?> tokens</strong>?<br>The amount will be transferred directly to the mentor to support their content. <strong>This transaction is final and non-refundable.</strong>
                    </p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="hidePurchaseModal()">Cancel</button>
                    <button id="purchaseConfirm" class="btn-confirm" onclick="confirmPlayerPurchase()">Confirm Payment Now</button>
                </div>
            </div>
        </div>

        <div id="toastContainer"></div>

        <script>
            const courseId = <?php echo $course_id; ?>;
            const coursePrice = <?php echo $course['price_tokens']; ?>;
            const freeLessonCount = <?php echo intval($course['free_lessons_count'] ?? 0); ?>;
            const totalLessonsCount = <?php echo count($videos); ?>;
            const activeVideoIndex = <?php echo $active_video_index; ?>;
            const userIsUnlocked = <?php echo $is_unlocked ? 'true' : 'false'; ?>;
            const userIsOwner = <?php echo $is_owner ? 'true' : 'false'; ?>;
            const courseIsFree = <?php echo $_price_zero ? 'true' : 'false'; ?>;
            let purchaseRedirectIndex = null;

            function openPlayerPurchaseModal(e, redirectIndex = null) {
                if (e) e.preventDefault();
                purchaseRedirectIndex = (redirectIndex !== null) ? redirectIndex : null;
                const messageEl = document.querySelector('.modal-message');
                if (redirectIndex !== null) {
                    messageEl.innerHTML = `You have completed the free lesson. Click confirm payment to deduct ${coursePrice} tokens from your wallet and unlock the next lesson.`;
                } else {
                    messageEl.innerHTML = `Do you want to buy this course for <strong>${coursePrice} tokens</strong>?<br>The amount will be transferred directly to the mentor to support their content. <strong>This transaction is final and non-refundable.</strong>`;
                }
                document.getElementById('purchaseModal').classList.add('open');
            }

            function hidePurchaseModal() {
                document.getElementById('purchaseModal').classList.remove('open');
            }

            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `toast show ${type === 'error' ? 'toast-error' : ''}`;
                toast.innerHTML = `<i class='fa-solid ${type === 'error' ? 'fa-triangle-exclamation' : 'fa-check-circle'}'></i> <span>${message}</span>`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => { toast.remove(); }, 500);
                }, 3000);
            }

            function confirmPlayerPurchase() {
                const btn = document.getElementById('purchaseConfirm');
                const originalText = btn.innerHTML;
                btn.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Processing payment...";
                btn.disabled = true;

                const data = {
                    price: coursePrice,
                    xp: <?php echo $course['xp']; ?>,
                    skillTitle: "<?php echo addslashes($course['title']); ?>",
                    courseId: courseId
                };

                fetch('update_stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            showToast("Payment to mentor successful! Activating course...");
                            setTimeout(() => {
                                if (purchaseRedirectIndex !== null) {
                                    window.location.href = `course_player.php?id=${courseId}&v=${purchaseRedirectIndex}`;
                                } else {
                                    window.location.reload();
                                }
                            }, 1200);
                        } else {
                            showToast(res.message || "An error occurred during purchase", "error");
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast("Server connection error", "error");
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
            }

            const videoElement = document.querySelector('#courseVideo');
            const lessonId = <?php echo isset($current_video['id']) ? $current_video['id'] : 0; ?>;

            if (videoElement && lessonId > 0) {
                let lastUpdate = 0;
                videoElement.addEventListener('timeupdate', () => {
                    const currentTime = videoElement.currentTime;
                    const duration = videoElement.duration;
                    if (duration > 0 && currentTime - lastUpdate > 10) {
                        lastUpdate = currentTime;
                        const pct = Math.floor((currentTime / duration) * 100);
                        sendProgress(pct);
                    }
                });
                videoElement.addEventListener('ended', () => {
                    sendProgress(100);
                    if (!userIsUnlocked && !userIsOwner && !courseIsFree && freeLessonCount > 0 && activeVideoIndex === freeLessonCount - 1 && totalLessonsCount > freeLessonCount) {
                        openPlayerPurchaseModal(null, activeVideoIndex + 1);
                    }
                });
            }

            <?php if ($show_locked_toast): ?>
                window.addEventListener('DOMContentLoaded', () => {
                    showToast("This content is premium. Please purchase the course to access all lessons.", "error");
                });
            <?php endif; ?>
            function sendProgress(pct) {
                fetch('update_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lesson_id: lessonId, watched_pct: pct })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const bar = document.querySelector('.progress-bar-fill-inner');
                            const text = document.querySelector('.progress-percentage');
                            if (bar && data.new_course_progress !== undefined) {
                                bar.style.width = data.new_course_progress + '%';
                            }
                            if (text && data.new_course_progress !== undefined) {
                                text.innerText = data.new_course_progress + '%';
                            }
                            if (data.is_complete) {
                                const currentPlaylistItem = document.querySelector('.playlist-item.active');
                                if (currentPlaylistItem) {
                                    const titleDiv = currentPlaylistItem.querySelector('div[style*="font-weight:600"]');
                                    if (titleDiv && !titleDiv.querySelector('.fa-check-circle')) {
                                        titleDiv.innerHTML += ' <i class="fa-solid fa-check-circle" style="color: #10b981; margin-left: 5px; font-size: 0.85rem;" title="Completed"></i>';
                                    }
                                }
                            }
                            if (data.course_finished) {
                                showCompletionModal();
                            }
                            if (data.new_course_progress === 100) {
                                // Unlock quiz button in playlist header
                                const headerQuiz = document.querySelector('.playlist-header a[href^="quiz.php"]');
                                if (!headerQuiz) {
                                    const headerSpan = document.querySelector('.playlist-header span[title*="unlock quiz"]');
                                    if (headerSpan) {
                                        const newQuizBtn = document.createElement('a');
                                        newQuizBtn.href = 'quiz.php?course_id=' + courseId;
                                        newQuizBtn.style.cssText = 'font-size:0.85rem; background:#f59e0b; color:white; padding:3px 10px; border-radius:12px; text-decoration:none;';
                                        newQuizBtn.title = 'Start Final Quiz';
                                        newQuizBtn.innerHTML = '<i class="fa-solid fa-clipboard-question"></i> Quiz';
                                        headerSpan.replaceWith(newQuizBtn);
                                    }
                                }
                                
                                // Unlock quiz item in playlist
                                const quizItem = document.querySelector('.quiz-item');
                                if (quizItem) {
                                    quizItem.href = 'quiz.php?course_id=' + courseId;
                                    quizItem.onclick = null;
                                    quizItem.style.background = 'rgba(245, 158, 11, 0.08)';
                                    quizItem.style.opacity = '1';
                                    quizItem.style.cursor = 'pointer';
                                    
                                    const iconContainer = quizItem.querySelector('.play-icon');
                                    if (iconContainer) {
                                        iconContainer.style.background = 'rgba(245, 158, 11, 0.2)';
                                        iconContainer.style.borderColor = 'rgba(245, 158, 11, 0.5)';
                                        iconContainer.innerHTML = '<i class="fa-solid fa-clipboard-question" style="color: #f59e0b; font-size: 0.9rem;"></i>';
                                    }
                                    
                                    const textContainer = quizItem.querySelector('div[style*="flex:1"]');
                                    if (textContainer) {
                                        const title = textContainer.querySelector('div:first-child');
                                        if (title) {
                                            title.style.color = '#fbbf24';
                                            title.innerHTML = '<i class="fa-solid fa-star" style="font-size:0.7rem; margin-left:4px; color:#f59e0b;"></i> Final Quiz';
                                        }
                                        const subtext = textContainer.querySelector('div:nth-child(2)');
                                        if (subtext) {
                                            subtext.style.color = 'rgba(245, 158, 11, 0.7)';
                                            subtext.innerText = 'Click to start quiz ✓';
                                        }
                                    }
                                    
                                    const badge = quizItem.querySelector('div[style*="white-space:nowrap"]');
                                    if (badge) {
                                        badge.style.cssText = 'font-size:0.65rem; background:rgba(245,158,11,0.15); color:#fbbf24; border:1px solid rgba(245,158,11,0.3); padding:2px 7px; border-radius:4px; white-space:nowrap;';
                                        badge.innerText = 'Available!';
                                    }
                                }
                            }
                        }
                    });
            }
            // ===== حماية الفيديو من الـ Direct Access =====
            document.addEventListener('DOMContentLoaded', function () {
                const video = document.getElementById('courseVideo');
                if (video) {
                    // منع الـ Right Click
                    video.addEventListener('contextmenu', e => e.preventDefault());

                    // منع الـ Drag
                    video.draggable = false;

                    // منع Print Screen (جزئياً)
                    document.addEventListener('keydown', function (e) {
                        // Ctrl+U, Ctrl+S, F12, Ctrl+Shift+I
                        if ((e.ctrlKey && (e.key === 'u' || e.key === 's')) ||
                            e.key === 'F12' ||
                            (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                            e.preventDefault();
                        }
                    });

                    // حماية الـ Network URLs
                    video.addEventListener('loadstart', function () {
                        const sources = video.querySelectorAll('source');
                        sources.forEach(source => {
                            source.src = source.src + '&t=' + Date.now(); // Cache busting
                        });
                    });
                }
            });

            // DevTools detection removed to prevent false positive alerts

            function showCompletionModal() {
                const modal = document.getElementById('completionModal');
                modal.classList.add('open');
                spawnConfetti();
            }

            function closeCompletionModal() {
                document.getElementById('completionModal').classList.remove('open');
            }

            function spawnConfetti() {
                const container = document.getElementById('confettiContainer');
                const colors = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#f43f5e', '#fbbf24'];
                for (let i = 0; i < 60; i++) {
                    const dot = document.createElement('div');
                    dot.className = 'confetti-dot';
                    dot.style.cssText = `
                        left: ${Math.random() * 100}%;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        width: ${Math.random() * 8 + 4}px;
                        height: ${Math.random() * 8 + 4}px;
                        border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
                        animation-delay: ${Math.random() * 1.5}s;
                        animation-duration: ${Math.random() * 2 + 1.5}s;
                    `;
                    container.appendChild(dot);
                }
                setTimeout(() => { container.innerHTML = ''; }, 5000);
            }
        </script>

        <style>
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                opacity: 0;
                transition: 0.3s;
            }

            .modal.open {
                display: flex;
                opacity: 1;
            }

            .modal-content {
                width: 90%;
                max-width: 500px;
                padding: 30px;
                border-radius: 20px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                transform: translateY(20px);
                transition: 0.3s;
            }

            .modal.open .modal-content {
                transform: translateY(0);
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .modal-title {
                font-size: 1.4rem;
                color: white;
                margin: 0;
            }

            .close-modal {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                font-size: 1.2rem;
            }

            .modal-body {
                color: var(--text-muted);
                line-height: 1.6;
                margin-bottom: 30px;
            }

            .modal-footer {
                display: flex;
                gap: 15px;
            }

            .btn-cancel {
                flex: 1;
                padding: 12px;
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.05);
                color: white;
                border: 1px solid rgba(255, 255, 255, 0.1);
                cursor: pointer;
            }

            .btn-confirm {
                flex: 2;
                padding: 12px;
                border-radius: 10px;
                background: var(--primary);
                color: white;
                border: none;
                cursor: pointer;
                font-weight: bold;
            }

            .toast {
                position: fixed;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%) translateY(100px);
                background: rgba(15, 23, 42, 0.9);
                border: 1px solid var(--accent-teal);
                color: white;
                padding: 15px 30px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                gap: 12px;
                transition: 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
                z-index: 2000;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(10px);
            }

            .toast.show {
                transform: translateX(-50%) translateY(0);
            }

            .toast-error {
                border-color: #f43f5e;
            }

            .playlist-item.locked-item {
                pointer-events: auto;
            }

            /* ===== Course Completion Modal ===== */
            .completion-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.85);
                backdrop-filter: blur(10px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 3000;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.4s ease;
            }

            .completion-modal-overlay.open {
                opacity: 1;
                pointer-events: all;
            }

            .completion-card {
                position: relative;
                background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.98));
                border: 1px solid rgba(245, 158, 11, 0.35);
                border-radius: 28px;
                padding: 50px 40px 40px;
                width: 90%;
                max-width: 460px;
                text-align: center;
                overflow: hidden;
                box-shadow: 0 30px 80px rgba(0,0,0,0.6), 0 0 60px rgba(245,158,11,0.1);
                transform: scale(0.85) translateY(30px);
                transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            }

            .completion-modal-overlay.open .completion-card {
                transform: scale(1) translateY(0);
            }

            .confetti-container {
                position: absolute;
                inset: 0;
                overflow: hidden;
                pointer-events: none;
                z-index: 1;
            }

            .confetti-dot {
                position: absolute;
                top: -10px;
                animation: confettiFall linear forwards;
            }

            @keyframes confettiFall {
                0%   { transform: translateY(0) rotate(0deg); opacity: 1; }
                100% { transform: translateY(600px) rotate(720deg); opacity: 0; }
            }

            .trophy-ring {
                position: relative;
                z-index: 2;
                width: 90px;
                height: 90px;
                background: linear-gradient(135deg, rgba(245,158,11,0.25), rgba(251,191,36,0.08));
                border: 2px solid rgba(245,158,11,0.5);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                font-size: 2.2rem;
                color: #f59e0b;
                animation: trophyPulse 2s ease-in-out infinite;
            }

            @keyframes trophyPulse {
                0%, 100% { box-shadow: 0 0 20px rgba(245,158,11,0.3); }
                50%       { box-shadow: 0 0 45px rgba(245,158,11,0.6); }
            }

            .completion-title {
                position: relative;
                z-index: 2;
                font-size: 2rem;
                font-weight: 900;
                color: white;
                margin: 0 0 8px;
            }

            .completion-subtitle {
                position: relative;
                z-index: 2;
                color: rgba(255,255,255,0.5);
                font-size: 1rem;
                margin: 0 0 12px;
            }

            .completion-course-name {
                position: relative;
                z-index: 2;
                color: #fbbf24;
                font-size: 1rem;
                font-weight: 700;
                margin: 0 0 22px;
                padding: 8px 18px;
                background: rgba(245,158,11,0.1);
                border: 1px solid rgba(245,158,11,0.25);
                border-radius: 30px;
                display: inline-block;
            }

            .completion-badge {
                position: relative;
                z-index: 2;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: rgba(16,185,129,0.12);
                border: 1px solid rgba(16,185,129,0.3);
                color: #34d399;
                font-size: 0.9rem;
                font-weight: 600;
                padding: 7px 18px;
                border-radius: 30px;
                margin-bottom: 30px;
            }

            .completion-actions {
                position: relative;
                z-index: 2;
                display: flex;
                gap: 12px;
                justify-content: center;
            }

            .btn-view-cert {
                flex: 2;
                padding: 14px 20px;
                border-radius: 14px;
                background: linear-gradient(135deg, #f59e0b, #d97706);
                color: #000;
                font-weight: 800;
                font-size: 1rem;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.3s;
                box-shadow: 0 8px 25px rgba(245,158,11,0.35);
            }

            .btn-view-cert:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 35px rgba(245,158,11,0.55);
                filter: brightness(1.1);
            }

            .btn-later-cert {
                flex: 1;
                padding: 14px;
                border-radius: 14px;
                background: rgba(255,255,255,0.06);
                color: rgba(255,255,255,0.45);
                font-size: 0.9rem;
                border: 1px solid rgba(255,255,255,0.1);
                cursor: pointer;
                transition: all 0.3s;
            }

            .btn-later-cert:hover {
                background: rgba(255,255,255,0.1);
                color: white;
            }
        </style>
</body>

</html>