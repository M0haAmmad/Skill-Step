<?php
// Main/achievements_helper.php

function checkAndAwardAchievements($conn, $user_id, $type = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // If type is null, check all types
    $types = $type ? [$type] : ['courses_completed', 'streak_days', 'tokens_earned', 'level_reached', 'courses_created'];
    
    foreach ($types as $current_type) {
        $user_val = 0;
        switch ($current_type) {
            case 'courses_completed':
                // Check course completions via certificates count
                $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM certificates WHERE user_id = $user_id");
                $user_val = $res ? mysqli_fetch_assoc($res)['cnt'] : 0;
                break;
                
            case 'streak_days':
                $res = mysqli_query($conn, "SELECT streak_days FROM users WHERE user_id = $user_id");
                $user_val = $res ? mysqli_fetch_assoc($res)['streak_days'] : 0;
                break;
                
            case 'level_reached':
                $res = mysqli_query($conn, "SELECT level FROM users WHERE user_id = $user_id");
                $user_val = $res ? mysqli_fetch_assoc($res)['level'] : 0;
                break;
                
            case 'courses_created':
                $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM courses WHERE creator_id = $user_id AND status = 'active'");
                $user_val = $res ? mysqli_fetch_assoc($res)['cnt'] : 0;
                break;
                
            case 'tokens_earned':
                $res = mysqli_query($conn, "SELECT lifetime_earned FROM wallet WHERE user_id = $user_id");
                $user_val = $res ? mysqli_fetch_assoc($res)['lifetime_earned'] : 0;
                break;
        }
        
        // Fetch active achievements for this type
        $query = "SELECT * FROM achievements WHERE condition_type = ? AND is_active = 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $current_type);
        mysqli_stmt_execute($stmt);
        $res_ach = mysqli_stmt_get_result($stmt);
        
        while ($ach = mysqli_fetch_assoc($res_ach)) {
            $ach_id = $ach['achievement_id'];
            $req_val = $ach['condition_value'];
            $reward = $ach['token_reward'];
            $name = $ach['name'];
            $desc = $ach['description'];
            
            if ($user_val >= $req_val) {
                // Check if already awarded
                $chk_earned = mysqli_query($conn, "SELECT 1 FROM user_achievements WHERE user_id = $user_id AND achievement_id = $ach_id");
                if ($chk_earned && mysqli_num_rows($chk_earned) == 0) {
                    // Award it!
                    mysqli_query($conn, "INSERT INTO user_achievements (user_id, achievement_id) VALUES ($user_id, $ach_id)");
                    
                    // Award tokens
                    if ($reward > 0) {
                        mysqli_query($conn, "UPDATE wallet SET token_balance = token_balance + $reward, lifetime_earned = lifetime_earned + $reward WHERE user_id = $user_id");
                        
                        // Log in token ledger
                        $ins_ledger = "INSERT INTO token_ledger (user_id, action_type, amount, balance_after, reference_type, reference_id, description) 
                                       VALUES (?, 'Achievement_Reward', ?, (SELECT token_balance FROM wallet WHERE user_id = ?), 'achievement', ?, ?)";
                        $desc_msg = "Achievement Unlock: " . $name;
                        $stmt_led = mysqli_prepare($conn, $ins_ledger);
                        mysqli_stmt_bind_param($stmt_led, "iiiis", $user_id, $reward, $user_id, $ach_id, $desc_msg);
                        mysqli_stmt_execute($stmt_led);
                    }
                    
                    // Create notification
                    $notif_title = "New Achievement Unlocked! 🏆";
                    $notif_body = "Congratulations! You have unlocked the achievement: \"" . $name . "\". You've earned " . $reward . " tokens.";
                    $ins_notif = "INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'achievement_unlocked', ?, ?)";
                    $stmt_not = mysqli_prepare($conn, $ins_notif);
                    mysqli_stmt_bind_param($stmt_not, "iss", $user_id, $notif_title, $notif_body);
                    mysqli_stmt_execute($stmt_not);
                    
                    // Store in session for layout alert popups
                    if (!isset($_SESSION['newly_unlocked_achievements'])) {
                        $_SESSION['newly_unlocked_achievements'] = [];
                    }
                    $_SESSION['newly_unlocked_achievements'][] = $name;
                }
            }
        }
    }
}
?>
