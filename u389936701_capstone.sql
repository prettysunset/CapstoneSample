-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 18, 2025 at 10:10 PM
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
(2, NULL, 'Bachelor of Science in Office Administration'),
(3, NULL, 'Bachelor of Science in Accountancy'),
(5, NULL, 'Bachelor of Science in Accounting Information System'),
(6, NULL, 'BSBA Major in Financial Management'),
(10, NULL, 'Bachelor of Science in Office Management');

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

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `eval_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `rating` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
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
(8, 'City Budget Office', 3, 0, NULL, NULL, 'Approved'),
(9, 'City Accounting Office', 5, 0, NULL, NULL, 'Approved'),
(14, 'City Admin Office', 2, 0, NULL, NULL, 'Approved');

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
(29, 8, NULL, 3, 'increased workload due to holiday season', 'approved', '2025-11-18', '2025-11-18 13:05:34');

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
(56, 66, 8, NULL, 'uploads/1763467842_LETTER_OF_INTENT.pdf', 'uploads/1763467842_ENDORSEMENTLETTER.pdf', 'uploads/1763467842_RESUME.pdf', '', 'uploads/1763467842_formalpic.jpg', 'approved', 'Orientation/Start: November 26, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2025-11-18', '2025-11-18'),
(57, 67, 8, 9, 'uploads/1763468321_LETTER_OF_INTENT.pdf', 'uploads/1763468321_ENDORSEMENTLETTER.pdf', 'uploads/1763468321_RESUME.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1763468321_formalpic.jpg', 'rejected', 'incorrect requirements', '2025-11-18', '2025-11-18'),
(58, 68, 8, NULL, 'uploads/1763468527_LETTER_OF_INTENT.pdf', 'uploads/1763468527_ENDORSEMENTLETTER.pdf', 'uploads/1763468527_Resume.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1763468527_formalpic.jpg', 'approved', 'Orientation/Start: November 26, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2025-11-18', '2025-11-18');

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
(17, 9, 56, '2025-11-18 12:55:25'),
(18, 9, 58, '2025-11-18 13:04:39');

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
(6, '2025-11-23', '08:30:00', 'CHRMO/3rd Floor'),
(7, '2025-11-24', '08:30:00', 'CHRMO/3rd Floor'),
(8, '2025-11-25', '08:30:00', 'CHRMO/3rd Floor'),
(9, '2025-11-26', '08:30:00', 'CHRMO/3rd Floor');

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
(66, 54, 'Elisha', NULL, 'Lumanlan', 'Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2007-10-15', 'Ann Lumanlan', 'Mother', '09134664654', 'AMA Computer College – Malolos', 'Bachelor of Science in Accountancy', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', NULL),
(67, NULL, 'Angel', NULL, 'Mendoza', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2007-09-08', 'Maria Mendoza', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', 'incorrect requirements'),
(68, 55, 'Mikaili', NULL, 'Mesia', 'Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2007-11-17', 'Maria Rosario', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', NULL);

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
  `status` enum('active','inactive','approved','ongoing','completed','evaluated','deactivated') DEFAULT 'active',
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `first_name`, `middle_name`, `last_name`, `password`, `role`, `office_name`, `status`, `date_created`) VALUES
(5, 'hrhead', NULL, 'Cecilia', NULL, 'Ramos', '123456', 'hr_head', NULL, 'active', '2025-10-12 13:34:28'),
(6, 'hrstaff', 'santiagojasminem@gmail.com', 'Andrea', NULL, 'Lopez', '123456', 'hr_staff', NULL, 'active', '2025-10-12 13:34:28'),
(27, 'headbudget', 'santiagojasminem@gmail.com', 'Layla', NULL, 'Garcia', '123456', 'office_head', 'City Budget Office', 'active', '2025-11-07 09:58:55'),
(29, 'cbass610', 'santiagojasminem@gmail.com', 'Charles', NULL, 'Bass', 'qGKHPLR8Eo', 'office_head', 'City Accounting Office', 'active', '2025-11-08 07:58:24'),
(50, 'jdiamante370', 'jenny.robles@bpc.edu.ph', 'Jimwell', NULL, 'Diamante', '%BCJbqY3U4', 'office_head', 'City Admin Office', 'active', '2025-11-17 01:56:34'),
(54, 'santiagojasminem', NULL, NULL, NULL, NULL, 'b74f41efeb', 'ojt', 'City Budget Office', 'approved', '2025-10-18 12:55:25'),
(55, 'santiagojasminem1', NULL, NULL, NULL, NULL, '03e3822a6d', 'ojt', 'City Budget Office', 'approved', '2025-11-18 13:04:39');

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
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dtr`
--
ALTER TABLE `dtr`
  MODIFY `dtr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `moa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `office_courses`
--
ALTER TABLE `office_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `office_requests`
--
ALTER TABLE `office_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `orientation_assignments`
--
ALTER TABLE `orientation_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `orientation_sessions`
--
ALTER TABLE `orientation_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
