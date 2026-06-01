-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 30, 2026 at 06:45 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12
SET SESSION sql_require_primary_key = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `skill_step`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `achievement_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `condition_type` enum('courses_completed','streak_days','tokens_earned','level_reached','courses_created') NOT NULL,
  `condition_value` int(11) NOT NULL CHECK (`condition_value` > 0),
  `token_reward` int(11) DEFAULT 0 CHECK (`token_reward` >= 0),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_out_requests`
--

CREATE TABLE `cash_out_requests` (
  `cashout_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount_tokens` int(11) NOT NULL CHECK (`amount_tokens` >= 100),
  `usd_equivalent` decimal(10,2) NOT NULL,
  `platform_commission` decimal(10,2) NOT NULL,
  `net_payout` decimal(10,2) NOT NULL,
  `method` enum('visa','apple_pay','paypal') NOT NULL,
  `account_identifier` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`) VALUES
(3, 'Data Science'),
(4, 'Design'),
(2, 'Mobile Apps'),
(1, 'Web Development');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `cert_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `qr_token` varchar(64) NOT NULL,
  `pdf_path` varchar(500) DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`cert_id`, `user_id`, `course_id`, `qr_token`, `pdf_path`, `issued_at`) VALUES
(21, 1, 34, 'c543d11a44a77ae20672d679f8c89736', NULL, '2026-05-12 07:37:11'),
(22, 13, 35, 'ff2fb949e25e5e5ad57ecfabdabc6cff', NULL, '2026-05-12 08:45:07');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price_tokens` int(11) NOT NULL DEFAULT 0 CHECK (`price_tokens` >= 0),
  `release_threshold` int(11) NOT NULL DEFAULT 20 CHECK (`release_threshold` >= 20),
  `has_quiz` tinyint(1) DEFAULT 0,
  `quiz_pass_score` int(11) DEFAULT NULL CHECK (`quiz_pass_score` between 1 and 100),
  `status` enum('draft','pending_review','active','rejected') DEFAULT 'pending_review',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `free_lessons_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `creator_id`, `skill_id`, `title`, `description`, `price_tokens`, `release_threshold`, `has_quiz`, `quiz_pass_score`, `status`, `created_at`, `updated_at`, `free_lessons_count`) VALUES
(34, 13, 1, 'kjhs', '', 200, 20, 1, 70, 'active', '2026-05-12 07:36:56', '2026-05-12 07:55:00', 0),
(35, 1, 1, 'etste', '', 0, 20, 1, NULL, 'active', '2026-05-12 08:15:24', '2026-05-12 08:39:55', 0),
(36, 1, 1, 'test', '', 356, 20, 1, NULL, 'active', '2026-05-12 09:33:48', '2026-05-12 09:33:48', 0);

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `dispute_id` int(11) NOT NULL,
  `escrow_id` int(11) NOT NULL,
  `raised_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `resolution` enum('pending','resolved_creator','resolved_student','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `course_id`, `is_active`, `enrolled_at`) VALUES
(26, 1, 34, 1, '2026-05-12 07:37:09'),
(27, 13, 35, 1, '2026-05-12 08:41:08');

-- --------------------------------------------------------

--
-- Table structure for table `escrow`
--

CREATE TABLE `escrow` (
  `escrow_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `amount_tokens` int(11) NOT NULL CHECK (`amount_tokens` > 0),
  `status` enum('held','released','frozen','cancelled') DEFAULT 'held',
  `frozen_reason` text DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `escrow`
--

