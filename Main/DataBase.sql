CREATE DATABASE IF NOT EXISTS skill_step;

USE skill_step;

-- 7.1 users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    roles SET('student', 'creator', 'admin') DEFAULT 'student',
    profile_pic VARCHAR(255) DEFAULT 'images/avatar1.png',
    is_verified TINYINT(1) DEFAULT 0,
    is_suspended TINYINT(1) DEFAULT 0,
    `level` INT DEFAULT 1 CHECK (`level` >= 1),
    xp INT DEFAULT 0 CHECK (xp >= 0),
    streak_days INT DEFAULT 0 CHECK (streak_days >= 0),
    last_streak_date DATE NULL,
    last_login DATETIME NULL,
    failed_login_count INT DEFAULT 0,
    lockout_until DATETIME NULL,
    verification_token VARCHAR(64) NULL,
    reset_token VARCHAR(64) NULL,
    reset_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- 7.2 wallet
CREATE TABLE wallet (
    wallet_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    token_balance INT DEFAULT 0 CHECK (token_balance >= 0),
    lifetime_earned INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
);

-- 7.3 categories
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE
);

-- 7.4 skills
CREATE TABLE skills (
    skill_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    skill_name VARCHAR(150) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE RESTRICT
);

-- 7.5 courses
CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    skill_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    price_tokens INT NOT NULL DEFAULT 0 CHECK (price_tokens >= 0),
    release_threshold INT NOT NULL DEFAULT 20 CHECK (release_threshold >= 20),
    has_quiz TINYINT(1) DEFAULT 0,
    quiz_pass_score INT NULL CHECK (
        quiz_pass_score BETWEEN 1 AND 100
    ),
    status ENUM(
        'draft',
        'pending_review',
        'active',
        'rejected'
    ) DEFAULT 'pending_review',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    FOREIGN KEY (skill_id) REFERENCES skills (skill_id) ON DELETE RESTRICT
);

-- 7.6 lessons
CREATE TABLE lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    video_path VARCHAR(500) NOT NULL,
    duration_seconds INT NOT NULL CHECK (duration_seconds > 0),
    order_index INT NOT NULL,
    is_free_preview TINYINT(1) DEFAULT 0,
    status ENUM('draft', 'published') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (course_id, order_index),
    FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE CASCADE
);

-- 7.7 enrollments
CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE RESTRICT
);

-- 7.8 progress
CREATE TABLE progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    watched_pct INT DEFAULT 0 CHECK (watched_pct BETWEEN 0 AND 100),
    is_complete TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    UNIQUE (student_id, lesson_id),
    FOREIGN KEY (student_id) REFERENCES users (user_id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons (lesson_id) ON DELETE CASCADE
);

-- 7.9 payments
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    amount_tokens INT NOT NULL CHECK (amount_tokens > 0),
    status ENUM(
        'pending',
        'released',
        'disputed',
        'cancelled'
    ) DEFAULT 'pending',
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE RESTRICT
);

-- 7.10 escrow
CREATE TABLE escrow (
    escrow_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT UNIQUE NOT NULL,
    creator_id INT NOT NULL,
    amount_tokens INT NOT NULL CHECK (amount_tokens > 0),
    status ENUM(
        'held',
        'released',
        'frozen',
        'cancelled'
    ) DEFAULT 'held',
    frozen_reason TEXT NULL,
    released_at TIMESTAMP NULL,
    FOREIGN KEY (payment_id) REFERENCES payments (payment_id) ON DELETE RESTRICT,
    FOREIGN KEY (creator_id) REFERENCES users (user_id) ON DELETE RESTRICT
);

-- 7.11 disputes
CREATE TABLE disputes (
    dispute_id INT AUTO_INCREMENT PRIMARY KEY,
    escrow_id INT UNIQUE NOT NULL,
    raised_by INT NOT NULL,
    reason TEXT NOT NULL,
    admin_id INT NULL,
    resolution ENUM(
        'pending',
        'resolved_creator',
        'resolved_student',
        'cancelled'
    ) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (escrow_id) REFERENCES escrow (escrow_id) ON DELETE RESTRICT,
    FOREIGN KEY (raised_by) REFERENCES users (user_id) ON DELETE RESTRICT,
    FOREIGN KEY (admin_id) REFERENCES users (user_id) ON UPDATE CASCADE
);

