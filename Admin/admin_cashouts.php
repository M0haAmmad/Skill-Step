<?php
session_start();
require_once '../Main/db_connection.php';
require_once '../Main/auth_check.php';

$user = checkUserSession($conn);
if (empty($user['roles']) || strpos($user['roles'], 'admin') === false) {
    header('Location: ../Main/index.php');
    exit();
}

// Handle Approval
if (isset($_POST['approve_cashout'])) {
    $cashout_id = filter_var($_POST['cashout_id'], FILTER_VALIDATE_INT);
    if ($cashout_id) {
        $update = "UPDATE cash_out_requests SET status = 'approved', processed_at = NOW() WHERE cashout_id = ? AND status = 'pending'";
        $stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt, "i", $cashout_id);
        mysqli_stmt_execute($stmt);

        // Optional: Notify user
        $q_req = mysqli_query($conn, "SELECT user_id, net_payout FROM cash_out_requests WHERE cashout_id = $cashout_id");
        if ($req_data = mysqli_fetch_assoc($q_req)) {
            $notif_title = "Cashout Request Approved";
            $notif_body = "Your request to withdraw $" . $req_data['net_payout'] . " has been approved. The amount will reach you soon.";
            $ins_notif = "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'cashout_approved', ?, ?)";
            $stmt_notif = mysqli_prepare($conn, $ins_notif);
            mysqli_stmt_bind_param($stmt_notif, "iss", $req_data['user_id'], $notif_title, $notif_body);
            mysqli_stmt_execute($stmt_notif);
        }
    }
    header("Location: admin_cashouts.php");
    exit();
}

