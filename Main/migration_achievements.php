<?php
require_once 'db_connection.php';

echo "Running Achievements Migration...\n";

// 1. Create achievements table
$create_achievements = "
CREATE TABLE IF NOT EXISTS `achievements` (
  `achievement_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `condition_type` enum('courses_completed','streak_days','tokens_earned','level_reached','courses_created') NOT NULL,
  `condition_value` int(11) NOT NULL,
  `token_reward` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`achievement_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $create_achievements)) {
    echo "Table 'achievements' verified/created.\n";
} else {
    die("Error creating table 'achievements': " . mysqli_error($conn) . "\n");
}

// 2. Create user_achievements table
$create_user_achievements = "
CREATE TABLE IF NOT EXISTS `user_achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_achievement` (`user_id`,`achievement_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`achievement_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $create_user_achievements)) {
    echo "Table 'user_achievements' verified/created.\n";
} else {
    die("Error creating table 'user_achievements': " . mysqli_error($conn) . "\n");
}

// 3. Populate default achievements
$defaults = [
    [
        'name' => 'Scholar I',
        'description' => 'Complete your first course and earn a certificate.',
        'icon_path' => 'fa-graduation-cap',
        'condition_type' => 'courses_completed',
        'condition_value' => 1,
        'token_reward' => 100
    ],
    [
        'name' => 'Scholar II',
        'description' => 'Successfully complete 3 courses.',
        'icon_path' => 'fa-user-graduate',
        'condition_type' => 'courses_completed',
        'condition_value' => 3,
        'token_reward' => 300
    ],
    [
        'name' => 'Streak Rookie',
        'description' => 'Log in 3 days in a row.',
        'icon_path' => 'fa-fire',
        'condition_type' => 'streak_days',
        'condition_value' => 3,
        'token_reward' => 50
    ],
    [
        'name' => 'Streak Veteran',
        'description' => 'Log in 7 days in a row.',
        'icon_path' => 'fa-calendar-check',
        'condition_type' => 'streak_days',
        'condition_value' => 7,
        'token_reward' => 150
    ],
    [
        'name' => 'Ascending Hero',
        'description' => 'Reach Level 2.',
        'icon_path' => 'fa-bolt',
        'condition_type' => 'level_reached',
        'condition_value' => 2,
        'token_reward' => 50
    ],
    [
        'name' => 'Grand Master',
        'description' => 'Reach Level 5.',
        'icon_path' => 'fa-crown',
        'condition_type' => 'level_reached',
        'condition_value' => 5,
        'token_reward' => 250
    ],
    [
        'name' => 'First Coin',
        'description' => 'Accumulate 500 lifetime tokens.',
        'icon_path' => 'fa-coins',
        'condition_type' => 'tokens_earned',
        'condition_value' => 500,
        'token_reward' => 50
    ],
    [
        'name' => 'Wealthy Scholar',
        'description' => 'Accumulate 2000 lifetime tokens.',
        'icon_path' => 'fa-vault',
        'condition_type' => 'tokens_earned',
        'condition_value' => 2000,
        'token_reward' => 200
    ],
    [
        'name' => 'Mentor Initiate',
        'description' => 'Publish your first course for the community.',
        'icon_path' => 'fa-chalkboard-user',
        'condition_type' => 'courses_created',
        'condition_value' => 1,
        'token_reward' => 200
    ]
];

foreach ($defaults as $ach) {
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO achievements (name, description, icon_path, condition_type, condition_value, token_reward) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssii", $ach['name'], $ach['description'], $ach['icon_path'], $ach['condition_type'], $ach['condition_value'], $ach['token_reward']);
    mysqli_stmt_execute($stmt);
}

echo "Default achievements populated successfully.\n";
?>
