<?php
session_start();
include("../Main/db_connection.php");

$message = "";
$reset_link = "";

if (isset($_POST['reset_btn'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    $query = "SELECT user_id FROM users WHERE email=?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+60 minutes'));
        
        $update = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?";
        $ustmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($ustmt, "ssi", $token, $expires, $user['user_id']);
        mysqli_stmt_execute($ustmt);
        
        // Mock email sending
        $host = $_SERVER['HTTP_HOST'];
        $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $reset_link = "http://$host$uri/reset_password.php?token=$token";
    }
    
    // Generic message for security (no enumeration)
    $message = "If that email is in our system, we have sent a password reset link.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill - Step | Forgot Password</title>
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
                <h2>Reset Password</h2>
                
                <?php if ($message != ""): ?>
                    <div class="error-msg" style="color: #10b981; margin-bottom: 20px;">
                        <?php echo $message; ?>
                        <?php if ($reset_link != ""): ?>
                            <br><br>
                            <div style="background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 8px; border: 1px solid #10b981;">
                                <p style="margin-bottom: 10px;">[MOCK EMAIL] Please click this link to reset your password:</p>
                                <a href="<?php echo htmlspecialchars($reset_link); ?>" style="color: #10b981; font-weight: bold; word-break: break-all; text-decoration: underline;">
                                    <?php echo htmlspecialchars($reset_link); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($reset_link == ""): ?>
                <form action="forgot_password.php" method="POST" class="login-form">
                    <p style="text-align: center; color: #a1a1aa; margin-bottom: 20px;">
                        Enter your email address and we'll send you a link to reset your password.
                    </p>
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Email Address" required>
                        <i class="fa-solid fa-envelope"></i>
                    </div>

                    <button type="submit" name="reset_btn" class="btn-action">
                        Send Reset Link <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
                <?php endif; ?>

                <div class="register">
                    Remember your password? <a href="login.php">Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
