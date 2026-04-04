-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 04, 2026 at 11:16 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`) VALUES
(2, NULL, 'Bachelor of Science in Office Administration'),
(3, NULL, 'Bachelor of Science in Accountancy'),
(5, NULL, 'Bachelor of Science in Accounting Information System'),
(6, NULL, 'BSBA Major in Financial Management'),
(18, NULL, 'Bachelor of Science in Information Systems'),
(19, NULL, 'Bachelor of Science in Computer Science');

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
  `minutes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dtr`
--

INSERT INTO `dtr` (`dtr_id`, `student_id`, `log_date`, `am_in`, `am_out`, `pm_in`, `pm_out`, `hours`, `minutes`) VALUES
(240, 80, '2026-02-26', '08:00', '12:00', '1:00', '5:00', 8, 0),
(241, 80, '2026-02-27', '08:00', '12:00', '1:00', '5:00', 8, 0),
(242, 80, '2026-03-03', '6:00', '10:54', '4:05', '5:58', 6, 47),
(245, 82, '2026-03-09', '06:52', '11:52', '1:00', '3:30', 7, 30),
(246, 82, '2026-03-10', '07:10', '12:00', '1:05', '3:35', 7, 20),
(247, 82, '2026-03-11', '07:00', '11:45', '1:00', '3:45', 7, 30),
(248, 82, '2026-03-12', '06:58', '11:58', '1:10', '3:40', 7, 30),
(249, 82, '2026-03-13', '07:15', '12:00', '1:00', '4:00', 7, 45),
(250, 89, '2026-03-16', '07:05', '12:00', '1:00', '3:35', 7, 30),
(251, 89, '2026-03-17', '06:55', '11:50', '1:05', '3:40', 7, 30),
(268, 81, '2026-03-28', NULL, NULL, '21:39', '22:13', 0, 34),
(269, 93, '2026-03-28', NULL, NULL, '22:12', NULL, 0, 0),
(276, 81, '2026-04-02', NULL, NULL, '16:37', NULL, 0, 0);

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
  `rating_desc` varchar(64) DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `improvement` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `hiring` enum('Yes','Maybe','No') DEFAULT NULL,
  `cert_serial` varchar(9) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`eval_id`, `student_id`, `rating`, `feedback`, `school_eval`, `date_evaluated`, `user_id`, `rating_desc`, `strengths`, `improvement`, `comments`, `hiring`, `cert_serial`) VALUES
(19, 104, 4.67, 'Strengths: time management\n\nAreas for improvement: accuracy\n\nOther comments: very good\n\nHire decision: Yes', '95', '2026-04-01', 27, '4.67 | Outstanding', 'time management', 'accuracy', 'very good', 'Yes', '2026-0001');

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
-- Table structure for table `evaluation_questions`
--

CREATE TABLE `evaluation_questions` (
  `question_key` varchar(32) NOT NULL,
  `category` varchar(32) NOT NULL,
  `qtext` text NOT NULL,
  `max_score` tinyint(4) NOT NULL DEFAULT 5,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_questions`
--

INSERT INTO `evaluation_questions` (`question_key`, `category`, `qtext`, `max_score`, `sort_order`) VALUES
('c1', 'Competency', 'Application of Knowledge: Applies academic theories to practical work.', 5, 1),
('c2', 'Competency', 'Quality of Work: Produces accurate, thorough, and neat work.', 5, 2),
('c3', 'Competency', 'Job-Specific Skills: Performs tasks specific to the role effectively.', 5, 3),
('c4', 'Competency', 'Quantity of Work: Completes satisfactory volume of work on time.', 5, 4),
('c5', 'Competency', 'Learning & Adaptability: Learns new tasks, procedures, and systems quickly.', 5, 5),
('s1', 'Skill', 'Communication (Oral & Written): Expresses ideas clearly, professionally, and actively listens to others.', 5, 101),
('s2', 'Skill', 'Teamwork & Collaboration: Works cooperatively with supervisors and colleagues; contributes positively to the team.', 5, 102),
('s3', 'Skill', 'Problem-Solving: Analyzes situations, identifies problems, and suggests logical solutions.', 5, 103),
('s4', 'Skill', 'Critical Thinking: Gathers and evaluates information to make sound judgments.', 5, 104),
('s5', 'Skill', 'Initiative & Resourcefulness: Seeks new responsibilities, asks relevant questions, and works independently when appropriate.', 5, 105),
('t1', 'Trait', 'Punctuality & Attendance: Adheres to the agreed-upon work schedule and informs the supervisor of any absences.', 5, 201),
('t2', 'Trait', 'Professional Conduct: Observes company policies, follows instructions, and maintains confidentiality.', 5, 202),
('t3', 'Trait', 'Attitude & Receptiveness: Maintains a positive attitude and accepts constructive feedback gracefully.', 5, 203),
('t4', 'Trait', 'Time Management: Prioritizes tasks effectively to manage workload.', 5, 204),
('t5', 'Trait', 'Professional Appearance: Adheres to the company\'s dress code and grooming standards.', 5, 205);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_responses`
--

CREATE TABLE `evaluation_responses` (
  `id` int(11) NOT NULL,
  `eval_id` int(11) NOT NULL,
  `question_key` varchar(128) NOT NULL,
  `question_order` int(11) DEFAULT NULL,
  `score` tinyint(4) NOT NULL CHECK (`score` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_responses`
--

INSERT INTO `evaluation_responses` (`id`, `eval_id`, `question_key`, `question_order`, `score`) VALUES
(121, 19, 'c1', 1, 5),
(122, 19, 'c2', 2, 5),
(123, 19, 'c3', 3, 5),
(124, 19, 'c4', 4, 5),
(125, 19, 'c5', 5, 5),
(126, 19, 's1', 1, 4),
(127, 19, 's2', 2, 4),
(128, 19, 's3', 3, 4),
(129, 19, 's4', 4, 4),
(130, 19, 's5', 5, 4),
(131, 19, 't1', 1, 5),
(132, 19, 't2', 2, 5),
(133, 19, 't3', 3, 5),
(134, 19, 't4', 4, 5),
(135, 19, 't5', 5, 5);

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

--
-- Dumping data for table `moa`
--

