-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 08, 2025 at 05:00 AM
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
-- Database: `capstone`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(28, 9, '2025-11-02', NULL, NULL, '12:59', '15:59', 3, 0);

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
(8, 'Bulacan Polytechnic College', 'uploads/moa/img029_1761923740.jpg', '2025-10-31', 3);

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
(1, 'Accounting Office', 15, 15, 20, 'Increase slots due to high number of applicants', 'Pending'),
(2, 'IT Office', 10, 10, NULL, NULL, 'Approved'),
(3, 'Human Resources', 10, 10, 12, 'Assist in employee profiling and documentation', 'Pending'),
(4, 'City Planning Office', 6, 6, 10, 'Assist with mapping and data digitization', 'Pending'),
(5, 'Treasury Office', 12, 12, 15, 'Help with financial records encoding', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `office_courses`
--

CREATE TABLE `office_courses` (
  `id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `date_requested` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_requests`
--

INSERT INTO `office_requests` (`request_id`, `office_id`, `old_limit`, `new_limit`, `reason`, `status`, `date_requested`) VALUES
(1, 4, 6, 10, 'Assist with mapping and data digitization', 'pending', '2025-10-20'),
(2, 2, 8, 10, 'Need additional interns for system updates', 'approved', '2025-10-26');

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
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `date_submitted` date DEFAULT NULL,
  `date_updated` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ojt_applications`
--

INSERT INTO `ojt_applications` (`application_id`, `student_id`, `office_preference1`, `office_preference2`, `letter_of_intent`, `endorsement_letter`, `resume`, `moa_file`, `picture`, `status`, `remarks`, `date_submitted`, `date_updated`) VALUES
(9, 9, 2, 4, 'uploads/1761223567_id.jpg', 'uploads/1761223567_id.jpg', 'uploads/1761223567_id.jpg', '', 'uploads/1761223567_id.jpg', 'approved', 'Orientation/Start: 2025-10-28 | Assigned Office: IT Office', '2025-10-23', '2025-10-25'),
(27, 28, 2, NULL, 'uploads/1762223192_60_percent_checklist.pdf', 'uploads/1762223192_60_percent_checklist.pdf', 'uploads/1762223192_60_percent_checklist.pdf', 'uploads/moa/img029_1761923740.jpg', 'uploads/1762223192_566604494_4276808525876147_3434795590404980034_n.jpg', 'approved', 'Orientation/Start: November 11, 2025 | Assigned Office: IT Office', '2025-11-04', '2025-11-04'),
(28, 29, 2, NULL, 'uploads/1762224372_60_percent_checklist.pdf', 'uploads/1762224372_60_percent_checklist.pdf', 'uploads/1762224372_60_percent_checklist.pdf', '', 'uploads/1762224372_566604494_4276808525876147_3434795590404980034_n.jpg', 'approved', 'Orientation/Start: November 13, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: IT Office', '2025-11-04', '2025-11-06');

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
(1, 1, 28, '2025-11-06 06:20:22');

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
(1, '2025-11-13', '08:30:00', 'CHRMO/3rd Floor');

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
  `status` enum('pending','ongoing','completed') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `address`, `contact_number`, `email`, `birthday`, `emergency_name`, `emergency_relation`, `emergency_contact`, `college`, `course`, `year_level`, `school_year`, `semester`, `school_address`, `ojt_adviser`, `adviser_contact`, `total_hours_required`, `hours_rendered`, `status`) VALUES
(9, 13, 'Jim Well', NULL, 'Diamante', 'Bagna', '09454659878', 'jimwell@gmail.com', '2004-11-06', 'Jampol', 'Father', '09345646546', 'Bulacan Polytechnic College', 'BSIS-4B', '4', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'ongoing'),
(28, 19, 'Jasmine', NULL, 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2004-11-06', 'Rosaly Santiago', 'mother', '09345646546', 'La Consolacion University Philippines', 'Bachelor of Science in Information Technology', '4', '2025 - 2026', '1st Semester', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'ongoing'),
(29, 20, 'Blair', NULL, 'Waldorf', 'Sumapang Matanda, Malolos, Bulacan', '09457842558', 'santiagojasminem@gmail.com', '2004-11-06', 'Myrna Waldorf', 'Mother', '09134664654', 'La Consolacion University Philippines', 'Bachelor of Science in Information Technology', '4', NULL, NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'ongoing');

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
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `first_name`, `middle_name`, `last_name`, `password`, `role`, `office_name`, `status`, `date_created`) VALUES
(5, 'hrhead', NULL, 'Cecilia', NULL, 'Ramos', '123456', 'hr_head', NULL, 'active', '2025-10-12 13:34:28'),
(6, 'hrstaff', NULL, 'Andrea', NULL, 'Lopez', '123456', 'hr_staff', NULL, 'active', '2025-10-12 13:34:28'),
(8, 'head_accounting', NULL, 'Maria', NULL, 'Santos', '123456', 'office_head', 'Accounting', 'active', '2025-10-12 13:34:28'),
(9, 'head_it', 'carloreyes@gmail.com', 'Carlo', NULL, 'Reyes', '123456', 'office_head', 'IT', 'active', '2025-10-12 13:34:28'),
(10, 'head_cityplanning', NULL, 'Angela', NULL, 'Bautista', '123456', 'office_head', 'City Planning', 'active', '2025-10-12 13:34:28'),
(13, 'jimwell', NULL, NULL, NULL, NULL, '60a69c38c2', 'ojt', 'IT Office', 'active', '2025-10-25 12:09:07'),
(19, 'santiagojasminem3', NULL, NULL, NULL, NULL, '378d162747', 'ojt', 'IT Office', 'active', '2025-11-04 03:01:25'),
(20, 'santiagojasminem', NULL, NULL, NULL, NULL, '59d0650e0f', 'ojt', 'IT Office', 'active', '2025-11-06 06:20:22'),
(21, 'narchibald439', 'santiagojasminem@gmail.com', 'Nate', NULL, 'Archibald', 'd9q0VQae2i', 'office_head', 'City General Services Office', 'active', '2025-11-07 06:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `weekly_journal`
--

CREATE TABLE `weekly_journal` (
  `journal_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `week_coverage` varchar(50) DEFAULT NULL,
  `date_uploaded` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','declined') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

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
  ADD KEY `student_id` (`student_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
