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
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($row['creator_name']); ?></td>
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

                                <!-- Admin Edit Course (Opens edit_course.php but we must ensure it bypasses creator check if admin) -->
                                <a href="../profile/edit_course.php?id=<?php echo $row['course_id']; ?>"
                                    class="btn-action-small btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>