-- 7.12 cash_out_requests
CREATE TABLE cash_out_requests (
    cashout_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount_tokens INT NOT NULL CHECK (amount_tokens >= 100),
    usd_equivalent DECIMAL(10, 2) NOT NULL,
    platform_commission DECIMAL(10, 2) NOT NULL,
    net_payout DECIMAL(10, 2) NOT NULL,
    method ENUM('visa', 'apple_pay', 'paypal') NOT NULL,
    account_identifier VARCHAR(255) NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected'
    ) DEFAULT 'pending',
    rejection_reason TEXT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT
);

-- 7.13 quizzes
CREATE TABLE quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNIQUE NOT NULL,
    time_limit_minutes INT NULL,
    randomize_questions TINYINT(1) DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE CASCADE
);

-- 7.14 quiz_questions
CREATE TABLE quiz_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    order_index INT NOT NULL,
    UNIQUE (quiz_id, order_index),
    FOREIGN KEY (quiz_id) REFERENCES quizzes (quiz_id) ON DELETE CASCADE
);

-- 7.15 quiz_choices
CREATE TABLE quiz_choices (
    choice_id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    choice_text VARCHAR(500) NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions (question_id) ON DELETE CASCADE
);

-- 7.16 quiz_attempts
CREATE TABLE quiz_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    attempt_no INT NOT NULL,
    score DECIMAL(5, 2) NOT NULL,
    passed TINYINT(1) NOT NULL,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    FOREIGN KEY (quiz_id) REFERENCES quizzes (quiz_id) ON DELETE RESTRICT
);

-- 7.17 notifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM(
        'course_approved',
        'course_rejected',
        'dispute_filed',
        'dispute_resolved',
        'cashout_approved',
        'cashout_rejected',
        'certificate_issued',
        'achievement_unlocked',
        'escrow_released',
        'streak_bonus',
        'general'
    ) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
);

-- 7.18 token_ledger
CREATE TABLE token_ledger (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM(
        'Daily_Login',
        'Daily_Engagement',
        'Streak_Bonus',
        'Purchase',
        'Escrow_Release',
        'Cash_Out',
        'Refund',
        'Achievement_Reward',
        'Registration_Bonus'
    ) NOT NULL,
    amount INT NOT NULL,
    balance_after INT NOT NULL,
    reference_type ENUM(
        'payment',
        'escrow',
        'dispute',
        'cashout',
        'achievement',
        'none'
    ) DEFAULT 'none',
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT
);

-- 7.19 certificates
CREATE TABLE certificates (
    cert_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    qr_token VARCHAR(64) UNIQUE NOT NULL,
    pdf_path VARCHAR(500) NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE RESTRICT
);

-- 7.20 achievements
CREATE TABLE achievements (
    achievement_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    icon_path VARCHAR(255) NULL,
    condition_type ENUM(
        'courses_completed',
        'streak_days',
        'tokens_earned',
        'level_reached',
        'courses_created'
    ) NOT NULL,
    condition_value INT NOT NULL CHECK (condition_value > 0),
    token_reward INT DEFAULT 0 CHECK (token_reward >= 0),
    is_active TINYINT(1) DEFAULT 1
);

-- 7.21 user_achievements
CREATE TABLE user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements (achievement_id) ON DELETE RESTRICT
);

-- messages
CREATE TABLE messages (
    Message_id INT AUTO_INCREMENT PRIMARY KEY,
    Sender_id INT NOT NULL,
    Receiver_id INT NOT NULL,
    Course_id INT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Sender_id) REFERENCES users (user_id) ON DELETE CASCADE,
    FOREIGN KEY (Receiver_id) REFERENCES users (user_id) ON DELETE CASCADE
);

-- إدراج الأدمن
INSERT INTO
    users (
        full_name,
        email,
        password_hash,
        roles,
        is_verified,
        is_suspended,
        failed_login_count,
        lockout_until,
        `level`,
        created_at
    )
VALUES (
        'Admin',
        'admin@admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin',
        1,
        0,
        0,
        NULL,
        100,
        NOW()
    );

-- إدراج محفظة الأدمن
INSERT INTO
    wallet (
        user_id,
        token_balance,
        lifetime_earned
    )
VALUES (LAST_INSERT_ID(), 1000, 1000);