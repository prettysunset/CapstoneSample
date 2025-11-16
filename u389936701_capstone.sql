-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 16, 2025 at 03:23 AM
-- Server version: 11.8.3-MariaDB-log
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
(1, NULL, 'Bachelor of Science in Public Administration'),
(2, NULL, 'Bachelor of Science in Office Administration'),
(3, NULL, 'Bachelor of Science in Accountancy'),
(5, NULL, 'Bachelor of Science in Accounting Information System'),
(6, NULL, 'BSBA Major in Financial Management'),
(7, NULL, 'Bachelor of Science in Civil Engineering'),
(8, NULL, 'Bachelor of Science in Electrical Engineering');

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
(28, 9, '2025-11-02', NULL, NULL, '12:59', '15:59', 3, 0),
(35, 9, '2025-11-08', '09:52', '11:22', NULL, NULL, 1, 30),
(38, 39, '2025-11-16', '07:58', NULL, NULL, NULL, 0, 0),
(39, 38, '2025-11-16', '09:06', NULL, NULL, NULL, 0, 0),
(40, 41, '2025-11-16', '09:19', NULL, NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `eval_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `date_evaluated` date DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
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
  `date_uploaded` date DEFAULT NULL,
  `validity_months` int(11) DEFAULT 12
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `moa`
--

INSERT INTO `moa` (`moa_id`, `school_name`, `moa_file`, `date_uploaded`, `validity_months`) VALUES
(9, 'Bulacan Polytechnic College', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', '2025-11-14', 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `date_sent` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(6, 'City General Services Office', 2, 0, NULL, NULL, 'Approved'),
(8, 'City Budget Office', 1, 0, 2, 'increased workload', 'Pending'),
(9, 'City Accounting Office', 4, 0, NULL, NULL, 'Approved'),
(11, 'City Mayor\'s Office', 2, 0, NULL, NULL, 'Approved'),
(12, 'City Engineering Office', 2, 0, NULL, NULL, 'Approved');

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
(1, 6, 1),
(2, 6, 2),
(5, 8, 3),
(6, 8, 5),
(7, 8, 6),
(8, 9, 3),
(9, 9, 5),
(11, 11, 1),
(10, 11, 2),
(12, 12, 7),
(13, 12, 8);

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
(3, 8, 1, 3, 'holiday season', 'approved', '2025-11-09', '2025-11-10 08:22:51'),
(4, 9, 2, 3, 'increased workload', 'approved', '2025-11-10', '2025-11-10 10:06:21'),
(5, 8, 3, 2, 'decreased workload', 'approved', '2025-11-10', '2025-11-10 10:07:26'),
(6, 8, 2, 3, 'increased workload', 'approved', '2025-11-10', '2025-11-10 10:15:44'),
(7, 8, 3, 2, 'decreased workload', 'approved', '2025-11-10', '2025-11-10 10:25:55'),
(8, 8, 2, 1, 'decreased workload', 'approved', '2025-11-10', '2025-11-10 13:10:38'),
(9, 6, 3, 2, 'decreased workload', 'rejected', '2025-11-11', '2025-11-11 02:20:04'),
(10, 6, 3, 2, 'decreased workload', 'approved', '2025-11-11', '2025-11-11 02:51:22'),
(11, 11, 3, 2, 'decreased workload', 'approved', '2025-11-11', '2025-11-11 02:56:54'),
(13, 9, 3, 4, 'increased workload', 'approved', '2025-11-15', '2025-11-15 12:35:13');

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
  `status` enum('pending','approved','rejected','ongoing','deactivated') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `date_submitted` date DEFAULT NULL,
  `date_updated` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ojt_applications`
--

INSERT INTO `ojt_applications` (`application_id`, `student_id`, `office_preference1`, `office_preference2`, `letter_of_intent`, `endorsement_letter`, `resume`, `moa_file`, `picture`, `status`, `remarks`, `date_submitted`, `date_updated`) VALUES
(31, 35, 11, NULL, 'uploads/1762610225_Needs_Assessment_Questionnaire_Template.pdf', 'uploads/1762610225_Needs_Assessment_Questionnaire_Template.pdf', 'uploads/1762610225_Needs_Assessment_Questionnaire_Template.pdf', 'uploads/1762610225_Needs_Assessment_Questionnaire_Template.pdf', 'uploads/1762610225_3674f89d-dd46-4d3b-af93-8111af7fb386.jpg', 'ongoing', 'Orientation/Start: November 18, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Mayor\'s Office', '2025-11-08', '2025-11-10'),
(34, 38, 12, NULL, 'uploads/1762610748_Career_Skills_and_On-the-Job_Training_Performance_.pdf', 'uploads/1762610748_Career_Skills_and_On-the-Job_Training_Performance_.pdf', 'uploads/1762610748_Career_Skills_and_On-the-Job_Training_Performance_.pdf', 'uploads/moa/img029_1761923740.jpg', 'uploads/1762610748_3674f89d-dd46-4d3b-af93-8111af7fb386.jpg', 'ongoing', 'Orientation/Start: November 17, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Engineering Office', '2025-11-08', '2025-11-08'),
(35, 39, 12, NULL, 'uploads/1762638392_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762638392_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762638392_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/moa/img029_1761923740.jpg', 'uploads/1762638392_Untitled_design__7_.png', 'approved', 'Orientation/Start: November 17, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Engineering Office', '2025-11-08', '2025-11-08'),
(36, 40, 8, 9, 'uploads/1762644952_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762644952_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762644952_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/moa/img029_1761923740.jpg', 'uploads/1762644952_Untitled_design__7_.png', 'approved', 'Orientation/Start: November 17, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2025-11-08', '2025-11-09'),
(37, 41, 8, 9, 'uploads/1762645132_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762645132_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762645132_Deficiencies-of-BSIS-4B-AY-25-26.pdf', '', 'uploads/1762645132_Untitled_design__7_.png', 'ongoing', 'Orientation/Start: November 17, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2025-11-08', '2025-11-09'),
(38, 43, 8, 9, 'uploads/1762769920_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762769920_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762769920_Deficiencies-of-BSIS-4B-AY-25-26.pdf', '', 'uploads/1762769920_563836451_1485251962807957_1963355612580041957_n.jpg', 'approved', 'Orientation/Start: November 18, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2025-11-10', '2025-11-10'),
(40, 45, 6, NULL, 'uploads/1762825595_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762825595_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762825595_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/moa/img029_1761923740.jpg', 'uploads/1762825595_566604494_4276808525876147_3434795590404980034_n.jpg', 'approved', 'Orientation/Start: November 19, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City General Services Office', '2025-11-11', '2025-11-11'),
(41, 46, 11, NULL, 'uploads/1762828184_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762828184_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1762828184_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/moa/img029_1761923740.jpg', 'uploads/1762828184_566604494_4276808525876147_3434795590404980034_n.jpg', 'approved', 'Orientation/Start: November 19, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Mayor\'s Office', '2025-11-11', '2025-11-11'),
(46, 52, 9, NULL, 'uploads/1763209522_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1763209522_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1763209522_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1763209522_563836451_1485251962807957_1963355612580041957_n.jpg', 'approved', 'Orientation/Start: November 23, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2025-11-15', '2025-11-15'),
(47, 53, 9, NULL, 'uploads/1763210308_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1763210308_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/1763210308_Deficiencies-of-BSIS-4B-AY-25-26.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1763210308_553957216_752408427835333_1980390186998591902_n.jpg', 'pending', 'Auto-rejected: Preferred office(s) have reached capacity.', '2025-11-15', '2025-11-15');

-- --------------------------------------------------------

--
-- Table structure for table `orientation_assignments`
--

CREATE TABLE `orientation_assignments` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orientation_assignments`
--

INSERT INTO `orientation_assignments` (`id`, `session_id`, `application_id`, `assigned_at`) VALUES
(3, 3, 35, '2025-11-08 21:48:02'),
(4, 3, 34, '2025-11-08 21:48:49'),
(5, 3, 37, '2025-11-09 00:00:43'),
(6, 3, 36, '2025-11-09 00:05:52'),
(7, 4, 38, '2025-11-10 10:38:12'),
(8, 4, 31, '2025-11-10 12:20:21'),
(9, 5, 40, '2025-11-11 02:51:37'),
(10, 5, 41, '2025-11-11 02:52:11'),
(11, 6, 46, '2025-11-15 12:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `orientation_sessions`
--

CREATE TABLE `orientation_sessions` (
  `session_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `location` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orientation_sessions`
--

INSERT INTO `orientation_sessions` (`session_id`, `session_date`, `session_time`, `location`) VALUES
(1, '2025-11-13', '08:30:00', 'CHRMO/3rd Floor'),
(2, '2025-11-14', '08:30:00', 'CHRMO/3rd Floor'),
(3, '2025-11-17', '08:30:00', 'CHRMO/3rd Floor'),
(4, '2025-11-18', '08:30:00', 'CHRMO/3rd Floor'),
(5, '2025-11-19', '08:30:00', 'CHRMO/3rd Floor'),
(6, '2025-11-23', '08:30:00', 'CHRMO/3rd Floor');

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
  `school_year` varchar(20) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `school_address` varchar(255) DEFAULT NULL,
  `ojt_adviser` varchar(100) DEFAULT NULL,
  `adviser_contact` varchar(20) DEFAULT NULL,
  `total_hours_required` int(11) DEFAULT 500,
  `hours_rendered` int(11) DEFAULT 0,
  `progress` decimal(5,2) GENERATED ALWAYS AS (`hours_rendered` / `total_hours_required` * 100) STORED,
  `status` enum('pending','approved','ongoing','completed','rejected','deactivated') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `address`, `contact_number`, `email`, `birthday`, `emergency_name`, `emergency_relation`, `emergency_contact`, `college`, `course`, `year_level`, `school_year`, `semester`, `school_address`, `ojt_adviser`, `adviser_contact`, `total_hours_required`, `hours_rendered`, `status`) VALUES
(35, 39, 'Julia', NULL, 'Cruz', 'Guiguinto Bulacan', '09123456789', 'roblesjenny0326@gmail.com', '2003-07-11', 'Joyce Cruz', 'Siblings', '09987654321', 'STI College – Malolos', 'Bachelor of Science in Office Administration', '4', NULL, NULL, 'Dakila, McArthur Highway, Malolos City, Bulacan ', 'Migs Gatchalian', '09111111111', 500, 0, 'approved'),
(38, 35, 'Jasmine', NULL, 'Santiago', 'Malolos, Bulacan', '09269317441', 'roblesjenny0326@gmail.com', '2002-11-06', 'Jenny Santiago', 'Siblings', '09987654321', 'Bulacan Polytechnic College', 'Bachelor of Science in Electrical Engineering', '4', NULL, NULL, 'Bulihan Malolos Bulacan', 'Migs Gatchalian', '09111111111', 500, 0, 'ongoing'),
(39, 34, 'Jasmin', NULL, 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2004-10-08', 'Rosaly Santiago', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Civil Engineering', '3', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'ongoing'),
(40, 37, 'John', NULL, 'Coria', 'Sta. Maria, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2025-10-13', 'Jen Robles', 'Father', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '4', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'approved'),
(41, 36, 'Jeremiah', NULL, 'Ong', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09457842558', 'santiagojasminem@gmail.com', '2025-11-04', 'Geo Ong', 'Father', '09134664654', 'Bulacan State University', 'Bachelor of Science in Accounting Information System', '3', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'ongoing'),
(43, 38, 'Vanessa', NULL, 'Manuel', 'Malolos, Bulacan', '', 'santiagojasminem@gmail.com', NULL, 'Lily', '', '', 'Centro Escolar University – Malolos Campus', 'Bachelor of Science in Accounting Information System', '4', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'approved'),
(45, 40, 'Jadon', NULL, 'Ong', 'Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2007-10-31', 'Janice Ong', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Office Administration', '4', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'approved'),
(46, 41, 'Arvin', NULL, 'Ong', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2007-11-10', 'Janice Ong', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Office Administration', '4', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'approved'),
(52, 45, 'Angelie', NULL, 'Mendoza', 'Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2006-01-24', 'Marilou Ong', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '4', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending'),
(53, NULL, 'Adie', NULL, 'Lumanlan', 'Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2007-11-14', 'Rosaly Santiago', 'Guardian', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '3', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending');

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
  `password` varchar(255) NOT NULL,
  `role` enum('ojt','hr_head','hr_staff','office_head') NOT NULL,
  `office_name` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','approved','ongoing','completed','deactivated') DEFAULT 'active',
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `first_name`, `middle_name`, `last_name`, `password`, `role`, `office_name`, `status`, `date_created`) VALUES
(5, 'hrhead', NULL, 'Cecilia', NULL, 'Ramos', '123456', 'hr_head', NULL, 'active', '2025-10-12 13:34:28'),
(6, 'hrstaff', 'santiagojasminem@gmail.com', 'Andrea', NULL, 'Lopez', '123456', 'hr_staff', NULL, 'active', '2025-10-12 13:34:28'),
(9, 'head_it', 'carloreyes@gmail.com', 'Carlo', NULL, 'Reyes', '123456', 'office_head', 'IT', 'active', '2025-10-12 13:34:28'),
(25, 'narchibald833', 'nate@gmail.com', 'Nate', NULL, 'Archibald', 'smlVMRHYSY', 'office_head', 'City General Services Office', 'active', '2025-11-07 07:13:06'),
(27, 'lgarcia811', 'santiagojasminem@gmail.com', 'Layla', NULL, 'Garcia', 'pZEiw1LxZm', 'office_head', 'City Budget Office', 'active', '2025-11-07 09:58:55'),
(29, 'cbass610', 'santiagojasminem@gmail.com', 'Charles', NULL, 'Bass', 'qGKHPLR8Eo', 'office_head', 'City Accounting Office', 'active', '2025-11-08 07:58:24'),
(32, 'fdurupa407', 'santiagojasminem@gmail.com', 'Fredy', NULL, 'Durupa', '2uDY8lTdbm', 'office_head', 'City Mayor\'s Office', 'active', '2025-11-08 08:50:03'),
(33, 'rmilan347', 'santiagojasminem@gmail.com', 'Rico', NULL, 'Milan', 'UN8BMbOyne', 'office_head', 'City Engineering Office', 'active', '2025-11-08 10:24:44'),
(34, 'santiagojasminem2', NULL, NULL, NULL, NULL, '8b58db06d7', 'ojt', 'City Engineering Office', 'ongoing', '2025-11-08 21:48:02'),
(35, 'roblesjenny0326', NULL, NULL, NULL, NULL, '0c0789741b', 'ojt', 'City Engineering Office', 'ongoing', '2025-11-08 21:48:49'),
(36, 'santiagojasminem4', NULL, NULL, NULL, NULL, 'd16064a812', 'ojt', 'City Budget Office', 'ongoing', '2025-11-09 00:00:43'),
(37, 'santiagojasminem5', NULL, NULL, NULL, NULL, 'e27ea9aec4', 'ojt', 'City Accounting Office', 'approved', '2025-11-09 00:05:52'),
(38, 'santiagojasminem6', NULL, NULL, NULL, NULL, '7ea8782bf2', 'ojt', 'City Accounting Office', 'approved', '2025-11-10 10:38:12'),
(39, 'roblesjenny03261', NULL, NULL, NULL, NULL, 'e87bea7663', 'ojt', 'City Mayor\'s Office', 'approved', '2025-11-10 12:20:21'),
(40, 'santiagojasminem7', NULL, NULL, NULL, NULL, '5f8c12fef6', 'ojt', 'City General Services Office', 'approved', '2025-11-11 02:51:37'),
(41, 'santiagojasminem8', NULL, NULL, NULL, NULL, '736c1490a6', 'ojt', 'City Mayor\'s Office', 'approved', '2025-11-11 02:52:11'),
(44, 'mvelasco953', 'santiagojasminem@gmail.com', 'Myrna', NULL, 'Velasco', '123456', 'hr_staff', NULL, 'active', '2025-11-15 10:54:14'),
(45, 'santiagojasminem', NULL, NULL, NULL, NULL, '0f67670072', 'ojt', 'City Accounting Office', 'approved', '2025-11-15 12:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `weekly_journal`
--

CREATE TABLE `weekly_journal` (
  `journal_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `week_coverage` varchar(50) DEFAULT NULL,
  `date_uploaded` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `dtr`
--
ALTER TABLE `dtr`
  MODIFY `dtr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `moa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `office_courses`
--
ALTER TABLE `office_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `office_requests`
--
ALTER TABLE `office_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `orientation_assignments`
--
ALTER TABLE `orientation_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orientation_sessions`
--
ALTER TABLE `orientation_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

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
