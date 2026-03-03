-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 09:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u389936701_capstone`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`) VALUES
(2, NULL, 'Bachelor of Science in Office Administration'),
(3, NULL, 'Bachelor of Science in Accountancy'),
(5, NULL, 'Bachelor of Science in Accounting Information System'),
(6, NULL, 'BSBA Major in Financial Management'),
(10, NULL, 'Bachelor of Science in Office Management'),
(11, NULL, 'Bachelor of Science in Information Technology'),
(12, NULL, 'Bachelor of Science in Information System'),
(13, NULL, 'Computer Science'),
(14, NULL, 'Associate in Computer Technology');

-- --------------------------------------------------------

--
-- Table structure for table `dtr`
--

CREATE TABLE `dtr` (
  `dtr_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `am_in` char(5) DEFAULT NULL,
  `am_out` char(5) DEFAULT NULL,
  `pm_in` char(5) DEFAULT NULL,
  `pm_out` char(5) DEFAULT NULL,
  `hours` int(11) DEFAULT 0,
  `minutes` int(11) DEFAULT 0,
  `synced` tinyint(1) DEFAULT 0,
  `buffered_at` datetime DEFAULT current_timestamp(),
  `attempts` int(11) DEFAULT 0,
  `last_error` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dtr`
--

INSERT INTO `dtr` (`dtr_id`, `student_id`, `log_date`, `am_in`, `am_out`, `pm_in`, `pm_out`, `hours`, `minutes`, `synced`, `buffered_at`, `attempts`, `last_error`) VALUES
(252, 80, '2026-03-05', '08:00', '12:00', '13:00', '17:00', 8, 0, 1, '2026-03-02 15:56:46', 0, NULL),
(253, 80, '2026-03-06', '08:00', '12:00', '13:00', '17:00', 8, 0, 1, '2026-03-02 15:56:46', 0, NULL),
(254, 80, '2026-03-09', '', '', '13:00', '', 0, 0, 0, '2026-03-02 15:56:46', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `eval_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `rating` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `school_eval` varchar(255) NOT NULL DEFAULT '',
  `date_evaluated` date DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `rating_desc` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluations_backup`
--

CREATE TABLE `evaluations_backup` (
  `eval_id` int(11) NOT NULL DEFAULT 0,
  `student_id` int(11) DEFAULT NULL,
  `office_head_id` int(11) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `date_evaluated` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `face_templates`
--

CREATE TABLE `face_templates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `descriptor` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`descriptor`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `face_templates`
--

INSERT INTO `face_templates` (`id`, `user_id`, `file_path`, `created_at`, `descriptor`) VALUES
(30, 80, 'uploads/faces/80_1770781000.jpg', '2026-02-11 11:36:40', '[-0.17239606380462646,0.1280432939529419,0.036501236259937286,-0.1460030972957611,-0.11649807542562485,-0.025256536900997162,-0.022269682958722115,-0.07597382366657257,0.20801113545894623,-0.14324961602687836,0.28103628754615784,-0.12332458794116974,-0.2534247636795044,-0.0274939127266407,-0.020636022090911865,0.22401553392410278,-0.1742313802242279,-0.09424847364425659,-0.0465356707572937,-0.008489858359098434,0.08477464318275452,-0.055916834622621536,0.03060067631304264,0.1375913769006729,-0.08910287916660309,-0.42945563793182373,-0.11510826647281647,-0.06564684957265854,-0.09312132000923157,-0.09095782041549683,0.00621255487203598,0.07904621213674545,-0.25759798288345337,-0.09223669022321701,-0.0841076672077179,0.09159582853317261,-0.00855796318501234,-0.09487633407115936,0.14615103602409363,0.023250140249729156,-0.22469110786914825,-0.016913894563913345,0.04166477918624878,0.24809838831424713,0.1675451099872589,0.026356957852840424,0.027403395622968674,-0.0662226676940918,0.05401331186294556,-0.1520877182483673,-0.015880459919571877,0.07334595173597336,0.07518507540225983,-0.014195794239640236,0.019942641258239746,-0.11716512590646744,0.03438521549105644,0.1354229748249054,-0.1866910606622696,-0.0678466185927391,0.04719817638397217,-0.09436019510030746,-0.041345320641994476,-0.15398848056793213,0.3496767580509186,0.17938338220119476,-0.10440152138471603,-0.1415146440267563,0.18524491786956787,-0.06756969541311264,-0.036615174263715744,0.0459887869656086,-0.18603359162807465,-0.16628971695899963,-0.3130398690700531,0.03663528338074684,0.3414410650730133,0.09173724055290222,-0.16795168817043304,0.05596616119146347,-0.08181900531053543,0.01607067510485649,0.06039571017026901,0.18672797083854675,0.006048387847840786,0.054376255720853806,-0.0587424598634243,0.024089954793453217,0.14457450807094574,-0.013811488635838032,-0.04939349368214607,0.2264857441186905,-0.0515136644244194,0.011397402733564377,0.030747955664992332,-0.047342605888843536,-0.1276610940694809,0.01501975953578949,-0.1502101570367813,-0.019298316910862923,-0.023490581661462784,-0.015571298077702522,-0.06010555848479271,0.10245126485824585,-0.1760488599538803,0.05513200908899307,-0.03074062429368496,-0.000303201493807137,-0.03407915681600571,0.0023159459233283997,-0.0696333721280098,-0.07398156821727753,0.10691671818494797,-0.25444474816322327,0.11761915683746338,0.1350388377904892,-0.025515951216220856,0.15220271050930023,0.1068444550037384,0.11538698524236679,-0.023651843890547752,0.016457488760352135,-0.24250702559947968,0.03687937930226326,0.17724119126796722,-0.048662517219781876,0.14287197589874268,-0.005882028955966234]');

-- --------------------------------------------------------

--
-- Table structure for table `intern_stories`
--

CREATE TABLE `intern_stories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `intern_stories`
--

INSERT INTO `intern_stories` (`id`, `name`, `course`, `message`, `image`) VALUES
(1, 'Ong, Jasmine M.', 'BPC', 'My OJT at Malolos City Hall gave me the chance to apply what I learned in school to actual office tasks. I became more confident in handling clerical work and assisting people.', 'upload/1759319055_aa651614-a29c-4e06-9484-cb3b2ea6c9b1.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `late_dtr`
--

CREATE TABLE `late_dtr` (
  `late_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `date_filed` date DEFAULT NULL,
  `late_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `moa`
--

CREATE TABLE `moa` (
  `moa_id` int(11) NOT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `moa_file` varchar(255) DEFAULT NULL,
  `date_signed` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `message`, `created_at`) VALUES
(1, 'New application submitted by Tori Vega for City Accounting Office (also 2nd choice: City Budget Office).', '2026-02-19 05:35:42'),
(2, 'New applicant: Trina  Vega – City Admin Office', '2026-02-19 06:21:00'),
(3, 'New applicant: Mei Mei – City Admin Office', '2026-02-22 12:38:06'),
(4, 'New applicant: Min min – City Budget Office / City Accounting Office', '2026-02-22 13:33:43'),
(6, 'Application Approved: Min min has been approved for City Accounting Office. Orientation is scheduled on March 3, 2026 8:30 A.M..', '2026-03-02 05:39:21'),
(7, 'Orientation Rescheduled: Min min\'s orientation is now scheduled on March 4, 2026 at 9:30 A.M..', '2026-03-02 06:34:57'),
(8, 'Orientation Rescheduled: Min min\'s orientation is now scheduled on March 5, 2026 at 9:30 A.M..', '2026-03-02 06:39:45'),
(9, 'Orientation Rescheduled: Min min\'s orientation is now scheduled on March 3, 2026 at 9:30 A.M..', '2026-03-02 06:45:32'),
(10, 'Orientation Rescheduled: Min min\'s orientation is now scheduled on March 4, 2026 at 9:30 A.M..', '2026-03-02 07:03:12'),
(11, 'Orientation Rescheduled: Min min\'s orientation is now scheduled on March 3, 2026 at 9:30 A.M..', '2026-03-02 07:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `notification_users`
--

CREATE TABLE `notification_users` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_users`
--

INSERT INTO `notification_users` (`id`, `notification_id`, `user_id`, `is_read`) VALUES
(1, 1, 5, 1),
(2, 1, 6, 1),
(3, 1, 85, 0),
(4, 1, 29, 1),
(5, 1, 27, 1),
(6, 2, 5, 1),
(7, 2, 6, 1),
(8, 2, 85, 0),
(9, 2, 50, 1),
(10, 3, 5, 1),
(11, 3, 6, 1),
(12, 3, 85, 0),
(13, 3, 50, 1),
(14, 4, 5, 1),
(15, 4, 6, 1),
(16, 4, 85, 0),
(17, 4, 27, 1),
(18, 4, 29, 0),
(22, 6, 6, 1),
(23, 6, 85, 0),
(24, 6, 29, 0),
(25, 7, 6, 1),
(26, 7, 85, 0),
(27, 8, 27, 0),
(28, 8, 5, 1),
(29, 9, 27, 0),
(30, 9, 5, 1),
(31, 10, 27, 0),
(32, 10, 6, 1),
(33, 10, 85, 0),
(35, 11, 29, 0),
(36, 11, 6, 1),
(37, 11, 85, 0);

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `office_id` int(11) NOT NULL,
  `office_name` varchar(150) DEFAULT NULL,
  `current_limit` int(11) DEFAULT 0,
  `updated_limit` int(11) DEFAULT 0,
  `requested_limit` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Declined') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`office_id`, `office_name`, `current_limit`, `updated_limit`, `requested_limit`, `reason`, `status`) VALUES
(8, 'City Budget Office', 2, 2, NULL, '', 'Approved'),
(9, 'City Accounting Office', 2, 2, NULL, '', 'Approved'),
(14, 'City Admin Office', 2, 0, NULL, NULL, 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `office_courses`
--

CREATE TABLE `office_courses` (
  `id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `office_courses`
--

INSERT INTO `office_courses` (`id`, `office_id`, `course_id`) VALUES
(5, 8, 3),
(6, 8, 5),
(7, 8, 6),
(26, 9, 3),
(27, 9, 5),
(16, 14, 2),
(15, 14, 10);

-- --------------------------------------------------------

--
-- Table structure for table `office_heads_backup`
--

CREATE TABLE `office_heads_backup` (
  `office_head_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `office_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_requests`
--

CREATE TABLE `office_requests` (
  `request_id` int(11) NOT NULL,
  `office_id` int(11) DEFAULT NULL,
  `old_limit` int(11) DEFAULT NULL,
  `new_limit` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `date_requested` date DEFAULT NULL,
  `date_of_action` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_requests`
--

INSERT INTO `office_requests` (`request_id`, `office_id`, `old_limit`, `new_limit`, `reason`, `status`, `date_requested`, `date_of_action`) VALUES
(29, 8, NULL, 3, 'increased workload due to holiday season', 'approved', '2025-11-18', '2025-11-18 13:05:34'),
(31, 14, NULL, 2, 'increased workload', 'approved', '2025-11-19', '2025-11-20 03:17:07'),
(33, 8, NULL, 4, 'increased workload', 'approved', '2026-01-15', '2026-01-15 07:31:41'),
(34, 8, NULL, 2, 'increased workload', 'rejected', '2026-01-15', '2026-01-19 13:04:41'),
(35, 9, NULL, 6, 'increased workload', 'approved', '2026-01-15', '2026-02-10 14:18:30'),
(39, 8, NULL, 0, '', 'approved', '2026-02-10', NULL),
(41, 9, 0, 2, '', 'approved', '2026-02-10', '2026-02-10 14:18:30'),
(42, 8, 1, 2, '', 'approved', '2026-02-22', '2026-02-22 22:10:07');

-- --------------------------------------------------------

--
-- Table structure for table `ojt_applications`
--

CREATE TABLE `ojt_applications` (
  `application_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `office_preference1` int(11) DEFAULT NULL,
  `office_preference2` int(11) DEFAULT NULL,
  `letter_of_intent` varchar(255) DEFAULT NULL,
  `endorsement_letter` varchar(255) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `moa_file` varchar(255) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','ongoing','completed','evaluated','deactivated') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `date_submitted` date DEFAULT NULL,
  `date_updated` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ojt_applications`
--

INSERT INTO `ojt_applications` (`application_id`, `student_id`, `office_preference1`, `office_preference2`, `letter_of_intent`, `endorsement_letter`, `resume`, `moa_file`, `picture`, `status`, `remarks`, `date_submitted`, `date_updated`) VALUES
(81, 100, 9, 8, 'uploads/1770705776_Letter_of_Intent.pdf', 'uploads/1770705776_Letter_of_Endorsement.pdf', 'uploads/1770705776_Resume.pdf', '', 'uploads/1770705776_121f7f50bd34f5355dcba6953ea6484e.jpg', 'pending', '', '2026-02-10', NULL),
(82, 101, 14, NULL, 'uploads/1770705947_Letter_of_Intent.pdf', 'uploads/1770705947_Letter_of_Endorsement.pdf', 'uploads/1770705947_Resume.pdf', '', 'uploads/1770705947_2bc78ada6efb0115b1a7c31755e2350f.jpg', 'pending', '', '2026-02-10', NULL),
(83, 102, 8, NULL, 'uploads/1770706123_Letter_of_Intent.pdf', 'uploads/1770706123_Letter_of_Endorsement.pdf', 'uploads/1770706123_Resume.pdf', 'uploads/1770706123_Memorandum_of_Agreement.pdf', 'uploads/1770706123_0582a225589e3d81723e739625be15e7.jpg', 'pending', '', '2026-02-10', NULL),
(85, 104, 9, 8, 'uploads/1770718018_LETTER_OF_INTENT.pdf', 'uploads/1770718018_Endorsementlettersample.pdf', 'uploads/1770718018_Resumesample.pdf', '', 'uploads/1770718018_Screenshot_2026-02-08_160240.png', 'approved', 'Orientation/Start: February 11, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-02-10', '2026-02-10'),
(86, 105, 8, NULL, 'uploads/1770719104_LETTER_OF_INTENT.pdf', 'uploads/1770719104_Endorsementlettersample.pdf', 'uploads/1770719104_Resumesample.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770719104_72556f0f-31ec-44ff-a679-c52befb7aae2.jpg', 'approved', 'Orientation/Start: February 11, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-02-10', '2026-02-10'),
(87, 106, 8, NULL, 'uploads/1770779056_CT-WA213_Web_Application.pdf', 'uploads/1770779056_pdfcoffee.com_a-practical-guide-to-information-systems-strategic-planning-2nd-edition-pdf-free.pdf', 'uploads/1770779056_CT-WA213_Web_Application.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770779056_RobloxScreenShot20250620_075847493.png', 'pending', '', '2026-02-11', NULL),
(88, 107, 9, 8, 'uploads/1770779987_LETTER_OF_INTENT.pdf', 'uploads/1770779987_Endorsementlettersample.pdf', 'uploads/1770779987_Resumesample.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770779987_Screenshot_2026-02-08_160240.png', 'approved', 'Orientation/Start: February 12, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-02-11', '2026-02-11'),
(89, 108, 9, 8, 'uploads/1770780015_Memo_OIC-OCP-16-25-26-VISION-MISSION.pdf', 'uploads/1770780015_Memo_OIC-OCP-16-25-26-VISION-MISSION.pdf', 'uploads/1770780015_Memo_OIC-OCP-16-25-26-VISION-MISSION.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770780015_avatar.png', 'approved', 'Orientation/Start: February 12, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-02-11', '2026-02-11'),
(90, 109, 8, NULL, 'uploads/moa_1770699674.pdf', 'uploads/698bf6cd8611b_moa_1770699674.pdf', 'uploads/698bf6cd85508_moa_1770699674.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770780438_20260125_100148.jpg', 'pending', '', '2026-02-11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orientation_assignments`
--

CREATE TABLE `orientation_assignments` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resched_from` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orientation_sessions`
--

CREATE TABLE `orientation_sessions` (
  `session_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `rescheduled_from` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orientation_sessions`
--

INSERT INTO `orientation_sessions` (`session_id`, `session_date`, `session_time`, `location`, `rescheduled_from`) VALUES
(1, '2025-11-13', '08:30:00', 'CHRMO/3rd Floor', NULL),
(2, '2025-11-14', '08:30:00', 'CHRMO/3rd Floor', NULL),
(3, '2025-11-17', '08:30:00', 'CHRMO/3rd Floor', NULL),
(4, '2025-11-18', '08:30:00', 'CHRMO/3rd Floor', NULL),
(5, '2025-11-19', '08:30:00', 'CHRMO/3rd Floor', NULL),
(6, '2025-11-23', '08:30:00', 'CHRMO/3rd Floor', NULL),
(7, '2025-11-24', '08:30:00', 'CHRMO/3rd Floor', NULL),
(8, '2025-11-25', '08:30:00', 'CHRMO/3rd Floor', NULL),
(9, '2025-11-26', '08:30:00', 'CHRMO/3rd Floor', NULL),
(10, '2025-11-28', '08:30:00', 'CHRMO/3rd Floor', NULL),
(11, '2026-01-23', '08:30:00', 'CHRMO/3rd Floor', NULL),
(12, '2026-01-22', '08:30:00', 'CHRMO/3rd Floor', NULL),
(13, '2026-02-09', '08:30:00', 'CHRMO/3rd Floor', NULL),
(19, '2026-03-03', '09:30:00', 'CHRMO/3rd Floor', '2026-03-04');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `otp`, `expires_at`, `used`, `created_at`) VALUES
(1, 5, 'santiagojasminem@gmail.com', '972639', '2026-02-03 03:13:04', 0, '2026-02-03 02:03:04'),
(2, 5, 'santiagojasminem@gmail.com', '749819', '2026-02-03 03:17:15', 1, '2026-02-03 02:14:15'),
(3, 5, 'santiagojasminem@gmail.com', '456242', '2026-02-03 03:24:36', 0, '2026-02-03 02:21:36'),
(4, 5, 'santiagojasminem@gmail.com', '622537', '2026-02-03 03:29:33', 0, '2026-02-03 02:26:33'),
(5, 5, 'santiagojasminem@gmail.com', '826772', '2026-02-03 03:29:49', 0, '2026-02-03 02:26:49'),
(6, 5, 'santiagojasminem@gmail.com', '729085', '2026-02-03 03:43:20', 0, '2026-02-03 02:40:20'),
(7, 5, 'santiagojasminem@gmail.com', '869839', '2026-02-03 03:43:27', 0, '2026-02-03 02:40:27'),
(8, 5, 'santiagojasminem@gmail.com', '024793', '2026-02-03 04:12:22', 1, '2026-02-03 03:09:22'),
(9, 5, 'santiagojasminem@gmail.com', '828982', '2026-02-03 04:15:56', 1, '2026-02-03 03:12:56'),
(10, 5, 'santiagojasminem@gmail.com', '479169', '2026-02-03 04:17:32', 1, '2026-02-03 03:14:32'),
(11, 5, 'santiagojasminem@gmail.com', '191472', '2026-02-03 04:18:20', 0, '2026-02-03 03:15:20'),
(12, 5, 'santiagojasminem@gmail.com', '870522', '2026-02-03 04:29:39', 1, '2026-02-03 03:26:39'),
(13, 70, 'santiagojasminem@gmail.com', '213320', '2026-02-09 07:20:09', 1, '2026-02-09 06:17:09'),
(14, 6, 'santiagojasminem@gmail.com', '500541', '2026-02-09 16:37:59', 0, '2026-02-09 15:34:59'),
(15, 6, 'santiagojasminem@gmail.com', '100975', '2026-02-09 16:39:05', 0, '2026-02-09 15:36:05'),
(16, 6, 'santiagojasminem@gmail.com', '688856', '2026-02-09 22:27:27', 1, '2026-02-09 21:24:27'),
(17, 6, 'santiagojasminem@gmail.com', '566256', '2026-02-10 07:53:13', 1, '2026-02-10 06:50:13'),
(18, 6, 'santiagojasminem@gmail.com', '644533', '2026-02-23 04:36:21', 0, '2026-02-23 03:33:21');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `emergency_name` varchar(100) DEFAULT NULL,
  `emergency_relation` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `college` varchar(150) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL DEFAULT '2025-2026',
  `semester` varchar(50) DEFAULT NULL,
  `school_address` varchar(255) DEFAULT NULL,
  `ojt_adviser` varchar(100) DEFAULT NULL,
  `adviser_contact` varchar(20) DEFAULT NULL,
  `total_hours_required` int(11) DEFAULT 500,
  `hours_rendered` int(11) DEFAULT 0,
  `progress` decimal(5,2) GENERATED ALWAYS AS (`hours_rendered` / `total_hours_required` * 100) STORED,
  `status` enum('pending','approved','ongoing','completed','evaluated','rejected','deactivated') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `synced` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `address`, `contact_number`, `email`, `birthday`, `emergency_name`, `emergency_relation`, `emergency_contact`, `college`, `course`, `year_level`, `school_year`, `semester`, `school_address`, `ojt_adviser`, `adviser_contact`, `total_hours_required`, `hours_rendered`, `status`, `reason`, `synced`) VALUES
(100, NULL, 'Jean', NULL, 'Mercado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 400, 0, 'pending', NULL, 1),
(101, NULL, 'John', NULL, 'Santos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 500, 0, 'pending', NULL, 1),
(102, NULL, 'Jaimee', NULL, 'Bautista', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 400, 0, 'pending', NULL, 1),
(104, 80, 'Blair', NULL, 'Waldorf', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 20, 0, 'completed', NULL, 1),
(105, 81, 'Mikaili', NULL, 'Mesia', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 10, 0, 'pending', NULL, 1),
(106, NULL, 'Ai-vee', NULL, 'Fulgencio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 500, 0, 'pending', NULL, 1),
(107, 82, 'Joenel', NULL, 'Valenton', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 250, 0, 'pending', NULL, 1),
(108, 83, 'Deserie', NULL, 'Robles', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 500, 0, 'pending', NULL, 1),
(109, 86, 'Paulo', NULL, 'Victoria', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-2026', NULL, NULL, NULL, NULL, 500, 0, 'pending', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `sync_queue`
--

CREATE TABLE `sync_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sync_queue`
--

INSERT INTO `sync_queue` (`id`, `payload`, `created_at`) VALUES
(1, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:51:30.061Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:51:30\",\"client_local_ts\":\"2026-02-15 21:51:30\",\"host\":\"localhost\"}', '2026-02-15 21:51:35'),
(2, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:56:15.066Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:56:15\",\"client_local_ts\":\"2026-02-15 21:56:15\",\"host\":\"localhost\"}', '2026-02-15 21:56:20'),
(3, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:59:45.054Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:59:45\",\"client_local_ts\":\"2026-02-15 21:59:45\",\"host\":\"localhost\"}', '2026-02-15 21:59:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `password_changed_at` datetime DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('ojt','hr_head','hr_staff','office_head') NOT NULL,
  `office_name` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','approved','ongoing','completed','evaluated','deactivated') DEFAULT 'active',
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `endorsement_printed` tinyint(1) NOT NULL DEFAULT 0,
  `synced` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `first_name`, `middle_name`, `last_name`, `avatar`, `force_password_change`, `password_changed_at`, `password`, `role`, `office_name`, `status`, `date_created`, `endorsement_printed`, `synced`) VALUES
(5, 'hrhead', 'cecilia@gmail.com', 'Cecilia', '', 'Ramos', '../uploads/avatars/user_5_1770536678.png', 0, NULL, '$2y$10$SH3vw4WoYc6KrcSC2J0Qi.ht4DnCduuwl0yzAJ69xovLkNiGJg6G6', 'hr_head', NULL, 'active', '2025-10-12 13:34:28', 0, 1),
(6, 'hrstaff', 'santiagojasminem@gmail.com', 'Andrea', NULL, 'Lopez', NULL, 0, NULL, '$2y$10$i2FWwB33v57nz8d6dSBLt.oLE7zkuU/c27MN9FG3YAlKTnxksQ4VG', 'hr_staff', NULL, 'active', '2025-10-12 13:34:28', 0, 1),
(27, 'headbudget', 'layla@gmail.com', 'Layla', '', 'Garcia', NULL, 0, NULL, '$2y$10$QWidsmwj2042wtG/QwqppeiTglq1fmFqoL8JejgJtu6n8svNrdNf6', 'office_head', 'City Budget Office', 'active', '2025-11-07 09:58:55', 0, 1),
(29, 'cbass610', 'santiagojasminem0@gmail.com', 'Charles', NULL, 'Bass', NULL, 0, NULL, '$2y$10$yq1airn1Bj28mFpXU1hnoO.yDPqGh/LRaBUE0aDIc8Jw5MWeyajhq', 'office_head', 'City Accounting Office', 'active', '2025-11-08 07:58:24', 0, 1),
(50, 'jdiamante370', 'jenny.robles1@bpc.edu.ph', 'Jimwell', NULL, 'Diamante', NULL, 0, NULL, '$2y$10$lOwQ5A6gsVZAL.nPKgU1BemHcV5JLLN57PT5OOXgeZrlRjrKeTjQa', 'office_head', 'City Admin Office', 'active', '2025-11-17 01:56:34', 0, 1),
(80, 'santiagojasminem', 'santiagojasminem01@gmail.com', 'Elisha', NULL, 'Lumanlan', NULL, 0, NULL, '$2y$10$zsMGuHusyuVSZmgcZAizYOJUbak3nOsyKPEtnCTkk515c3bzPtu86', 'ojt', NULL, 'ongoing', '2025-10-18 12:55:25', 1, 1),
(81, 'santiagojasminem1', NULL, '', NULL, '', NULL, 0, NULL, '$2y$10$YIh5Evbp5YY2Yb.BO2swj.Vz.2kZKNp2LXY85Mouxfd7/FumwAQb.', 'ojt', NULL, 'approved', '2026-02-10 02:25:16', 1, 1),
(82, 'jasmine.santiago', NULL, '', NULL, '', NULL, 0, NULL, '30d928f278', 'ojt', NULL, 'approved', '2026-02-10 19:21:22', 0, 1),
(83, 'deserie.robles', NULL, '', NULL, '', NULL, 0, NULL, '$2y$10$56VQ7kNNyaCRR1eeclCglO1KZgf3kAYEFx.DcKX5C0n4wbT60kmQ.', 'ojt', NULL, 'approved', '2026-02-10 19:22:07', 0, 1),
(84, 'narchibald845', NULL, 'Nate', NULL, 'Archibald', NULL, 0, NULL, '@3*cS6yX3A', 'office_head', NULL, 'active', '2026-02-10 19:25:32', 0, 1),
(85, 'jrobles855', NULL, 'Jen', NULL, 'Robles', NULL, 0, NULL, '$2y$10$xjJbwLzbqfIkoBj5gu3iFu6.QCZ1nfSQt1hryZksL1ABriMzAeOq6', 'hr_staff', NULL, 'active', '2026-02-10 19:25:54', 0, 1),
(86, 'vpaulo1312', NULL, '', NULL, '', NULL, 0, NULL, '$2y$10$QF8ah/Azg76BHVco8AbJ..evRSJdF5R61YnBhPs59SuzHs7mOZNyK', 'ojt', NULL, 'approved', '2026-02-10 19:30:27', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `weekly_journal`
--

CREATE TABLE `weekly_journal` (
  `journal_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `week_coverage` varchar(50) DEFAULT NULL,
  `date_uploaded` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weekly_journal`
--

INSERT INTO `weekly_journal` (`journal_id`, `user_id`, `week_coverage`, `date_uploaded`, `attachment`, `from_date`, `to_date`) VALUES
(14, 104, 'Week 1 (2026-02-09|2026-02-13)', '2026-02-14', 'uploads/journals/1771074715_ae1357823538_WEEKLY_JOURNAL_SAMPLE.docx', '2026-02-09', '2026-02-13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `ux_course_code` (`course_code`);

--
-- Indexes for table `dtr`
--
ALTER TABLE `dtr`
  ADD PRIMARY KEY (`dtr_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`eval_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_evaluations_user_id` (`user_id`);

--
-- Indexes for table `face_templates`
--
ALTER TABLE `face_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `intern_stories`
--
ALTER TABLE `intern_stories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `late_dtr`
--
ALTER TABLE `late_dtr`
  ADD PRIMARY KEY (`late_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `moa`
--
ALTER TABLE `moa`
  ADD PRIMARY KEY (`moa_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_users`
--
ALTER TABLE `notification_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_id` (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`office_id`);

--
-- Indexes for table `office_courses`
--
ALTER TABLE `office_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_office_course` (`office_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `office_requests`
--
ALTER TABLE `office_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `office_preference1` (`office_preference1`),
  ADD KEY `office_preference2` (`office_preference2`);

--
-- Indexes for table `orientation_assignments`
--
ALTER TABLE `orientation_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_session_application` (`session_id`,`application_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_application_id` (`application_id`);

--
-- Indexes for table `orientation_sessions`
--
ALTER TABLE `orientation_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `ux_session_date_time_loc` (`session_date`,`session_time`,`location`),
  ADD KEY `idx_session_date` (`session_date`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sync_queue`
--
ALTER TABLE `sync_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  ADD PRIMARY KEY (`journal_id`),
  ADD KEY `student_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `dtr`
--
ALTER TABLE `dtr`
  MODIFY `dtr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=255;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `face_templates`
--
ALTER TABLE `face_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `intern_stories`
--
ALTER TABLE `intern_stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `late_dtr`
--
ALTER TABLE `late_dtr`
  MODIFY `late_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `moa`
--
ALTER TABLE `moa`
  MODIFY `moa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notification_users`
--
ALTER TABLE `notification_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `office_courses`
--
ALTER TABLE `office_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `office_requests`
--
ALTER TABLE `office_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `orientation_assignments`
--
ALTER TABLE `orientation_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `orientation_sessions`
--
ALTER TABLE `orientation_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `sync_queue`
--
ALTER TABLE `sync_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dtr`
--
ALTER TABLE `dtr`
  ADD CONSTRAINT `dtr_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `fk_evaluations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `late_dtr`
--
ALTER TABLE `late_dtr`
  ADD CONSTRAINT `late_dtr_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`);

--
-- Constraints for table `notification_users`
--
ALTER TABLE `notification_users`
  ADD CONSTRAINT `fk_notification_users_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notification_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `office_courses`
--
ALTER TABLE `office_courses`
  ADD CONSTRAINT `office_courses_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `office_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `office_requests`
--
ALTER TABLE `office_requests`
  ADD CONSTRAINT `office_requests_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`);

--
-- Constraints for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  ADD CONSTRAINT `ojt_applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `ojt_applications_ibfk_2` FOREIGN KEY (`office_preference1`) REFERENCES `offices` (`office_id`),
  ADD CONSTRAINT `ojt_applications_ibfk_3` FOREIGN KEY (`office_preference2`) REFERENCES `offices` (`office_id`);

--
-- Constraints for table `orientation_assignments`
--
ALTER TABLE `orientation_assignments`
  ADD CONSTRAINT `fk_orientation_application` FOREIGN KEY (`application_id`) REFERENCES `ojt_applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orientation_session` FOREIGN KEY (`session_id`) REFERENCES `orientation_sessions` (`session_id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  ADD CONSTRAINT `weekly_journal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `students` (`student_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
