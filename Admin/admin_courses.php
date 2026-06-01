<?php
session_start();
require_once '../Main/db_connection.php';
require_once '../Main/auth_check.php';

$user = checkUserSession($conn);
if (strpos($user['roles'], 'admin') === false) {
    header('Location: ../Main/index.php');
    exit();
}

// Handle Status Change (Approve, Reject, Draft)
if (isset($_POST['change_status'])) {
    $course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
    $new_status = $_POST['new_status'];

    if ($course_id && in_array($new_status, ['draft', 'pending_review', 'active', 'rejected'])) {
        $update = "UPDATE courses SET status = ? WHERE course_id = ?";
        $ustmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($ustmt, "si", $new_status, $course_id);
        mysqli_stmt_execute($ustmt);
    }
    header("Location: admin_courses.php");
    exit();
}

// Stats
$total_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM courses"))['count'];
$active_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM courses WHERE status = 'active'"))['count'];
$pending_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM courses WHERE status = 'pending_review'"))['count'];

// Fetch all courses
$query = "
    SELECT c.*, u.full_name as creator_name, 
           (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.course_id) as lesson_count
    FROM courses c 
    JOIN users u ON c.creator_id = u.user_id 
    ORDER BY 
        CASE WHEN c.status = 'pending_review' THEN 1 ELSE 2 END, 
        c.created_at DESC
";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Courses Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Main/style.css">
    <style>
        .admin-dashboard {
            padding: 40px;
            max-width: 1400px;
            margin: auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 25px;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5), 0 0 15px var(--primary-glow);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            margin-top: 20px;
            padding: 10px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 18px 15px;
            text-align: center;
            border-bottom: 1px solid var(--glass-border);
        }

        tbody tr {
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        th {
            background: rgba(0, 0, 0, 0.2);
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-block;
        }

        .badge.active {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge.pending_review {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge.rejected {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .badge.draft {
            background: rgba(107, 114, 128, 0.15);
            color: #9ca3af;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        .btn-action-small {
            padding: 8px 16px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.85rem;
            margin: 2px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-approve {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.4);
        }

        .btn-approve:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }

        .btn-reject {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .btn-reject:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.4);
        }

        .btn-edit:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }

        .nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 10px 20px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .nav-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
        }
    </style>
</head>