INSERT INTO `escrow` (`escrow_id`, `payment_id`, `creator_id`, `amount_tokens`, `status`, `frozen_reason`, `released_at`) VALUES
(3, 15, 13, 200, 'released', NULL, '2026-05-12 07:37:45');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `lesson_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(500) NOT NULL,
  `duration_seconds` int(11) NOT NULL CHECK (`duration_seconds` > 0),
  `order_index` int(11) NOT NULL,
  `is_free_preview` tinyint(1) DEFAULT 0,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`lesson_id`, `course_id`, `title`, `description`, `video_path`, `duration_seconds`, `order_index`, `is_free_preview`, `status`, `created_at`, `updated_at`) VALUES
(62, 34, 'kjh', NULL, 'course_34_vid_1778571416_0.mp4', 60, 0, 0, 'draft', '2026-05-12 07:36:56', '2026-05-12 07:36:56'),
(63, 35, 'kjahskdj', NULL, 'course_35_vid_1778573724_0.mp4', 60, 0, 0, 'draft', '2026-05-12 08:15:24', '2026-05-12 08:15:24'),
(64, 35, 'jhejfsadg', NULL, 'course_35_vid_1778573724_1.mp4', 60, 1, 0, 'draft', '2026-05-12 08:15:24', '2026-05-12 08:15:24'),
(65, 35, 'kjGSJDfgas', NULL, 'course_35_vid_1778573724_2.mp4', 60, 2, 0, 'draft', '2026-05-12 08:15:24', '2026-05-12 08:15:24'),
(66, 35, 'kajsdhfjl', NULL, 'course_35_vid_1778573724_3.mp4', 60, 3, 0, 'draft', '2026-05-12 08:15:24', '2026-05-12 08:15:24'),
(67, 36, 'test', NULL, 'course_36_vid_1778578428_0.mp4', 60, 0, 0, 'draft', '2026-05-12 09:33:48', '2026-05-12 09:33:48'),
(68, 36, 'test', NULL, 'course_36_vid_1778578428_1.mp4', 60, 1, 0, 'draft', '2026-05-12 09:33:48', '2026-05-12 09:33:48');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `Message_id` int(11) NOT NULL,
  `Sender_id` int(11) NOT NULL,
  `Receiver_id` int(11) NOT NULL,
  `Course_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`Message_id`, `Sender_id`, `Receiver_id`, `Course_id`, `body`, `is_read`, `created_at`) VALUES
