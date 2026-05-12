<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/login.php');
    exit();
}

require_once '../Main/db_connection.php';
$user_id = intval($_SESSION['user_id']);
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($course_id <= 0)
    die("Invalid Course ID");

$q = "SELECT courses.*, courses.course_id AS skill_id, courses.price_tokens AS price, 100 AS xp, courses.creator_id AS user_id, (SELECT categories.name FROM categories JOIN skills ON skills.category_id = categories.category_id WHERE skills.skill_id = courses.skill_id LIMIT 1) AS category FROM courses WHERE course_id = ?";
if (strpos($_SESSION['roles'], 'admin') === false) {
    $q .= " AND creator_id = ?";
}
$stmt = mysqli_prepare($conn, $q);
if (strpos($_SESSION['roles'], 'admin') === false) {
    mysqli_stmt_bind_param($stmt, "ii", $course_id, $user_id);
} else {
    mysqli_stmt_bind_param($stmt, "i", $course_id);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$course = mysqli_fetch_assoc($res);

if (!$course)
    die("Course not found or unauthorized access.");

$vq = "SELECT lesson_id AS id, course_id AS skill_id, title, video_path AS file_path FROM lessons WHERE course_id = ? ORDER BY order_index ASC";
$vst = mysqli_prepare($conn, $vq);
mysqli_stmt_bind_param($vst, "i", $course_id);
mysqli_stmt_execute($vst);
$vRes = mysqli_stmt_get_result($vst);
$videos = [];
while ($v = mysqli_fetch_assoc($vRes)) {
    $videos[] = $v;
}

// Check if quiz exists
$quiz_q = mysqli_query($conn, "SELECT quiz_id FROM quizzes WHERE course_id = $course_id");
$has_quiz = mysqli_num_rows($quiz_q) > 0;
$quiz_id = $has_quiz ? mysqli_fetch_assoc($quiz_q)['quiz_id'] : null;

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course | Skill-Step</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Main/style.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="../Main/alert-system.css?v=<?php echo time(); ?>">
    <style>
        .video-list-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            padding: 15px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
    </style>
</head>

<body style="min-height: 100vh; overflow-y: auto;">
    <nav>
        <a href="profile.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50" onerror="this.style.display='none';"> Skill-Step
        </a>
        <a href="profile.php" class="btn-action" style="width:auto; padding: 10px 20px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Profile
        </a>
    </nav>
    <br><br>
    <div style="max-width: 900px; margin: 0 auto; padding: 20px;">
        <h2 style="margin-bottom:20px; color:var(--text-main);"><i class="fa-solid fa-pen-to-square"></i> Edit Course:
            <?php echo htmlspecialchars($course['title']); ?>
        </h2>

        <div class="glass-card" style="padding:30px;">
            <form id="editCourseForm" onsubmit="submitEditCourse(event)">
                <input type="hidden" id="editSkillId" value="<?php echo $course_id; ?>">

                <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:20px;">
                    <label style="color:var(--text-main); font-weight:bold;">Course Title:</label>
                    <input type="text" id="editCourseTitle" value="<?php echo htmlspecialchars($course['title']); ?>"
                        required style="width:100%; max-width:100%;">
                </div>

                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                        <label style="color:var(--text-main); font-weight:bold;">Category:</label>
                        <select id="editCourseCategory" class="native-style-select">
                            <option value="programming" <?php if ($course['category'] == 'programming')
                                echo 'selected'; ?>>Programming & Tech</option>
                            <option value="design" <?php if ($course['category'] == 'design')
                                echo 'selected'; ?>>Design & Arts</option>
                            <option value="languages" <?php if ($course['category'] == 'languages')
                                echo 'selected'; ?>>Languages & Communication</option>
                        </select>
                    </div>
                    <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                        <label style="color:var(--text-main); font-weight:bold;">Select Icon:</label>
                        <select id="editCourseIcon" class="native-style-select">
                            <option value="fa-solid fa-code" <?php if (($course['icon'] ?? '') == 'fa-solid fa-code')
                                echo 'selected'; ?>>🖥️ Code</option>
                            <option value="fa-solid fa-palette" <?php if (($course['icon'] ?? '') == 'fa-solid fa-palette')
                                echo 'selected'; ?>>🎨 Palette</option>
                            <option value="fa-solid fa-language" <?php if (($course['icon'] ?? '') == 'fa-solid fa-language')
                                echo 'selected'; ?>>🌐 Languages</option>
                            <option value="fa-solid fa-laptop-code" <?php if (($course['icon'] ?? '') == 'fa-solid fa-laptop-code')
                                echo 'selected'; ?>>💻 Laptop</option>
                            <option value="fa-solid fa-wand-magic-sparkles" <?php if (($course['icon'] ?? '') == 'fa-solid fa-wand-magic-sparkles')
                                echo 'selected'; ?>>✨ Magic</option>
                            <option value="fa-solid fa-robot" <?php if (($course['icon'] ?? '') == 'fa-solid fa-robot')
                                echo 'selected'; ?>>🤖 AI</option>
                            <option value="fa-brands fa-youtube" <?php if (($course['icon'] ?? '') == 'fa-brands fa-youtube')
                                echo 'selected'; ?>>🎥 Content Creation</option>
                        </select>
                    </div>
                    <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                        <label style="color:var(--text-main); font-weight:bold;">Support Type:</label>
                        <select id="editCourseSupport" class="native-style-select">
                            <option value="24/7 Support" <?php if (($course['support_type'] ?? '') == '24/7 Support')
                                echo 'selected'; ?>>24/7 Support</option>
                            <option value="Live Chat Available" <?php if (($course['support_type'] ?? '') == 'Live Chat Available')
                                echo 'selected'; ?>>Live Chat Available</option>
                            <option value="Discord Community" <?php if (($course['support_type'] ?? '') == 'Discord Community')
                                echo 'selected'; ?>>Discord Community</option>
                            <option value="No Direct Support" <?php if (($course['support_type'] ?? '') == 'No Direct Support')
                                echo 'selected'; ?>>No Direct Support</option>
                        </select>
                    </div>
                    <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                        <label style="color:var(--text-main); font-weight:bold;">XP Reward:</label>
                        <input type="number" id="editCourseXP" min="50" max="250" value="<?php echo $course['xp']; ?>"
                            required>
                    </div>
                    
                    <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                        <label style="color:var(--text-main); font-weight:bold;">Price (Tokens):</label>
                        <input type="number" id="editCoursePrice" min="0" max="3000"
                            value="<?php echo $course['price']; ?>" required>
                    </div>
                    
                    <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-bottom:0;">
                        <label style="color:var(--text-main); font-weight:bold;">Free Lessons:</label>
                        <input type="number" id="editCourseFreeLessons" min="0" max="10"
                            value="<?php echo intval($course['free_lessons_count'] ?? 0); ?>" required>
                    </div>
                </div>

                <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-top:20px; margin-bottom:0;">
                    <label style="color:var(--text-main); font-weight:bold;">Course Description:</label>
                    <textarea id="editCourseDescription" rows="4"
                        style="background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); color:white; padding:15px; border-radius:12px; font-family:inherit; outline:none; font-size:1.1rem; resize:vertical;"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                </div>

                <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-top:30px;">
                    <label style="color:var(--text-main); font-weight:bold;"><i class="fa-solid fa-video"></i> Manage Lessons (Videos):</label>
                    <div id="lessonsBuilder" style="background: rgba(0,0,0,0.2); padding:20px; border-radius:16px; border:1px solid var(--glass-border);">
                        <div id="lessonsList" style="display:flex; flex-direction:column; gap:10px; margin-bottom:15px;">
                            <?php foreach ($videos as $i => $v): ?>
                                <div class="glass-card" id="vidItem_<?php echo $v['id']; ?>" style="display:flex; justify-content:space-between; align-items:center; padding:12px 15px; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.2); margin-bottom:10px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <i class="fa-solid fa-circle-play" style="color:var(--primary);"></i>
                                        <span style="font-weight:bold; color:white;"><?php echo htmlspecialchars($v['title']); ?></span>
                                        <span style="font-size:0.8rem; color:var(--text-muted); opacity:0.7;">(Current Video)</span>
                                    </div>
                                    <button type="button" onclick="deleteSingleVideo(<?php echo $v['id']; ?>, <?php echo $course_id; ?>)" style="background:none; border:none; color:#f43f5e; cursor:pointer; font-size:1.1rem; padding:5px;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap; background: rgba(255,255,255,0.03); padding:15px; border-radius:12px;">
                            <input type="file" id="lessonFile" accept="video/mp4,video/webm" style="display:none;" onchange="handleLessonFileSelect(this)">
                            <div style="flex:1; min-width:200px; position:relative;">
                                <input type="text" id="lessonTitleInput" placeholder="New lesson name (e.g. Part 2)" style="width:100%; padding-right:15px;">
                            </div>
                            <button type="button" onclick="document.getElementById('lessonFile').click()" class="btn-action" style="background:rgba(255,255,255,0.1); width:auto; white-space:nowrap; border:1px solid rgba(255,255,255,0.2);">
                                <i class="fa-solid fa-file-video"></i> Select Video
                            </button>
                            <button type="button" onclick="addNewLessonToList()" class="btn-action" style="background:var(--primary); width:auto; padding:10px 25px;">
                                <i class="fa-solid fa-plus"></i> Add to Lessons
                            </button>
                        </div>
                        <p id="fileSelectedInfo" style="font-size:0.85rem; color:var(--accent-teal); margin-top:10px; display:none;"></p>
                    </div>
                </div>

                <div class="name-edit-group" style="flex-direction:column; gap:15px; margin-top:30px;">
                    <label style="color:var(--text-main); font-weight:bold;"><i class="fa-solid fa-clipboard-question"></i> Manage Quiz:</label>
                    <div style="background: rgba(0,0,0,0.2); padding:20px; border-radius:16px; border:1px solid var(--glass-border);">
                        <?php if ($has_quiz): ?>
                            <p style="color:var(--text-main); margin-bottom:15px;">This course has a quiz. You can edit questions and answers.</p>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="edit_quiz.php?course_id=<?php echo $course_id; ?>" class="btn-action" style="background:var(--primary); width:auto; padding:10px 25px;">
                                    <i class="fa-solid fa-edit"></i> Edit Quiz
                                </a>
                                <button type="button" onclick="deleteQuiz(<?php echo $course_id; ?>)" class="btn-action" style="background:#f43f5e; width:auto; padding:10px 25px;">
                                    <i class="fa-solid fa-trash"></i> Delete Quiz
                                </button>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-main); margin-bottom:15px;">This course does not have a quiz yet. You can add one.</p>
                            <button type="button" onclick="addQuizToCourse(<?php echo $course_id; ?>)" class="btn-action" style="background:var(--accent-teal); width:auto; padding:10px 25px;">
                                <i class="fa-solid fa-plus"></i> Add Quiz
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <button id="saveCourseBtn" type="submit" class="btn-save"
                    style="width:100%; margin-top:30px; background:linear-gradient(135deg, #3b82f6, #2563eb); font-size:1.2rem; padding:15px; box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);"><i
                        class="fa-solid fa-save"></i> Save All Changes</button>
            </form>
        </div>
    </div>

    <script src="../Main/alert-system.js?v=<?php echo time(); ?>"></script>
    <script src="edit_course.js"></script>
</body>
</html>