// Handle Rejection
if (isset($_POST['reject_cashout'])) {
    $cashout_id = filter_var($_POST['cashout_id'], FILTER_VALIDATE_INT);
    $reason = $_POST['rejection_reason'] ?? 'Request rejected by administration.';

    if ($cashout_id) {
        mysqli_begin_transaction($conn);
        try {
            // Fetch request details
            $q_req = "SELECT * FROM cash_out_requests WHERE cashout_id = ? AND status = 'pending' FOR UPDATE";
            $stmt_req = mysqli_prepare($conn, $q_req);
            mysqli_stmt_bind_param($stmt_req, "i", $cashout_id);
            mysqli_stmt_execute($stmt_req);
            $res_req = mysqli_stmt_get_result($stmt_req);

            if ($req = mysqli_fetch_assoc($res_req)) {
                $uid = $req['user_id'];
                $tokens = $req['amount_tokens'];

                // 1. Update request status
                $u_req = "UPDATE cash_out_requests SET status = 'rejected', rejection_reason = ?, processed_at = NOW() WHERE cashout_id = ?";
                $stmt_u = mysqli_prepare($conn, $u_req);
                mysqli_stmt_bind_param($stmt_u, "si", $reason, $cashout_id);
                mysqli_stmt_execute($stmt_u);

                // 2. Return tokens to wallet
                $u_wallet = "UPDATE wallet SET token_balance = token_balance + ? WHERE user_id = ?";
                $stmt_w = mysqli_prepare($conn, $u_wallet);
                mysqli_stmt_bind_param($stmt_w, "ii", $tokens, $uid);
                mysqli_stmt_execute($stmt_w);

                // 3. Get new balance for ledger
                $q_bal = mysqli_query($conn, "SELECT token_balance FROM wallet WHERE user_id = $uid");
                $new_bal = mysqli_fetch_assoc($q_bal)['token_balance'];

                // 4. Log in ledger (Refund)
                $ins_ledger = "INSERT INTO token_ledger (user_id, action_type, amount, balance_after, reference_type, reference_id) VALUES (?, 'Refund', ?, ?, 'cashout', ?)";
                $stmt_l = mysqli_prepare($conn, $ins_ledger);
                mysqli_stmt_bind_param($stmt_l, "iiii", $uid, $tokens, $new_bal, $cashout_id);
                mysqli_stmt_execute($stmt_l);

                // 5. Notify user
                $notif_title = "Cashout Request Rejected";
                $notif_body = "Your cashout request was rejected. Reason: " . $reason . ". Tokens have been returned to your wallet.";
                $ins_notif = "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'cashout_rejected', ?, ?)";
                $stmt_notif = mysqli_prepare($conn, $ins_notif);
                mysqli_stmt_bind_param($stmt_notif, "iss", $uid, $notif_title, $notif_body);
                mysqli_stmt_execute($stmt_notif);

                mysqli_commit($conn);
            } else {
                mysqli_rollback($conn);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
        }
    }
    header("Location: admin_cashouts.php");
    exit();
}

// Fetch all requests
$query = "
    SELECT r.*, u.full_name, u.email as user_email
    FROM cash_out_requests r
    JOIN users u ON r.user_id = u.user_id
    ORDER BY 
        CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END, 
        r.requested_at DESC
";
$result = mysqli_query($conn, $query);

// Pending count for nav badge
$pending_count_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM cash_out_requests WHERE status = 'pending'");
$pending_count = mysqli_fetch_assoc($pending_count_q)['count'];

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashouts Management | Skill-Step Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Main/style.css">
    <style>
        .admin-dashboard {
            padding: 40px;
            max-width: 1400px;
            margin: auto;
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

        .badge.approved {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge.rejected {
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--bg-card);
            margin: 15% auto;
            padding: 30px;
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            width: 400px;
            color: white;
        }

        .modal-content textarea {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: white;
            padding: 10px;
            margin: 15px 0;
            resize: none;
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
        ?>
        <div class="nav-links">
            <a href="admin_courses.php" class="nav-btn"><i class="fas fa-book"></i> Courses
                <?php if ($pending_course_count > 0)
                    echo '<span style="background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:0.75rem;margin-right:5px;font-weight:bold;">' . $pending_course_count . '</span>'; ?></a>
            <a href="admin_users.php" class="nav-btn"><i class="fas fa-users"></i> Users</a>
            <a href="admin_escrow.php" class="nav-btn"><i class="fas fa-money-bill-wave"></i> Escrow</a>
            <a href="admin_cashouts.php" class="nav-btn active"><i class="fas fa-hand-holding-usd"></i> Cashouts
                <?php if ($pending_count > 0)
                    echo '<span style="background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:0.75rem;margin-right:5px;font-weight:bold;">' . $pending_count . '</span>'; ?></a>
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
        <h1><i class="fas fa-hand-holding-usd"></i> Manage Cashout Requests</h1>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>PayPal Account</th>
                        <th>Tokens</th>
                        <th>Net Payout</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                <small
                                    style="color:var(--text-muted);"><?php echo htmlspecialchars($row['user_email']); ?></small>
                            </td>
                            <td><code><?php echo htmlspecialchars($row['account_identifier']); ?></code></td>
                            <td><?php echo $row['amount_tokens']; ?> 🪙</td>
                            <td style="color:#34d399; font-weight:bold;">
                                $<?php echo number_format($row['net_payout'], 2); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['requested_at'])); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status']; ?>">
                                    <?php
                                    if ($row['status'] == 'pending')
                                        echo 'Pending';
                                    elseif ($row['status'] == 'approved')
                                        echo 'Approved';
                                    else
                                        echo 'Rejected';
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'pending'): ?>
                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to approve this request? Make sure you have manually transferred the amount via PayPal first.');">
                                        <input type="hidden" name="cashout_id" value="<?php echo $row['cashout_id']; ?>">
                                        <button type="submit" name="approve_cashout" class="btn-action-small btn-approve">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <button type="button" class="btn-action-small btn-reject"
                                        onclick="showRejectModal(<?php echo $row['cashout_id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php else: ?>
                                    <small style="color:var(--text-muted);">
                                        <?php echo $row['processed_at'] ? date('Y-m-d', strtotime($row['processed_at'])) : '-'; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Reject Cashout Request</h3>
            <form method="POST">
                <input type="hidden" name="cashout_id" id="modal_cashout_id">
                <label>Rejection Reason:</label>
                <textarea name="rejection_reason" rows="4" placeholder="Type the reason for rejection here to notify the user..."
                    required></textarea>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="reject_cashout" class="btn-action" style="background:#ef4444;">Confirm Reject</button>
                    <button type="button" class="btn-action" style="background:#6b7280;"
                        onclick="hideRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRejectModal(id) {
            document.getElementById('modal_cashout_id').value = id;
            document.getElementById('rejectModal').style.display = 'block';
        }
        function hideRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        window.onclick = function (event) {
            if (event.target == document.getElementById('rejectModal')) {
                hideRejectModal();
            }
        }
    </script>
</body>

</html>