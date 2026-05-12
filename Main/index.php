<?php
session_start();
require_once('db_connection.php');
require_once('level_helper.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT u.full_name, COALESCE(w.token_balance, 0) as token_balance, u.xp, u.level, u.streak_days, u.last_streak_date, u.last_login, u.profile_pic FROM users u LEFT JOIN wallet w ON u.user_id = w.user_id WHERE u.user_id = ?";
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

$row = $user; // Alias for legacy code

//Streak
if ($row) {
    $db_name = $row['full_name'];
    $db_tokens = $row['token_balance'];
    $db_xp = $row['xp'];
    $db_lvl = $row['level'];
    $db_pic = $row['profile_pic'] ?? 'default.png';

    $streak = $row['streak_days'] ?? 0;
    $last_streak_date = $row['last_streak_date'];

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($last_streak_date !== $today) {
        if ($last_streak_date === $yesterday) {
            $streak++;
        } else {
            $streak = 1;
        }
        $update_hist = "UPDATE users SET streak_days = ?, last_streak_date = ?, last_login = NOW() WHERE user_id = ?";
        $stmt_hist = mysqli_prepare($conn, $update_hist);
        mysqli_stmt_bind_param($stmt_hist, "isi", $streak, $today, $user_id);
        mysqli_stmt_execute($stmt_hist);
    } else {
        $update_hist = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
        $stmt_hist = mysqli_prepare($conn, $update_hist);
        mysqli_stmt_bind_param($stmt_hist, "i", $user_id);
        mysqli_stmt_execute($stmt_hist);
    }

    //level
    $level_data = getLevelData($db_xp);
    $db_lvl = $level_data['level'];
    $display_xp = $level_data['current_level_xp'];
    $xp_percentage = $level_data['progress_percent'];
    $next_lvl_req = $level_data['next_level_required'];

    if ($db_lvl != $row['level']) {
        mysqli_query($conn, "UPDATE users SET level = $db_lvl WHERE user_id = $user_id");
    }
} else {
    $db_name = "مستخدم";
    $db_tokens = 0;
    $db_xp = 0;
    $db_lvl = 1;
    $db_unlocked = '';
    $streak = 0;
    $db_pic = 'default.png';
}

$unlocked_array = [];
$enroll_q = mysqli_query($conn, "SELECT e.course_id, c.title FROM enrollments e JOIN courses c ON e.course_id = c.course_id WHERE e.student_id = $user_id AND e.is_active = 1");
if ($enroll_q) {
    while ($erow = mysqli_fetch_assoc($enroll_q)) {
        $unlocked_array[] = (int)$erow['course_id'];
        $unlocked_array[] = trim($erow['title']);
    }
}

// Also check token_ledger for hardcoded skill purchases (those without a database course_id)
$ledger_q = mysqli_query($conn, "SELECT description FROM token_ledger WHERE user_id = $user_id AND action_type = 'Purchase' AND description LIKE 'شراء دورة: %'");
if ($ledger_q) {
    while ($lrow = mysqli_fetch_assoc($ledger_q)) {
        $purchased_title = str_replace('شراء دورة: ', '', $lrow['description']);
        $unlocked_array[] = trim($purchased_title);
    }
}

// Already calculated above in level_data


$name_parts = explode(' ', trim($db_name));
$initials = mb_substr($name_parts[0], 0, 1, 'UTF-8');
if (count($name_parts) > 1) {
    $initials .= '.' . mb_substr($name_parts[1], 0, 1, 'UTF-8');
}

// Unread Messages Count
$msg_count_q = mysqli_query($conn, "SELECT COUNT(*) as unread FROM messages WHERE Receiver_id = $user_id AND is_read = 0");
$unread_msg_count = mysqli_fetch_assoc($msg_count_q)['unread'] ?? 0;

// Unread Notifications Count
$notif_count_q = mysqli_query($conn, "SELECT COUNT(*) as unread FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_notif_count = mysqli_fetch_assoc($notif_count_q)['unread'] ?? 0;

$total_unread = $unread_msg_count + $unread_notif_count;
?>

<!DOCTYPE html>
<html lang="en" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill-Step | Skill Exchange Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body>

    <nav>
        <a href="index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50">
            Skill-Step
        </a>

        <div class=" user-stats">
            <div class="stat-badge" title="Streak">
                <i class="fa-solid fa-fire streak-icon"></i>
                <span id="streakDays"><?php echo $streak; ?> Days</span>
            </div>
            <div class="stat-badge token-link" id="tokenBadge" title="Current Balance">
                <a href="../Tokens/tokens.php">
                    <i class="fa-solid fa-coins token-icon"></i>
                    <span style="font-weight:900; color:var(--accent-gold); font-size:1.1rem;"
                        id="userTokens"><?php echo htmlspecialchars($db_tokens); ?></span>
                </a>
            </div>
            <div class="level-container" title="Level">
                <div class="level-badge"><i class="fa-solid fa-star"></i> LVL <span
                        id="userLevel"><?php echo htmlspecialchars($db_lvl); ?></span></div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="xpBar" style="width: <?php echo $xp_percentage; ?>%;"></div>
                </div>
                <span style="font-size:0.85rem; color:var(--text-main); font-weight:600;"><span
                        id="xpText"><?php echo htmlspecialchars($display_xp); ?></span>/<span id="nextLevelXP"><?php echo $next_lvl_req; ?></span> XP</span>
            </div>
            <div class="profile-dropdown-container" onclick="toggleProfileDropdown(event)">
                <?php if($total_unread > 0): ?>
                    <span class="notif-badge"><?php echo $total_unread; ?></span>
                <?php endif; ?>
                <?php if ($db_pic !== 'default.png' && $db_pic !== 'images/avatar1.png' && $db_pic !== '../images/avatar1.png' && !empty($db_pic)): ?>
                    <img src="../profile/uploads/<?php echo htmlspecialchars($db_pic); ?>" alt="Avatar"
                        class="profile-avatar"
                        style="width: 50px; height: 50px; border-radius: 16px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); cursor: pointer; transition: all 0.3s; box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);"
                        onmouseover="this.style.transform='scale(1.1) rotate(5deg)'"
                        onmouseout="this.style.transform='scale(1)'"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="profile-avatar initials-fallback"
                        style="width: 50px; height: 50px; border-radius: 16px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border: 2px solid rgba(255,255,255,0.3); display: none; place-items: center; font-weight: 800; font-size: 1.2rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);"
                        onmouseover="this.style.transform='scale(1.1) rotate(5deg)'"
                        onmouseout="this.style.transform='scale(1)'">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>

                <?php else: ?>
                    <div class="profile-avatar"
                        style="width: 50px; height: 50px; border-radius: 16px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border: 2px solid rgba(255,255,255,0.3); display: grid; place-items: center; font-weight: 800; font-size: 1.2rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);"
                        onmouseover="this.style.transform='scale(1.1) rotate(5deg)'"
                        onmouseout="this.style.transform='scale(1)'">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                <?php endif; ?>

                <div class="profile-dropdown-menu" id="profileDropdown">
                    <div class="dropdown-header">
                        <i class="fa-solid fa-user-astronaut"></i> <?php echo htmlspecialchars($db_name); ?>
                    </div>
                    <?php if (isset($_SESSION['roles']) && strpos($_SESSION['roles'], 'admin') !== false): ?>
                        <a href="../Admin/admin_users.php" style="color:#f59e0b;"><i class="fa-solid fa-shield-halved line-icon" style="color:#f59e0b;"></i>Admin Dashboard</a>
                    <?php endif; ?>
                    <a href="../profile/profile.php"><i class="fa-solid fa-user line-icon"></i>Profile</a>

                    <a href="../profile/profile.php#notifications" style="display:flex; align-items:center;">
                        <i class="fa-solid fa-bell line-icon" style="color:#f59e0b;"></i>
                        Notifications
                        <?php if($unread_notif_count > 0): ?>
                            <span class="dropdown-notif-count" style="background:rgba(245, 158, 11, 0.2); color:#f59e0b;"><?php echo $unread_notif_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="../profile/profile.php#unlocked-courses"><i
                            class="fa-solid fa-graduation-cap line-icon"></i> My Courses</a>

                    <a href="../profile/profile.php#achievements"><i
                            class="fa-solid fa-award line-icon"></i>Achievements</a>

                    <a href="../Tokens/tokens.php"><i class="fa-solid fa-coins line-icon"></i> Tokens</a>

                    <a href="chat.php" style="display:flex; align-items:center;">
                        <i class="fa-solid fa-comments line-icon" style="color:var(--accent-teal);"></i>
                        Messages
                        <?php if($unread_msg_count > 0): ?>
                            <span class="dropdown-notif-count"><?php echo $unread_msg_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="../profile/settings.php"><i class="fa-solid fa-gear line-icon"></i> Settings</a>
                    <div class="dropdown-divider"></div>


                    <a href="../Login/logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="filters-container" id="filtersContainer">

        <div class="search-bar-group" id="searchBarGroup" style="display: none;">
            <button class="close-search-btn" onclick="toggleSearch()" title="Close Search">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <input type="text" id="searchInput" placeholder="Search for skills or mentors..." onkeyup="filterData()">
            <button class="submit-search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>

        <div class="filter-group filter-item">
            <label><i class="fa-solid fa-layer-group"></i> Filter by Worlds (Categories)</label>
            <select id="catSelect" onchange="filterData()">
                <option value="all">🌐 Explore All Worlds</option>
                <option value="programming">💻 Programming World</option>
                <option value="design">🎨 Design World</option>
                <option value="languages">🗣️ Languages World</option>
            </select>
        </div>
        <div class="filter-group filter-item">
            <label><i class="fa-solid fa-sack-dollar"></i> Cost (Tokens)</label>
            <select id="priceSelect" onchange="filterData()">
                <option value="all">💫 Explore All Courses</option>
                <option value="free">🎁 Free Courses</option>
                <option value="low">🥉 Less than 50 tokens</option>
                <option value="high">🏆 More than 50 tokens</option>
            </select>
        </div>

        <div class="filter-action toggle-search-wrapper">
            <button class="btn-search-toggle" onclick="toggleSearch()" title="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
    </div>



    <div class="main-content">
        <div class="card-container" id="grid">
            <?php
            $user_courses_query = "
                SELECT courses.*, courses.course_id AS skill_id, courses.price_tokens AS price, 100 AS xp, courses.creator_id AS user_id, 
                       (SELECT categories.name FROM categories JOIN skills ON skills.category_id = categories.category_id WHERE skills.skill_id = courses.skill_id LIMIT 1) AS category,
                       u.full_name AS User_name, u.profile_pic,
                       (SELECT COUNT(*) FROM lessons v WHERE v.course_id = courses.course_id) AS actual_lessons
                FROM courses 
                JOIN users u ON courses.creator_id = u.user_id 
                WHERE courses.status = 'active'
                ORDER BY courses.course_id DESC";
            $res_user_courses = mysqli_query($conn, $user_courses_query);

            while ($c = mysqli_fetch_assoc($res_user_courses)) {
                $c_title = htmlspecialchars($c['title']);
                $c_cat = $c['category'];
                $c_price = $c['price'];
                $c_icon = $c['icon'] ?? 'fa-code';
                if (strpos($c_icon, 'fa-solid') === false && strpos($c_icon, 'fa-brands') === false) {
                    $c_icon = 'fa-solid ' . $c_icon;
                }
                $c_author = htmlspecialchars($c['User_name']);
                $c_pic = $c['profile_pic'] ? $c['profile_pic'] : 'default.png';

                if ($c_pic === 'default.png' || $c_pic === 'images/avatar1.png' || $c_pic === '../images/avatar1.png') {
                    $pic_html = "<div class='provider-avatar' style='background: linear-gradient(135deg, #10b981, #059669)'>" . mb_substr($c_author, 0, 1, 'UTF-8') . "</div>";
                } else {
                    $pic_html = "<img src='../profile/uploads/" . htmlspecialchars($c_pic) . "' alt='Avatar' style='width:35px; height:35px; border-radius:50%; object-fit:cover; border:2px solid var(--primary); margin-left:10px;' onerror=\"this.style.display='none'; this.nextElementSibling.style.display='flex';\"><div class='provider-avatar' style='background: linear-gradient(135deg, #10b981, #059669); display:none;'>" . mb_substr($c_author, 0, 1, 'UTF-8') . "</div>";
                }

                $price_display = $c_price == 0 ? 'Free' : $c_price;
                $c_id = $c['skill_id'];
                $is_unlocked = in_array((int)$c_id, $unlocked_array);

                $is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $c['user_id']);

                $rating = number_format(rand(45, 50) / 10, 1);
                $xp = $c['xp'] ?? 100;
                $lessons = $c['actual_lessons'] > 0 ? $c['actual_lessons'] : ($c['lessons'] ?? 0);
                $support_type = $c['support_type'] ?? 'chat';
                $icon_type = strpos($support_type, 'chat') !== false ? 'fa-comment-dots' : 'fa-headset';
                $support_text = htmlspecialchars($support_type);
                $desc_text = !empty($c['description']) ? htmlspecialchars($c['description']) : 'Develop your skills and immerse yourself in a world full of unique challenges to advance in your career as a true hero.';

                if ($is_unlocked || $is_owner) {
                    $locked_cls = '';
                    $action_icon = 'fa-play';
                    $action_text = 'Watch Course';
                    $button_class = 'btn-action';
                    $button_attrs = "onclick=\"window.location.href='course_player.php?id={$c_id}'\"";
                } else {
                    $locked_cls = $c_price > 0 ? 'locked' : '';
                    $action_icon = $c_price > 0 ? 'fa-lock' : 'fa-play';
                    $action_text = $c_price > 0 ? 'Unlock and Start the Challenge' : 'Start Now for Free';
                    $button_class = "btn-action {$locked_cls} unlock-btn";
                    $button_attrs = "data-provider='" . htmlspecialchars($c_author, ENT_QUOTES) . "' data-price='{$c_price}' data-xp='{$xp}' data-skill-title='" . htmlspecialchars($c_title, ENT_QUOTES) . "' data-course-id='{$c_id}'";
                }

                echo "
                <div class='skill-card card-epic' data-cat='{$c_cat}' data-price='{$c_price}' style='border: 1px solid var(--primary);'>
                    <div class='price-tag'><i class='fa-solid fa-coins' style='color:var(--accent-gold)'></i> {$price_display}</div>
                    <div class='card-icon'><i class='{$c_icon}' style='color:var(--primary);'></i></div>
                    <h3 class='card-title'>{$c_title}</h3>
                    
                    <div class='provider-info' style='cursor: pointer; transition: transform 0.2s;' onmouseover='this.style.transform=\"scale(1.02)\"' onmouseout='this.style.transform=\"scale(1)\"' onclick='openMentorProfile({$c['user_id']})'>
                        {$pic_html}
                        <span>Mentor: {$c_author}</span>
                        <span style='margin-left:auto; color:var(--accent-gold); font-weight:bold;'><i class='fa-solid fa-star'></i> {$rating}</span>
                    </div>

                    <div class='features-grid'>
                        <div class='feature-item'><i class='fa-solid fa-video' style='color:#60a5fa'></i> {$lessons} Lessons</div>
                        <div class='feature-item'><i class='fa-solid {$icon_type}' style='color:#34d399'></i> {$support_text}</div>
                        <div class='feature-item'><i class='fa-solid fa-trophy' style='color:#fbbf24'></i> +{$xp} XP</div>
                        <div class='feature-item'><i class='fa-solid fa-users' style='color:var(--accent-gold)'></i> Community Course</div>
                    </div>

                    <p class='card-desc'>{$desc_text}</p>

                    <button class='{$button_class}' {$button_attrs}>
                        <i class='fa-solid {$action_icon}' id='icon-usr-{$c_id}'></i> <span id='text-usr-{$c_id}'>{$action_text}</span>
                    </button>
                </div>";
            }

            $categories = ['programming', 'design', 'languages'];
            $rarities = ['common', 'epic', 'legendary'];

            $providers = [
                ['name' => 'Nour Eldin', 'avatar' => 'NE', 'bg' => 'linear-gradient(135deg, var(--primary), var(--secondary))'],
                ['name' => 'Malek', 'avatar' => 'MA', 'bg' => 'linear-gradient(135deg, #f59e0b, #ef4444)'],
                ['name' => 'Zaid', 'avatar' => 'ZA', 'bg' => 'linear-gradient(135deg, #10b981, #059669)'],
                ['name' => 'Omar', 'avatar' => 'OM', 'bg' => 'linear-gradient(135deg, #8b5cf6, #3b82f6)'],
                ['name' => 'Mina', 'avatar' => 'MI', 'bg' => 'linear-gradient(135deg, #f59e0b, #eab308)'],
                ['name' => 'Nosa', 'avatar' => 'NO', 'bg' => 'linear-gradient(135deg, #06b6d4, #3b82f6)'],
                ['name' => 'Mohamed Ashraf', 'avatar' => 'MA', 'bg' => 'linear-gradient(135deg, #a855f7, #ec4899)'],
                ['name' => 'Sarah Ali', 'avatar' => 'SA', 'bg' => 'linear-gradient(135deg, #ef4444, #f97316)'],
                ['name' => 'Ahmed Youssef', 'avatar' => 'AY', 'bg' => 'linear-gradient(135deg, #3b82f6, #14b8a6)']
            ];

            $skills_data = [
                'programming' => [
                    ['icon' => 'fa-brands fa-react', 'title' => 'Mastering React.js'],
                    ['icon' => 'fa-brands fa-node-js', 'title' => 'Advanced Node.js'],
                    ['icon' => 'fa-brands fa-python', 'title' => 'AI with Python'],
                    ['icon' => 'fa-brands fa-java', 'title' => 'Java Fundamentals'],
                    ['icon' => 'fa-solid fa-code', 'title' => 'Algorithm Building (C++)'],
                    ['icon' => 'fa-brands fa-php', 'title' => 'Web Development with PHP'],
                    ['icon' => 'fa-brands fa-vuejs', 'title' => 'Mastering Vue.js'],
                    ['icon' => 'fa-brands fa-angular', 'title' => 'Advanced Angular Apps'],
                    ['icon' => 'fa-brands fa-swift', 'title' => 'iOS Development (Swift)'],
                    ['icon' => 'fa-brands fa-android', 'title' => 'Android Development'],
                    ['icon' => 'fa-brands fa-github', 'title' => 'Project Management with Git'],
                    ['icon' => 'fa-solid fa-database', 'title' => 'SQL Database Design'],
                    ['icon' => 'fa-brands fa-docker', 'title' => 'Docker for Developers'],
                    ['icon' => 'fa-brands fa-aws', 'title' => 'Cloud Computing (AWS)'],
                    ['icon' => 'fa-brands fa-linux', 'title' => 'Linux Server Administration'],
                    ['icon' => 'fa-solid fa-code-branch', 'title' => 'Data Structures for Pros'],
                    ['icon' => 'fa-solid fa-bug', 'title' => 'Ethical Hacking'],
                    ['icon' => 'fa-solid fa-gamepad', 'title' => 'Game Dev with Unity'],
                    ['icon' => 'fa-brands fa-js', 'title' => 'JavaScript Ninja'],
                    ['icon' => 'fa-brands fa-html5', 'title' => 'Advanced HTML5 & CSS3']
                ],
                'design' => [
                    ['icon' => 'fa-solid fa-wand-magic-sparkles', 'title' => 'UI/UX Legend (Rare)'],
                    ['icon' => 'fa-solid fa-palette', 'title' => 'Advanced Graphic Design'],
                    ['icon' => 'fa-solid fa-bezier-curve', 'title' => 'Vector Drawing (Illustrator)'],
                    ['icon' => 'fa-solid fa-object-group', 'title' => 'Digital Compositing (Photoshop)'],
                    ['icon' => 'fa-solid fa-cubes', 'title' => '3D Design (Blender)'],
                    ['icon' => 'fa-solid fa-video', 'title' => 'VFX (After Effects)'],
                    ['icon' => 'fa-solid fa-pen-nib', 'title' => 'Pro Logo Design'],
                    ['icon' => 'fa-solid fa-font', 'title' => 'Classic Typography'],
                    ['icon' => 'fa-brands fa-figma', 'title' => 'Master Figma in 7 Days'],
                    ['icon' => 'fa-solid fa-images', 'title' => 'Photo Retouching'],
                    ['icon' => 'fa-solid fa-clapperboard', 'title' => 'Video Editing (Premiere)'],
                    ['icon' => 'fa-solid fa-swatchbook', 'title' => 'Color Theory for Designers'],
                    ['icon' => 'fa-solid fa-crop', 'title' => 'Layout & Composition Secrets'],
                    ['icon' => 'fa-solid fa-pen-ruler', 'title' => 'Tech Product Design'],
                    ['icon' => 'fa-solid fa-vector-square', 'title' => 'Basic Animation for Designers'],
                    ['icon' => 'fa-solid fa-stamp', 'title' => 'Visual Identity Design'],
                    ['icon' => 'fa-solid fa-vr-cardboard', 'title' => 'VR Interface Design'],
                    ['icon' => 'fa-solid fa-display', 'title' => 'Digital Ad Design']
                ],
                'languages' => [
                    ['icon' => 'fa-solid fa-comments', 'title' => 'Fluent English Conversation'],
                    ['icon' => 'fa-solid fa-language', 'title' => 'Spanish for Beginners'],
                    ['icon' => 'fa-solid fa-earth-americas', 'title' => 'Business English'],
                    ['icon' => 'fa-solid fa-book', 'title' => 'Speed Reading in English'],
                    ['icon' => 'fa-solid fa-pen', 'title' => 'Academic Writing (English)'],
                    ['icon' => 'fa-solid fa-microphone', 'title' => 'Accent Reduction'],
                    ['icon' => 'fa-solid fa-globe', 'title' => 'French for Daily Life'],
                    ['icon' => 'fa-solid fa-plane-departure', 'title' => 'German for Travelers'],
                    ['icon' => 'fa-solid fa-language', 'title' => 'Russian B1 Level'],
                    ['icon' => 'fa-solid fa-language', 'title' => 'Easy Turkish'],
                    ['icon' => 'fa-solid fa-laptop-code', 'title' => 'Tech Jargon in English'],
                    ['icon' => 'fa-solid fa-language', 'title' => 'Japanese (Basic Kanji)'],
                    ['icon' => 'fa-solid fa-comments', 'title' => 'Simplified Mandarin'],
                    ['icon' => 'fa-solid fa-landmark', 'title' => 'Italian & Culture'],
                    ['icon' => 'fa-solid fa-comment-dots', 'title' => 'Linguistic Negotiation'],
                    ['icon' => 'fa-solid fa-language', 'title' => 'Advanced Arabic Grammar']
                ]
            ];

            $generated_cards = [];
            $id_counter = 1;
            foreach ($skills_data as $cat_key => $skills) {
                foreach ($skills as $skill) {

                    $provider = $providers[array_rand($providers)];

                    $rarity = $rarities[array_rand($rarities)];

                    if ($rarity === 'common') {
                        $price = rand(0, 3) == 0 ? 0 : rand(10, 45);
                    } elseif ($rarity === 'epic') {
                        $price = rand(30, 80);
                    } else { // legendary
                        $price = rand(70, 150);
                    }

                    $rating = number_format(rand(40, 50) / 10, 1);
                    $xp = $price == 0 ? 50 : $price * rand(3, 6);
                    $lessons = rand(4, 25);

                    $icon_type = rand(0, 1) ? 'fa-headset' : 'fa-comment-dots';
                    $support_text = rand(0, 1) ? '24/7 Support' : 'Chat Available';
                    $badge_icon = $rarity === 'legendary' ? 'fa-crown' : 'fa-medal';
                    $badge_text = $rarity === 'legendary' ? 'Crown of Creativity' : 'Epic Badge';

                    $is_unlocked = in_array($skill['title'], $unlocked_array);
                    $price_display = $price == 0 ? 'Free' : $price;

                    if ($is_unlocked) {
                        $locked_cls = '';
                        $action_icon = 'fa-check';
                        $action_text = 'Unlocked';
                        $button_class = 'btn-action';
                        $button_attrs = '';
                    } else {
                        $locked_cls = $price > 0 ? 'locked' : '';
                        $action_icon = $price > 0 ? 'fa-lock' : 'fa-play';
                        $action_text = $price > 0 ? 'Unlock & Start Challenge' : 'Start Now for Free';
                        $button_class = "btn-action {$locked_cls} unlock-btn";
                        $button_attrs = "data-provider='" . htmlspecialchars($provider['name'], ENT_QUOTES) . "' data-price='{$price}' data-xp='{$xp}' data-skill-title='" . htmlspecialchars($skill['title'], ENT_QUOTES) . "'";
                    }

                    // ---- بناء كود HTML للبطاقة (Building the HTML Card) ----
                    $card_html = "
                    <div class='skill-card card-{$rarity}' data-cat='{$cat_key}' data-price='{$price}'>
                        <div class='price-tag'><i class='fa-solid fa-coins' style='color:var(--accent-gold)'></i> {$price_display}</div>
                        <div class='card-icon'><i class='{$skill['icon']}'></i></div>
                        <h3 class='card-title'>{$skill['title']}</h3>

                        <div class='provider-info'>
                            <div class='provider-avatar' style='background: {$provider['bg']}'>{$provider['avatar']}</div>
                            <span>Mentor: {$provider['name']}</span>
                            <span style='margin-left:auto; color:var(--accent-gold); font-weight:bold;'><i class='fa-solid fa-star'></i> {$rating}</span>
                        </div>

                        <div class='features-grid'>
                            <div class='feature-item'><i class='fa-solid fa-video' style='color:#60a5fa'></i> {$lessons} Lessons</div>
                            <div class='feature-item'><i class='fa-solid {$icon_type}' style='color:#34d399'></i> {$support_text}</div>
                            <div class='feature-item'><i class='fa-solid fa-trophy' style='color:#fbbf24'></i> +{$xp} XP</div>
                            <div class='feature-item'><i class='fa-solid {$badge_icon}' style='color:var(--accent-gold)'></i> {$badge_text}</div>
                        </div>

                        <p class='card-desc'>Develop your skills and immerse yourself in a world full of unique challenges to advance in your career as a true hero.</p>

                        <button class='{$button_class}' {$button_attrs}>
                            <i class='fa-solid {$action_icon}' id='icon-{$id_counter}'></i> <span id='text-{$id_counter}'>{$action_text}</span>
                        </button>
                    </div>";

                    $generated_cards[] = $card_html;
                    $id_counter++;
                }
            }

            // ترتيب البطاقات بشكل عشوائي تماماً قبل عرضها حتى لا تظهر بترتيب ثابت
            shuffle($generated_cards);

            // أخيراً، طباعة جميع البطاقات داخل الـ div في الـ HTML
            foreach ($generated_cards as $card) {
                echo $card;
            }
            ?>

        </div>
        <div id="showMoreContainer" style="text-align: center; margin-top: 40px; display: none;">
            <button class="btn-action"
                style="width: auto; padding: 15px 40px; display: inline-flex; border-radius: 30px; box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);"
                onclick="showMoreCards()">
                Show More <i class="fa-solid fa-angle-down" style="margin-left: 10px;"></i>
            </button>
        </div>
    </div>

    <div class="modal-overlay" id="purchaseModal">
        <div class="purchase-modal">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Purchase</h3>
                <button class="modal-close" type="button" onclick="hidePurchaseModal()"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <p class="modal-message"></p>
            <div class="modal-actions">
                <button class="btn-action btn-secondary" type="button" onclick="hidePurchaseModal()">Cancel</button>
                <button class="btn-action" type="button" id="purchaseConfirm">Buy Now</button>
            </div>
        </div>
    </div>

    <!-- Mentor Profile Modal -->
    <div class="modal-overlay" id="mentorProfileModal" style="display: none; align-items: center; justify-content: center; z-index: 10001;">
        <div class="mentor-modal" style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.98)); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 30px; width: 90%; max-width: 500px; box-shadow: 0 30px 60px rgba(0,0,0,0.6); position: relative; transform: scale(0.9); opacity: 0; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <button onclick="closeMentorProfile()" style="position: absolute; top: 20px; left: 20px; background: rgba(255,255,255,0.05); border: none; color: #94a3b8; font-size: 1.5rem; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.color='white'; this.style.background='rgba(255,255,255,0.1)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <div id="mentorModalContent" style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Content injected via JS -->
                <div style="text-align: center; padding: 40px;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
            </div>
        </div>
    </div>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>

</html>