-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 08, 2026 at 05:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ovpre`
--

-- --------------------------------------------------------

--
-- Table structure for table `accomplishment_reports`
--

CREATE TABLE `accomplishment_reports` (
  `id` int(11) NOT NULL,
  `title` varchar(220) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `submitted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` int(11) NOT NULL,
  `title` varchar(220) NOT NULL,
  `activity_type` enum('Seminar','Training','Workshop','Community Outreach','Project Defense') NOT NULL,
  `date` date NOT NULL,
  `date_to` date DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `venue` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `title`, `activity_type`, `date`, `venue`, `description`, `created_by`, `created_at`) VALUES
(1, 'Happy Birthday', 'Training', '2026-06-09', 'adawsa', 'awdasdaw', 1, '2026-06-07 17:42:51');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(220) NOT NULL,
  `content` text NOT NULL,
  `audience` enum('All','Faculty','Researchers','Staff') DEFAULT 'All',
  `status` enum('Draft','Published','Archived') DEFAULT 'Published',
  `posted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `audience`, `status`, `posted_by`, `created_at`) VALUES
(1, 'Research proposal submissions are open', 'Faculty researchers may now submit new research proposals through I-Record.', 'Researchers', 'Published', 1, '2026-06-07 14:57:39'),
(2, 'Extension accomplishment reports due this month', 'Coordinators are requested to upload signed accomplishment reports before month end.', 'Staff', 'Published', 2, '2026-06-07 14:57:39'),
(3, 'gagaga', 'gagagaga', 'Faculty', 'Published', 1, '2026-06-07 17:42:19');

-- --------------------------------------------------------

--
-- Table structure for table `archived_documents`
--