(1, 1, 8, NULL, ',,', 1, '2026-05-05 06:54:05'),
(2, 12, 1, NULL, '..', 1, '2026-05-06 03:27:51'),
(3, 12, 1, NULL, ',', 1, '2026-05-06 03:28:04'),
(4, 12, 1, NULL, ',', 1, '2026-05-06 03:28:04'),
(5, 12, 1, NULL, ',', 1, '2026-05-06 03:28:05'),
(6, 12, 1, NULL, ',', 1, '2026-05-06 03:28:05'),
(7, 12, 1, NULL, ',', 1, '2026-05-06 03:28:05'),
(8, 12, 1, NULL, ',', 1, '2026-05-06 03:28:05'),
(9, 12, 1, NULL, ',', 1, '2026-05-06 03:28:06'),
(10, 12, 1, NULL, ',', 1, '2026-05-06 03:28:06'),
(11, 12, 1, NULL, '.', 1, '2026-05-06 06:12:38'),
(12, 12, 1, NULL, '.', 1, '2026-05-06 06:12:38'),
(13, 12, 1, NULL, '.', 1, '2026-05-06 06:12:38'),
(14, 12, 1, NULL, '.', 1, '2026-05-06 06:12:38'),
(15, 12, 1, NULL, '.', 1, '2026-05-06 06:12:38'),
(16, 12, 1, NULL, '.', 1, '2026-05-06 06:12:39'),
(17, 12, 1, NULL, '.', 1, '2026-05-06 06:12:39'),
(18, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:54'),
(19, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:54'),
(20, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:54'),
(21, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:54'),
(22, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:55'),
(23, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:55'),
(24, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:55'),
(25, 12, 1, NULL, 'ز', 1, '2026-05-06 06:18:55'),
(26, 1, 13, NULL, 'lskt;lhks;ld;skdgf', 1, '2026-05-12 08:40:33'),
(27, 1, 13, NULL, 'as;ldg;lsd;fg', 1, '2026-05-12 08:40:34'),
(28, 1, 13, NULL, 'as;ldglksdmfklg', 1, '2026-05-12 08:40:35'),
(29, 1, 13, NULL, 'asdmlkgmalksndf', 1, '2026-05-12 08:40:36'),
(30, 1, 13, NULL, 'asldkgnklasnglk', 1, '2026-05-12 08:40:37'),
(31, 1, 13, NULL, 'aslkdgnlkandfg', 1, '2026-05-12 08:40:37'),
(32, 1, 13, NULL, 'amsdgklnasdfn', 1, '2026-05-12 08:40:38');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('course_approved','course_rejected','dispute_filed','dispute_resolved','cashout_approved','cashout_rejected','certificate_issued','achievement_unlocked','escrow_released','streak_bonus','general') NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `title`, `body`, `is_read`, `created_at`) VALUES
(2, 12, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"ل\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-06 06:57:27'),
(3, 12, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"ةةة\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-06 06:59:37'),
(4, 12, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"ة\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-06 07:01:27'),
(5, 12, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"test\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-06 07:05:34'),
(6, 12, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"ة\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-06 07:11:30'),
(10, 12, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"بسيسيس\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-06 07:46:33'),
(12, 12, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"mm\" بنجاح واستحققت شهادة الإنجاز.', 0, '2026-05-09 00:57:58'),
(13, 12, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: mm', 0, '2026-05-09 01:01:12'),
(14, 12, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: mm', 0, '2026-05-09 01:04:02'),
(15, 14, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: testquiz', 0, '2026-05-09 02:02:04'),
(16, 14, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: testquiz', 0, '2026-05-09 02:07:33'),
(17, 8, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: testquiz', 1, '2026-05-09 02:13:41'),
(18, 13, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: test', 1, '2026-05-12 06:10:43'),
(20, 13, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: test', 1, '2026-05-12 06:20:33'),
(21, 13, 'certificate_issued', 'شهادة جديدة', 'تم إصدار شهادة إتمام دورة: testquiz', 1, '2026-05-12 06:21:46'),
(22, 13, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"test\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-12 06:45:05'),
(23, 13, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"jhg\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-12 06:58:01'),
(24, 13, 'course_approved', 'مبارك! لقد حصلت على شهادة', 'لقد أتممت دورة \"test\" بنجاح واستحققت شهادة الإنجاز.', 1, '2026-05-12 07:02:05');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount_tokens` int(11) NOT NULL CHECK (`amount_tokens` > 0),
  `status` enum('pending','released','disputed','cancelled') DEFAULT 'pending',
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_id`, `course_id`, `amount_tokens`, `status`, `paid_at`) VALUES
(15, 1, 34, 200, 'released', '2026-05-12 07:37:09');

-- --------------------------------------------------------

--
-- Table structure for table `progress`
--

CREATE TABLE `progress` (
  `progress_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `watched_pct` int(11) DEFAULT 0 CHECK (`watched_pct` between 0 and 100),
  `is_complete` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `progress`
--

INSERT INTO `progress` (`progress_id`, `student_id`, `lesson_id`, `watched_pct`, `is_complete`, `completed_at`) VALUES
(114, 1, 62, 100, 1, '2026-05-12 06:37:11'),
(127, 13, 63, 100, 1, '2026-05-12 07:41:10'),
(128, 13, 64, 100, 1, '2026-05-12 07:41:13'),
(129, 13, 65, 100, 1, '2026-05-12 07:41:16'),
(130, 13, 66, 100, 1, '2026-05-12 07:41:19');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `time_limit_minutes` int(11) DEFAULT NULL,
  `randomize_questions` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quiz_id`, `course_id`, `time_limit_minutes`, `randomize_questions`) VALUES
(12, 34, NULL, 0),
(15, 35, NULL, 0),
(16, 36, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `attempt_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `attempt_no` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `passed` tinyint(1) NOT NULL,
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_choices`
--

CREATE TABLE `quiz_choices` (
  `choice_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `choice_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_choices`
--

INSERT INTO `quiz_choices` (`choice_id`, `question_id`, `choice_text`, `is_correct`) VALUES
(201, 70, 'this', 1),
(202, 70, 'h', 0),
(203, 70, 'g', 0),
(204, 70, 'g', 0),
(205, 71, 'this', 1),
(206, 71, 'j', 0),
(207, 71, 'hj', 0),
(208, 71, 'j', 0),
(209, 72, 'this', 1),
(210, 72, 'm', 0),
(211, 72, 'n', 0),
(212, 72, 'n', 0);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `question_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `order_index` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`question_id`, `quiz_id`, `question_text`, `order_index`) VALUES
(70, 16, 'jakshj', 0),
(71, 16, 'yrsy', 1),
(72, 16, 'lksdjlk', 2);

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `skill_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`skill_id`, `category_id`, `skill_name`) VALUES
(1, 1, 'PHP & MySQL'),
(2, 1, 'JavaScript Master'),
(3, 2, 'Flutter Dev'),
(4, 3, 'Python Data'),
(5, 4, 'UI/UX Design');

-- --------------------------------------------------------

--
-- Table structure for table `token_ledger`
--

CREATE TABLE `token_ledger` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('Daily_Login','Daily_Engagement','Streak_Bonus','Purchase','Escrow_Release','Cash_Out','Refund','Achievement_Reward','Registration_Bonus','Course_Upload_Reward') NOT NULL,
  `amount` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reference_type` enum('payment','escrow','dispute','cashout','achievement','none') DEFAULT 'none',
  `reference_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `token_ledger`
--

INSERT INTO `token_ledger` (`log_id`, `user_id`, `action_type`, `amount`, `balance_after`, `reference_type`, `reference_id`, `description`, `created_at`) VALUES
(1, 1, 'Registration_Bonus', 212121, 100, 'none', NULL, NULL, '2026-05-05 05:20:45'),
(2, 12, 'Registration_Bonus', 100, 100, 'none', NULL, NULL, '2026-05-05 05:44:40'),
(3, 1, 'Purchase', -12, 212109, 'payment', 1, NULL, '2026-05-05 07:00:57'),
(4, 1, 'Course_Upload_Reward', 200, 210614, 'none', NULL, 'مكافأة رفع دورة جديدة: test', '2026-05-06 04:44:03'),
(5, 12, 'Purchase', -250, 99750, 'payment', 2, 'شراء دورة: ', '2026-05-06 04:47:20'),
(6, 1, 'Escrow_Release', 250, 210864, 'escrow', 2, 'تحرير الرصيد لإتمام الطالب للدورة', '2026-05-06 04:47:24'),
(7, 8, 'Escrow_Release', 12, 112, 'escrow', 1, NULL, '2026-05-06 06:25:47'),
(8, 1, 'Course_Upload_Reward', 200, 210903, 'none', NULL, 'مكافأة رفع دورة جديدة: ة', '2026-05-06 06:39:23'),
(9, 1, '', 1499, 212402, 'payment', 3, 'بيع دورة: ', '2026-05-06 06:52:18'),
(10, 12, 'Purchase', -1499, 98251, 'payment', 3, 'شراء دورة: ', '2026-05-06 06:52:18'),
(11, 1, '', 99, 212501, 'payment', 4, 'بيع دورة: ', '2026-05-06 06:59:22'),
(12, 12, 'Purchase', -99, 98152, 'payment', 4, 'شراء دورة: ', '2026-05-06 06:59:22'),
(13, 1, '', 200, 212701, 'payment', 5, 'بيع دورة: ', '2026-05-06 07:01:16'),
(14, 12, 'Purchase', -200, 97952, 'payment', 5, 'شراء دورة: ', '2026-05-06 07:01:16'),
(15, 1, '', 202, 212903, 'payment', 6, 'بيع دورة: ', '2026-05-06 07:05:20'),
(16, 12, 'Purchase', -202, 97750, 'payment', 6, 'شراء دورة: ', '2026-05-06 07:05:20'),
(17, 1, '', 19, 212922, 'payment', 7, 'بيع دورة: ', '2026-05-06 07:11:18'),
(18, 12, 'Purchase', -19, 97689, 'payment', 7, 'شراء دورة: ', '2026-05-06 07:11:18'),
(19, 12, '', 98, 97787, 'payment', 8, 'بيع دورة: ', '2026-05-06 07:22:45'),
(20, 1, 'Purchase', -98, 212824, 'payment', 8, 'شراء دورة: ', '2026-05-06 07:22:45'),
(21, 12, '', 12, 97799, 'payment', 9, 'بيع دورة: ', '2026-05-06 07:25:35'),
(22, 1, 'Purchase', -12, 212812, 'payment', 9, 'شراء دورة: ', '2026-05-06 07:25:35'),
(23, 12, '', 99, 97898, 'payment', 10, 'بيع دورة: ', '2026-05-06 07:38:01'),
(24, 1, 'Purchase', -99, 212713, 'payment', 10, 'شراء دورة: ', '2026-05-06 07:38:01'),
(25, 1, '', 22, 212735, 'payment', 11, 'بيع دورة: ', '2026-05-06 07:46:22'),
(26, 12, 'Purchase', -22, 97876, 'payment', 11, 'شراء دورة: ', '2026-05-06 07:46:22'),
(27, 1, '', 1, 1001, 'payment', 12, 'بيع دورة: ', '2026-05-09 00:57:53'),
(28, 12, 'Purchase', -1, 97875, 'payment', 12, 'شراء دورة: ', '2026-05-09 00:57:53'),
(29, 14, 'Registration_Bonus', 100, 100, 'none', NULL, NULL, '2026-05-09 01:41:33'),
(30, 1, '', 3, 200003, 'payment', 13, 'بيع دورة: ', '2026-05-12 06:45:01'),
(31, 13, 'Purchase', -3, 29997, 'payment', 13, 'شراء دورة: ', '2026-05-12 06:45:01'),
(32, 1, '', 200, 201866, 'payment', 14, 'بيع دورة: test', '2026-05-12 07:02:03'),
(33, 13, 'Purchase', -200, 33247, 'payment', 14, 'شراء دورة: test', '2026-05-12 07:02:03'),
(34, 1, 'Purchase', -200, 201666, 'payment', 15, 'شراء دورة: kjhs', '2026-05-12 07:37:09'),
(35, 13, 'Escrow_Release', 200, 34670, 'escrow', 3, NULL, '2026-05-12 07:37:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `roles` set('student','creator','admin') DEFAULT 'student',
  `is_verified` tinyint(1) DEFAULT 0,
  `is_suspended` tinyint(1) DEFAULT 0,
  `level` int(11) DEFAULT 1 CHECK (`level` >= 1),
  `xp` int(11) DEFAULT 0 CHECK (`xp` >= 0),
  `streak_days` int(11) DEFAULT 0 CHECK (`streak_days` >= 0),
  `last_streak_date` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_login_count` int(11) DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT 'default.png',
  `login_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`login_history`)),
  `verification_token` varchar(64) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `roles`, `is_verified`, `is_suspended`, `level`, `xp`, `streak_days`, `last_streak_date`, `last_login`, `failed_login_count`, `lockout_until`, `created_at`, `profile_pic`, `login_history`, `verification_token`, `reset_token`, `reset_expires`) VALUES
(1, 'Admin admin', 'admin@admin', '$2y$10$Gom1oHzfwOEXJBt.K8GDmunK.6ux74Nk5TpAPyZzsd/9J06p8d2Ea', 'admin', 1, 0, 21, 58737, 1, '2026-05-12', '2026-05-12 12:56:33', 0, NULL, '2026-05-05 05:29:58', 'user_1_1778048025.jpg', '[\"2026-05-05\",\"2026-05-06\",\"2026-05-09\",\"2026-05-12\"]', NULL, NULL, NULL),
(2, 'test', 'test@test', '111', 'student', 0, 1, 1, 0, 1, '2026-05-05', '2026-05-05 07:46:01', 0, '2026-05-12 08:39:03', '2026-05-05 04:43:44', 'default.png', NULL, NULL, NULL, NULL),
(8, 'mohammad', 'MohammadAshraf@gmail.com', '$2y$12$6t.noChF8oqgJatUJS26cuvvCyTxHvgSczmiZrHDUJsnRoEQGbeG2', 'student', 1, 0, 1, 100, 1, '2026-05-09', '2026-05-09 06:03:18', 0, NULL, '2026-05-05 05:20:39', 'default.png', '[\"2026-05-05\",\"2026-05-09\"]', NULL, NULL, NULL),
(12, 'mm', 'mm@mm', '$2y$12$zxjdg2DQhIVKfqG3qepPTuQHmDt6sunQwKwisf29Cnkr4Ml1Q4WR.', 'student', 1, 0, 2, 1466, 1, '2026-05-09', '2026-05-09 05:08:51', 0, NULL, '2026-05-05 05:44:37', 'default.png', '[\"2026-05-05\",\"2026-05-06\",\"2026-05-09\"]', NULL, NULL, NULL),
(13, 'Test User', 'test@test.com', '$2y$12$oFajJUcpACt4z3i22M.LW.wpaD.SulIiQeCjIvjvy4oXE2pWcGwm2', 'admin', 1, 0, 3, 2873, 1, '2026-05-12', '2026-05-12 12:36:39', 0, NULL, '2026-05-09 00:17:08', 'user_13_1778569596.png', '[\"2026-05-09\",\"2026-05-12\"]', '7eabddb716996436318707872ec758f424ce3c4b4bf7d4316bf1259b8d5fea4e', NULL, NULL),
(14, 'test', 'test@test.test', '$2y$12$F80gyFcFErOizMr8XG6HUu5mL81sXYPTt4Jb3FRzeoAaCnWesd0HS', 'student', 1, 0, 1, 200, 1, '2026-05-09', '2026-05-09 05:07:38', 0, NULL, '2026-05-09 01:41:32', 'default.png', '[\"2026-05-09\"]', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_achievements`
--

CREATE TABLE `user_achievements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet`
--

CREATE TABLE `wallet` (
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_balance` int(11) DEFAULT 0 CHECK (`token_balance` >= 0),
  `lifetime_earned` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallet`
--

INSERT INTO `wallet` (`wallet_id`, `user_id`, `token_balance`, `lifetime_earned`, `updated_at`) VALUES
(2, 1, 190769, 5277, '2026-05-12 07:47:56'),
(3, 8, 468, 368, '2026-05-09 03:03:13'),
(4, 12, 97875, 209, '2026-05-09 00:57:53'),
(5, 13, 35599, 6349, '2026-05-12 09:36:07'),
(6, 14, 100, 0, '2026-05-09 01:41:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`achievement_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `cash_out_requests`
--
ALTER TABLE `cash_out_requests`
  ADD PRIMARY KEY (`cashout_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`cert_id`),
  ADD UNIQUE KEY `qr_token` (`qr_token`),
  ADD UNIQUE KEY `user_id` (`user_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD KEY `creator_id` (`creator_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`dispute_id`),
  ADD UNIQUE KEY `escrow_id` (`escrow_id`),
  ADD KEY `raised_by` (`raised_by`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `escrow`
--
ALTER TABLE `escrow`
  ADD PRIMARY KEY (`escrow_id`),
  ADD UNIQUE KEY `payment_id` (`payment_id`),
  ADD KEY `creator_id` (`creator_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`lesson_id`),
  ADD UNIQUE KEY `course_id` (`course_id`,`order_index`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`Message_id`),
  ADD KEY `Sender_id` (`Sender_id`),
  ADD KEY `Receiver_id` (`Receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `progress`
--
ALTER TABLE `progress`
  ADD PRIMARY KEY (`progress_id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`lesson_id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quiz_id`),
  ADD UNIQUE KEY `course_id` (`course_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  ADD PRIMARY KEY (`choice_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD UNIQUE KEY `quiz_id` (`quiz_id`,`order_index`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `token_ledger`
--
ALTER TABLE `token_ledger`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`achievement_id`),
  ADD KEY `achievement_id` (`achievement_id`);

--
-- Indexes for table `wallet`
--
ALTER TABLE `wallet`
  ADD PRIMARY KEY (`wallet_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `achievement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_out_requests`
--
ALTER TABLE `cash_out_requests`
  MODIFY `cashout_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `cert_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `dispute_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `escrow`
--
ALTER TABLE `escrow`
  MODIFY `escrow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `lesson_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `Message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `progress`
--
ALTER TABLE `progress`
  MODIFY `progress_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quiz_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  MODIFY `choice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=213;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `token_ledger`
--
ALTER TABLE `token_ledger`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_achievements`
--
ALTER TABLE `user_achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet`
--
ALTER TABLE `wallet`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cash_out_requests`
--
ALTER TABLE `cash_out_requests`
  ADD CONSTRAINT `cash_out_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`);

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`escrow_id`) REFERENCES `escrow` (`escrow_id`),
  ADD CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`raised_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `disputes_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `escrow`
--
ALTER TABLE `escrow`
  ADD CONSTRAINT `escrow_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`),
  ADD CONSTRAINT `escrow_ibfk_2` FOREIGN KEY (`creator_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`Sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`Receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `progress`
--
ALTER TABLE `progress`
  ADD CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`lesson_id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`);

--
-- Constraints for table `quiz_choices`
--
ALTER TABLE `quiz_choices`
  ADD CONSTRAINT `quiz_choices_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE;

--
-- Constraints for table `skills`
--
ALTER TABLE `skills`
  ADD CONSTRAINT `skills_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `token_ledger`
--
ALTER TABLE `token_ledger`
  ADD CONSTRAINT `token_ledger_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`achievement_id`);

--
-- Constraints for table `wallet`
--
ALTER TABLE `wallet`
  ADD CONSTRAINT `wallet_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
