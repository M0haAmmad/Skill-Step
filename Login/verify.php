<?php
session_start();
include("../Main/db_connection.php");

if (!isset($_GET['token'])) {
    die("Invalid verification link.");
}

$token = $_GET['token'];

// Find the user by token
$query = "SELECT user_id, is_verified FROM users WHERE verification_token = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    if ($row['is_verified'] == 1) {
        die("Account is already verified. You can login.");
    }

    $user_id = $row['user_id'];

    // Begin ACID transaction to verify user and credit tokens
    mysqli_begin_transaction($conn);
    try {
        // 1. Mark as verified and clear token
        $update_user = "UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = ?";
        $stmt1 = mysqli_prepare($conn, $update_user);
        mysqli_stmt_bind_param($stmt1, "i", $user_id);
        mysqli_stmt_execute($stmt1);

        // 2. Credit 100 tokens to wallet
        $update_wallet = "UPDATE wallet SET token_balance = token_balance + 100 WHERE user_id = ?";
        $stmt2 = mysqli_prepare($conn, $update_wallet);
        mysqli_stmt_bind_param($stmt2, "i", $user_id);
        mysqli_stmt_execute($stmt2);

        // 3. Insert into token_ledger for the bonus
        $ledger_query = "INSERT INTO token_ledger (user_id, action_type, amount, balance_after, reference_type) VALUES (?, 'Registration_Bonus', 100, 100, 'none')";
        $stmt3 = mysqli_prepare($conn, $ledger_query);
        mysqli_stmt_bind_param($stmt3, "i", $user_id);
        mysqli_stmt_execute($stmt3);

        mysqli_commit($conn);

        // Start session
        $_SESSION['user_id'] = $user_id;

        // Redirect to dashboard
        header("Location: ../Main/index.php");
        exit();

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($conn);
        die("An error occurred during verification. Please try again later.");
    }
} else {
    die("Invalid verification link.");
}
?>