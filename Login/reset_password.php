<?php
session_start();
include("../Main/db_connection.php");

$message = "";
$token_valid = false;
$user_id = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $query = "SELECT user_id, reset_expires FROM users WHERE reset_token = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if (strtotime($row['reset_expires']) > time()) {
            $token_valid = true;
            $user_id = $row['user_id'];
        } else {
            $message = "Your password reset link has expired. Please request a new one.";
        }
    } else {
        $message = "Invalid password reset link.";
    }
} else {
    header("Location: login.php");
    exit();
}

if (isset($_POST['reset_submit'])) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $token = $_POST['token'];
    $user_id = $_POST['user_id'];
    
    if ($password !== $confirm) {
        $message = "Passwords do not match.";
        $token_valid = true; // keep form visible
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $update = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, failed_login_count = 0, lockout_until = NULL WHERE user_id = ?";
        $ustmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($ustmt, "si", $hashed_password, $user_id);
        
        if (mysqli_stmt_execute($ustmt)) {
            $message = "Password successfully reset! You can now login.";
            $token_valid = false; // hide form
        } else {
            $message = "An error occurred while resetting your password.";
            $token_valid = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill - Step | Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="login.css?v=<?php echo time(); ?>">
</head>
<body>
    <nav>
        <a href="../Main/index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step Logo">
            Skill-Step
        </a>
    </nav>

    <div class="login-wrapper">
        <div class="login-box">
            <div class="login-content">
                <h2>New Password</h2>
                
                <?php if ($message != ""): ?>
                    <div class="error-msg" style="color: <?php echo ($token_valid) ? '#ef4444' : '#10b981'; ?>; margin-bottom: 20px;">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($token_valid): ?>
                <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" class="login-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    
                    <div class="input-group">
                        <input type="password" name="password" placeholder="New Password" required>
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    
                    <div class="input-group">
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                        <i class="fa-solid fa-lock"></i>
                    </div>

                    <button type="submit" name="reset_submit" class="btn-action">
                        Update Password <i class="fa-solid fa-check"></i>
                    </button>
                </form>
                <?php endif; ?>

                <div class="register" style="margin-top: 20px;">
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
