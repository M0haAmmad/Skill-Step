<?php
session_start();
require_once '../Main/db_connection.php';
require_once '../Main/auth_check.php';

$user = checkUserSession($conn);
if (empty($user['roles']) || strpos($user['roles'], 'admin') === false) {
    header('Location: ../Main/index.php');
    exit();
}

// Handle Escrow Release
if (isset($_POST['release_escrow'])) {
    $escrow_id = filter_var($_POST['escrow_id'], FILTER_VALIDATE_INT);

    if ($escrow_id) {
        mysqli_begin_transaction($conn);
        try {
            // Fetch escrow details
            $q_escrow = "SELECT * FROM escrow WHERE escrow_id = ? AND status = 'held' FOR UPDATE";
            $stmt_escrow = mysqli_prepare($conn, $q_escrow);
            mysqli_stmt_bind_param($stmt_escrow, "i", $escrow_id);
            mysqli_stmt_execute($stmt_escrow);
            $res_escrow = mysqli_stmt_get_result($stmt_escrow);

            if ($escrow = mysqli_fetch_assoc($res_escrow)) {
                $creator_id = $escrow['creator_id'];
                $amount = $escrow['amount_tokens'];
                $payment_id = $escrow['payment_id'];

                // Update escrow status
                $u_escrow = "UPDATE escrow SET status = 'released', released_at = NOW() WHERE escrow_id = ?";
                $stmt_u_escrow = mysqli_prepare($conn, $u_escrow);
                mysqli_stmt_bind_param($stmt_u_escrow, "i", $escrow_id);
                mysqli_stmt_execute($stmt_u_escrow);

                // Update payment status
                $u_pay = "UPDATE payments SET status = 'released' WHERE payment_id = ?";
                $stmt_u_pay = mysqli_prepare($conn, $u_pay);
                mysqli_stmt_bind_param($stmt_u_pay, "i", $payment_id);
                mysqli_stmt_execute($stmt_u_pay);

                // Update creator wallet
                $u_wallet = "UPDATE wallet SET token_balance = token_balance + ?, lifetime_earned = lifetime_earned + ? WHERE user_id = ?";
                $stmt_wallet = mysqli_prepare($conn, $u_wallet);
                mysqli_stmt_bind_param($stmt_wallet, "iii", $amount, $amount, $creator_id);
                mysqli_stmt_execute($stmt_wallet);

                // Get new balance for ledger
                $q_bal = mysqli_query($conn, "SELECT token_balance FROM wallet WHERE user_id = $creator_id");
                $new_bal = mysqli_fetch_assoc($q_bal)['token_balance'];

                // Insert ledger entry
                $ins_ledger = "INSERT INTO token_ledger (user_id, action_type, amount, balance_after, reference_type, reference_id) VALUES (?, 'Escrow_Release', ?, ?, 'escrow', ?)";
                $stmt_ledger = mysqli_prepare($conn, $ins_ledger);
                mysqli_stmt_bind_param($stmt_ledger, "iiii", $creator_id, $amount, $new_bal, $escrow_id);
                mysqli_stmt_execute($stmt_ledger);

                mysqli_commit($conn);
            } else {
                mysqli_rollback($conn);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
        }
    }
    header("Location: admin_escrow.php");
    exit();
}

// Fetch all escrow records
$query = "
    SELECT e.*, c.full_name as creator_name, p.course_id, co.title as course_title, p.student_id, s.full_name as student_name
    FROM escrow e
    JOIN users c ON e.creator_id = c.user_id
    JOIN payments p ON e.payment_id = p.payment_id
    JOIN users s ON p.student_id = s.user_id
    JOIN courses co ON p.course_id = co.course_id
    ORDER BY 
        CASE WHEN e.status = 'held' THEN 1 ELSE 2 END, 
        p.paid_at DESC
";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Escrow Management</title>
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

        .badge.released {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge.held {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
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
            <a href="admin_courses.php" class="nav-btn"><i class="fas fa-book"></i> Courses
                <?php if ($pending_course_count > 0)
                    echo '<span style="background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:0.75rem;margin-right:5px;font-weight:bold;">' . $pending_course_count . '</span>'; ?></a>
            <a href="admin_users.php" class="nav-btn"><i class="fas fa-users"></i> Users</a>
            <a href="admin_escrow.php" class="nav-btn active"><i class="fas fa-money-bill-wave"></i> Escrow</a>
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
        <h1><i class="fas fa-money-bill-wave"></i> Escrow Management</h1>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Course</th>
                        <th>Student</th>
                        <th>Creator</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>#<?php echo $row['escrow_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['creator_name']); ?></td>
                            <td><?php echo $row['amount_tokens']; ?> 🪙</td>
                            <td>
                                <span class="badge <?php echo $row['status']; ?>">
                                    <?php echo $row['status'] == 'held' ? 'Held' : 'Released'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'held'): ?>
                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to release these funds to the creator?');">
                                        <input type="hidden" name="escrow_id" value="<?php echo $row['escrow_id']; ?>">
                                        <button type="submit" name="release_escrow" class="btn-action-small btn-approve">
                                            <i class="fas fa-unlock"></i> Release Funds
                                        </button>
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