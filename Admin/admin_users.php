<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/login.php');
    exit();
}

require_once '../Main/db_connection.php';
$admin_id = $_SESSION['user_id'];

// Check if admin
$check_admin = "SELECT roles FROM users WHERE user_id = ?";
$stmt_admin = mysqli_prepare($conn, $check_admin);
mysqli_stmt_bind_param($stmt_admin, "i", $admin_id);
mysqli_stmt_execute($stmt_admin);
$res_admin = mysqli_stmt_get_result($stmt_admin);
$admin_row = mysqli_fetch_assoc($res_admin);

if (!$admin_row || strpos($admin_row['roles'], 'admin') === false) {
    header('Location: ../Main/index.php');
    exit();
}

// Handle verify/unverify
if (isset($_POST['toggle_verify'])) {
    $target_id = filter_var($_POST['target_user_id'], FILTER_VALIDATE_INT);
    $new_status = $_POST['new_status'];

    if ($target_id && $target_id != $admin_id) {
        $update = "UPDATE users SET is_verified = ? WHERE user_id = ?";
        $ustmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($ustmt, "ii", $new_status, $target_id);
        mysqli_stmt_execute($ustmt);
    }
    header("Location: admin_users.php");
    exit();
}

// Handle suspend/unsuspend
if (isset($_POST['toggle_suspend'])) {
    $target_id = filter_var($_POST['target_user_id'], FILTER_VALIDATE_INT);
    $new_status = $_POST['new_status'];

    if ($target_id && $target_id != $admin_id) {
        $update = "UPDATE users SET is_suspended = ? WHERE user_id = ?";
        $ustmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($ustmt, "ii", $new_status, $target_id);
        mysqli_stmt_execute($ustmt);
    }
    header("Location: admin_users.php");
    exit();
}

// Stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'];
$verified_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE is_verified = 1"))['count'];
$suspended_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE is_suspended = 1"))['count'];

// Search Logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search) {
    $query = "SELECT user_id, full_name, email, roles, is_verified, is_suspended, created_at 
              FROM users 
              WHERE full_name LIKE ? OR email LIKE ? OR user_id = ? 
              ORDER BY user_id DESC";
    $stmt = mysqli_prepare($conn, $query);
    $search_param = "%$search%";
    mysqli_stmt_bind_param($stmt, "sss", $search_param, $search_param, $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $query = "SELECT user_id, full_name, email, roles, is_verified, is_suspended, created_at 
              FROM users 
              ORDER BY user_id DESC";
    $result = mysqli_query($conn, $query);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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

        .badge.verified {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge.unverified {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge.suspended {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
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

        .btn-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .btn-warning:hover {
            background: #f59e0b;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
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

        .search-container {
            margin-bottom: 30px;
            display: flex;
            justify-content: flex-end;
        }

        .search-box {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            color: white;
            font-family: 'Cairo', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 15px var(--primary-glow);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .search-box .clear-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.3s;
        }

        .search-box .clear-search:hover {
            color: #f87171;
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
            <a href="admin_courses.php" class="nav-btn"><i class="fas fa-book"></i> Courses
                <?php if ($pending_course_count > 0)
                    echo '<span style="background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:0.75rem;margin-right:5px;font-weight:bold;">' . $pending_course_count . '</span>'; ?></a>
            <a href="admin_users.php" class="nav-btn active"><i class="fas fa-users"></i> Users</a>
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
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div>Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $verified_users; ?></div>
                <div>Verified Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $suspended_users; ?></div>
                <div>Suspended Users</div>
            </div>
        </div>

        <!-- Users Table -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;"><i class="fas fa-users"></i> Users Management</h2>
            <form action="" method="GET" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name, email, or ID..." value="<?php echo htmlspecialchars($search); ?>">
                <?php if($search): ?>
                    <a href="admin_users.php" class="clear-search"><i class="fas fa-times-circle"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>#<?php echo $row['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['roles']); ?></td>
                            <td>
                                <?php if ($row['is_suspended']): ?>
                                    <span class="badge suspended">Suspended</span>
                                <?php else: ?>
                                    <span class="badge active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['is_verified']): ?>
                                    <span class="badge verified">Verified</span>
                                <?php else: ?>
                                    <span class="badge unverified">Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['user_id'] != $admin_id): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="target_user_id" value="<?php echo $row['user_id']; ?>">
                                        <?php if ($row['is_verified']): ?>
                                            <input type="hidden" name="new_status" value="0">
                                            <button type="submit" name="toggle_verify" class="btn-action-small btn-warning"
                                                onclick="return confirm('Are you sure you want to unverify this user?')">
                                                <i class="fas fa-times"></i> Unverify
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="1">
                                            <button type="submit" name="toggle_verify" class="btn-action-small btn-approve"
                                                onclick="return confirm('Are you sure you want to verify this user?')">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" style="display:inline; margin-right: 5px;">
                                        <input type="hidden" name="target_user_id" value="<?php echo $row['user_id']; ?>">
                                        <?php if ($row['is_suspended']): ?>
                                            <input type="hidden" name="new_status" value="0">
                                            <button type="submit" name="toggle_suspend" class="btn-action-small btn-approve"
                                                onclick="return confirm('Are you sure you want to unsuspend this user?')">
                                                <i class="fas fa-unlock"></i> Unsuspend
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="1">
                                            <button type="submit" name="toggle_suspend" class="btn-action-small btn-reject"
                                                onclick="return confirm('Are you sure you want to suspend this user?')">
                                                <i class="fas fa-ban"></i> Suspend
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #6b7280;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>