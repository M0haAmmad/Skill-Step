<?php
session_start();
include("../Main/db_connection.php");

$message = "";

if (isset($_POST['login_btn'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email=?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // Check if locked out
        if ($row['lockout_until'] && strtotime($row['lockout_until']) > time()) {
            $message = "Your account is temporarily locked due to too many failed attempts. Please try again later.";
        } else if ($row['is_suspended'] == 1) {
            $message = "Your account has been suspended by an administrator.";
        } else if ($row['is_verified'] == 0) {
            $message = "Please verify your email address before logging in.";
        } else {
            // Verify password
            if (password_verify($password, $row['password_hash'])) {
                // Success: reset failures and lockout
                $update = "UPDATE users SET failed_login_count = 0, lockout_until = NULL WHERE user_id = ?";
                $ustmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($ustmt, "i", $row['user_id']);
                mysqli_stmt_execute($ustmt);

                // حفظ بيانات الجلسة
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['roles'] = $row['roles'];

                // التحقق من الـ admin
                if (strpos($row['roles'], 'admin') !== false) {
                    header("Location: ../Admin/admin_users.php");
                } else {
                    header("Location: ../Main/index.php");
                }
                exit();
            } else {
                // Failure: increment count
                $fails = $row['failed_login_count'] + 1;
                $lockout = NULL;
                $message = "The Username or Password is not correct";

                if ($fails >= 5) {
                    $lockout = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $fails = 0;
                    $message = "Too many failed attempts. Your account is locked for 30 minutes.";
                }

                $update = "UPDATE users SET failed_login_count = ?, lockout_until = ? WHERE user_id = ?";
                $ustmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($ustmt, "isi", $fails, $lockout, $row['user_id']);
                mysqli_stmt_execute($ustmt);
            }
        }
    } else {
        $message = "The Username or Password is not correct";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill - Step | Login</title>
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
                <h2>Sign in</h2>

                <?php if ($message != ""): ?>
                    <div
                        style="color: var(--accent-fire); margin-bottom: 20px; font-weight: bold; background: rgba(239, 68, 68, 0.1); padding: 10px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3);">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Email" required>
                        <i class="fa-solid fa-envelope"></i>
                    </div>

                    <div class="input-group">
                        <input type="password" name="password" id="password" class="with-toggle" placeholder="Password" required>
                        <i class="fa-solid fa-lock"></i>
                        <i class="fa-solid fa-eye toggle-password" id="toggleIcon"></i>
                    </div>

                    <button type="submit" name="login_btn" class="btn-action">
                        Login <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    </button>
                </form>

                <div class="register" style="margin-top: 15px; margin-bottom: 5px;">
                    <a href="forgot_password.php" style="font-size: 0.9rem;">Forgot Password?</a>
                </div>

                <div class="register">
                    New here? <a href="register.php">Create an account</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        toggleIcon.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>