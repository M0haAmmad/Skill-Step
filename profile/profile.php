<?php
session_start();
require_once '../Main/db_connection.php';
require_once '../Main/auth_check.php';
require_once '../Main/level_helper.php';
checkUserSession($conn);
$user_id = intval($_SESSION['user_id']);

// Force Log Today's Login
$today = date('Y-m-d');
$query_login = "SELECT login_history FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query_login);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);


$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    session_destroy();
    header('Location: ../Login/login.php');
    exit();
}
$history_str = $row['login_history'] ?? '[]';
$history_arr = json_decode($history_str, true);
if (!is_array($history_arr))
    $history_arr = [];

if (!in_array($today, $history_arr)) {
    $history_arr[] = $today;
    $new_history_str = json_encode($history_arr);
    $up_stmt = mysqli_prepare($conn, "UPDATE users SET login_history = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($up_stmt, "si", $new_history_str, $user_id);
    mysqli_stmt_execute($up_stmt);
} else {
    $new_history_str = $history_str;
}

// Fetch Full User Profile
$query = "SELECT u.full_name, u.email, u.level, u.xp, u.profile_pic, COALESCE(w.token_balance, 0) as token_balance, COALESCE(w.lifetime_earned, 0) as lifetime_earned FROM users u LEFT JOIN wallet w ON u.user_id = w.user_id WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$row = mysqli_fetch_assoc($result)) {
    die("User not found.");
}

$db_name = $row['full_name'];
$db_email = $row['email'];
$db_lvl = $row['level'];
$db_xp = $row['xp'];
$db_tokens = $row['token_balance'];
$db_earnings = $row['lifetime_earned'] ?? 0;
$db_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default.png';

$name_parts = explode(' ', trim($db_name));
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';

$level_data = getLevelData($db_xp);
$db_lvl = $level_data['level'];
$display_xp = $level_data['current_level_xp'];
$xp_percentage = $level_data['progress_percent'];
$next_lvl_req = $level_data['next_level_required'];

if ($db_lvl != $row['level']) {
    mysqli_query($conn, "UPDATE users SET level = $db_lvl WHERE user_id = $user_id");
}

// Unread Messages Count
$msg_count_q = mysqli_query($conn, "SELECT COUNT(*) as unread FROM messages WHERE Receiver_id = $user_id AND is_read = 0");
$unread_msg_count = mysqli_fetch_assoc($msg_count_q)['unread'] ?? 0;

// Unread Notifications Count
$notif_count_q = mysqli_query($conn, "SELECT COUNT(*) as unread FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_notif_count = mysqli_fetch_assoc($notif_count_q)['unread'] ?? 0;

$total_unread = $unread_msg_count + $unread_notif_count;

// Fetch User Notifications
$notifs_q = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
$user_notifications = [];
while ($nr = mysqli_fetch_assoc($notifs_q)) {
    $user_notifications[] = $nr;
}

// Mark all as read when visiting notifications section (optional, but good for UX)
// For now, we'll just fetch them.

// Parse unlocked skills
$unlocked_array = [];
$enroll_q = mysqli_query($conn, "SELECT courses.course_id, courses.title FROM enrollments JOIN courses ON enrollments.course_id = courses.course_id WHERE enrollments.student_id = $user_id AND enrollments.is_active = 1");
if ($enroll_q) {
    while ($erow = mysqli_fetch_assoc($enroll_q)) {
        $unlocked_array[] = [
            'id' => $erow['course_id'],
            'title' => trim($erow['title'])
        ];
    }
}

// Fetch User Certificates
$certs_q = mysqli_query($conn, "SELECT c.*, co.title as course_title FROM certificates c JOIN courses co ON c.course_id = co.course_id WHERE c.user_id = $user_id");
$user_certs = [];
while ($cr = mysqli_fetch_assoc($certs_q)) {
    $user_certs[] = $cr;
}

// Query my uploaded courses
$my_courses_query = "
    SELECT c.*, c.course_id AS skill_id, c.price_tokens AS price, c.status,
           (SELECT COUNT(*) FROM lessons v WHERE v.course_id = c.course_id) AS v_count,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) AS student_count
    FROM courses c 
    WHERE c.creator_id = ? 
    ORDER BY c.course_id DESC";
