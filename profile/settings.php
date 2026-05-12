<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/login.php');
    exit();
}

require_once __DIR__ . '/../Main/db_connection.php';
$user_id = intval($_SESSION['user_id']);
$query = "SELECT full_name AS User_name, email AS User_email, profile_pic FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

$db_name = $user['User_name'] ?? 'مستخدم';
$db_email = $user['User_email'] ?? '';
$db_pic = $user['profile_pic'] ?? 'default.png';
$pic_path = ($db_pic !== 'default.png') ? "../profile/uploads/" . htmlspecialchars($db_pic) : "../images/logo.png";

$name_parts = explode(' ', trim($db_name));
$first_name = htmlspecialchars($name_parts[0] ?? '');
$last_name = htmlspecialchars($name_parts[1] ?? '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Skill-Step</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Main/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="setting.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../Main/alert-system.css?v=<?php echo time(); ?>">
</head>

<body>
    <nav>
        <a href="../Main/index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50"
                onerror="this.style.display='none';">
            Skill-Step
        </a>
        <a href="../Main/index.php" class="btn-action" style="width:auto; padding: 10px 20px;">
            <i class="fa-solid fa-arrow-right"></i> Back to Platform
        </a>
    </nav>

    <section class="settings-page">
        <div class="settings-grid">
            <div class="settings-card">
                <h1>Settings</h1>
                <p>Here you can update your username, profile picture, and control your notification preferences.</p>

                <div class="settings-summary">
                    <div class="summary-item">
                        <span>Email</span>
                        <strong><?php echo htmlspecialchars($db_email); ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Username</span>
                        <strong><?php echo htmlspecialchars($db_name); ?></strong>
                    </div>
                </div>

                <div style="margin-top:30px;">
                    <h2>Account Information</h2>
                    <form id="profileForm" onsubmit="updateName(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" value="<?php echo $first_name; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" value="<?php echo $last_name; ?>">
                            </div>
                        </div>
                        <div class="settings-footer">
                            <button type="submit" class="btn-action">Save Name</button>
                        </div>
                    </form>
                </div>

                <div style="margin-top:30px;">
                    <h2>Profile Picture</h2>
                    <div class="avatar-preview">
                        <img src="<?php echo $pic_path; ?>" alt="Avatar Preview">
                    </div>
                    <form id="pictureForm" onsubmit="updatePicture(event)">
                        <div class="form-group">
                            <label for="profilePic">Choose New Picture</label>
                            <input type="file" id="profilePic" accept="image/png, image/jpeg, image/webp">
                        </div>
                        <div class="settings-footer">
                            <button type="submit" class="btn-action">Upload Picture</button>
                        </div>
                    </form>
                </div>
            </div>

            <aside class="settings-card">
                <h2>Notification Preferences</h2>
                <p>Control the notifications you want to receive within the platform.</p>
                <div class="toggle-switch">
                    <span>Push Notifications</span>
                    <label class="switch">
                        <input type="checkbox" id="notifyToggle">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="toggle-switch">
                    <span>Message Alerts</span>
                    <label class="switch">
                        <input type="checkbox" id="chatToggle">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="toggle-switch">
                    <span>Receive Product Updates</span>
                    <label class="switch">
                        <input type="checkbox" id="updatesToggle">
                        <span class="slider"></span>
                    </label>
                </div>

                <div style="margin-top:30px;">
                    <h2>Account Options</h2>
                    <p style="color: var(--text-muted);">You can log out or open your profile page from here.</p>
                    <div class="settings-footer">
                        <a href="../Login/logout.php" class="btn-action" style="width:auto; padding: 10px 24px;">Logout</a>
                        <a href="../profile/profile.php" class="btn-action"
                            style="width:auto; padding: 10px 24px;">My Page</a>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <script src="../Main/alert-system.js"></script>
    <script src="settings.js"></script>
</body>

</html>