<body>
    <nav>
        <a href="../Main/index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="40" height="40"
                style="border-radius: 50%; object-fit: cover;">
            <span style="color: white;font-size: 20px;margin: 0;">Skill-Step Admin</span>
        </a>
        <?php
        $nav_admin_id = $_SESSION['user_id'];
        $unread_msg_q = mysqli_query($conn, "SELECT COUNT(*) as unread FROM messages WHERE Receiver_id = $nav_admin_id AND is_read = 0");
        $unread_msg_count = $unread_msg_q ? mysqli_fetch_assoc($unread_msg_q)['unread'] : 0;

        $pending_course_q = mysqli_query($conn, "SELECT COUNT(*) as pending FROM courses WHERE status = 'pending_review'");
        $pending_course_count = $pending_course_q ? mysqli_fetch_assoc($pending_course_q)['pending'] : 0;

        $pending_cashout_q = mysqli_query($conn, "SELECT COUNT(*) as pending FROM cash_out_requests WHERE status = 'pending'");
        $pending_cashout_count = $pending_cashout_q ? mysqli_fetch_assoc($pending_cashout_q)['pending'] : 0;
        ?>
        <div class="nav-links">
            <a href="admin_courses.php" class="nav-btn active"><i class="fas fa-book"></i> Courses
                <?php if ($pending_course_count > 0)
                    echo '<span style="background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:0.75rem;margin-right:5px;font-weight:bold;">' . $pending_course_count . '</span>'; ?></a>
            <a href="admin_users.php" class="nav-btn"><i class="fas fa-users"></i> Users</a>
            <a href="admin_escrow.php" class="nav-btn"><i class="fas fa-money-bill-wave"></i> Escrow</a>
            <a href="admin_cashouts.php" class="nav-btn"><i class="fas fa-hand-holding-usd"></i> Cashouts
                <?php if ($pending_cashout_count > 0)
                    echo '<span style="background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:0.75rem;margin-right:5px;font-weight:bold;">' . $pending_cashout_count . '</span>'; ?></a>
            <a href="../Main/chat.php" class="nav-btn"><i class="fas fa-comments"></i> Messages
                <?php if ($unread_msg_count > 0)
                    echo '<span style="background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:0.75rem;margin-right:5px;font-weight:bold;">' . $unread_msg_count . '</span>'; ?></a>
            <a href="../Main/index.php" class="nav-btn"><i class="fas fa-home"></i> Home</a>
            <a href="../Login/logout.php" class="nav-btn"
                style="color: #f87171; border-color: rgba(239, 68, 68, 0.3);"><i class="fas fa-sign-out-alt"></i>
                Logout</a>
        </div>
    </nav>

    <div class="admin-dashboard">
        <h1><i class="fas fa-book-open"></i> Courses Management</h1>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_courses; ?></div>
                <div>Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_courses; ?></div>
                <div>Active Courses</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="stat-number"><?php echo $pending_courses; ?></div>
                <div>Pending Review</div>
            </div>
        </div>

        <!-- Courses Table -->
        <h2><i class="fas fa-list"></i> Courses List</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Creator</th>
                        <th>Lessons</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>#<?php echo $row['course_id']; ?></td>
                            <td>
                                <strong style="cursor: pointer; color: #60a5fa; transition: color 0.2s;"
                                    onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#60a5fa'"
                                    onclick="openCourseDetails(<?php echo $row['course_id']; ?>)"><?php echo htmlspecialchars($row['title']); ?></strong>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                    <span style="font-weight: 600; cursor: pointer; color: #a5b4fc; transition: color 0.2s;"
                                        onmouseover="this.style.color='#c7d2fe'" onmouseout="this.style.color='#a5b4fc'"
                                        onclick="window.location.href='../Main/chat.php?user_id=<?php echo $row['creator_id']; ?>'">
                                        <?php echo htmlspecialchars($row['creator_name']); ?>
                                    </span>
                                    <a href="../Main/chat.php?user_id=<?php echo $row['creator_id']; ?>"
                                        class="btn-action-small"
                                        style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.75rem; padding: 4px 8px; border-radius: 8px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; font-weight: 600; transition: all 0.2s;"
                                        onmouseover="this.style.background='#10b981'; this.style.color='white';"
                                        onmouseout="this.style.background='rgba(16, 185, 129, 0.15)'; this.style.color='#34d399';">
                                        <i class="fas fa-envelope"></i> Send Message
                                    </a>
                                </div>
                            </td>
                            <td><?php echo $row['lesson_count']; ?></td>
                            <td><?php echo $row['price_tokens']; ?> 🪙</td>
                            <td>
                                <span class="badge <?php echo $row['status']; ?>">
                                    <?php
                                    switch ($row['status']) {
                                        case 'active':
                                            echo 'Active';
                                            break;
                                        case 'pending_review':
                                            echo 'Pending';
                                            break;
                                        case 'rejected':
                                            echo 'Rejected';
                                            break;
                                        case 'draft':
                                            echo 'Draft';
                                            break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="course_id" value="<?php echo $row['course_id']; ?>">

                                    <?php if ($row['status'] == 'pending_review' || $row['status'] == 'rejected'): ?>
                                        <button type="submit" name="change_status" value="1"
                                            class="btn-action-small btn-approve"
                                            onclick="document.getElementById('status_<?php echo $row['course_id']; ?>').value='active';">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($row['status'] == 'pending_review' || $row['status'] == 'active'): ?>
                                        <button type="submit" name="change_status" value="1" class="btn-action-small btn-reject"
                                            onclick="document.getElementById('status_<?php echo $row['course_id']; ?>').value='rejected';">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>

                                    <input type="hidden" name="new_status" id="status_<?php echo $row['course_id']; ?>"
                                        value="">
                                </form>

                                <!-- Admin View Course (Opens Details Modal) -->
                                <button type="button" class="btn-action-small btn-edit"
                                    style="background: rgba(99, 102, 241, 0.2); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.4);"
                                    onclick="openCourseDetails(<?php echo $row['course_id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Course Details Modal -->
    <div class="modal-overlay" id="courseDetailsModal"
        style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 10000; backdrop-filter: blur(8px);">
        <div class="mentor-modal"
            style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.98)); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 30px; width: 90%; max-width: 600px; max-height: 85vh; overflow-y: auto; box-shadow: 0 30px 60px rgba(0,0,0,0.6); position: relative; transform: scale(0.9); opacity: 0; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); color: white; text-align: right;">
            <button onclick="closeCourseDetails()"
                style="position: absolute; top: 20px; left: 20px; background: rgba(255,255,255,0.05); border: none; color: #94a3b8; font-size: 1.5rem; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center;"
                onmouseover="this.style.color='white'; this.style.background='rgba(255,255,255,0.1)'">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div id="courseModalContent" style="display: flex; flex-direction: column; gap: 20px;">
                <div style="text-align: center; padding: 40px;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openCourseDetails(courseId) {
            const modal = document.getElementById('courseDetailsModal');
            const content = document.getElementById('courseModalContent');

            modal.style.display = 'flex';
            setTimeout(() => {
                modal.querySelector('.mentor-modal').style.transform = 'scale(1)';
                modal.querySelector('.mentor-modal').style.opacity = '1';
            }, 10);

            content.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
            `;

            fetch('../Main/course_api.php?id=' + courseId)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        content.innerHTML = `<p style="color: #f43f5e; text-align: center;">Error: ${data.message}</p>`;
                        return;
                    }

                    const course = data.data;

                    let lessonsHtml = '';
                    if (course.lessons.length > 0) {
                        lessonsHtml = course.lessons.map((l, index) => `
                            <div style="background: rgba(255,255,255,0.05); padding: 12px 15px; border-radius: 12px; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; direction: rtl; text-align: right;">
                                <i class="fa-solid fa-circle-play" style="color: var(--primary); font-size: 1.1rem;"></i>
                                <span style="flex: 1; color: white; font-weight: 600;">${index + 1}. ${l.title}</span>
                            </div>
                        `).join('');
                    } else {
                        lessonsHtml = '<p style="color: #94a3b8; font-size: 0.95rem; text-align: center;">لا توجد دروس مرفوعة بعد.</p>';
                    }

                    content.innerHTML = `
                        <div style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; direction: rtl; text-align: right;">
                            <h2 style="color: white; margin: 0 0 10px 0; font-size: 1.6rem;">${course.title}</h2>
                            <div style="color: #94a3b8; font-size: 0.95rem; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                <span style="display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-user" style="color: var(--primary);"></i> Creator: ${course.creator_name}
                                    <a href="../Main/chat.php?user_id=${course.creator_id}" 
                                       style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.8rem; padding: 3px 8px; border-radius: 8px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; font-weight: 600; transition: all 0.2s;"
                                       onmouseover="this.style.background='#10b981'; this.style.color='white';"
                                       onmouseout="this.style.background='rgba(16, 185, 129, 0.15)'; this.style.color='#34d399';">
                                        <i class="fas fa-envelope"></i> Send Message                                     </a>
                                </span>
                                <span><i class="fa-solid fa-folder" style="color: var(--primary);"></i> Category: ${course.category_name}</span>
                                <span><i class="fa-solid fa-coins" style="color: var(--accent-gold);"></i> Price: ${course.price_tokens} tokens</span>
                            </div>
                        </div>
                        
                        <div style="direction: rtl; text-align: right;">
                            <h3 style="color: white; font-size: 1.2rem; margin-bottom: 10px;"><i class="fa-solid fa-file-lines" style="color: var(--primary);"></i> Course Description:</h3>
                            <p style="color: #e2e8f0; font-size: 1rem; line-height: 1.6; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">${course.description}</p>
                        </div>
                        
                        <div style="direction: rtl; text-align: right;">
                            <h3 style="color: white; font-size: 1.2rem; margin-bottom: 15px;"><i class="fa-solid fa-list" style="color: var(--primary);"></i> Lessons (${course.lessons.length}):</h3>
                            <div style="max-height: 250px; overflow-y: auto; padding-left: 5px;">
                                ${lessonsHtml}
                            </div>
                        </div>
                    `;
                })
                .catch(err => {
                    console.error(err);
                    content.innerHTML = `<p style="color: #f43f5e; text-align: center;">Error loading course data.</p>`;
                });
        }

        function closeCourseDetails() {
            const modal = document.getElementById('courseDetailsModal');
            if (modal) {
                modal.querySelector('.mentor-modal').style.transform = 'scale(0.9)';
                modal.querySelector('.mentor-modal').style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', (event) => {
            const modal = document.getElementById('courseDetailsModal');
            if (modal && modal.style.display === 'flex' && event.target === modal) {
                closeCourseDetails();
            }
        });
    </script>
</body>

</html>