CREATE TABLE `archived_documents` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `archived_by` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `created_at`) VALUES
(1, 1, 'Password accepted; OTP sent', '::1', '2026-06-07 14:58:13'),
(2, 1, 'Password accepted; OTP sent', '::1', '2026-06-07 14:59:25'),
(3, 1, 'Password accepted; OTP sent', '::1', '2026-06-07 15:02:47'),
(4, 1, 'Password accepted; OTP sent', '::1', '2026-06-07 15:03:10'),
(5, 1, 'Login completed', '::1', '2026-06-07 15:08:34'),
(6, 1, 'Updated user status #1', '::1', '2026-06-07 15:09:05'),
(7, 1, 'Logout', '::1', '2026-06-07 15:12:55'),
(8, 1, 'Login completed', '::1', '2026-06-07 15:14:16'),
(9, 1, 'Logout', '::1', '2026-06-07 15:21:26'),
(10, 1, 'Login completed', '::1', '2026-06-07 15:30:15'),
(11, 1, 'Logout', '::1', '2026-06-07 15:44:16'),
(12, 1, 'Login completed', '::1', '2026-06-07 15:46:42'),
(13, 1, 'Logout', '::1', '2026-06-07 16:54:12'),
(14, 1, 'Login completed', '::1', '2026-06-07 16:58:23'),
(15, 1, 'Updated user status #3', '::1', '2026-06-07 16:58:47'),
(16, 1, 'Updated user status #3', '::1', '2026-06-07 16:59:01'),
(17, 1, 'Logout', '::1', '2026-06-07 16:59:07'),
(18, 1, 'Login completed', '::1', '2026-06-07 16:59:45'),
(19, 1, 'Logout', '::1', '2026-06-07 17:00:09'),
(20, 3, 'Password reset requested', '::1', '2026-06-07 17:00:14'),
(21, 1, 'Login completed', '::1', '2026-06-07 17:04:08'),
(22, 1, 'Created user account: edrian', '::1', '2026-06-07 17:08:04'),
(23, 1, 'Logout', '::1', '2026-06-07 17:08:09'),
(24, 6, 'Login completed', '::1', '2026-06-07 17:08:18'),
(25, 6, 'Uploaded document: haha', '::1', '2026-06-07 17:09:46'),
(26, 6, 'Uploaded document: haha', '::1', '2026-06-07 17:09:52'),
(27, 6, 'Logout', '::1', '2026-06-07 17:10:04'),
(28, 1, 'Login completed', '::1', '2026-06-07 17:10:11'),
(29, 1, 'Set document #2 to Approved', '::1', '2026-06-07 17:11:11'),
(30, 1, 'Set document #1 to Pending', '::1', '2026-06-07 17:11:14'),
(31, 1, 'Set document #1 to Approved', '::1', '2026-06-07 17:11:17'),
(32, 1, 'Logout', '::1', '2026-06-07 17:11:55'),
(33, 6, 'Login completed', '::1', '2026-06-07 17:12:04'),
(34, 6, 'Logout', '::1', '2026-06-07 17:13:05'),
(35, 1, 'Login completed', '::1', '2026-06-07 17:13:12'),
(36, 1, 'Set document #2 to Declined', '::1', '2026-06-07 17:13:40'),
(37, 1, 'Set document #2 to Pending', '::1', '2026-06-07 17:13:45'),
(38, 1, 'Logout', '::1', '2026-06-07 17:27:59'),
(39, 6, 'Failed login attempt', '::1', '2026-06-07 17:28:10'),
(40, 1, 'Login completed', '::1', '2026-06-07 17:28:18'),
(41, 1, 'Updated user status #6', '::1', '2026-06-07 17:28:26'),
(42, 1, 'Logout', '::1', '2026-06-07 17:28:30'),
(43, 6, 'Login completed', '::1', '2026-06-07 17:28:38'),
(44, 6, 'Logout', '::1', '2026-06-07 17:29:04'),
(45, 6, 'Failed login attempt', '::1', '2026-06-07 17:29:14'),
(46, 6, 'Password reset requested', '::1', '2026-06-07 17:29:57'),
(47, 6, 'Password reset requested', '::1', '2026-06-07 17:31:11'),
(48, 1, 'Failed login attempt', '::1', '2026-06-07 17:32:28'),
(49, 1, 'Login completed', '::1', '2026-06-07 17:33:22'),
(50, 1, 'Deactivated user account with linked records: edrian', '::1', '2026-06-07 17:35:08'),
(51, 1, 'Deactivated user account with linked records: edrian', '::1', '2026-06-07 17:35:20'),
(52, 1, 'Deactivated user account with linked records: edrian', '::1', '2026-06-07 17:41:05'),
(53, 1, 'Set document #1 to Pending', '::1', '2026-06-07 17:41:35'),
(54, 1, 'Set document #1 to Pending', '::1', '2026-06-07 17:41:40'),
(55, 1, 'Set document #1 to Under Review', '::1', '2026-06-07 17:41:44'),
(56, 1, 'Set document #2 to Pending', '::1', '2026-06-07 17:41:45'),
(57, 1, 'Set document #2 to Under Review', '::1', '2026-06-07 17:41:47'),
(58, 1, 'Published announcement #3', '::1', '2026-06-07 17:42:19'),
(59, 1, 'Created activity: fawdada', '::1', '2026-06-07 17:42:51'),
(60, 1, 'Submitted extension project: asdaw', '::1', '2026-06-07 17:43:41'),
(61, 1, 'Uploaded document: adwasd', '::1', '2026-06-07 17:44:42'),
(62, 1, 'Logout', '::1', '2026-06-07 17:45:13'),
(63, 1, 'Login completed', '::1', '2026-06-08 13:27:46'),
(64, 1, 'Deactivated user account with linked records: edrian', '::1', '2026-06-08 13:37:46'),
(65, 1, 'Logout', '::1', '2026-06-08 13:47:01'),
(66, 6, 'Failed login attempt', '::1', '2026-06-08 13:47:11'),
(67, 3, 'Failed login attempt', '::1', '2026-06-08 13:47:34'),
(68, 1, 'Login completed', '::1', '2026-06-08 13:47:52'),
(69, 1, 'Submitted research proposal: asdawdas', '::1', '2026-06-08 13:49:14'),
(70, 1, 'Submitted extension project: dawdsad', '::1', '2026-06-08 13:49:37'),
(71, 1, 'Logout', '::1', '2026-06-08 13:52:02'),
(72, 1, 'Login completed', '::1', '2026-06-08 13:52:10'),
(73, 1, 'Logout', '::1', '2026-06-08 13:52:14'),
(74, 1, 'Login completed', '::1', '2026-06-08 13:54:09'),
(75, 1, 'Logout', '::1', '2026-06-08 13:58:12'),
(76, 1, 'Login completed', '::1', '2026-06-08 14:01:48'),
(77, 1, 'Logout', '::1', '2026-06-08 14:09:07'),
(78, 1, 'Login completed', '::1', '2026-06-08 14:09:13'),
(79, 1, 'Set document #3 to Approved', '::1', '2026-06-08 14:11:04'),
(80, 1, 'Updated activity #1', '::1', '2026-06-08 14:44:15'),
(81, 1, 'Created user account: jiro', '::1', '2026-06-08 14:46:36'),
(82, 1, 'Logout', '::1', '2026-06-08 14:46:43'),
(83, 7, 'Failed login attempt', '::1', '2026-06-08 14:46:53'),
(84, 7, 'Login completed', '::1', '2026-06-08 14:47:04'),
(85, 7, 'Logout', '::1', '2026-06-08 14:52:25'),
(86, 7, 'Login completed', '::1', '2026-06-08 14:52:36'),
(87, 7, 'Logout', '::1', '2026-06-08 15:05:10'),
(88, 1, 'Login completed', '::1', '2026-06-08 15:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`) VALUES
(4, 'Accomplishment Report'),
(7, 'Administrative Record'),
(3, 'Extension Project'),
(6, 'Memorandum'),
(5, 'Publication'),
(1, 'Research Proposal'),
(2, 'Research Report');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `created_at`) VALUES
(1, 'College of Computer Studies and Technology', '2026-06-07 14:57:39'),
(2, 'College of Engineering', '2026-06-07 14:57:39'),
(3, 'College of Nursing and Allied Health Sciences', '2026-06-07 14:57:39'),
(4, 'College of Arts and Sciences', '2026-06-07 14:57:39'),
(5, 'College of Business Administration', '2026-06-07 14:57:39'),
(6, 'College of Human Kinetics', '2026-06-07 14:57:39'),
(7, 'College of Teacher Education', '2026-06-07 15:59:34'),
(8, 'College of Tourism and Hospitality Management', '2026-06-07 15:59:34'),
(9, 'College of Accountancy', '2026-06-07 15:59:34');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `title` varchar(220) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `author` varchar(160) NOT NULL,
  `department_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `status` enum('Pending','Under Review','Approved','Declined') DEFAULT 'Pending',
  `submission_date` date NOT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `title`, `category_id`, `description`, `author`, `department_id`, `filename`, `original_filename`, `uploaded_by`, `status`, `submission_date`, `archived_at`, `created_at`, `updated_at`) VALUES
(1, 'haha', 4, 'hahhaha', 'jerome', 1, '4/7f7b63383acaaa0bfbcce2e2b910e200.docx', 'Family tree.docx', 6, 'Under Review', '2026-06-07', NULL, '2026-06-07 17:09:46', '2026-06-08 01:41:44'),
(2, 'haha', 4, 'hahhaha', 'jerome', 1, '4/e73a2e0fab2e3ecf72dfdec33a6d7c77.docx', 'Family tree.docx', 6, 'Under Review', '2026-06-07', NULL, '2026-06-07 17:09:52', '2026-06-08 01:41:47'),
(3, 'adwasd', 5, 'dadwds', 'System Administrator', 4, '5/0244aa584648ac28850b84d73edcbd91.docx', 'Family tree.docx', 1, 'Approved', '2026-06-07', NULL, '2026-06-07 17:44:42', '2026-06-08 22:11:04');

-- --------------------------------------------------------

--
-- Table structure for table `document_versions`
--

CREATE TABLE `document_versions` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `version_label` varchar(80) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `modified_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_versions`
--

INSERT INTO `document_versions` (`id`, `document_id`, `version_label`, `filename`, `modified_by`, `created_at`) VALUES
(1, 1, 'Original Version', '4/7f7b63383acaaa0bfbcce2e2b910e200.docx', 6, '2026-06-07 17:09:46'),
(2, 2, 'Original Version', '4/e73a2e0fab2e3ecf72dfdec33a6d7c77.docx', 6, '2026-06-07 17:09:52'),
(3, 3, 'Original Version', '5/0244aa584648ac28850b84d73edcbd91.docx', 1, '2026-06-07 17:44:42');

-- --------------------------------------------------------

--
-- Table structure for table `extension_projects`
--

CREATE TABLE `extension_projects` (
  `id` int(11) NOT NULL,
  `project_title` varchar(220) NOT NULL,
  `coordinator` varchar(160) NOT NULL,
  `community_partner` varchar(180) NOT NULL,
  `budget` decimal(12,2) DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `progress_status` enum('Planning','Ongoing','Completed') DEFAULT 'Planning',
  `submitted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_projects`
--

INSERT INTO `extension_projects` (`id`, `project_title`, `coordinator`, `community_partner`, `budget`, `start_date`, `end_date`, `progress_status`, `submitted_by`, `created_at`) VALUES
(1, 'asdaw', 'System Administrator', 'dawdsa', 1231241.00, '2026-06-09', '2026-06-30', 'Planning', 1, '2026-06-07 17:43:41'),
(2, 'dawdsad', 'System Administrator', 'adwasd', 123.00, '2026-06-11', '2026-06-06', 'Ongoing', 1, '2026-06-08 13:49:37');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `status` enum('Unread','Read') DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `status`, `created_at`) VALUES
(1, 1, 'New announcement: gagaga', 'Unread', '2026-06-07 17:42:19'),
(2, 3, 'New announcement: gagaga', 'Unread', '2026-06-07 17:42:19');

-- --------------------------------------------------------

--
-- Table structure for table `research_projects`
--

CREATE TABLE `research_projects` (
  `id` int(11) NOT NULL,
  `title` varchar(220) NOT NULL,
  `researchers` text NOT NULL,
  `abstract` text NOT NULL,
  `funding_source` varchar(180) DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `submitted_by` int(11) NOT NULL,
  `status` enum('Pending','Under Review','Approved','Declined') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `research_projects`
--

INSERT INTO `research_projects` (`id`, `title`, `researchers`, `abstract`, `funding_source`, `document_id`, `submitted_by`, `status`, `created_at`) VALUES
(1, 'asdawdas', 'asdawsda', 'asdadw', 'sdasd', NULL, 1, 'Pending', '2026-06-08 13:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `username` varchar(80) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `auth_user_id` char(36) DEFAULT NULL,
  `role` enum('Super Admin','OVPRE Admin','Research Coordinator','Extension Coordinator','Faculty Extensionist','Researcher') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('Pending Invitation','Pending Approval','Active','Inactive') DEFAULT 'Pending Approval',
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `users_auth_user_id_unique` (`auth_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `username`, `password`, `role`, `department_id`, `status`, `login_attempts`, `locked_until`, `reset_token`, `reset_expires`, `created_at`) VALUES
(1, 'System Administrator', 'admin@plsp.edu.ph', 'admin', '$2y$10$ApePRZk2RoQdlV6WuRB6gOqViGhiMlIxFITLUdSvbVwekPgEgd3Dm', 'Super Admin', 1, 'Active', 0, NULL, NULL, NULL, '2026-06-07 14:57:39'),
(2, 'OVPRE Records Staff', 'staff@plsp.edu.ph', 'staff', '$2y$10$ApePRZk2RoQdlV6WuRB6gOqViGhiMlIxFITLUdSvbVwekPgEgd3Dm', 'OVPRE Admin', 1, 'Inactive', 0, NULL, NULL, NULL, '2026-06-07 14:57:39'),
(3, 'Maria Santos', 'faculty@plsp.edu.ph', 'faculty', '$2y$10$ApePRZk2RoQdlV6WuRB6gOqViGhiMlIxFITLUdSvbVwekPgEgd3Dm', 'Researcher', 2, 'Active', 1, NULL, '$2y$10$vr/Dey9d9WZmY6xWD7/M.eCF2JffVd98L8EFEtliRDw9yvNf5Qoh6', '2026-06-08 01:30:14', '2026-06-07 14:57:39'),
(4, 'Carlo Reyes', 'extension@plsp.edu.ph', 'extension', '$2y$10$ApePRZk2RoQdlV6WuRB6gOqViGhiMlIxFITLUdSvbVwekPgEgd3Dm', 'Extension Coordinator', 6, 'Inactive', 0, NULL, NULL, NULL, '2026-06-07 14:57:39'),
(6, 'jerome', 'facultyplsp@gmail.com', 'edrian', '$2y$10$fPi4FaichiIAm7lK8x5FVeBoRfSx9PTyXGGWrkkEhPHFjPoM71VvG', 'Researcher', 9, 'Inactive', 2, NULL, '$2y$10$9metdNw2kO.apkMYyhFyL.4IDU3Jgj7dW5Gj7WXvHyMqcQBqylTWO', '2026-06-08 02:01:11', '2026-06-07 17:08:04'),
(7, 'Jiro', 'jiro@gmail.com', 'jiro', '$2y$10$9DFNe4u31TBdA/59PpUg5Oc.puYPU25yJaiAJGj8RalKBMhiLvO6a', 'OVPRE Admin', 6, 'Active', 0, NULL, NULL, NULL, '2026-06-08 14:46:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accomplishment_reports`
--
ALTER TABLE `accomplishment_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `archived_documents`
--
ALTER TABLE `archived_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `modified_by` (`modified_by`);

--
-- Indexes for table `extension_projects`
--
ALTER TABLE `extension_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `research_projects`
--
ALTER TABLE `research_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accomplishment_reports`
--
ALTER TABLE `accomplishment_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `archived_documents`
--
ALTER TABLE `archived_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_versions`
--
ALTER TABLE `document_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `extension_projects`
--
ALTER TABLE `extension_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `research_projects`
--
ALTER TABLE `research_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accomplishment_reports`
--
ALTER TABLE `accomplishment_reports`
  ADD CONSTRAINT `accomplishment_reports_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `extension_projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `accomplishment_reports_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `accomplishment_reports_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD CONSTRAINT `document_versions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_versions_ibfk_2` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `extension_projects`
--
ALTER TABLE `extension_projects`
  ADD CONSTRAINT `extension_projects_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `research_projects`
--
ALTER TABLE `research_projects`
  ADD CONSTRAINT `research_projects_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `research_projects_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
