<?php
session_start();
require_once '../Main/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in first.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$amount_tokens = filter_var($_POST['amount'], FILTER_VALIDATE_INT);
$paypal_email = trim($_POST['paypal_email']); // Trim whitespace

if (!$amount_tokens || $amount_tokens < 500) {
    echo json_encode(['success' => false, 'message' => 'The minimum cashout amount is 500 tokens.']);
    exit();
}

// Check PayPal email - ONLY admin@admin allowed
if ($paypal_email !== 'admin@admin') {
    echo json_encode(['success' => false, 'message' => 'PayPal information is incorrect. Please contact support.']);
    exit();
}

// Fetch user data and wallet
$query = "SELECT u.level, w.token_balance FROM users u JOIN wallet w ON u.user_id = w.user_id WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($res);

if (!$user_data) {
    echo json_encode(['success' => false, 'message' => 'Error finding user data.']);
    exit();
}

if ($user_data['level'] < 20) {
    echo json_encode(['success' => false, 'message' => 'You must reach level 20 to withdraw earnings.']);
    exit();
}

if ($user_data['token_balance'] < $amount_tokens) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance.']);
    exit();
}

// Calculations
$token_rate = 0.02;
$commission_rate = 0.10; // 10% commission

$usd_equivalent = $amount_tokens * $token_rate;
$commission = $usd_equivalent * $commission_rate;
$net_payout = $usd_equivalent - $commission;

mysqli_begin_transaction($conn);

try {
    // 1. Deduct tokens from wallet
    $update_wallet = "UPDATE wallet SET token_balance = token_balance - ? WHERE user_id = ?";
    $stmt_wallet = mysqli_prepare($conn, $update_wallet);
    mysqli_stmt_bind_param($stmt_wallet, "ii", $amount_tokens, $user_id);
    mysqli_stmt_execute($stmt_wallet);

    // Get new balance for ledger
    $new_balance = $user_data['token_balance'] - $amount_tokens;

    // 2. Insert cashout request for admin dashboard
    $insert_request = "INSERT INTO cash_out_requests (user_id, amount_tokens, usd_equivalent, platform_commission, net_payout, method, account_identifier, status, created_at) VALUES (?, ?, ?, ?, ?, 'paypal', ?, 'pending', NOW())";
    $stmt_request = mysqli_prepare($conn, $insert_request);
    mysqli_stmt_bind_param($stmt_request, "iiddds", $user_id, $amount_tokens, $usd_equivalent, $commission, $net_payout, $paypal_email);
    mysqli_stmt_execute($stmt_request);
    $cashout_id = mysqli_insert_id($conn);

    // 3. Log in ledger
    $insert_ledger = "INSERT INTO token_ledger (user_id, action_type, amount, balance_after, reference_type, reference_id, created_at) VALUES (?, 'Cash_Out', ?, ?, 'cashout', ?, NOW())";
    $stmt_ledger = mysqli_prepare($conn, $insert_ledger);
    $neg_amount = -$amount_tokens;
    mysqli_stmt_bind_param($stmt_ledger, "iiii", $user_id, $neg_amount, $new_balance, $cashout_id);
    mysqli_stmt_execute($stmt_ledger);

    mysqli_commit($conn);
    echo json_encode([
        'success' => true, 
        'message' => 'Cashout request submitted successfully! It will be reviewed by the administration.', 
        'new_balance' => $new_balance,
        'cashout_id' => $cashout_id
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}
?>