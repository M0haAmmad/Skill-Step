<?php
session_start();
include("../Main/db_connection.php");

$message = "";
$verification_link = "";

if (isset($_POST['register_btn'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match";
    } else {
        $query = "SELECT * FROM users WHERE email='$email'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $message = "Email already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $token = bin2hex(random_bytes(32));
            
            $query = "INSERT INTO users (full_name, email, password_hash, verification_token, is_verified) VALUES (?, ?, ?, ?, 0)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed_password, $token);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_user_id = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO wallet (user_id, token_balance) VALUES ($new_user_id, 0)");
                
                // Mock email sending
                $host = $_SERVER['HTTP_HOST'];
                $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $verification_link = "http://$host$uri/verify.php?token=$token";
                
                $message = "Registration successful! Please verify your email.";
            } else {
                $message = "An error occurred during registration";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill - Step | Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="register.css?v=<?php echo time(); ?>">
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

                <h2>Create Account</h2>

                <?php if ($message != ""): ?>
                    <div class="error-msg" style="color: <?php echo ($verification_link != "") ? '#10b981' : '#ef4444'; ?>; margin-bottom: 20px;">
                        <?php echo $message; ?>
                        <?php if ($verification_link != ""): ?>
                            <br><br>
                            <div style="background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 8px; border: 1px solid #10b981;">
                                <p style="margin-bottom: 10px;">[MOCK EMAIL] Please click this link to verify your account:</p>
                                <a href="<?php echo htmlspecialchars($verification_link); ?>" style="color: #10b981; font-weight: bold; word-break: break-all; text-decoration: underline;">
                                    <?php echo htmlspecialchars($verification_link); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($verification_link == ""): ?>
                <form method="POST" action="register.php">
                    <div class="input-group">
                        <input type="text" name="name" placeholder="Full Name" required>
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div class="input-group">
                        <input type="email" name="email" placeholder="Email" required>
                        <i class="fa-solid fa-envelope"></i>
                    </div>

                    <div class="input-group">
                        <input type="password" name="password" id="password" class="with-toggle" placeholder="Password" required>
                        <i class="fa-solid fa-lock"></i>
                        <i class="fa-solid fa-eye toggle-password" data-target="password"></i>
                    </div>

                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" class="with-toggle" placeholder="Confirm Password" required>
                        <i class="fa-solid fa-lock"></i>
                        <i class="fa-solid fa-eye toggle-password" data-target="confirm_password"></i>
                    </div>

                    <button type="submit" name="register_btn" class="btn-action">
                        Create Account <i class="fa-solid fa-user-plus"></i>
                    </button>
                </form>
                <?php endif; ?>

                <div class="register">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </div>
        </div>

    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>

</html>