$mc_stmt = mysqli_prepare($conn, $my_courses_query);
mysqli_stmt_bind_param($mc_stmt, "i", $user_id);
mysqli_stmt_execute($mc_stmt);
$my_courses_res = mysqli_stmt_get_result($mc_stmt);
$my_courses = [];
while ($c = mysqli_fetch_assoc($my_courses_res)) {
    $my_courses[] = $c;
}

?>

<!DOCTYPE html>
<html lang="en" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Skill-Step</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Main/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="profile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../Main/alert-system.css?v=<?php echo time(); ?>">
</head>

<body>

    <nav>
        <a href="../Main/index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50" onerror="this.style.display='none';">
            Skill-Step
        </a>
        <a href="../Main/index.php" class="btn-action" style="width:auto; padding: 10px 20px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Platform
        </a>
    </nav>

    <div class="profile-dashboard">

        <div class="profile-sidebar">
            <div class="sidebar-links">
                <?php if (isset($user['roles']) && strpos($user['roles'], 'admin') !== false): ?>
                    <a href="../Admin/admin_users.php" style="color:#f59e0b; background: rgba(245, 158, 11, 0.1);"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
                <?php endif; ?>
                <a href="#" class="active"><i class="fa-solid fa-user"></i> Overview</a>
                <a href="#achievements"><i class="fa-solid fa-award"></i> My Achievements</a>
                <a href="#unlocked-courses"><i class="fa-solid fa-graduation-cap"></i> My Courses</a>
                <a href="#notifications" style="display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-bell"></i> Notifications</span>
                    <?php if($unread_notif_count > 0): ?>
                        <span class="dropdown-notif-count" style="background:rgba(245,158,11,0.2); color:#f59e0b; margin-left:10px;"><?php echo $unread_notif_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="#activity"><i class="fa-solid fa-calendar-days"></i> My Activity</a>
                <a href="../Main/chat.php" style="color:var(--accent-teal); display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-comments"></i> My Messages</span>
                    <?php if($unread_msg_count > 0): ?>
                        <span class="dropdown-notif-count" style="margin-left:10px;"><?php echo $unread_msg_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="#create-course" style="color:#10b981;"><i class="fa-solid fa-plus-circle"></i> Create New Course</a>
                <a href="../Tokens/tokens.php" style="color:var(--accent-gold);"><i class="fa-solid fa-coins"></i> Manage Tokens</a>
            </div>
            <div style="margin-top:auto; padding:20px;">
                <p style="color:var(--text-muted); font-size:0.9rem;">Account updates are saved automatically</p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="profile-content">

            <!-- Profile Header Card -->
            <section class="glass-card profile-header">
                <div class="avatar-wrapper" style="position:relative;">
                    <?php if($total_unread > 0): ?>
                        <span class="notif-badge" style="width:25px; height:25px; font-size:0.9rem; display:flex; align-items:center; justify-content:center; top:0; right:0;"><?php echo $total_unread; ?></span>
                    <?php endif; ?>
                    <img src="<?php echo ($db_pic == 'default.png') ? '../images/default-avatar.png' : 'uploads/' . htmlspecialchars($db_pic); ?>"
                        alt="Avatar" id="profileImagePreview"
                        onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($first_name); ?>&background=3b82f6&color=fff&size=150';">
                    <div class="avatar-overlay" onclick="document.getElementById('profilePicInput').click();">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                    <input type="file" id="profilePicInput" style="display:none;"
                        accept="image/png, image/jpeg, image/webp" onchange="uploadProfilePic()">
                </div>

                <div class="profile-info">
                    <div class="name-edit-group">
                        <input type="text" id="firstNameInput" value="<?php echo htmlspecialchars($first_name); ?>"
                            placeholder="First Name">
                        <input type="text" id="lastNameInput" value="<?php echo htmlspecialchars($last_name); ?>"
                            placeholder="Last Name">
                        <button class="btn-save" onclick="updateName()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                    </div>
                    <p class="user-email"><i class="fa-solid fa-envelope"></i>
                        <?php echo htmlspecialchars($db_email); ?></p>
                </div>
            </section>

            <!-- Stats & Achievements Row -->
            <section id="achievements" class="stats-grid">
                <div class="stat-card">
                    <i class="fa-solid fa-star stat-icon star"></i>
                    <h3>Level <?php echo $db_lvl; ?></h3>
                    <div class="progress-bar-bg" style="margin-top:15px; width:100%; height:8px;">
                        <div class="progress-bar-fill" style="width: <?php echo $xp_percentage; ?>%;"></div>
                    </div>
                    <p style="margin-top:10px; font-size:0.9rem; color:var(--text-muted);"><span id="xpText"><?php echo $display_xp; ?></span> /
                        <span id="nextLevelXP"><?php echo $next_lvl_req; ?></span> XP</p>
                </div>

                <div class="stat-card">
                    <i class="fa-solid fa-coins stat-icon coin"></i>
                    <h3>Balance</h3>
                    <p class="stat-value"><?php echo $db_tokens; ?></p>
                    <p style="font-size:0.9rem; color:var(--text-muted);">Tokens available</p>
                </div>

                <div class="stat-card">
                    <i class="fa-solid fa-bolt stat-icon bolt"></i>
                    <h3>Overall Progress</h3>
                    <p class="stat-value"><?php echo count($unlocked_array); ?></p>
                    <p style="font-size:0.9rem; color:var(--text-muted);">Skills Unlocked</p>
                </div>

                <div class="stat-card"
                    style="border-color: #f43f5e; box-shadow: inset 0 0 20px rgba(244, 63, 94, 0.1);">
                    <i class="fa-solid fa-wallet stat-icon coin" style="color:#f43f5e"></i>
                    <h3 style="color:#f43f5e;">Creator Earnings</h3>
                    <p class="stat-value" id="creatorWalletDisplay" style="color:#f43f5e;"><?php echo $db_earnings; ?>
                    </p>
                    <button class="btn-action"
                        style="margin-top:15px; padding: 5px 15px; font-size:0.85rem; background: rgba(244, 63, 94, 0.2); color:#f43f5e;"
                        onclick="simulateSale()">Simulate Course Sales</button>
                    <p style="font-size:0.8rem; color:var(--text-muted); margin-top:10px;">Your simulated earnings</p>
                </div>
            </section>

            <!-- Unlocked Courses -->
            <section id="unlocked-courses" class="courses-section">
                <h2><i class="fa-solid fa-unlock-keyhole"></i> Unlocked Courses</h2>
                <div class="unlocked-cards-scroller">
                    <?php if (count($unlocked_array) > 0): ?>
                        <?php foreach ($unlocked_array as $unlocked): ?>
                            <div class="mini-course-card">
                                <i class="fa-solid fa-check-circle success-icon"></i>
                                <h4><?php echo htmlspecialchars($unlocked['title']); ?></h4>
                                <a href="../Main/course_player.php?id=<?php echo $unlocked['id']; ?>" class="btn-action"
                                    style="font-size:0.8rem; padding: 5px 15px; margin-top:15px;">Continue Learning</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--text-muted); width:100%; text-align:center;">You haven't unlocked any courses yet. Explore the platform to unlock more loot!</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- My Certificates -->
            <section id="certificates" class="courses-section" style="margin-top: 40px;">
                <h2><i class="fa-solid fa-award" style="color:#f59e0b;"></i> My Earned Certificates</h2>
                <div class="unlocked-cards-scroller">
                    <?php if (count($user_certs) > 0): ?>
                        <?php foreach ($user_certs as $cert): ?>
                            <div class="mini-course-card" style="border-color: #f59e0b;">
                                <i class="fa-solid fa-graduation-cap" style="font-size: 2rem; color: #f59e0b; margin-bottom: 10px;"></i>
                                <h4 style="font-size:1rem;"><?php echo htmlspecialchars($cert['course_title']); ?></h4>
                                <a href="../Main/certificate.php?course_id=<?php echo $cert['course_id']; ?>" class="btn-action"
                                    style="font-size:0.8rem; padding: 5px 15px; margin-top:15px; background: #f59e0b; color: #0f172a; font-weight:bold;">View Certificate</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--text-muted); width:100%; text-align:center;">You haven't earned any certificates yet. Complete the available quizzes to get them!</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Manage My Courses -->
            <section id="manage-courses" class="courses-section" style="margin-top: 40px;">
                <h2><i class="fa-solid fa-list-check" style="color:#f59e0b;"></i> Manage My Uploaded Courses</h2>
                <div class="unlocked-cards-scroller"
                    style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                    <?php if (count($my_courses) > 0): ?>
                        <?php foreach ($my_courses as $mc): ?>
                            <div class="mini-course-card"
                                style="display:flex; flex-direction:column; justify-content:space-between; text-align:left;"
                                id="my-course-<?php echo $mc['skill_id']; ?>">
                                <div>
                                    <div style="display:flex; justify-content:space-between; align-items:start;">
                                        <i class="<?php echo htmlspecialchars(!empty($mc['icon']) ? $mc['icon'] : 'fa-solid fa-code'); ?>"
                                            style="font-size: 2rem; color: var(--accent-blue); margin-bottom: 10px;"></i>
                                        <?php 
                                            $st = $mc['status'];
                                            $st_color = '#9ca3af'; $st_text = 'Draft';
                                            if($st == 'active') { $st_color = '#10b981'; $st_text = 'Active'; }
                                            if($st == 'pending_review') { $st_color = '#f59e0b'; $st_text = 'Pending Review'; }
                                            if($st == 'rejected') { $st_color = '#f43f5e'; $st_text = 'Rejected'; }
                                        ?>
                                        <span style="font-size: 0.75rem; background: <?php echo $st_color; ?>; color: white; padding: 2px 8px; border-radius: 20px; font-weight: bold;"><?php echo $st_text; ?></span>
                                    </div>
                                    <h4 style="margin-bottom: 5px; font-size:1.1rem;">
                                        <?php echo htmlspecialchars($mc['title']); ?>
                                    </h4>
                                    <div style="display:flex; gap:15px; margin-bottom:10px;">
                                        <p style="font-size:0.85rem; color:var(--accent-gold);">
                                            <strong>Price:</strong> <?php echo $mc['price'] == 0 ? 'Free' : $mc['price']; ?>
                                        </p>
                                        <p style="font-size:0.85rem; color:var(--accent-teal);">
                                            <strong>Students:</strong> <?php echo $mc['student_count']; ?>
                                        </p>
                                    </div>
                                    <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom: 15px;"><i
                                            class="fa-solid fa-video"></i> <?php echo $mc['v_count']; ?> Lessons</p>
                                </div>
                                <div style="display:flex; gap: 10px; margin-top:10px;">
                                    <a href="edit_course.php?id=<?php echo $mc['skill_id']; ?>" class="btn-action"
                                        style="flex:1; text-align:center; background: rgba(59, 130, 246, 0.2); color: #3b82f6; font-size:0.85rem; padding: 8px;"><i
                                            class="fa-solid fa-pen"></i> Edit</a>
                                    <button onclick="deleteCourse(<?php echo $mc['skill_id']; ?>)" class="btn-action"
                                        style="flex:1; background: rgba(244, 63, 94, 0.2); color: #f43f5e; font-size:0.85rem; padding: 8px; box-shadow:none;"><i
                                            class="fa-solid fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--text-muted); width:100%; text-align:center;">You haven't uploaded any courses yet. Start sharing your skills now!</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Notifications Section -->
            <section id="notifications" class="courses-section" style="margin-top: 40px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2><i class="fa-solid fa-bell" style="color:#f59e0b;"></i> My Notifications</h2>
                    <?php if (count($user_notifications) > 0): ?>
                        <button onclick="deleteAllNotifs()" class="btn-action" style="width:auto; padding: 5px 15px; font-size:0.85rem; background:rgba(244, 63, 94, 0.2); color:#f43f5e; border:1px solid rgba(244, 63, 94, 0.4);"><i class="fa-solid fa-trash-can"></i> Clear All</button>
                    <?php endif; ?>
                </div>
                <div class="glass-card" style="padding:20px; margin-top:20px;">
                    <?php if (count($user_notifications) > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:15px;">
                            <?php foreach ($user_notifications as $notif): ?>
                                <div class="notif-item-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>" 
                                     onclick="markNotifRead(<?php echo $notif['notification_id']; ?>, this)"
                                     style="padding:15px; border-radius:12px; background:<?php echo $notif['is_read'] ? 'rgba(255,255,255,0.03)' : 'rgba(59,130,246,0.1)'; ?>; border:1px solid <?php echo $notif['is_read'] ? 'rgba(255,255,255,0.05)' : 'rgba(59,130,246,0.2)'; ?>; transition:0.3s; cursor:pointer; position:relative;">
                                    <?php if(!$notif['is_read']): ?>
                                        <div style="position:absolute; top:10px; right:10px; width:8px; height:8px; background:var(--primary); border-radius:50%;"></div>
                                    <?php endif; ?>
                                    <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:5px;">
                                        <h4 style="color:<?php echo $notif['is_read'] ? 'var(--text-main)' : 'var(--primary)'; ?>; font-size:1.1rem;"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                        <span style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('Y/m/d H:i', strtotime($notif['created_at'])); ?></span>
                                    </div>
                                    <p style="color:var(--text-muted); font-size:0.95rem; line-height:1.5;"><?php echo htmlspecialchars($notif['body']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted); text-align:center; padding:20px;">No notifications currently.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Login Activity Heatmap -->
            <section id="activity" class="activity-section">
                <h2><i class="fa-solid fa-fire"></i> Login Activity</h2>
                <p style="color:var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">Your heatmap for the days you visited the platform during the current month.</p>

                <div class="heatmap-container" id="heatmapContainer">
                    <!-- Javascript will render the heatmap grid here -->
                </div>
            </section>

            <!-- Create a Course Panel -->
            <section id="create-course" class="courses-section">
                <h2><i class="fa-solid fa-plus-circle" style="color:#10b981;"></i> Course Creation Panel</h2>
                <div class="glass-card" style="padding:30px; margin-top:20px;">
                    <form id="createCourseForm" onsubmit="submitNewCourse(event)">
                        <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:20px;">
                            <label style="color:var(--text-main); font-weight:bold;">Course / Skill Title:</label>
                            <input type="text" id="courseTitle" placeholder="Example: Mastering UI/UX Design in 2025"
                                required style="width:100%; max-width:100%;">
                        </div>

                        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                            <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                                <label style="color:var(--text-main); font-weight:bold;">Skill Category:</label>
                                <select id="courseCategory" required class="native-style-select">
                                    <option value="programming">Programming & Tech</option>
                                    <option value="design">Design & Arts</option>
                                    <option value="languages">Languages & Comm</option>
                                </select>
                            </div>
                            <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                                <label style="color:var(--text-main); font-weight:bold;">Select Icon:</label>
                                <select id="courseIcon" class="native-style-select">
                                    <option value="fa-solid fa-code">🖥️ Code</option>
                                    <option value="fa-solid fa-palette">🎨 Palette</option>
                                    <option value="fa-solid fa-language">🌐 Languages</option>
                                    <option value="fa-solid fa-laptop-code">💻 Laptop</option>
                                    <option value="fa-solid fa-wand-magic-sparkles">✨ Skill Magic</option>
                                    <option value="fa-solid fa-robot">🤖 AI</option>
                                    <option value="fa-brands fa-youtube">🎥 Content Creation</option>
                                </select>
                            </div>
                            <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:20px; grid-column: 1 / -1;">
                                <label style="color:var(--text-main); font-weight:bold;"><i class="fa-solid fa-video"></i> Course Lessons (Videos):</label>
                                <div id="lessonsBuilder" style="background: rgba(0,0,0,0.2); padding:20px; border-radius:16px; border:1px solid var(--glass-border);">
                                    <div id="lessonsList" style="display:flex; flex-direction:column; gap:10px; margin-bottom:15px;">
                                        <p style="color:var(--text-muted); font-size:0.9rem; text-align:center;" id="noLessonsMsg">No lessons added yet. Start by adding videos below.</p>
                                    </div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap; background: rgba(255,255,255,0.03); padding:15px; border-radius:12px;">
                                        <input type="file" id="lessonFile" accept="video/mp4,video/webm" style="display:none;" onchange="handleLessonFileSelect(this)">
                                        <div style="flex:1; min-width:200px; position:relative;">
                                            <input type="text" id="lessonTitleInput" placeholder="Lesson Title (e.g.: Intro to Design)" style="width:100%; padding-left:15px;">
                                        </div>
                                        <button type="button" onclick="document.getElementById('lessonFile').click()" class="btn-action" style="background:rgba(255,255,255,0.1); width:auto; white-space:nowrap; border:1px solid rgba(255,255,255,0.2);">
                                            <i class="fa-solid fa-file-video"></i> Select Video
                                        </button>
                                        <button type="button" onclick="addNewLessonToList()" class="btn-action" style="background:var(--primary); width:auto; padding:10px 25px;">
                                            <i class="fa-solid fa-plus"></i> Add Lesson
                                        </button>
                                    </div>
                                    <p id="fileSelectedInfo" style="font-size:0.85rem; color:var(--accent-teal); margin-top:10px; display:none;"></p>
                                </div>
                            </div>
                            <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                                <label style="color:var(--text-main); font-weight:bold;">Support / Comm Type:</label>
                                <select id="courseSupport" class="native-style-select">
                                    <option value="24/7 Support">24/7 Support</option>
                                    <option value="Chat Available">Chat Available</option>
                                    <option value="Discord Community">Discord Community</option>
                                    <option value="No Direct Support">No Direct Support</option>
                                </select>
                            </div>
                            
                            <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                                <label style="color:var(--text-main); font-weight:bold;">XP Value for Trainee:</label>
                                <input type="number" id="courseXP" min="50" max="250" value="100" required>
                            </div>
                            <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                                <label style="color:var(--text-main); font-weight:bold;">Price (Tokens):</label>
                                <input type="number" id="coursePrice" min="0" max="3000" placeholder="0 = Free (Max 3000)"
                                    required>
                            </div>
                        </div>

                        <div class="name-edit-group"
                            style="flex-direction:column; gap:15px; margin-top:20px; margin-bottom:0;">
                            <label style="color:var(--text-main); font-weight:bold;">Brief Course Description (Optional):</label>
                            <textarea id="courseDescription" rows="4"
                                placeholder="Write what students will learn in this course..."
                                style="background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); color:white; padding:15px; border-radius:12px; font-family:inherit; outline:none; font-size:1.1rem; resize:vertical;"></textarea>
                        </div>

                        <!-- Quiz Section -->
                        <div class="glass-card quiz-builder-section" style="padding:25px; margin-top:30px; border: 1px dashed rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.05); border-radius:16px;">
                            <h3 style="color:#f59e0b; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                                <i class="fa-solid fa-clipboard-list"></i> Final Quiz Design
                            </h3>
                            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Add questions and options that students will face after completing 100% of the course.</p>
                            
                            <div id="quizQuestionsContainer" style="display:flex; flex-direction:column; gap:20px;">
                                <!-- Dynamic questions here -->
                            </div>

                            <button type="button" onclick="addQuizQuestion()" class="btn-action" 
                                style="background:rgba(245, 158, 11, 0.15); color:#f59e0b; border:1px solid rgba(245, 158, 11, 0.3); font-size:0.95rem; padding:12px 25px; width:auto; margin-top:15px; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-plus-circle"></i> Add New Question
                            </button>
                        </div>

                        <button type="submit" class="btn-save"
                            style="width:100%; margin-top:30px; background:linear-gradient(135deg, #10b981, #059669); font-size:1.2rem; padding:15px; box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);"><i
                                class="fa-solid fa-paper-plane"></i> Publish Course to Community</button>
                    </form>
                </div>
            </section>

        </main>
    </div>

    <div id="loginHistoryData" style="display:none;"><?php echo htmlspecialchars($new_history_str); ?></div>

    <script src="../Main/alert-system.js?v=<?php echo time(); ?>"></script>
    <script src="profile.js?v=<?php echo time(); ?>"></script>
</body>

</html>