<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['price']) || !isset($data['xp'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$price = intval($data['price']);
$xp_to_add = intval($data['xp']);
$course_id = isset($data['courseId']) ? intval($data['courseId']) : 0;
$skillTitle = isset($data['skillTitle']) ? $data['skillTitle'] : '';
$user_id = intval($_SESSION['user_id']);

require_once 'db_connection.php';
require_once 'level_helper.php';

// Begin Transaction early for SELECT FOR UPDATE
mysqli_begin_transaction($conn);

try {
    // Fetch current user stats and wallet with locking
    $query = "SELECT u.xp, u.level, w.token_balance FROM users u JOIN wallet w ON u.user_id = w.user_id WHERE u.user_id = ? FOR UPDATE";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $current_tokens = intval($row['token_balance']);
    $current_xp = intval($row['xp']);
    $current_lvl = intval($row['level']);

    // Check if skill is already unlocked
    $already_unlocked = false;
    if ($course_id > 0) {
        $chk_en = mysqli_query($conn, "SELECT 1 FROM enrollments WHERE student_id = $user_id AND course_id = $course_id");
        if (mysqli_num_rows($chk_en) > 0) {
            $already_unlocked = true;
        }
    } else if (!empty($skillTitle)) {
        // Fallback for hardcoded skills: check if we have an exact purchase record in token_ledger
        $safe_title = mysqli_real_escape_string($conn, $skillTitle);
        $chk_ledger = mysqli_query($conn, "SELECT 1 FROM token_ledger WHERE user_id = $user_id AND action_type = 'Purchase' AND description = 'شراء دورة: $safe_title'");
        if (mysqli_num_rows($chk_ledger) > 0) {
            $already_unlocked = true;
        }
    }

    if ($already_unlocked) {
        $level_data = getLevelData($current_xp);
        echo json_encode([
            'success' => true,
            'message' => 'Already unlocked',
            'new_tokens' => $current_tokens,
            'new_xp' => $current_xp,
            'new_lvl' => $current_lvl,
            'level_data' => $level_data
        ]);
        exit();
    }

    if ($current_tokens >= $price || $price === 0) {
        $new_tokens = $current_tokens - $price;
        $new_xp = $current_xp + $xp_to_add;
        
        $level_data = getLevelData($new_xp);
        $new_lvl = $level_data['level'];
            // Update wallet
            $update_w = "UPDATE wallet SET token_balance = ? WHERE user_id = ?";
            $stmt_w = mysqli_prepare($conn, $update_w);
            mysqli_stmt_bind_param($stmt_w, "ii", $new_tokens, $user_id);
            mysqli_stmt_execute($stmt_w);

            // Update user xp
            $update_u = "UPDATE users SET xp = ?, level = ? WHERE user_id = ?";
            $stmt_u = mysqli_prepare($conn, $update_u);
            mysqli_stmt_bind_param($stmt_u, "iii", $new_xp, $new_lvl, $user_id);
            mysqli_stmt_execute($stmt_u);

            // Insert enrollment and handle payments
            if ($course_id > 0) {
                // Get course creator
                $c_query = "SELECT creator_id FROM courses WHERE course_id = ?";
                $c_stmt = mysqli_prepare($conn, $c_query);
                mysqli_stmt_bind_param($c_stmt, "i", $course_id);
                mysqli_stmt_execute($c_stmt);
                $c_res = mysqli_stmt_get_result($c_stmt);
                $creator_id = 0;
                if ($c_row = mysqli_fetch_assoc($c_res)) {
                    $creator_id = $c_row['creator_id'];
                }

                $ins_en = "INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)";
                $stmt_en = mysqli_prepare($conn, $ins_en);
                mysqli_stmt_bind_param($stmt_en, "ii", $user_id, $course_id);
                mysqli_stmt_execute($stmt_en);
                
                if ($price > 0) {
                    $payment_id = 0;
                    if ($creator_id > 0) {
                        // Record in payments table (student side) with 'held' status
                        $ins_pay = "INSERT INTO payments (student_id, course_id, amount_tokens, status) VALUES (?, ?, ?, 'held')";
                        $stmt_pay = mysqli_prepare($conn, $ins_pay);
                        mysqli_stmt_bind_param($stmt_pay, "iii", $user_id, $course_id, $price);
                        mysqli_stmt_execute($stmt_pay);
                        $payment_id = mysqli_insert_id($conn);

                        // Transfer to Escrow instead of direct wallet transfer
                        $ins_escrow = "INSERT INTO escrow (payment_id, creator_id, amount_tokens, status) VALUES (?, ?, ?, 'held')";
                        $stmt_escrow = mysqli_prepare($conn, $ins_escrow);
                        mysqli_stmt_bind_param($stmt_escrow, "iii", $payment_id, $creator_id, $price);
                        mysqli_stmt_execute($stmt_escrow);
                    }

                    // Log in student ledger (Purchase)
                    $ins_ledger = "INSERT INTO token_ledger (user_id, action_type, amount, balance_after, reference_type, reference_id, description) VALUES (?, 'Purchase', ?, ?, 'payment', ?, ?)";
                    $amount_negative = -$price;
                    $desc = "شراء دورة: " . $skillTitle;
                    $stmt_ledger = mysqli_prepare($conn, $ins_ledger);
                    mysqli_stmt_bind_param($stmt_ledger, "iiiis", $user_id, $amount_negative, $new_tokens, $payment_id, $desc);
                    mysqli_stmt_execute($stmt_ledger);
                }
            }

            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true, 
                'new_tokens' => $new_tokens, 
                'new_xp' => $new_xp,
                'new_lvl' => $new_lvl,
                'level_data' => $level_data
            ]);
    } else {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Insufficient tokens']);
    }

    } else {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Failed to process transaction']);
}
?>