INSERT INTO `moa` (`moa_id`, `school_name`, `moa_file`, `date_signed`, `valid_until`) VALUES
(22, 'Bulacan Polytechnic College', 'uploads/moa/moa_1773843918.pdf', '2026-03-18', '2026-05-18'),
(23, 'Bulacan State University', 'uploads/moa/moa_1774420737.pdf', '2026-03-25', '2026-04-25');

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
(49, 'New applicant: Elisha Lumanlan – City Accounting Office / City Budget Office', '2026-03-25 14:36:54'),
(50, 'Application Approved: Elisha Lumanlan has been approved for City Accounting Office. Orientation is scheduled on March 26, 2026 8:30 A.M..', '2026-03-25 14:37:46'),
(51, 'MOA Uploaded: The MOA with Bulacan State University has been uploaded. Valid until April 25, 2026.', '2026-03-25 14:38:57'),
(52, 'Evaluation Submitted: The performance evaluation for Blair Waldorf has been submitted.', '2026-03-25 15:09:07'),
(53, 'Evaluation Submitted: Your performance evaluation has been submitted.', '2026-03-25 15:09:07'),
(54, 'Evaluation Submitted: The performance evaluation for Blair Waldorf has been submitted.', '2026-03-25 15:12:10'),
(55, 'Evaluation Submitted: Your performance evaluation has been submitted.', '2026-03-25 15:12:10'),
(56, 'Evaluation Submitted: The performance evaluation for Blair Waldorf has been submitted.', '2026-03-30 16:27:29'),
(57, 'Evaluation Submitted: Your performance evaluation has been submitted.', '2026-03-30 16:27:29'),
(58, 'Evaluation Submitted: The performance evaluation for Blair Waldorf has been submitted.', '2026-04-01 14:21:21'),
(59, 'Evaluation Submitted: Your performance evaluation has been submitted.', '2026-04-01 14:21:21'),
(60, 'New applicant: Jenny Robles – City Accounting Office', '2026-04-02 14:39:17'),
(61, 'Application Approved: Jenny Robles has been approved for City Accounting Office. Orientation is scheduled on April 3, 2026 8:30 A.M..', '2026-04-02 14:55:01'),
(62, 'MOA Uploaded: The MOA with STI College – Malolos has been uploaded. Valid until May 2, 2026.', '2026-04-02 15:15:37'),
(63, 'New applicant: Adriel Lumanlan – City Accounting Office / City Budget Office', '2026-04-02 16:05:12'),
(64, 'Application Approved: Adriel Lumanlan has been approved for City Accounting Office. Orientation is scheduled on April 3, 2026 8:30 A.M..', '2026-04-02 16:05:37');

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
(154, 49, 5, 1),
(155, 49, 6, 1),
(156, 49, 85, 0),
(157, 49, 29, 0),
(158, 49, 27, 0),
(159, 50, 6, 1),
(160, 50, 85, 0),
(161, 50, 29, 0),
(162, 51, 6, 1),
(163, 51, 85, 0),
(164, 51, 80, 0),
(165, 51, 89, 0),
(166, 51, 27, 0),
(167, 52, 5, 1),
(168, 52, 6, 1),
(169, 52, 85, 0),
(170, 53, 80, 0),
(171, 54, 5, 1),
(172, 54, 6, 1),
(173, 54, 85, 0),
(174, 55, 80, 0),
(175, 56, 5, 1),
(176, 56, 6, 1),
(177, 56, 85, 0),
(178, 57, 80, 0),
(179, 58, 5, 1),
(180, 58, 6, 1),
(181, 58, 85, 0),
(182, 59, 80, 0),
(183, 60, 5, 0),
(184, 60, 6, 0),
(185, 60, 85, 0),
(186, 60, 29, 0),
(187, 61, 6, 0),
(188, 61, 85, 0),
(189, 61, 29, 0),
(190, 62, 6, 0),
(191, 62, 85, 0),
(192, 63, 5, 0),
(193, 63, 6, 0),
(194, 63, 85, 0),
(195, 63, 29, 0),
(196, 63, 27, 0),
(197, 64, 6, 0),
(198, 64, 85, 0),
(199, 64, 29, 0);

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
(8, 'City Budget Office', 11, 11, NULL, '', 'Approved'),
(9, 'City Accounting Office', 4, 4, NULL, '', 'Approved'),
(14, 'City Admin Office', 2, 0, NULL, NULL, 'Approved'),
(22, 'Information Technology Office', 4, 0, NULL, NULL, 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `office_courses`
--

CREATE TABLE `office_courses` (
  `id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `office_courses`
--

INSERT INTO `office_courses` (`id`, `office_id`, `course_id`) VALUES
(5, 8, 3),
(6, 8, 5),
(7, 8, 6),
(8, 9, 3),
(9, 9, 5),
(16, 14, 2),
(29, 22, 18),
(30, 22, 19);

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
(35, 9, NULL, 6, 'increased workload', 'approved', '2026-01-15', '2026-03-16 12:20:09'),
(39, 8, 3, 5, '', 'approved', '2026-02-10', '2026-02-10 10:16:49'),
(40, 8, 5, 6, '', 'approved', '2026-02-11', '2026-02-11 03:50:05'),
(41, 8, 6, 10, '', 'approved', '2026-02-11', '2026-02-11 03:51:44'),
(42, 9, 1, 4, '', 'approved', '2026-03-16', '2026-03-16 12:20:09'),
(43, 8, 10, 11, '', 'approved', '2026-03-25', '2026-03-25 07:07:12');

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
(81, 100, 9, 8, 'uploads/1770705776_Letter_of_Intent.pdf', 'uploads/1770705776_Letter_of_Endorsement.pdf', 'uploads/1770705776_Resume.pdf', '', 'uploads/1770705776_121f7f50bd34f5355dcba6953ea6484e.jpg', 'pending', NULL, '2026-02-10', NULL),
(82, 101, 14, NULL, 'uploads/1770705947_Letter_of_Intent.pdf', 'uploads/1770705947_Letter_of_Endorsement.pdf', 'uploads/1770705947_Resume.pdf', '', 'uploads/1770705947_2bc78ada6efb0115b1a7c31755e2350f.jpg', 'rejected', 'incorrect requirements', '2026-02-10', '2026-03-16'),
(83, 102, 8, NULL, 'uploads/1770706123_Letter_of_Intent.pdf', 'uploads/1770706123_Letter_of_Endorsement.pdf', 'uploads/1770706123_Resume.pdf', 'uploads/1770706123_Memorandum_of_Agreement.pdf', 'uploads/1770706123_0582a225589e3d81723e739625be15e7.jpg', 'pending', NULL, '2026-02-10', NULL),
(85, 104, 9, 8, 'uploads/1770718018_LETTER_OF_INTENT.pdf', 'uploads/1770718018_Endorsementlettersample.pdf', 'uploads/1770718018_Resumesample.pdf', '', 'uploads/1770718018_Screenshot_2026-02-08_160240.png', 'evaluated', 'Orientation/Start: February 11, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-02-10', '2026-04-01'),
(86, 105, 8, NULL, 'uploads/1770719104_LETTER_OF_INTENT.pdf', 'uploads/1770719104_Endorsementlettersample.pdf', 'uploads/1770719104_Resumesample.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770719104_72556f0f-31ec-44ff-a679-c52befb7aae2.jpg', 'approved', 'Orientation/Start: February 11, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-02-10', '2026-02-10'),
(87, 106, 8, NULL, 'uploads/1770779056_CT-WA213_Web_Application.pdf', 'uploads/1770779056_pdfcoffee.com_a-practical-guide-to-information-systems-strategic-planning-2nd-edition-pdf-free.pdf', 'uploads/1770779056_CT-WA213_Web_Application.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770779056_RobloxScreenShot20250620_075847493.png', 'pending', NULL, '2026-02-11', NULL),
(88, 107, 9, 8, 'uploads/1770779987_LETTER_OF_INTENT.pdf', 'uploads/1770779987_Endorsementlettersample.pdf', 'uploads/1770779987_Resumesample.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/1770779987_Screenshot_2026-02-08_160240.png', 'approved', 'Orientation/Start: February 12, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-02-11', '2026-02-11'),
(91, 110, 8, NULL, 'uploads/LETTER_OF_INTENT.pdf', 'uploads/Endorsementlettersample.pdf', 'uploads/Resumesample.pdf', 'uploads/moa/moa_1770699674.pdf', 'uploads/3f3634ba-cf61-4167-ae97-ae03fb0908f7.jpg', 'pending', NULL, '2026-03-04', NULL),
(92, 111, 9, 8, 'uploads/1773663846_LETTER_OF_INTENT.pdf', 'uploads/1773663846_Endorsementlettersample.pdf', 'uploads/1773663846_Resumesample.pdf', 'uploads/1773663846_moa.pdf', 'uploads/1773663846_b5ac1230-3a5f-47d0-99df-3fd19ef1811f.jpg', 'approved', 'Orientation/Start: March 17, 2026 9:30 A.M. | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-03-16', '2026-03-16'),
(94, 113, 8, NULL, 'uploads/1773842857_LETTER_OF_INTENT.pdf', 'uploads/1773842857_Endorsementlettersample.pdf', 'uploads/1773842857_Resumesample.pdf', 'uploads/moa/moa_1773838602.pdf', 'uploads/1773842857_ab23f6f1-98ed-41e1-96d8-55d6d337fb67.jpg', 'approved', 'Orientation/Start: March 19, 2026 8:30 A.M. | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-03-18', '2026-03-18'),
(95, 114, 9, 8, 'uploads/1774420614_LETTER_OF_INTENT.pdf', 'uploads/1774420614_Endorsementlettersample.pdf', 'uploads/1774420614_Resumesample.pdf', 'uploads/moa/moa_1773843918.pdf', 'uploads/1774420614_3f3634ba-cf61-4167-ae97-ae03fb0908f7.jpg', 'approved', 'Orientation/Start: March 26, 2026 8:30 A.M. | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-03-25', '2026-03-25'),
(96, 115, 9, NULL, 'r2://attachments/1775111956_5048699c2379_LETTER_OF_INTENT.pdf', 'r2://attachments/1775111956_570c36abae97_Endorsementlettersample.pdf', 'r2://attachments/1775111956_10d6388e7519_Resumesample.pdf', 'uploads/moa/moa_1774420737.pdf', 'r2://attachments/1775111955_c8fc50ca9c71_Screenshot_2026-02-08_160240.png', 'approved', 'Orientation/Start: April 3, 2026 8:30 A.M. | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-04-02', '2026-04-02'),
(97, 116, 9, 8, 'r2://attachments/1775117111_55886bc46aea_LETTER_OF_INTENT.pdf', 'r2://attachments/1775117111_df5ba85f9ff7_Endorsementlettersample.pdf', 'r2://attachments/1775117111_5514f6f15fe9_Resumesample.pdf', 'uploads/moa/moa_1774420737.pdf', 'r2://attachments/1775117110_368ce9076be8_c3b2a8a9-78db-49f6-affb-b60abad0b75d.jpg', 'approved', 'Orientation/Start: April 3, 2026 8:30 A.M. | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-04-02', '2026-04-02');

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

--
-- Dumping data for table `orientation_assignments`
--

INSERT INTO `orientation_assignments` (`id`, `session_id`, `application_id`, `assigned_at`, `resched_from`) VALUES
(37, 16, 85, '2026-02-10 10:07:34', NULL),
(38, 17, 86, '2026-02-08 10:25:16', NULL),
(39, 18, 88, '2026-02-11 03:21:22', NULL),
(42, 19, 92, '2026-03-16 12:26:10', NULL),
(45, 21, 94, '2026-03-18 14:10:46', '2026-03-19'),
(46, 22, 95, '2026-03-25 06:37:41', NULL),
(47, 23, 96, '2026-04-02 06:54:57', NULL),
(48, 23, 97, '2026-04-02 08:05:33', NULL);

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
(16, '2026-02-11', '08:30:00', 'CHRMO/3rd Floor', NULL),
(17, '2026-03-25', '08:30:00', 'CHRMO/3rd Floor', NULL),
(18, '2026-02-12', '08:30:00', 'CHRMO/3rd Floor', NULL),
(19, '2026-03-17', '09:30:00', 'CHRMO/3rd Floor', NULL),
(21, '2026-03-24', '08:30:00', 'CHRMO/3rd Floor', '2026-03-19'),
(22, '2026-03-26', '08:30:00', 'CHRMO/3rd Floor', NULL),
(23, '2026-04-03', '08:30:00', 'CHRMO/3rd Floor', NULL);

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
(13, 6, 'santiagojasminem@gmail.com', '677491', '2026-02-10 07:12:31', 1, '2026-02-10 07:09:31'),
(14, 6, 'santiagojasminem@gmail.com', '492103', '2026-02-10 07:14:03', 1, '2026-02-10 07:11:03'),
(15, 54, 'santiagojasminem@gmail.com', '425108', '2026-02-10 09:32:59', 1, '2026-02-10 09:29:59'),
(16, 27, 'santiagojasminem@gmail.com', '593652', '2026-02-10 09:36:11', 1, '2026-02-10 09:33:11'),
(17, 29, 'santiagojasminem@gmail.com', '695750', '2026-02-10 09:54:52', 1, '2026-02-10 09:51:52'),
(18, 50, 'santiagojasminem@gmail.com', '584404', '2026-02-10 09:56:56', 1, '2026-02-10 09:53:56'),
(19, 81, 'santiagojasminem@gmail.com', '900098', '2026-02-10 10:31:10', 1, '2026-02-10 10:28:10'),
(20, 89, 'jasmine.santiago@bpc.edu.ph', '578496', '2026-03-16 12:38:25', 1, '2026-03-16 12:35:25'),
(21, 82, 'jasmine.santiago@bpc.edu.ph', '740289', '2026-03-18 12:55:21', 1, '2026-03-18 12:52:21');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `school_id` int(11) NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `offers_5th_year` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`school_id`, `school_name`, `offers_5th_year`) VALUES
(1, 'ABE International Business College', 0),
(2, 'ACLC College', 0),
(3, 'AMA Computer Center', 0),
(4, 'Bulacan Agricultural State College', 0),
(7, 'Bulacan Polytechnic College', 0),
(8, 'Bulacan State University', 1),
(9, 'Centro Escolar University', 1),
(10, 'College of Our Lady of Mercy', 0),
(11, 'Dr. Yanga\'s Colleges, Inc.', 1),
(12, 'Immaculate Conception International College of Arts and Technology', 0),
(13, 'La Consolacion University Philippines', 0),
(14, 'Meycauayan College', 0),
(15, 'Norzagaray College', 0),
(16, 'Pambayang Dalubhasaan ng Marilao', 0),
(17, 'Polytechnic College of the City of Meycauayan', 0),
(18, 'St. Mary\'s College of Meycauayan', 0),
(19, 'STI College', 0);

-- --------------------------------------------------------

--
-- Table structure for table `school_courses`
--

CREATE TABLE `school_courses` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `offers_5th_year` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_courses`
--

INSERT INTO `school_courses` (`id`, `school_id`, `course_id`, `course_name`, `offers_5th_year`) VALUES
(1, 1, NULL, 'Bachelor of Science in Hospitality Management', 0),
(2, 1, NULL, 'Bachelor of Science in Tourism', 0),
(3, 1, NULL, 'Bachelor of Science in Tourism Management', 0),
(4, 1, NULL, 'Bachelor of Science in Business Administration', 0),
(5, 1, 3, NULL, 0),
(6, 1, NULL, 'Bachelor of Science in Information Management', 0),
(7, 1, NULL, 'Bachelor of Science in Information Technology', 0),
(8, 1, 19, 'Bachelor of Science in Computer Science', 0),
(9, 1, NULL, 'Diploma in Hotel and Restaurant Services', 0),
(10, 1, NULL, 'Diploma in Tourism Management', 0),
(11, 1, NULL, 'Diploma in Financial Accounting', 0),
(12, 1, NULL, 'Diploma in Business Economics', 0),
(13, 1, NULL, 'Diploma in Business Management', 0),
(14, 1, NULL, 'Programming NC IV', 0),
(15, 1, NULL, 'Commecial Cooking NC II', 0),
(16, 1, NULL, 'Food & Beverage Services NC IV', 0),
(17, 1, NULL, 'Housekeeping NC II', 0),
(18, 1, NULL, 'Bartending NC II', 0),
(19, 1, NULL, 'Travel Services NC II', 0),
(20, 1, NULL, 'Tour Guiding NC II', 0),
(21, 1, NULL, 'Front Office Services NC II', 0),
(22, 1, NULL, 'Housekeeping NC II', 0),
(23, 1, NULL, 'Food and Beverage Services NC II', 0),
(24, 1, NULL, 'English Proficiency', 0),
(25, 1, NULL, 'Finishing Course for Call Center', 0),
(26, 1, NULL, 'Associate in Computer Technology', 0),
(27, 1, NULL, 'Medical Transcription', 0),
(28, 1, NULL, 'Practical Nursing', 0),
(29, 1, NULL, 'Healthcare Services', 0),
(30, 1, NULL, 'Caregiving', 0),
(31, 2, 19, 'Bachelor of Science in Computer Science', 0),
(32, 2, NULL, 'Bachelor of Science in Entrepreneurship', 0),
(33, 2, 18, 'Bachelor of Science in Information Systems', 0),
(34, 2, NULL, 'Associate in Computer Technology', 0),
(35, 3, NULL, 'Bachelor of Science in Information Technology', 0),
(36, 3, 19, 'Bachelor of Science in Computer Science', 0),
(37, 3, NULL, 'Bachelor of Science in Computer Engineering', 1),
(38, 3, NULL, 'Bachelor of Science in Electronics Engineering', 1),
(39, 3, NULL, 'Bachelor of Science in Business Ad', 0),
(40, 3, 3, NULL, 0),
(41, 4, NULL, 'Doctor of Veterinary Medicine', 1),
(42, 4, NULL, 'Bachelor of Science in Agriculture', 0),
(43, 4, NULL, 'Bachelor of Science in Agroforestry', 0),
(44, 4, NULL, 'Bachelor of Science in Agribusiness', 0),
(45, 4, NULL, 'Bachelor of Science in Agricultural and Biosystems Engineering', 1),
(46, 4, NULL, 'Bachelor of Science in Geodetic Engineering', 1),
(47, 4, NULL, 'Bachelor of Science in Food Technology', 0),
(48, 4, NULL, 'Bachelor of Science in Information Technology', 0),
(49, 4, NULL, 'Bachelor of Science in Business Administration', 0),
(50, 4, NULL, 'Bachelor of Science in Hospitality Management', 0),
(51, 4, NULL, 'Bachelor of Elementary Education', 0),
(52, 4, NULL, 'Bachelor of Secondary Education', 0),
(53, 4, NULL, 'Bachelor of Science in Development Communication', 0),
(54, 4, NULL, 'Bachelor of Science in Agroforestry', 0),
(55, 4, NULL, 'Bachelor of Elementary Education', 0),
(56, 4, NULL, 'Doctor of Veterinary Medicine', 1),
(57, 4, NULL, 'Bachelor of Science in Agricultural and Biosystems Engineering', 1),
(170, 7, NULL, 'Bachelor of Science in Office Management', 0),
(171, 7, 18, 'Bachelor of Science in Information Systems', 0),
(172, 7, 5, NULL, 0),
(173, 7, NULL, 'Bachelor in Technical-Vocational Teacher Education', 0),
(174, 7, NULL, 'Computer Secretarial', 0),
(175, 7, NULL, 'Hotel and Restaurant Services', 0),
(176, 7, NULL, 'Associate in Computer Technology', 0),
(177, 7, NULL, 'Bachelor of Science in Customs', 0),
(178, 7, NULL, 'Administration', 0),
(179, 7, NULL, 'Diploma in Hotel and Restaurant Management Technology', 0),
(180, 7, NULL, 'Hotel and Restaurant Services', 0),
(181, 7, NULL, 'Contact Center Services', 0),
(182, 7, NULL, 'Bookkeeping NCIII', 0),
(183, 7, NULL, 'Electrical Installation & Maintenance NCII', 0),
(184, 7, NULL, 'Shield Metal Arc Welding NCI', 0),
(185, 7, NULL, 'NCII', 0),
(186, 8, NULL, 'Bachelor of Fine Arts Major in Visual Communication', 0),
(187, 8, NULL, 'Bachelor of Landscape Architecture', 1),
(188, 8, NULL, 'Bachelor of Science in Architecture', 1),
(189, 8, NULL, 'Bachelor of Arts in Broadcasting', 0),
(190, 8, NULL, 'Bachelor of Arts in Journalism', 0),
(191, 8, NULL, 'Bachelor of Performing Arts (Theater Track)', 0),
(192, 8, NULL, 'Batsilyer ng Sining sa Malikhaing Pagsulat', 0),
(193, 8, 3, NULL, 0),
(194, 8, NULL, 'Bachelor of Science in Business Administration', 0),
(195, 8, NULL, 'Bachelor of Science in Entrepreneurship', 0),
(196, 8, NULL, 'Bachelor of Arts in Legal Management', 0),
(197, 8, NULL, 'Bachelor of Science in Criminology', 0),
(198, 8, NULL, 'Bachelor of Science in Hospitality Management', 0),
(199, 8, NULL, 'Bachelor of Science in Tourism Management', 0),
(200, 8, NULL, 'Bachelor of Library and Information Science', 0),
(201, 8, 18, 'Bachelor of Science in Information Systems', 0),
(202, 8, NULL, 'Bachelor of Science in Information Technology', 0),
(203, 8, NULL, 'Bachelor of Industrial Technology', 0),
(204, 8, NULL, 'Juris Doctor', 1),
(205, 8, NULL, 'Bachelor of Science in Nursing', 0),
(206, 8, NULL, 'Bachelor of Science in Civil Engineering', 1),
(207, 8, NULL, 'Bachelor of Science in Computer Engineering', 1),
(208, 8, NULL, 'Bachelor of Science in Electrical Engineering', 1),
(209, 8, NULL, 'Bachelor of Science in Electronics Engineering', 1),
(210, 8, NULL, 'Bachelor of Science in Industrial Engineering', 1),
(211, 8, NULL, 'Bachelor of Science in Manufacturing Engineering', 1),
(212, 8, NULL, 'Bachelor of Science in Mechanical Engineering', 1),
(213, 8, NULL, 'Bachelor of Science in Mechatronics Engineering', 1),
(214, 8, NULL, 'Bachelor of Early Childhood Education', 0),
(215, 8, NULL, 'Bachelor of Elementary Education', 0),
(216, 8, NULL, 'Bachelor of Physical Education', 0),
(217, 8, NULL, 'Bachelor of Secondary Education', 0),
(218, 8, NULL, 'Bachelor of Technology and Livelihood Education', 0),
(219, 8, NULL, 'Bachelor of Science in Environmental Science', 0),
(220, 8, NULL, 'Bachelor of Science in Food Technology', 0),
(221, 8, NULL, 'Bachelor of Science in Math', 0),
(222, 8, NULL, 'Bachelor of Science in Exercise and Sports Sciences with Specialization in Fitness and Sports Coaching', 0),
(223, 8, NULL, 'Bachelor of Science in Exercise and Sports Sciences with Specialization in Fitness and Sports Management', 0),
(224, 8, NULL, 'Certificate of Physical Education', 0),
(225, 8, NULL, 'Bachelor of Public Administration', 0),
(226, 8, NULL, 'Bachelor of Science in Psychology', 0),
(227, 8, NULL, 'Bachelor of Science in Social Work', 0),
(228, 8, NULL, 'Doctor of Education', 0),
(229, 8, NULL, 'Doctor of Philosophy', 0),
(230, 8, NULL, 'Doctor of Public Administration', 0),
(231, 8, NULL, 'Master in Business Administration', 0),
(232, 8, NULL, 'Master in Physical Education', 0),
(233, 8, NULL, 'Master in Public Administration', 0),
(234, 8, NULL, 'Master of Arts in Education', 0),
(235, 8, NULL, 'Master of Engineering Program', 1),
(236, 8, NULL, 'Master of Industrial Technology Management', 0),
(237, 8, NULL, 'Master of Information Technology', 0),
(238, 8, NULL, 'Master of Manufacturing Engineering', 1),
(239, 8, NULL, 'Master of Science in Civil Engineering', 1),
(240, 8, NULL, 'Master of Science in Computer Engineering', 1),
(241, 8, NULL, 'Master of Science in Electronics and Communications Engineering', 1),
(242, 9, NULL, 'Bachelor of Arts in Communication and Media', 0),
(243, 9, 3, NULL, 0),
(244, 9, NULL, 'Bachelor of Science in Information Technology', 0),
(245, 9, NULL, 'Bachelor of Science in International Hospitality Management', 0),
(246, 9, NULL, 'Bachelor of Science in International Hospitality Management', 0),
(247, 9, NULL, 'Bachelor of Science in International Tourism and Travel Management', 0),
(248, 9, NULL, 'Bachelor of Science in Medical Technology', 0),
(249, 9, NULL, 'Bachelor of Science in Nursing', 0),
(250, 9, NULL, 'Bachelor of Science in Pharmacy', 0),
(251, 9, NULL, 'Bachelor of Science in Psychology', 0),
(252, 9, NULL, 'Bachelor of Science in Psychology', 0),
(253, 9, NULL, 'Doctor of Dental Medicine', 1),
(254, 9, NULL, 'Bachelor of Special Needs Education', 0),
(255, 9, NULL, 'Bachelor of Special Needs Education Specialization in Early Childhood Education', 0),
(256, 9, NULL, 'Bachelor of Science in Business Administration Major in International Management', 0),
(257, 9, NULL, 'Bachelor of Science in Clinical Pharmacy', 0),
(258, 9, NULL, 'Doctor of Optometry', 1),
(259, 10, NULL, 'Bachelor of Science in Criminology', 0),
(260, 10, NULL, 'Bachelor of Science in Radiologic Technology', 0),
(261, 10, 3, NULL, 0),
(262, 10, NULL, 'Bachelor of Science Business Administration', 0),
(263, 10, NULL, 'Bachelor of Science in Entrepreneurship', 0),
(264, 10, NULL, 'Bachelor of Science Accounting Information System', 0),
(265, 10, NULL, 'Bachelor of Science Tourism Management', 0),
(266, 10, NULL, 'Bachelor of Science Hospitality Management', 0),
(267, 10, NULL, 'Bachelor of Science in Information Technology', 0),
(268, 10, 19, 'Bachelor of Science in Computer Science', 0),
(269, 10, NULL, 'Bachelor in Technical-Vocational Teacher Education', 0),
(270, 10, NULL, 'Bachelor in Technology and Livelihood Education', 0),
(271, 11, 3, NULL, 0),
(272, 11, 5, NULL, 0),
(273, 11, NULL, 'Bachelor of Arts in Political Science', 0),
(274, 11, NULL, 'Bachelor of Science in Business Administration', 0),
(275, 11, NULL, 'Bachelor of Science in Business Administration Major in Human Resource Development Management', 0),
(276, 11, NULL, 'Bachelor of Science in Business Administration Major in Financial Management', 0),
(277, 11, NULL, 'Bachelor of Science in Business Administration Major in Operations Management', 0),
(278, 11, NULL, 'Bachelor of Science in Business Administration Major in Marketing Management', 0),
(279, 11, 19, 'Bachelor of Science in Computer Science', 0),
(280, 11, NULL, 'Bachelor of Science in Computer Engineering', 1),
(281, 11, NULL, 'Bachelor of Science in Information Technology', 0),
(282, 11, NULL, 'Associate in Computer Technology', 0),
(283, 11, NULL, 'Bachelor of Elementary Education', 0),
(284, 11, NULL, 'Bachelor of Secondary Education Major in Mathematics', 0),
(285, 11, NULL, 'Bachelor of Secondary Education Major in Filipino', 0),
(286, 11, NULL, 'Bachelor of Secondary Education Major in English', 0),
(287, 11, NULL, 'Bachelor of Secondary Education Major in Sciences', 0),
(288, 11, NULL, 'Continuing Professional Teacher Education', 0),
(289, 11, NULL, 'Bachelor of Science in Nursing', 0),
(290, 11, NULL, 'Bachelor of Science in Midwifery', 0),
(291, 11, NULL, 'Bachelor of Science in Hospitality Management', 0),
(292, 11, NULL, 'Bachelor of Science in Tourism Management', 0),
(293, 11, NULL, 'Bachelor of Science in Marine Transportation', 0),
(294, 11, NULL, 'Bachelor of Science in Marine Engineering', 1),
(295, 11, NULL, 'Bachelor of Science in Mechanical Engineering', 1),
(296, 11, NULL, 'Bachelor of Arts in Psychology', 0),
(297, 12, 3, NULL, 0),
(298, 12, NULL, 'Bachelor of Science in Entrepreneurship', 0),
(299, 12, NULL, 'Bachelor of Science in Tourism Management', 0),
(300, 12, 19, 'Bachelor of Science in Computer Science', 0),
(301, 12, 18, 'Bachelor of Science in Information Systems', 0),
(302, 12, NULL, 'Bachelor of Science in Psychology', 0),
(303, 12, NULL, 'Bachelor of Science in Criminology', 0),
(304, 12, 5, NULL, 0),
(305, 12, NULL, 'Bachelor in Technical-Vocational Teacher Education', 0),
(306, 13, NULL, 'Bachelor of Science in Hospitality Management', 0),
(307, 13, NULL, 'Bachelor of Science in Tourism Management', 0),
(308, 13, 3, NULL, 0),
(309, 13, NULL, 'Bachelor of Science in Business Administration Major in Financial Management', 0),
(310, 13, NULL, 'Bachelor of Science in Business Administration Major in Marketing Management', 0),
(311, 13, NULL, 'Bachelor of Science in Business Administration Major in Human Resource Development Management', 0),
(312, 13, NULL, 'Bachelor of Science in Business Administration Major in Operations Management', 0),
(313, 13, NULL, 'Bachelor of Science in Nursing', 0),
(314, 13, NULL, 'Bachelor of Science in Radiologic Technology', 0),
(315, 13, NULL, 'Bachelor of Science in Medical Technology', 0),
(316, 13, NULL, 'Bachelor of Science in Information Technology', 0),
(317, 13, NULL, 'Bachelor of Science in Computer Engineering', 1),
(318, 13, NULL, 'Bachelor of Science in Industrial Engineering', 1),
(319, 13, NULL, 'Bachelor of Secondary Education Major in English', 0),
(320, 13, NULL, 'Bachelor of Secondary Education Major in Filipino', 0),
(321, 13, NULL, 'Bachelor of Secondary Education Major in Mathematics', 0),
(322, 13, NULL, 'Bachelor of Secondary Education Major in General Science', 0),
(323, 13, NULL, 'Bachelor of Secondary Education Major in Social Studies', 0),
(324, 13, NULL, 'Bachelor of Secondary Education Major in Values Education', 0),
(325, 13, NULL, 'Bachelor of Early Childhood Education', 0),
(326, 13, NULL, 'Bachelor of Social Work', 0),
(327, 13, NULL, 'Bachelor of Arts in Communication Arts', 0),
(328, 13, NULL, 'Bachelor of Arts in Psychology', 0),
(329, 13, NULL, 'Doctor of Medicine', 1),
(330, 14, 3, NULL, 0),
(331, 14, NULL, 'Bachelor of Science in Accounting Information Systems', 0),
(332, 14, NULL, 'Bachelor of Science in Management Accounting', 0),
(333, 14, NULL, 'Bachelor of Science in Legal Management', 0),
(334, 14, NULL, 'Bachelor of Science in Business Administration', 0),
(335, 14, NULL, 'Bachelor of Science in Business Management', 0),
(336, 14, NULL, 'Bachelor of Science in Hospitality Management', 0),
(337, 14, NULL, 'Bachelor of Science in Travel Management', 0),
(338, 14, NULL, 'Bachelor in Elementary Education', 0),
(339, 14, NULL, 'Bachelor of Secondary Education', 0),
(340, 14, NULL, 'Bachelor of Physical Education', 0),
(341, 14, NULL, 'Bachelor of Arts in Psychology', 0),
(342, 14, NULL, 'Bachelor of Science in Criminology', 0),
(343, 15, NULL, 'Bachelor of Science Computer Science', 0),
(344, 15, NULL, 'Associate in Computer Technology', 0),
(345, 15, NULL, 'Bachelor of Science in Hospitality Management', 0),
(346, 15, NULL, 'Bachelor of Secondary Education', 0),
(347, 15, NULL, 'Bachelor of Elementary Education', 0),
(348, 16, NULL, 'Bachelor of Science in Information Technology', 0),
(349, 16, 19, 'Bachelor of Science in Computer Science', 0),
(350, 16, NULL, 'Bachelor of Science in Hospitality Managemenet', 0),
(351, 16, NULL, 'Bachelor of Science in Tourism Management', 0),
(352, 16, 2, NULL, 0),
(353, 16, NULL, 'Bachelor of Early Childhood Education', 0),
(354, 16, NULL, 'Bachelor of Technology and Livelihood Education', 0),
(355, 17, NULL, 'Bachelor of Secondary in Education', 0),
(356, 17, NULL, 'Bachelor of Science in Hospitality Management', 0),
(357, 17, 2, NULL, 0),
(358, 18, 3, NULL, 0),
(359, 18, NULL, 'Bachelor of Science in Business Administration', 0),
(360, 18, NULL, 'Bachelor of Science in Education', 0),
(361, 18, NULL, 'Bachelor of Science in Hospitality and Tourism Management', 0),
(362, 18, NULL, 'Bachelor of Science in Information Technology', 0),
(363, 19, NULL, 'Bachelor of Science in Information Technology', 0),
(364, 19, NULL, 'Bachelor of Science in Computer Engineering', 1),
(365, 19, NULL, 'Bachelor of Science in Business Administration', 0),
(366, 19, 5, NULL, 0),
(367, 19, 3, NULL, 0),
(368, 19, NULL, 'Bachelor of Science in Hospitality Management', 0),
(369, 19, NULL, 'Bachelor of Arts in Communication', 0),
(370, 19, NULL, 'Bachelor of Multimedia Arts', 0),
(371, 19, NULL, 'Bachelor of Science in Tourism Management', 0);

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
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `address`, `contact_number`, `email`, `birthday`, `emergency_name`, `emergency_relation`, `emergency_contact`, `college`, `course`, `year_level`, `school_year`, `semester`, `school_address`, `ojt_adviser`, `adviser_contact`, `total_hours_required`, `hours_rendered`, `status`, `reason`) VALUES
(100, NULL, 'Jean', NULL, 'Mercado', 'Bungahan, Malolos Bulacan', '09269317441', 'jenny.robles@bpc.edu.ph', '2003-11-26', 'Joyce Mercado', 'Sibling', '09987654321', 'Bulacan State University', 'Bachelor of Science in Accountancy', '4', '2025-2026', NULL, 'Bulihan Malolos Bulacan', 'Rhey Santos', '09111111111', 400, 0, 'pending', NULL),
(101, NULL, 'John', NULL, 'Santos', 'Apalit Pampanga', '09222222222', 'roblesjenny0326@gmail.com', '2001-06-14', 'Angelo Santos', 'Father', '09333333333', 'Calumpit Institute', 'Bachelor of Science in Office Management', '3', '2025-2026', NULL, 'Bulihan Malolos Bulacan', 'Migs Gatchalian', '09444444444', 500, 0, 'pending', 'incorrect requirements'),
(102, NULL, 'Jaimee', NULL, 'Bautista', 'Hagonoy, Bulacan', '09555555555', 'jenalba2628@gmail.com', '2008-02-06', 'Jasmine Bautista', 'Mother', '09656565656', 'STI College – Malolos', 'BSBA Major in Financial Management', '4', '2025-2026', NULL, 'Dakila, McArthur Highway, Malolos City, Bulacan ', 'Rhey SAntos', '09262626262', 400, 0, 'pending', NULL),
(104, 80, 'Blair', NULL, 'Waldorf', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem21@gmail.com', '2008-02-10', 'Jampol Sayo', 'Father', '09454659876', 'Bulacan State University', 'Bachelor of Science in Accountancy', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 20, 0, 'evaluated', NULL),
(105, 81, 'Mikaili', NULL, 'Mesia', 'Malolos, Bulacan', '09454659878', 'santiagojasminem22@gmail.com', '2008-02-05', 'Maria Rosario', 'Mother', '09345646546', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 10, 0, 'pending', NULL),
(106, NULL, 'Ai-vee', NULL, 'Fulgencio', '12 Bagna', '09123456789', 'aiveefulgencio26@gmail.com', '2004-01-06', 'Pam Fulgencio', 'Mother', '09457815599', 'Bulacan Polytechnic College', 'BSBA Major in Financial Management', '3', '2025-2026', NULL, 'Malolos, Bulacan', 'Don Mucho', '09978546855', 500, 0, 'pending', NULL),
(107, 82, 'Joenel', NULL, 'Valenton', 'Malolos, Bulacan', '09454659878', 'jasmine.santiago22@bpc.edu.ph', '2008-02-10', 'Myrna Mendoza', 'Mother', '09454659879', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 250, 0, 'pending', NULL),
(110, NULL, 'Joan', NULL, 'Candy', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'jasmine3e@gmail.com', '2008-03-04', 'Myrna Mendoza', 'Mother', '09454659879', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 100, 0, 'pending', NULL),
(111, 89, 'Ana', NULL, 'Reyes', 'Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'jasmine.santiago2@bpc.edu.ph', '2008-03-10', 'Jampol Sayo', 'Father', '09454659456', 'Bulacan State University', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 50, 0, 'pending', NULL),
(113, 93, 'Lore', NULL, 'Lei', 'Malolos, Bulacan', '09454659878', 'cecili3a@gmail.com', '2008-03-17', 'Jampol san', 'Uncle', '09454659456', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 200, 0, 'pending', NULL),
(114, 94, 'Elisha', NULL, 'Lumanlan', 'Malolos, Bulacan', '09454659878', 'jasmine.santiago@bpc.edu.ph', '2008-03-11', 'Rosaly Santiago', 'Mother', '09454659873', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 250, 0, 'pending', NULL),
(115, 96, 'Jenny', NULL, 'Robles', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2008-04-02', 'Maria Robles', 'Mother', '09345646546', 'Bulacan State University', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 250, 0, 'pending', NULL),
(116, 97, 'Adriel', NULL, 'Lumanlan', 'Malolos, Bulacan', '09454659878', 'santiagojasminem42@gmail.com', '2008-04-01', 'Janice Lumanlan', 'Mother', '09454659873', 'Bulacan State University', 'Bachelor of Science in Accountancy', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 100, 0, 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sync_log`
--

CREATE TABLE `sync_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attempt` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','sent','error','blocked') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `sync_log`
--

INSERT INTO `sync_log` (`id`, `payload`, `created_at`, `attempt`, `status`, `error_message`, `blocked_until`) VALUES
(184, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:56:30.000Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:56:30\",\"client_local_ts\":\"2026-02-15 20:56:30\",\"host\":\"localhost\"}', '2026-02-15 20:56:30', 1, 'sent', NULL, NULL),
(185, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:56:45.341Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:56:45\",\"client_local_ts\":\"2026-02-15 20:56:45\",\"host\":\"localhost\"}', '2026-02-15 20:56:45', 1, 'sent', NULL, NULL),
(186, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:57:00.339Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:57:00\",\"client_local_ts\":\"2026-02-15 20:57:00\",\"host\":\"localhost\"}', '2026-02-15 20:57:00', 1, 'sent', NULL, NULL),
(187, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:57:15.335Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:57:15\",\"client_local_ts\":\"2026-02-15 20:57:15\",\"host\":\"localhost\"}', '2026-02-15 20:57:15', 1, 'sent', NULL, NULL),
(188, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:57:30.337Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:57:30\",\"client_local_ts\":\"2026-02-15 20:57:30\",\"host\":\"localhost\"}', '2026-02-15 20:57:30', 1, 'sent', NULL, NULL),
(189, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:57:45.331Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:57:45\",\"client_local_ts\":\"2026-02-15 20:57:45\",\"host\":\"localhost\"}', '2026-02-15 20:57:45', 1, 'sent', NULL, NULL),
(190, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:58:00.336Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:58:00\",\"client_local_ts\":\"2026-02-15 20:58:00\",\"host\":\"localhost\"}', '2026-02-15 20:58:00', 1, 'sent', NULL, NULL),
(191, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:58:15.333Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:58:15\",\"client_local_ts\":\"2026-02-15 20:58:15\",\"host\":\"localhost\"}', '2026-02-15 20:58:15', 1, 'sent', NULL, NULL),
(192, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:58:30.333Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:58:30\",\"client_local_ts\":\"2026-02-15 20:58:30\",\"host\":\"localhost\"}', '2026-02-15 20:58:30', 1, 'sent', NULL, NULL),
(193, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:58:45.333Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:58:45\",\"client_local_ts\":\"2026-02-15 20:58:45\",\"host\":\"localhost\"}', '2026-02-15 20:58:45', 1, 'sent', NULL, NULL),
(194, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:59:00.328Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:59:00\",\"client_local_ts\":\"2026-02-15 20:59:00\",\"host\":\"localhost\"}', '2026-02-15 20:59:00', 1, 'sent', NULL, NULL),
(195, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:59:15.333Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:59:15\",\"client_local_ts\":\"2026-02-15 20:59:15\",\"host\":\"localhost\"}', '2026-02-15 20:59:15', 1, 'sent', NULL, NULL),
(196, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:59:29.995Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:59:29\",\"client_local_ts\":\"2026-02-15 20:59:29\",\"host\":\"localhost\"}', '2026-02-15 20:59:29', 1, 'sent', NULL, NULL),
(197, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T12:59:44.992Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"20:59:44\",\"client_local_ts\":\"2026-02-15 20:59:44\",\"host\":\"localhost\"}', '2026-02-15 20:59:44', 1, 'sent', NULL, NULL),
(198, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:00:00.334Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:00:00\",\"client_local_ts\":\"2026-02-15 21:00:00\",\"host\":\"localhost\"}', '2026-02-15 21:00:00', 1, 'sent', NULL, NULL),
(199, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:00:15.001Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:00:15\",\"client_local_ts\":\"2026-02-15 21:00:15\",\"host\":\"localhost\"}', '2026-02-15 21:00:15', 1, 'sent', NULL, NULL),
(200, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:00:30.321Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:00:30\",\"client_local_ts\":\"2026-02-15 21:00:30\",\"host\":\"localhost\"}', '2026-02-15 21:00:30', 1, 'sent', NULL, NULL),
(201, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:00:45.335Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:00:45\",\"client_local_ts\":\"2026-02-15 21:00:45\",\"host\":\"localhost\"}', '2026-02-15 21:00:45', 1, 'sent', NULL, NULL),
(202, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:01:00.326Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:01:00\",\"client_local_ts\":\"2026-02-15 21:01:00\",\"host\":\"localhost\"}', '2026-02-15 21:01:00', 1, 'sent', NULL, NULL),
(203, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:01:15.336Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:01:15\",\"client_local_ts\":\"2026-02-15 21:01:15\",\"host\":\"localhost\"}', '2026-02-15 21:01:15', 1, 'sent', NULL, NULL),
(204, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:01:30.321Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:01:30\",\"client_local_ts\":\"2026-02-15 21:01:30\",\"host\":\"localhost\"}', '2026-02-15 21:01:30', 1, 'sent', NULL, NULL),
(205, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:01:45.329Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:01:45\",\"client_local_ts\":\"2026-02-15 21:01:45\",\"host\":\"localhost\"}', '2026-02-15 21:01:45', 1, 'sent', NULL, NULL),
(206, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:02:00.331Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:02:00\",\"client_local_ts\":\"2026-02-15 21:02:00\",\"host\":\"localhost\"}', '2026-02-15 21:02:00', 1, 'sent', NULL, NULL),
(207, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:02:15.320Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:02:15\",\"client_local_ts\":\"2026-02-15 21:02:15\",\"host\":\"localhost\"}', '2026-02-15 21:02:15', 1, 'sent', NULL, NULL),
(208, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:02:30.317Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:02:30\",\"client_local_ts\":\"2026-02-15 21:02:30\",\"host\":\"localhost\"}', '2026-02-15 21:02:30', 1, 'sent', NULL, NULL),
(209, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:02:45.316Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:02:45\",\"client_local_ts\":\"2026-02-15 21:02:45\",\"host\":\"localhost\"}', '2026-02-15 21:02:45', 1, 'sent', NULL, NULL),
(210, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:03:00.327Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:03:00\",\"client_local_ts\":\"2026-02-15 21:03:00\",\"host\":\"localhost\"}', '2026-02-15 21:03:00', 1, 'sent', NULL, NULL),
(211, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:03:15.324Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:03:15\",\"client_local_ts\":\"2026-02-15 21:03:15\",\"host\":\"localhost\"}', '2026-02-15 21:03:15', 1, 'sent', NULL, NULL),
(212, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:03:30.314Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:03:30\",\"client_local_ts\":\"2026-02-15 21:03:30\",\"host\":\"localhost\"}', '2026-02-15 21:03:30', 1, 'sent', NULL, NULL),
(213, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:03:45.328Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:03:45\",\"client_local_ts\":\"2026-02-15 21:03:45\",\"host\":\"localhost\"}', '2026-02-15 21:03:45', 1, 'sent', NULL, NULL),
(214, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:04:00.326Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:04:00\",\"client_local_ts\":\"2026-02-15 21:04:00\",\"host\":\"localhost\"}', '2026-02-15 21:04:00', 1, 'sent', NULL, NULL),
(215, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:04:15.315Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:04:15\",\"client_local_ts\":\"2026-02-15 21:04:15\",\"host\":\"localhost\"}', '2026-02-15 21:04:15', 1, 'sent', NULL, NULL),
(216, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:04:30.325Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:04:30\",\"client_local_ts\":\"2026-02-15 21:04:30\",\"host\":\"localhost\"}', '2026-02-15 21:04:30', 1, 'sent', NULL, NULL),
(217, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:04:45.322Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:04:45\",\"client_local_ts\":\"2026-02-15 21:04:45\",\"host\":\"localhost\"}', '2026-02-15 21:04:45', 1, 'sent', NULL, NULL),
(218, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:05:00.315Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:05:00\",\"client_local_ts\":\"2026-02-15 21:05:00\",\"host\":\"localhost\"}', '2026-02-15 21:05:00', 1, 'sent', NULL, NULL),
(219, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:05:15.328Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:05:15\",\"client_local_ts\":\"2026-02-15 21:05:15\",\"host\":\"localhost\"}', '2026-02-15 21:05:15', 1, 'sent', NULL, NULL),
(220, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:05:30.321Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:05:30\",\"client_local_ts\":\"2026-02-15 21:05:30\",\"host\":\"localhost\"}', '2026-02-15 21:05:30', 1, 'sent', NULL, NULL),
(221, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:05:45.318Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:05:45\",\"client_local_ts\":\"2026-02-15 21:05:45\",\"host\":\"localhost\"}', '2026-02-15 21:05:45', 1, 'sent', NULL, NULL),
(222, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:06:00.312Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:06:00\",\"client_local_ts\":\"2026-02-15 21:06:00\",\"host\":\"localhost\"}', '2026-02-15 21:06:00', 1, 'sent', NULL, NULL),
(223, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:06:15.322Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:06:15\",\"client_local_ts\":\"2026-02-15 21:06:15\",\"host\":\"localhost\"}', '2026-02-15 21:06:15', 1, 'sent', NULL, NULL),
(224, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:06:30.308Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:06:30\",\"client_local_ts\":\"2026-02-15 21:06:30\",\"host\":\"localhost\"}', '2026-02-15 21:06:30', 1, 'sent', NULL, NULL),
(225, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:06:45.309Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:06:45\",\"client_local_ts\":\"2026-02-15 21:06:45\",\"host\":\"localhost\"}', '2026-02-15 21:06:45', 1, 'sent', NULL, NULL),
(226, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:06:59.985Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:06:59\",\"client_local_ts\":\"2026-02-15 21:06:59\",\"host\":\"localhost\"}', '2026-02-15 21:06:59', 1, 'sent', NULL, NULL),
(227, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:07:15.321Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:07:15\",\"client_local_ts\":\"2026-02-15 21:07:15\",\"host\":\"localhost\"}', '2026-02-15 21:07:15', 1, 'sent', NULL, NULL),
(228, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:07:30.308Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:07:30\",\"client_local_ts\":\"2026-02-15 21:07:30\",\"host\":\"localhost\"}', '2026-02-15 21:07:30', 1, 'sent', NULL, NULL),
(229, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:07:45.310Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:07:45\",\"client_local_ts\":\"2026-02-15 21:07:45\",\"host\":\"localhost\"}', '2026-02-15 21:07:45', 1, 'sent', NULL, NULL),
(230, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:08:00.317Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:08:00\",\"client_local_ts\":\"2026-02-15 21:08:00\",\"host\":\"localhost\"}', '2026-02-15 21:08:00', 1, 'sent', NULL, NULL),
(231, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:08:15.321Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:08:15\",\"client_local_ts\":\"2026-02-15 21:08:15\",\"host\":\"localhost\"}', '2026-02-15 21:08:15', 1, 'sent', NULL, NULL),
(232, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:08:30.317Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:08:30\",\"client_local_ts\":\"2026-02-15 21:08:30\",\"host\":\"localhost\"}', '2026-02-15 21:08:30', 1, 'sent', NULL, NULL),
(233, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:08:45.307Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:08:45\",\"client_local_ts\":\"2026-02-15 21:08:45\",\"host\":\"localhost\"}', '2026-02-15 21:08:45', 1, 'sent', NULL, NULL),
(234, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:09:00.308Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:09:00\",\"client_local_ts\":\"2026-02-15 21:09:00\",\"host\":\"localhost\"}', '2026-02-15 21:09:00', 1, 'sent', NULL, NULL),
(235, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:09:15.309Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:09:15\",\"client_local_ts\":\"2026-02-15 21:09:15\",\"host\":\"localhost\"}', '2026-02-15 21:09:15', 1, 'sent', NULL, NULL),
(236, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:09:30.307Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:09:30\",\"client_local_ts\":\"2026-02-15 21:09:30\",\"host\":\"localhost\"}', '2026-02-15 21:09:30', 1, 'sent', NULL, NULL),
(237, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:09:45.305Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:09:45\",\"client_local_ts\":\"2026-02-15 21:09:45\",\"host\":\"localhost\"}', '2026-02-15 21:09:45', 1, 'sent', NULL, NULL),
(238, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:10:00.312Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:10:00\",\"client_local_ts\":\"2026-02-15 21:10:00\",\"host\":\"localhost\"}', '2026-02-15 21:10:00', 1, 'sent', NULL, NULL),
(239, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:10:15.307Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:10:15\",\"client_local_ts\":\"2026-02-15 21:10:15\",\"host\":\"localhost\"}', '2026-02-15 21:10:15', 1, 'sent', NULL, NULL),
(240, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:10:29.978Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:10:29\",\"client_local_ts\":\"2026-02-15 21:10:29\",\"host\":\"localhost\"}', '2026-02-15 21:10:29', 1, 'sent', NULL, NULL),
(241, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:10:45.305Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:10:45\",\"client_local_ts\":\"2026-02-15 21:10:45\",\"host\":\"localhost\"}', '2026-02-15 21:10:45', 1, 'sent', NULL, NULL),
(242, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:11:00.307Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:11:00\",\"client_local_ts\":\"2026-02-15 21:11:00\",\"host\":\"localhost\"}', '2026-02-15 21:11:00', 1, 'sent', NULL, NULL),
(243, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:11:15.302Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:11:15\",\"client_local_ts\":\"2026-02-15 21:11:15\",\"host\":\"localhost\"}', '2026-02-15 21:11:15', 1, 'sent', NULL, NULL),
(244, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:11:30.310Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:11:30\",\"client_local_ts\":\"2026-02-15 21:11:30\",\"host\":\"localhost\"}', '2026-02-15 21:11:30', 1, 'sent', NULL, NULL),
(245, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:11:45.299Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:11:45\",\"client_local_ts\":\"2026-02-15 21:11:45\",\"host\":\"localhost\"}', '2026-02-15 21:11:45', 1, 'sent', NULL, NULL),
(246, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:12:00.305Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:12:00\",\"client_local_ts\":\"2026-02-15 21:12:00\",\"host\":\"localhost\"}', '2026-02-15 21:12:00', 1, 'sent', NULL, NULL),
(247, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:12:15.308Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:12:15\",\"client_local_ts\":\"2026-02-15 21:12:15\",\"host\":\"localhost\"}', '2026-02-15 21:12:15', 1, 'sent', NULL, NULL),
(248, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:12:30.303Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:12:30\",\"client_local_ts\":\"2026-02-15 21:12:30\",\"host\":\"localhost\"}', '2026-02-15 21:12:30', 1, 'sent', NULL, NULL),
(249, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:12:45.302Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:12:45\",\"client_local_ts\":\"2026-02-15 21:12:45\",\"host\":\"localhost\"}', '2026-02-15 21:12:45', 1, 'sent', NULL, NULL),
(250, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:13:00.308Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:13:00\",\"client_local_ts\":\"2026-02-15 21:13:00\",\"host\":\"localhost\"}', '2026-02-15 21:13:00', 1, 'sent', NULL, NULL),
(251, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:13:15.298Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:13:15\",\"client_local_ts\":\"2026-02-15 21:13:15\",\"host\":\"localhost\"}', '2026-02-15 21:13:15', 1, 'sent', NULL, NULL),
(252, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:13:30.308Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:13:30\",\"client_local_ts\":\"2026-02-15 21:13:30\",\"host\":\"localhost\"}', '2026-02-15 21:13:30', 1, 'sent', NULL, NULL),
(253, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:13:45.299Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:13:45\",\"client_local_ts\":\"2026-02-15 21:13:45\",\"host\":\"localhost\"}', '2026-02-15 21:13:45', 1, 'sent', NULL, NULL),
(254, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:14:00.293Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:14:00\",\"client_local_ts\":\"2026-02-15 21:14:00\",\"host\":\"localhost\"}', '2026-02-15 21:14:00', 1, 'sent', NULL, NULL),
(255, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:14:14.972Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:14:14\",\"client_local_ts\":\"2026-02-15 21:14:14\",\"host\":\"localhost\"}', '2026-02-15 21:14:14', 1, 'sent', NULL, NULL),
(256, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:14:29.969Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:14:29\",\"client_local_ts\":\"2026-02-15 21:14:29\",\"host\":\"localhost\"}', '2026-02-15 21:14:29', 1, 'sent', NULL, NULL),
(257, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:14:44.973Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:14:44\",\"client_local_ts\":\"2026-02-15 21:14:44\",\"host\":\"localhost\"}', '2026-02-15 21:14:44', 1, 'sent', NULL, NULL),
(258, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:14:59.969Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:14:59\",\"client_local_ts\":\"2026-02-15 21:14:59\",\"host\":\"localhost\"}', '2026-02-15 21:14:59', 1, 'sent', NULL, NULL),
(259, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:15:14.976Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:15:14\",\"client_local_ts\":\"2026-02-15 21:15:14\",\"host\":\"localhost\"}', '2026-02-15 21:15:14', 1, 'sent', NULL, NULL),
(260, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:15:29.968Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:15:29\",\"client_local_ts\":\"2026-02-15 21:15:29\",\"host\":\"localhost\"}', '2026-02-15 21:15:29', 1, 'sent', NULL, NULL),
(261, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:15:44.967Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:15:44\",\"client_local_ts\":\"2026-02-15 21:15:44\",\"host\":\"localhost\"}', '2026-02-15 21:15:44', 1, 'sent', NULL, NULL),
(262, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:15:59.971Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:15:59\",\"client_local_ts\":\"2026-02-15 21:15:59\",\"host\":\"localhost\"}', '2026-02-15 21:15:59', 1, 'sent', NULL, NULL),
(263, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:16:14.978Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:16:14\",\"client_local_ts\":\"2026-02-15 21:16:14\",\"host\":\"localhost\"}', '2026-02-15 21:16:14', 1, 'sent', NULL, NULL),
(264, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:16:29.972Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:16:29\",\"client_local_ts\":\"2026-02-15 21:16:29\",\"host\":\"localhost\"}', '2026-02-15 21:16:29', 1, 'sent', NULL, NULL),
(265, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:16:44.966Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:16:44\",\"client_local_ts\":\"2026-02-15 21:16:44\",\"host\":\"localhost\"}', '2026-02-15 21:16:44', 1, 'sent', NULL, NULL),
(266, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:16:59.971Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:16:59\",\"client_local_ts\":\"2026-02-15 21:16:59\",\"host\":\"localhost\"}', '2026-02-15 21:16:59', 1, 'sent', NULL, NULL),
(267, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:17:14.965Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:17:14\",\"client_local_ts\":\"2026-02-15 21:17:14\",\"host\":\"localhost\"}', '2026-02-15 21:17:14', 1, 'sent', NULL, NULL),
(268, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:17:29.963Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:17:29\",\"client_local_ts\":\"2026-02-15 21:17:29\",\"host\":\"localhost\"}', '2026-02-15 21:17:29', 1, 'sent', NULL, NULL),
(269, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:17:44.970Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:17:44\",\"client_local_ts\":\"2026-02-15 21:17:44\",\"host\":\"localhost\"}', '2026-02-15 21:17:44', 1, 'sent', NULL, NULL),
(270, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:17:59.963Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:17:59\",\"client_local_ts\":\"2026-02-15 21:17:59\",\"host\":\"localhost\"}', '2026-02-15 21:17:59', 1, 'sent', NULL, NULL),
(271, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:18:14.971Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:18:14\",\"client_local_ts\":\"2026-02-15 21:18:14\",\"host\":\"localhost\"}', '2026-02-15 21:18:14', 1, 'sent', NULL, NULL),
(272, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:18:29.963Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:18:29\",\"client_local_ts\":\"2026-02-15 21:18:29\",\"host\":\"localhost\"}', '2026-02-15 21:18:29', 1, 'sent', NULL, NULL),
(273, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:18:44.961Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:18:44\",\"client_local_ts\":\"2026-02-15 21:18:44\",\"host\":\"localhost\"}', '2026-02-15 21:18:44', 1, 'sent', NULL, NULL),
(274, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:18:59.969Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:18:59\",\"client_local_ts\":\"2026-02-15 21:18:59\",\"host\":\"localhost\"}', '2026-02-15 21:18:59', 1, 'sent', NULL, NULL),
(275, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:19:14.960Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:19:14\",\"client_local_ts\":\"2026-02-15 21:19:14\",\"host\":\"localhost\"}', '2026-02-15 21:19:14', 1, 'sent', NULL, NULL),
(276, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:19:29.961Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:19:29\",\"client_local_ts\":\"2026-02-15 21:19:29\",\"host\":\"localhost\"}', '2026-02-15 21:19:29', 1, 'sent', NULL, NULL),
(277, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:19:44.960Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:19:44\",\"client_local_ts\":\"2026-02-15 21:19:44\",\"host\":\"localhost\"}', '2026-02-15 21:19:44', 1, 'sent', NULL, NULL),
(278, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:19:59.959Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:19:59\",\"client_local_ts\":\"2026-02-15 21:19:59\",\"host\":\"localhost\"}', '2026-02-15 21:19:59', 1, 'sent', NULL, NULL),
(279, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:20:14.959Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:20:14\",\"client_local_ts\":\"2026-02-15 21:20:14\",\"host\":\"localhost\"}', '2026-02-15 21:20:14', 1, 'sent', NULL, NULL),
(280, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:20:29.958Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:20:29\",\"client_local_ts\":\"2026-02-15 21:20:29\",\"host\":\"localhost\"}', '2026-02-15 21:20:29', 1, 'sent', NULL, NULL),
(281, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:20:44.961Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:20:44\",\"client_local_ts\":\"2026-02-15 21:20:44\",\"host\":\"localhost\"}', '2026-02-15 21:20:44', 1, 'sent', NULL, NULL),
(282, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:20:59.959Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:20:59\",\"client_local_ts\":\"2026-02-15 21:20:59\",\"host\":\"localhost\"}', '2026-02-15 21:20:59', 1, 'sent', NULL, NULL),
(283, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:21:14.959Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:21:14\",\"client_local_ts\":\"2026-02-15 21:21:14\",\"host\":\"localhost\"}', '2026-02-15 21:21:14', 1, 'sent', NULL, NULL),
(284, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:21:29.957Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:21:29\",\"client_local_ts\":\"2026-02-15 21:21:29\",\"host\":\"localhost\"}', '2026-02-15 21:21:29', 1, 'sent', NULL, NULL),
(285, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:21:44.963Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:21:44\",\"client_local_ts\":\"2026-02-15 21:21:44\",\"host\":\"localhost\"}', '2026-02-15 21:21:44', 1, 'sent', NULL, NULL),
(286, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:21:59.957Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:21:59\",\"client_local_ts\":\"2026-02-15 21:21:59\",\"host\":\"localhost\"}', '2026-02-15 21:21:59', 1, 'sent', NULL, NULL),
(287, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:22:14.956Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:22:14\",\"client_local_ts\":\"2026-02-15 21:22:14\",\"host\":\"localhost\"}', '2026-02-15 21:22:14', 1, 'sent', NULL, NULL),
(288, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:22:29.955Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:22:29\",\"client_local_ts\":\"2026-02-15 21:22:29\",\"host\":\"localhost\"}', '2026-02-15 21:22:29', 1, 'sent', NULL, NULL),
(289, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:22:44.960Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:22:44\",\"client_local_ts\":\"2026-02-15 21:22:44\",\"host\":\"localhost\"}', '2026-02-15 21:22:44', 1, 'sent', NULL, NULL),
(290, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:22:59.956Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:22:59\",\"client_local_ts\":\"2026-02-15 21:22:59\",\"host\":\"localhost\"}', '2026-02-15 21:22:59', 1, 'sent', NULL, NULL),
(291, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:23:14.954Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:23:14\",\"client_local_ts\":\"2026-02-15 21:23:14\",\"host\":\"localhost\"}', '2026-02-15 21:23:14', 1, 'sent', NULL, NULL),
(292, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:23:29.955Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:23:29\",\"client_local_ts\":\"2026-02-15 21:23:29\",\"host\":\"localhost\"}', '2026-02-15 21:23:29', 1, 'sent', NULL, NULL),
(293, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:23:44.953Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:23:44\",\"client_local_ts\":\"2026-02-15 21:23:44\",\"host\":\"localhost\"}', '2026-02-15 21:23:44', 1, 'sent', NULL, NULL),
(294, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:23:59.961Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:23:59\",\"client_local_ts\":\"2026-02-15 21:23:59\",\"host\":\"localhost\"}', '2026-02-15 21:23:59', 1, 'sent', NULL, NULL),
(295, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:24:14.955Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:24:14\",\"client_local_ts\":\"2026-02-15 21:24:14\",\"host\":\"localhost\"}', '2026-02-15 21:24:14', 1, 'sent', NULL, NULL),
(296, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:24:29.951Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:24:29\",\"client_local_ts\":\"2026-02-15 21:24:29\",\"host\":\"localhost\"}', '2026-02-15 21:24:29', 1, 'sent', NULL, NULL),
(297, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:24:44.956Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:24:44\",\"client_local_ts\":\"2026-02-15 21:24:44\",\"host\":\"localhost\"}', '2026-02-15 21:24:44', 1, 'sent', NULL, NULL),
(298, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:24:59.951Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:24:59\",\"client_local_ts\":\"2026-02-15 21:24:59\",\"host\":\"localhost\"}', '2026-02-15 21:24:59', 1, 'sent', NULL, NULL),
(299, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:25:14.952Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:25:14\",\"client_local_ts\":\"2026-02-15 21:25:14\",\"host\":\"localhost\"}', '2026-02-15 21:25:14', 1, 'sent', NULL, NULL),
(300, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:25:29.952Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:25:29\",\"client_local_ts\":\"2026-02-15 21:25:29\",\"host\":\"localhost\"}', '2026-02-15 21:25:29', 1, 'sent', NULL, NULL),
(301, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:25:44.958Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:25:44\",\"client_local_ts\":\"2026-02-15 21:25:44\",\"host\":\"localhost\"}', '2026-02-15 21:25:44', 1, 'sent', NULL, NULL),
(302, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:25:59.950Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:25:59\",\"client_local_ts\":\"2026-02-15 21:25:59\",\"host\":\"localhost\"}', '2026-02-15 21:25:59', 1, 'sent', NULL, NULL),
(303, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:26:14.956Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:26:14\",\"client_local_ts\":\"2026-02-15 21:26:14\",\"host\":\"localhost\"}', '2026-02-15 21:26:14', 1, 'sent', NULL, NULL),
(304, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:26:29.948Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:26:29\",\"client_local_ts\":\"2026-02-15 21:26:29\",\"host\":\"localhost\"}', '2026-02-15 21:26:29', 1, 'sent', NULL, NULL),
(305, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:26:44.948Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:26:44\",\"client_local_ts\":\"2026-02-15 21:26:44\",\"host\":\"localhost\"}', '2026-02-15 21:26:44', 1, 'sent', NULL, NULL),
(306, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:26:59.949Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:26:59\",\"client_local_ts\":\"2026-02-15 21:26:59\",\"host\":\"localhost\"}', '2026-02-15 21:26:59', 1, 'sent', NULL, NULL),
(307, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:27:14.949Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:27:14\",\"client_local_ts\":\"2026-02-15 21:27:14\",\"host\":\"localhost\"}', '2026-02-15 21:27:14', 1, 'sent', NULL, NULL),
(308, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:27:29.950Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:27:29\",\"client_local_ts\":\"2026-02-15 21:27:29\",\"host\":\"localhost\"}', '2026-02-15 21:27:29', 1, 'sent', NULL, NULL),
(309, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:27:45.082Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:27:45\",\"client_local_ts\":\"2026-02-15 21:27:45\",\"host\":\"localhost\"}', '2026-02-15 21:27:45', 1, 'sent', NULL, NULL),
(310, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:28:00.284Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:28:00\",\"client_local_ts\":\"2026-02-15 21:28:00\",\"host\":\"localhost\"}', '2026-02-15 21:28:00', 1, 'sent', NULL, NULL),
(311, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:28:15.093Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:28:15\",\"client_local_ts\":\"2026-02-15 21:28:15\",\"host\":\"localhost\"}', '2026-02-15 21:28:15', 1, 'sent', NULL, NULL),
(312, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:28:30.088Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:28:30\",\"client_local_ts\":\"2026-02-15 21:28:30\",\"host\":\"localhost\"}', '2026-02-15 21:28:30', 1, 'sent', NULL, NULL),
(313, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:28:45.095Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:28:45\",\"client_local_ts\":\"2026-02-15 21:28:45\",\"host\":\"localhost\"}', '2026-02-15 21:28:45', 1, 'sent', NULL, NULL),
(314, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:29:00.088Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:29:00\",\"client_local_ts\":\"2026-02-15 21:29:00\",\"host\":\"localhost\"}', '2026-02-15 21:29:00', 1, 'sent', NULL, NULL),
(315, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:29:15.093Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:29:15\",\"client_local_ts\":\"2026-02-15 21:29:15\",\"host\":\"localhost\"}', '2026-02-15 21:29:15', 1, 'sent', NULL, NULL),
(316, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:29:30.086Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:29:30\",\"client_local_ts\":\"2026-02-15 21:29:30\",\"host\":\"localhost\"}', '2026-02-15 21:29:30', 1, 'sent', NULL, NULL),
(317, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:29:45.086Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:29:45\",\"client_local_ts\":\"2026-02-15 21:29:45\",\"host\":\"localhost\"}', '2026-02-15 21:29:45', 1, 'sent', NULL, NULL),
(318, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:30:00.093Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:30:00\",\"client_local_ts\":\"2026-02-15 21:30:00\",\"host\":\"localhost\"}', '2026-02-15 21:30:00', 1, 'sent', NULL, NULL),
(319, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:30:15.087Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:30:15\",\"client_local_ts\":\"2026-02-15 21:30:15\",\"host\":\"localhost\"}', '2026-02-15 21:30:15', 1, 'sent', NULL, NULL),
(320, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:30:30.084Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:30:30\",\"client_local_ts\":\"2026-02-15 21:30:30\",\"host\":\"localhost\"}', '2026-02-15 21:30:30', 1, 'sent', NULL, NULL),
(321, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:30:45.088Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:30:45\",\"client_local_ts\":\"2026-02-15 21:30:45\",\"host\":\"localhost\"}', '2026-02-15 21:30:45', 1, 'sent', NULL, NULL),
(322, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:31:00.098Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:31:00\",\"client_local_ts\":\"2026-02-15 21:31:00\",\"host\":\"localhost\"}', '2026-02-15 21:31:00', 1, 'sent', NULL, NULL),
(323, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:31:15.089Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:31:15\",\"client_local_ts\":\"2026-02-15 21:31:15\",\"host\":\"localhost\"}', '2026-02-15 21:31:15', 1, 'sent', NULL, NULL),
(324, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:31:30.088Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:31:30\",\"client_local_ts\":\"2026-02-15 21:31:30\",\"host\":\"localhost\"}', '2026-02-15 21:31:30', 1, 'sent', NULL, NULL),
(325, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:31:45.083Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:31:45\",\"client_local_ts\":\"2026-02-15 21:31:45\",\"host\":\"localhost\"}', '2026-02-15 21:31:45', 1, 'sent', NULL, NULL),
(326, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:32:00.084Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:32:00\",\"client_local_ts\":\"2026-02-15 21:32:00\",\"host\":\"localhost\"}', '2026-02-15 21:32:00', 1, 'sent', NULL, NULL),
(327, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:32:15.085Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:32:15\",\"client_local_ts\":\"2026-02-15 21:32:15\",\"host\":\"localhost\"}', '2026-02-15 21:32:15', 1, 'sent', NULL, NULL),
(328, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:32:30.083Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:32:30\",\"client_local_ts\":\"2026-02-15 21:32:30\",\"host\":\"localhost\"}', '2026-02-15 21:32:30', 1, 'sent', NULL, NULL),
(329, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:32:45.083Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:32:45\",\"client_local_ts\":\"2026-02-15 21:32:45\",\"host\":\"localhost\"}', '2026-02-15 21:32:45', 1, 'sent', NULL, NULL),
(330, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:33:00.092Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:33:00\",\"client_local_ts\":\"2026-02-15 21:33:00\",\"host\":\"localhost\"}', '2026-02-15 21:33:00', 1, 'sent', NULL, NULL),
(331, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:33:15.084Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:33:15\",\"client_local_ts\":\"2026-02-15 21:33:15\",\"host\":\"localhost\"}', '2026-02-15 21:33:15', 1, 'sent', NULL, NULL),
(332, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:33:30.080Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:33:30\",\"client_local_ts\":\"2026-02-15 21:33:30\",\"host\":\"localhost\"}', '2026-02-15 21:33:30', 1, 'sent', NULL, NULL),
(333, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:33:45.087Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:33:45\",\"client_local_ts\":\"2026-02-15 21:33:45\",\"host\":\"localhost\"}', '2026-02-15 21:33:45', 1, 'sent', NULL, NULL),
(334, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:34:00.081Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:34:00\",\"client_local_ts\":\"2026-02-15 21:34:00\",\"host\":\"localhost\"}', '2026-02-15 21:34:00', 1, 'sent', NULL, NULL),
(335, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:34:15.087Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:34:15\",\"client_local_ts\":\"2026-02-15 21:34:15\",\"host\":\"localhost\"}', '2026-02-15 21:34:15', 1, 'sent', NULL, NULL),
(336, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:34:30.083Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:34:30\",\"client_local_ts\":\"2026-02-15 21:34:30\",\"host\":\"localhost\"}', '2026-02-15 21:34:30', 1, 'sent', NULL, NULL),
(337, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:34:45.079Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:34:45\",\"client_local_ts\":\"2026-02-15 21:34:45\",\"host\":\"localhost\"}', '2026-02-15 21:34:45', 1, 'sent', NULL, NULL),
(338, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:35:00.082Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:35:00\",\"client_local_ts\":\"2026-02-15 21:35:00\",\"host\":\"localhost\"}', '2026-02-15 21:35:00', 1, 'sent', NULL, NULL),
(339, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:35:15.080Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:35:15\",\"client_local_ts\":\"2026-02-15 21:35:15\",\"host\":\"localhost\"}', '2026-02-15 21:35:15', 1, 'sent', NULL, NULL),
(340, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:35:30.082Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:35:30\",\"client_local_ts\":\"2026-02-15 21:35:30\",\"host\":\"localhost\"}', '2026-02-15 21:35:30', 1, 'sent', NULL, NULL),
(341, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:35:45.081Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:35:45\",\"client_local_ts\":\"2026-02-15 21:35:45\",\"host\":\"localhost\"}', '2026-02-15 21:35:45', 1, 'sent', NULL, NULL),
(342, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:36:00.077Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:36:00\",\"client_local_ts\":\"2026-02-15 21:36:00\",\"host\":\"localhost\"}', '2026-02-15 21:36:00', 1, 'sent', NULL, NULL),
(343, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:36:15.085Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:36:15\",\"client_local_ts\":\"2026-02-15 21:36:15\",\"host\":\"localhost\"}', '2026-02-15 21:36:15', 1, 'sent', NULL, NULL),
(344, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:36:30.077Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:36:30\",\"client_local_ts\":\"2026-02-15 21:36:30\",\"host\":\"localhost\"}', '2026-02-15 21:36:30', 1, 'sent', NULL, NULL),
(345, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:36:45.087Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:36:45\",\"client_local_ts\":\"2026-02-15 21:36:45\",\"host\":\"localhost\"}', '2026-02-15 21:36:45', 1, 'sent', NULL, NULL),
(346, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:37:00.080Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:37:00\",\"client_local_ts\":\"2026-02-15 21:37:00\",\"host\":\"localhost\"}', '2026-02-15 21:37:00', 1, 'sent', NULL, NULL),
(347, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:37:15.076Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:37:15\",\"client_local_ts\":\"2026-02-15 21:37:15\",\"host\":\"localhost\"}', '2026-02-15 21:37:15', 1, 'sent', NULL, NULL),
(348, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:37:30.079Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:37:30\",\"client_local_ts\":\"2026-02-15 21:37:30\",\"host\":\"localhost\"}', '2026-02-15 21:37:30', 1, 'sent', NULL, NULL),
(349, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:37:45.076Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:37:45\",\"client_local_ts\":\"2026-02-15 21:37:45\",\"host\":\"localhost\"}', '2026-02-15 21:37:45', 1, 'sent', NULL, NULL),
(350, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:38:00.086Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:38:00\",\"client_local_ts\":\"2026-02-15 21:38:00\",\"host\":\"localhost\"}', '2026-02-15 21:38:00', 1, 'sent', NULL, NULL),
(351, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:38:15.079Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:38:15\",\"client_local_ts\":\"2026-02-15 21:38:15\",\"host\":\"localhost\"}', '2026-02-15 21:38:15', 1, 'sent', NULL, NULL),
(352, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:38:30.074Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:38:30\",\"client_local_ts\":\"2026-02-15 21:38:30\",\"host\":\"localhost\"}', '2026-02-15 21:38:30', 1, 'sent', NULL, NULL),
(353, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:38:45.082Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:38:45\",\"client_local_ts\":\"2026-02-15 21:38:45\",\"host\":\"localhost\"}', '2026-02-15 21:38:45', 1, 'sent', NULL, NULL),
(354, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:39:00.075Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:39:00\",\"client_local_ts\":\"2026-02-15 21:39:00\",\"host\":\"localhost\"}', '2026-02-15 21:39:00', 1, 'sent', NULL, NULL),
(355, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:39:15.075Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:39:15\",\"client_local_ts\":\"2026-02-15 21:39:15\",\"host\":\"localhost\"}', '2026-02-15 21:39:15', 1, 'sent', NULL, NULL),
(356, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:39:30.074Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:39:30\",\"client_local_ts\":\"2026-02-15 21:39:30\",\"host\":\"localhost\"}', '2026-02-15 21:39:30', 1, 'sent', NULL, NULL),
(357, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:39:45.073Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:39:45\",\"client_local_ts\":\"2026-02-15 21:39:45\",\"host\":\"localhost\"}', '2026-02-15 21:39:45', 1, 'sent', NULL, NULL),
(358, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:40:00.075Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:40:00\",\"client_local_ts\":\"2026-02-15 21:40:00\",\"host\":\"localhost\"}', '2026-02-15 21:40:00', 1, 'sent', NULL, NULL),
(359, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:40:15.073Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:40:15\",\"client_local_ts\":\"2026-02-15 21:40:15\",\"host\":\"localhost\"}', '2026-02-15 21:40:15', 1, 'sent', NULL, NULL),
(360, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:40:30.074Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:40:30\",\"client_local_ts\":\"2026-02-15 21:40:30\",\"host\":\"localhost\"}', '2026-02-15 21:40:30', 1, 'sent', NULL, NULL),
(361, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:40:45.077Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:40:45\",\"client_local_ts\":\"2026-02-15 21:40:45\",\"host\":\"localhost\"}', '2026-02-15 21:40:45', 1, 'sent', NULL, NULL),
(362, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:41:00.073Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:41:00\",\"client_local_ts\":\"2026-02-15 21:41:00\",\"host\":\"localhost\"}', '2026-02-15 21:41:00', 1, 'sent', NULL, NULL),
(363, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:41:15.080Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:41:15\",\"client_local_ts\":\"2026-02-15 21:41:15\",\"host\":\"localhost\"}', '2026-02-15 21:41:15', 1, 'sent', NULL, NULL),
(364, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:41:30.072Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:41:30\",\"client_local_ts\":\"2026-02-15 21:41:30\",\"host\":\"localhost\"}', '2026-02-15 21:41:30', 1, 'sent', NULL, NULL),
(365, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:41:45.071Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:41:45\",\"client_local_ts\":\"2026-02-15 21:41:45\",\"host\":\"localhost\"}', '2026-02-15 21:41:45', 1, 'sent', NULL, NULL),
(366, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:42:00.076Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:42:00\",\"client_local_ts\":\"2026-02-15 21:42:00\",\"host\":\"localhost\"}', '2026-02-15 21:42:00', 1, 'sent', NULL, NULL),
(367, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:42:15.071Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:42:15\",\"client_local_ts\":\"2026-02-15 21:42:15\",\"host\":\"localhost\"}', '2026-02-15 21:42:15', 1, 'sent', NULL, NULL),
(368, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:42:30.078Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:42:30\",\"client_local_ts\":\"2026-02-15 21:42:30\",\"host\":\"localhost\"}', '2026-02-15 21:42:30', 1, 'sent', NULL, NULL),
(369, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:42:45.070Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:42:45\",\"client_local_ts\":\"2026-02-15 21:42:45\",\"host\":\"localhost\"}', '2026-02-15 21:42:45', 1, 'sent', NULL, NULL),
(370, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:43:00.079Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:43:00\",\"client_local_ts\":\"2026-02-15 21:43:00\",\"host\":\"localhost\"}', '2026-02-15 21:43:00', 1, 'sent', NULL, NULL),
(371, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:43:15.075Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:43:15\",\"client_local_ts\":\"2026-02-15 21:43:15\",\"host\":\"localhost\"}', '2026-02-15 21:43:15', 1, 'sent', NULL, NULL),
(372, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:43:30.078Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:43:30\",\"client_local_ts\":\"2026-02-15 21:43:30\",\"host\":\"localhost\"}', '2026-02-15 21:43:30', 1, 'sent', NULL, NULL),
(373, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:43:45.080Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:43:45\",\"client_local_ts\":\"2026-02-15 21:43:45\",\"host\":\"localhost\"}', '2026-02-15 21:43:45', 1, 'sent', NULL, NULL),
(374, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:44:00.070Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:44:00\",\"client_local_ts\":\"2026-02-15 21:44:00\",\"host\":\"localhost\"}', '2026-02-15 21:44:00', 1, 'sent', NULL, NULL),
(375, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:44:15.071Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:44:15\",\"client_local_ts\":\"2026-02-15 21:44:15\",\"host\":\"localhost\"}', '2026-02-15 21:44:15', 1, 'sent', NULL, NULL),
(376, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:44:30.072Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:44:30\",\"client_local_ts\":\"2026-02-15 21:44:30\",\"host\":\"localhost\"}', '2026-02-15 21:44:30', 1, 'sent', NULL, NULL),
(377, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:44:45.069Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:44:45\",\"client_local_ts\":\"2026-02-15 21:44:45\",\"host\":\"localhost\"}', '2026-02-15 21:44:45', 1, 'sent', NULL, NULL),
(378, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:45:00.076Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:45:00\",\"client_local_ts\":\"2026-02-15 21:45:00\",\"host\":\"localhost\"}', '2026-02-15 21:45:00', 1, 'sent', NULL, NULL),
(379, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:45:15.067Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:45:15\",\"client_local_ts\":\"2026-02-15 21:45:15\",\"host\":\"localhost\"}', '2026-02-15 21:45:15', 1, 'sent', NULL, NULL);
INSERT INTO `sync_log` (`id`, `payload`, `created_at`, `attempt`, `status`, `error_message`, `blocked_until`) VALUES
(380, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:45:30.066Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:45:30\",\"client_local_ts\":\"2026-02-15 21:45:30\",\"host\":\"localhost\"}', '2026-02-15 21:45:30', 1, 'sent', NULL, NULL),
(381, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:45:45.072Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:45:45\",\"client_local_ts\":\"2026-02-15 21:45:45\",\"host\":\"localhost\"}', '2026-02-15 21:45:45', 1, 'sent', NULL, NULL),
(382, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:46:00.066Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:46:00\",\"client_local_ts\":\"2026-02-15 21:46:00\",\"host\":\"localhost\"}', '2026-02-15 21:46:00', 1, 'sent', NULL, NULL),
(383, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:46:15.068Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:46:15\",\"client_local_ts\":\"2026-02-15 21:46:15\",\"host\":\"localhost\"}', '2026-02-15 21:46:15', 1, 'sent', NULL, NULL),
(384, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:46:30.067Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:46:30\",\"client_local_ts\":\"2026-02-15 21:46:30\",\"host\":\"localhost\"}', '2026-02-15 21:46:30', 1, 'sent', NULL, NULL),
(385, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:46:45.071Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:46:45\",\"client_local_ts\":\"2026-02-15 21:46:45\",\"host\":\"localhost\"}', '2026-02-15 21:46:45', 1, 'sent', NULL, NULL),
(386, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:47:00.068Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:47:00\",\"client_local_ts\":\"2026-02-15 21:47:00\",\"host\":\"localhost\"}', '2026-02-15 21:47:00', 1, 'sent', NULL, NULL),
(387, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:47:15.070Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:47:15\",\"client_local_ts\":\"2026-02-15 21:47:15\",\"host\":\"localhost\"}', '2026-02-15 21:47:15', 1, 'sent', NULL, NULL),
(388, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:47:30.253Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:47:30\",\"client_local_ts\":\"2026-02-15 21:47:30\",\"host\":\"localhost\"}', '2026-02-15 21:47:30', 1, 'sent', NULL, NULL),
(389, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:47:45.066Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:47:45\",\"client_local_ts\":\"2026-02-15 21:47:45\",\"host\":\"localhost\"}', '2026-02-15 21:47:45', 1, 'sent', NULL, NULL),
(390, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:48:00.073Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:48:00\",\"client_local_ts\":\"2026-02-15 21:48:00\",\"host\":\"localhost\"}', '2026-02-15 21:48:00', 1, 'sent', NULL, NULL),
(391, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:48:15.064Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:48:15\",\"client_local_ts\":\"2026-02-15 21:48:15\",\"host\":\"localhost\"}', '2026-02-15 21:48:15', 1, 'sent', NULL, NULL),
(392, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:48:30.063Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:48:30\",\"client_local_ts\":\"2026-02-15 21:48:30\",\"host\":\"localhost\"}', '2026-02-15 21:48:30', 1, 'sent', NULL, NULL),
(393, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:48:45.072Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:48:45\",\"client_local_ts\":\"2026-02-15 21:48:45\",\"host\":\"localhost\"}', '2026-02-15 21:48:45', 1, 'sent', NULL, NULL),
(394, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:49:00.065Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:49:00\",\"client_local_ts\":\"2026-02-15 21:49:00\",\"host\":\"localhost\"}', '2026-02-15 21:49:00', 1, 'sent', NULL, NULL),
(395, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:49:15.063Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:49:15\",\"client_local_ts\":\"2026-02-15 21:49:15\",\"host\":\"localhost\"}', '2026-02-15 21:49:15', 1, 'sent', NULL, NULL),
(396, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:49:30.068Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:49:30\",\"client_local_ts\":\"2026-02-15 21:49:30\",\"host\":\"localhost\"}', '2026-02-15 21:49:30', 1, 'sent', NULL, NULL),
(397, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:49:45.068Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:49:45\",\"client_local_ts\":\"2026-02-15 21:49:45\",\"host\":\"localhost\"}', '2026-02-15 21:49:45', 1, 'sent', NULL, NULL),
(398, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:50:00.071Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:50:00\",\"client_local_ts\":\"2026-02-15 21:50:00\",\"host\":\"localhost\"}', '2026-02-15 21:50:00', 1, 'sent', NULL, NULL),
(399, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:50:15.065Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:50:15\",\"client_local_ts\":\"2026-02-15 21:50:15\",\"host\":\"localhost\"}', '2026-02-15 21:50:15', 1, 'sent', NULL, NULL),
(400, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:50:30.061Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:50:30\",\"client_local_ts\":\"2026-02-15 21:50:30\",\"host\":\"localhost\"}', '2026-02-15 21:50:30', 1, 'sent', NULL, NULL),
(401, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:50:45.067Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:50:45\",\"client_local_ts\":\"2026-02-15 21:50:45\",\"host\":\"localhost\"}', '2026-02-15 21:50:45', 1, 'sent', NULL, NULL),
(402, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:51:00.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:51:00\",\"client_local_ts\":\"2026-02-15 21:51:00\",\"host\":\"localhost\"}', '2026-02-15 21:51:00', 1, 'sent', NULL, NULL),
(403, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:51:15.061Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:51:15\",\"client_local_ts\":\"2026-02-15 21:51:15\",\"host\":\"localhost\"}', '2026-02-15 21:51:15', 1, 'sent', NULL, NULL),
(404, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:51:45.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:51:45\",\"client_local_ts\":\"2026-02-15 21:51:45\",\"host\":\"localhost\"}', '2026-02-15 21:51:45', 1, 'sent', NULL, NULL),
(405, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:52:00.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:52:00\",\"client_local_ts\":\"2026-02-15 21:52:00\",\"host\":\"localhost\"}', '2026-02-15 21:52:00', 1, 'sent', NULL, NULL),
(406, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:52:15.059Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:52:15\",\"client_local_ts\":\"2026-02-15 21:52:15\",\"host\":\"localhost\"}', '2026-02-15 21:52:15', 1, 'sent', NULL, NULL),
(407, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:52:30.064Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:52:30\",\"client_local_ts\":\"2026-02-15 21:52:30\",\"host\":\"localhost\"}', '2026-02-15 21:52:30', 1, 'sent', NULL, NULL),
(408, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:52:45.061Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:52:45\",\"client_local_ts\":\"2026-02-15 21:52:45\",\"host\":\"localhost\"}', '2026-02-15 21:52:45', 1, 'sent', NULL, NULL),
(409, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:53:00.058Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:53:00\",\"client_local_ts\":\"2026-02-15 21:53:00\",\"host\":\"localhost\"}', '2026-02-15 21:53:00', 1, 'sent', NULL, NULL),
(410, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:53:15.062Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:53:15\",\"client_local_ts\":\"2026-02-15 21:53:15\",\"host\":\"localhost\"}', '2026-02-15 21:53:15', 1, 'sent', NULL, NULL),
(411, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:53:30.058Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:53:30\",\"client_local_ts\":\"2026-02-15 21:53:30\",\"host\":\"localhost\"}', '2026-02-15 21:53:30', 1, 'sent', NULL, NULL),
(412, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:53:45.059Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:53:45\",\"client_local_ts\":\"2026-02-15 21:53:45\",\"host\":\"localhost\"}', '2026-02-15 21:53:45', 1, 'sent', NULL, NULL),
(413, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:54:00.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:54:00\",\"client_local_ts\":\"2026-02-15 21:54:00\",\"host\":\"localhost\"}', '2026-02-15 21:54:00', 1, 'sent', NULL, NULL),
(414, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:54:15.065Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:54:15\",\"client_local_ts\":\"2026-02-15 21:54:15\",\"host\":\"localhost\"}', '2026-02-15 21:54:15', 1, 'sent', NULL, NULL),
(415, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:54:30.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:54:30\",\"client_local_ts\":\"2026-02-15 21:54:30\",\"host\":\"localhost\"}', '2026-02-15 21:54:30', 1, 'sent', NULL, NULL),
(416, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:54:45.061Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:54:45\",\"client_local_ts\":\"2026-02-15 21:54:45\",\"host\":\"localhost\"}', '2026-02-15 21:54:45', 1, 'sent', NULL, NULL),
(417, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:55:00.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:55:00\",\"client_local_ts\":\"2026-02-15 21:55:00\",\"host\":\"localhost\"}', '2026-02-15 21:55:00', 1, 'sent', NULL, NULL),
(418, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:55:15.062Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:55:15\",\"client_local_ts\":\"2026-02-15 21:55:15\",\"host\":\"localhost\"}', '2026-02-15 21:55:15', 1, 'sent', NULL, NULL),
(419, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:55:30.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:55:30\",\"client_local_ts\":\"2026-02-15 21:55:30\",\"host\":\"localhost\"}', '2026-02-15 21:55:30', 1, 'sent', NULL, NULL),
(420, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:55:45.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:55:45\",\"client_local_ts\":\"2026-02-15 21:55:45\",\"host\":\"localhost\"}', '2026-02-15 21:55:45', 1, 'sent', NULL, NULL),
(421, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:56:00.056Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:56:00\",\"client_local_ts\":\"2026-02-15 21:56:00\",\"host\":\"localhost\"}', '2026-02-15 21:56:00', 1, 'sent', NULL, NULL),
(422, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:56:30.059Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:56:30\",\"client_local_ts\":\"2026-02-15 21:56:30\",\"host\":\"localhost\"}', '2026-02-15 21:56:30', 1, 'sent', NULL, NULL),
(423, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:56:45.057Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:56:45\",\"client_local_ts\":\"2026-02-15 21:56:45\",\"host\":\"localhost\"}', '2026-02-15 21:56:45', 1, 'sent', NULL, NULL),
(424, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:57:00.062Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:57:00\",\"client_local_ts\":\"2026-02-15 21:57:00\",\"host\":\"localhost\"}', '2026-02-15 21:57:00', 1, 'sent', NULL, NULL),
(425, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:57:15.056Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:57:15\",\"client_local_ts\":\"2026-02-15 21:57:15\",\"host\":\"localhost\"}', '2026-02-15 21:57:15', 1, 'sent', NULL, NULL),
(426, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:57:30.062Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:57:30\",\"client_local_ts\":\"2026-02-15 21:57:30\",\"host\":\"localhost\"}', '2026-02-15 21:57:30', 1, 'sent', NULL, NULL),
(427, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:57:45.058Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:57:45\",\"client_local_ts\":\"2026-02-15 21:57:45\",\"host\":\"localhost\"}', '2026-02-15 21:57:45', 1, 'sent', NULL, NULL),
(428, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:58:00.066Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:58:00\",\"client_local_ts\":\"2026-02-15 21:58:00\",\"host\":\"localhost\"}', '2026-02-15 21:58:00', 1, 'sent', NULL, NULL),
(429, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:58:30.055Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:58:30\",\"client_local_ts\":\"2026-02-15 21:58:30\",\"host\":\"localhost\"}', '2026-02-15 21:58:30', 1, 'sent', NULL, NULL),
(430, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:59:00.058Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:59:00\",\"client_local_ts\":\"2026-02-15 21:59:00\",\"host\":\"localhost\"}', '2026-02-15 21:59:00', 1, 'sent', NULL, NULL),
(431, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T13:59:30.054Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"21:59:30\",\"client_local_ts\":\"2026-02-15 21:59:30\",\"host\":\"localhost\"}', '2026-02-15 21:59:30', 1, 'sent', NULL, NULL),
(432, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:00:00.063Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:00:00\",\"client_local_ts\":\"2026-02-15 22:00:00\",\"host\":\"localhost\"}', '2026-02-15 22:00:00', 1, 'sent', NULL, NULL),
(433, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:00:15.057Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:00:15\",\"client_local_ts\":\"2026-02-15 22:00:15\",\"host\":\"localhost\"}', '2026-02-15 22:00:15', 1, 'sent', NULL, NULL),
(434, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:00:30.053Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:00:30\",\"client_local_ts\":\"2026-02-15 22:00:30\",\"host\":\"localhost\"}', '2026-02-15 22:00:30', 1, 'sent', NULL, NULL),
(435, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:00:45.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:00:45\",\"client_local_ts\":\"2026-02-15 22:00:45\",\"host\":\"localhost\"}', '2026-02-15 22:00:45', 1, 'sent', NULL, NULL),
(436, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:01:00.055Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:01:00\",\"client_local_ts\":\"2026-02-15 22:01:00\",\"host\":\"localhost\"}', '2026-02-15 22:01:00', 1, 'sent', NULL, NULL),
(437, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:01:15.052Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:01:15\",\"client_local_ts\":\"2026-02-15 22:01:15\",\"host\":\"localhost\"}', '2026-02-15 22:01:15', 1, 'sent', NULL, NULL),
(438, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:01:30.055Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:01:30\",\"client_local_ts\":\"2026-02-15 22:01:30\",\"host\":\"localhost\"}', '2026-02-15 22:01:30', 1, 'sent', NULL, NULL),
(439, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:01:45.060Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:01:45\",\"client_local_ts\":\"2026-02-15 22:01:45\",\"host\":\"localhost\"}', '2026-02-15 22:01:45', 1, 'sent', NULL, NULL),
(440, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:02:00.055Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:02:00\",\"client_local_ts\":\"2026-02-15 22:02:00\",\"host\":\"localhost\"}', '2026-02-15 22:02:00', 1, 'sent', NULL, NULL),
(441, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:02:15.052Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:02:15\",\"client_local_ts\":\"2026-02-15 22:02:15\",\"host\":\"localhost\"}', '2026-02-15 22:02:15', 1, 'sent', NULL, NULL),
(442, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:02:30.053Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:02:30\",\"client_local_ts\":\"2026-02-15 22:02:30\",\"host\":\"localhost\"}', '2026-02-15 22:02:30', 1, 'sent', NULL, NULL),
(443, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:02:45.055Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:02:45\",\"client_local_ts\":\"2026-02-15 22:02:45\",\"host\":\"localhost\"}', '2026-02-15 22:02:45', 1, 'sent', NULL, NULL),
(444, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:03:00.059Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:03:00\",\"client_local_ts\":\"2026-02-15 22:03:00\",\"host\":\"localhost\"}', '2026-02-15 22:03:00', 1, 'sent', NULL, NULL),
(445, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:03:15.059Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:03:15\",\"client_local_ts\":\"2026-02-15 22:03:15\",\"host\":\"localhost\"}', '2026-02-15 22:03:15', 1, 'sent', NULL, NULL),
(446, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:03:30.232Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:03:30\",\"client_local_ts\":\"2026-02-15 22:03:30\",\"host\":\"localhost\"}', '2026-02-15 22:03:30', 1, 'sent', NULL, NULL),
(447, '{\"type\":\"heartbeat\",\"ts\":\"2026-02-15T14:03:46.231Z\",\"client_local_date\":\"2026-02-15\",\"client_local_time\":\"22:03:46\",\"client_local_ts\":\"2026-02-15 22:03:46\",\"host\":\"localhost\"}', '2026-02-15 22:03:46', 1, 'sent', NULL, NULL),
(448, '{\"type\":\"heartbeat\",\"ts\":\"2026-03-03T09:17:00.010Z\",\"client_local_date\":\"2026-03-03\",\"client_local_time\":\"17:17:00\",\"client_local_ts\":\"2026-03-03 17:17:00\",\"host\":\"localhost\"}', '2026-03-03 17:17:00', 1, 'sent', NULL, NULL);

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
  `endorsement_printed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `first_name`, `middle_name`, `last_name`, `avatar`, `force_password_change`, `password_changed_at`, `password`, `role`, `office_name`, `status`, `date_created`, `endorsement_printed`) VALUES
(5, 'hrhead', 'hrheadd@gmail.com', 'Cecilia', '', 'Ramos', '../uploads/avatars/user_5_1770757140.jpg', 0, NULL, '$2y$10$SH3vw4WoYc6KrcSC2J0Qi.ht4DnCduuwl0yzAJ69xovLkNiGJg6G6', 'hr_head', NULL, 'active', '2025-10-12 13:34:28', 0),
(6, 'hrstaff', 'santiagojasminem2@gmail.com', 'Andrea', '', 'Lopez', '../uploads/avatars/user_6_1770757538.jpg', 0, NULL, '$2y$10$i2FWwB33v57nz8d6dSBLt.oLE7zkuU/c27MN9FG3YAlKTnxksQ4VG', 'hr_staff', NULL, 'active', '2025-10-12 13:34:28', 0),
(27, 'headbudget', 'santiagojasminem11@gmail.com', 'Layla', NULL, 'Garcia', NULL, 0, NULL, '$2y$10$QWidsmwj2042wtG/QwqppeiTglq1fmFqoL8JejgJtu6n8svNrdNf6', 'office_head', 'City Budget Office', 'active', '2025-11-07 09:58:55', 0),
(29, 'headaccounting', 'santiagojasminem23@gmail.com', 'Charles', NULL, 'Bass', NULL, 0, NULL, '$2y$10$yq1airn1Bj28mFpXU1hnoO.yDPqGh/LRaBUE0aDIc8Jw5MWeyajhq', 'office_head', 'City Accounting Office', 'active', '2025-11-08 07:58:24', 0),
(50, 'headca', 'santiagojasminem211@gmail.com', 'Jimwell', NULL, 'Diamante', NULL, 0, NULL, '$2y$10$lOwQ5A6gsVZAL.nPKgU1BemHcV5JLLN57PT5OOXgeZrlRjrKeTjQa', 'office_head', 'City Admin Office', 'active', '2025-11-17 01:56:34', 0),
(80, 'santiagojasminem', NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$zsMGuHusyuVSZmgcZAizYOJUbak3nOsyKPEtnCTkk515c3bzPtu86', 'ojt', 'City Budget Office', 'evaluated', '2026-02-10 10:07:34', 1),
(81, 'mikaili', NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$bZQBOqDMtBzGaSCyq3yyLu4KhGRFl.pVdjxt3MotysXYI58ypdBZW', 'ojt', 'City Budget Office', 'ongoing', '2026-02-10 10:25:16', 1),
(82, 'joenel.valenton', '', '', '', '', '../uploads/avatars/user_82_1774331438.jpg', 0, NULL, '$2y$10$BeTG1Jo.5QD.kD6c9dW8ue6A5jmQax8tMCxJ7.yKnapoQwwjeVMce', 'ojt', 'City Budget Office', 'ongoing', '2026-02-11 03:21:22', 0),
(85, 'jrobles855', 'jen@gmail.com', 'Jen', NULL, 'Robles', NULL, 0, NULL, '$2y$10$xjJbwLzbqfIkoBj5gu3iFu6.QCZ1nfSQt1hryZksL1ABriMzAeOq6', 'hr_staff', NULL, 'active', '2026-02-11 03:25:54', 0),
(89, 'jasmine.santiago2', NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$ZHtoxYtgRJHk8nDWIEHoZ.YQ5jGBpAs15D4aF7YUPneoBy0YSunxO', 'ojt', 'City Budget Office', 'ongoing', '2026-03-16 12:31:33', 1),
(93, 'Lorelie', NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$FP5vpO5Wv7rwApWVpIAZ7.0g5IBoboo5xPlxob3gpVQwhpQNod36K', 'ojt', 'City Budget Office', 'ongoing', '2026-03-18 14:10:46', 1),
(94, 'jasmine.santiago', NULL, NULL, NULL, NULL, NULL, 0, NULL, 'bea0d0cc7b', 'ojt', 'City Accounting Office', 'approved', '2026-03-25 06:37:41', 1),
(95, 'jsarmiento520', 'john@gmail.com', 'John', NULL, 'Sarmiento', NULL, 0, NULL, 'x!6mumuS$S', 'office_head', 'Information Technology Office', 'active', '2026-03-25 06:40:12', 0),
(96, 'jenny', 'santiagojasminem@gmail.com', 'Jenny', NULL, 'Robles', NULL, 0, NULL, '$2y$10$9AJ83EQeGotXvNj5STJMS.eTqmJpv5ug08YWoRMlYF/cBOw4lOA2a', 'ojt', 'City Accounting Office', 'approved', '2026-04-02 06:54:57', 0),
(97, 'santiagojasminem42', NULL, NULL, NULL, NULL, NULL, 0, NULL, '486f74820e', 'ojt', 'City Accounting Office', 'approved', '2026-04-02 08:05:33', 0);

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
(39, 104, 'Week 1 (2026-02-26|2026-02-27)', '2026-04-02', 'r2://journals/1775110683_b8f34495d6c7_WEEKLY_JOURNAL_SAMPLE.pdf', '2026-02-26', '2026-02-27'),
(40, 104, 'Week 2 (2026-03-03|2026-03-03)', '2026-04-02', 'r2://journals/1775110717_81ca738181cb_journal_1775110717_80.pdf', '2026-03-03', '2026-03-03');

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
  ADD UNIQUE KEY `uq_evaluations_cert_serial` (`cert_serial`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_evaluations_user_id` (`user_id`);

--
-- Indexes for table `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  ADD PRIMARY KEY (`question_key`);

--
-- Indexes for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eval_id` (`eval_id`);

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
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`school_id`),
  ADD UNIQUE KEY `school_name` (`school_name`);

--
-- Indexes for table `school_courses`
--
ALTER TABLE `school_courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sc_school` (`school_id`),
  ADD KEY `fk_sc_course` (`course_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sync_log`
--
ALTER TABLE `sync_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `status` (`status`);

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
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `dtr`
--
ALTER TABLE `dtr`
  MODIFY `dtr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=277;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `face_templates`
--
ALTER TABLE `face_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `moa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `notification_users`
--
ALTER TABLE `notification_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `office_courses`
--
ALTER TABLE `office_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `office_requests`
--
ALTER TABLE `office_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `orientation_assignments`
--
ALTER TABLE `orientation_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `orientation_sessions`
--
ALTER TABLE `orientation_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `school_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `school_courses`
--
ALTER TABLE `school_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=372;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `sync_log`
--
ALTER TABLE `sync_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=449;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

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
-- Constraints for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD CONSTRAINT `fk_eval_responses_eval` FOREIGN KEY (`eval_id`) REFERENCES `evaluations` (`eval_id`) ON DELETE CASCADE;

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
-- Constraints for table `school_courses`
--
ALTER TABLE `school_courses`
  ADD CONSTRAINT `fk_sc_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `fk_sc_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`) ON DELETE CASCADE;

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
