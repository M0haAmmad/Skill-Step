<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function checkUserSession($conn)
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../Login/login.php');
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user) {
        session_destroy();
        header('Location: ../Login/login.php');
        exit();
    }

    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['roles'] = $user['roles'];

    return $user;
}
?>
