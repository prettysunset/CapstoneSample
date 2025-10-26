-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 26, 2025 at 02:47 AM
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
(23, 7, '2025-10-19', NULL, NULL, '17:53', '17:53', 0, 0),
(24, 7, '2025-10-23', NULL, NULL, '20:50', NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `eval_id` int(11) NOT NULL,
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
(2, 'IT Office', 8, 8, 10, 'Need additional interns for system updates', 'Pending'),
(3, 'Human Resources', 10, 10, 12, 'Assist in employee profiling and documentation', 'Pending'),
(4, 'City Planning Office', 6, 6, 10, 'Assist with mapping and data digitization', 'Pending'),
(5, 'Treasury Office', 12, 12, 15, 'Help with financial records encoding', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `office_heads`
--

CREATE TABLE `office_heads` (
  `office_head_id` int(11) NOT NULL,
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
(1, 4, 6, 10, 'Assist with mapping and data digitization', 'pending', '2025-10-20');

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
(4, 4, 1, 5, 'uploads/1760661601_slip.jpg', 'uploads/1760661601_slip.jpg', 'uploads/1760661601_slip.jpg', '', 'uploads/1760661601_slip.jpg', 'approved', 'Orientation/Start: 2025-11-05 | Assigned Office: Accounting Office', '2025-10-17', '2025-10-19'),
(5, 5, 2, 5, 'uploads/1760669191_slip.jpg', 'uploads/1760669191_slip.jpg', 'uploads/1760669191_slip.jpg', '', 'uploads/1760669191_slip.jpg', 'approved', 'Orientation/Start: 2025-11-07 | Assigned Office: IT Office', '2025-10-17', '2025-10-19'),
(6, 6, 4, 1, 'uploads/1760782138_slip.jpg', 'uploads/1760782138_slip.jpg', 'uploads/1760782138_slip.jpg', '', 'uploads/1760782138_slip.jpg', 'approved', 'Orientation/Start: 2025-10-31 | Assigned Office: City Planning Office', '2025-10-18', '2025-10-18'),
(7, 7, 5, 1, 'uploads/1760844427_slip.jpg', 'uploads/1760844427_slip.jpg', 'uploads/1760844427_slip.jpg', '', 'uploads/1760844427_slip.jpg', 'approved', 'Orientation/Start: 2025-10-30 | Assigned Office: Treasury Office', '2025-10-19', '2025-10-19'),
(8, 8, 2, 1, 'uploads/1761044275_525492880_1271238488076033_6737501998408687547_n.jpg', 'uploads/1761044275_525492880_1271238488076033_6737501998408687547_n.jpg', 'uploads/1761044275_525492880_1271238488076033_6737501998408687547_n.jpg', '', 'uploads/1761044275_525492880_1271238488076033_6737501998408687547_n.jpg', 'rejected', 'course is not aligned', '2025-10-21', '2025-10-21'),
(9, 9, 2, 4, 'uploads/1761223567_id.jpg', 'uploads/1761223567_id.jpg', 'uploads/1761223567_id.jpg', '', 'uploads/1761223567_id.jpg', 'approved', 'Orientation/Start: 2025-10-28 | Assigned Office: IT Office', '2025-10-23', '2025-10-25'),
(10, 10, 2, 4, 'uploads/1761223646_id.jpg', 'uploads/1761223646_id.jpg', 'uploads/1761223646_id.jpg', '', 'uploads/1761223646_id.jpg', 'approved', 'Orientation/Start: 2025-10-29 | Assigned Office: IT Office', '2025-10-23', '2025-10-25'),
(11, 11, 2, 1, 'uploads/sample_loi_1.pdf', 'uploads/sample_end_1.pdf', 'uploads/sample_res_1.pdf', '', 'uploads/sample_pic_1.jpg', 'pending', 'Test submission', '2025-10-15', NULL),
(12, 12, 1, 3, 'uploads/sample_loi_2.pdf', 'uploads/sample_end_2.pdf', 'uploads/sample_res_2.pdf', '', 'uploads/sample_pic_2.jpg', 'pending', 'Test submission', '2025-10-16', NULL),
(13, 13, 2, 5, 'uploads/sample_loi_3.pdf', 'uploads/sample_end_3.pdf', 'uploads/sample_res_3.pdf', '', 'uploads/sample_pic_3.jpg', 'pending', 'Test submission', '2025-10-16', NULL),
(14, 14, 3, 2, 'uploads/sample_loi_4.pdf', 'uploads/sample_end_4.pdf', 'uploads/sample_res_4.pdf', '', 'uploads/sample_pic_4.jpg', 'approved', 'Orientation/Start: 2025-11-05 | Assigned Office: Human Resources', '2025-10-10', NULL),
(15, 15, 1, 5, 'uploads/sample_loi_5.pdf', 'uploads/sample_end_5.pdf', 'uploads/sample_res_5.pdf', '', 'uploads/sample_pic_5.jpg', 'approved', 'Orientation/Start: 2025-11-07 | Assigned Office: Accounting Office', '2025-10-12', NULL),
(16, 16, 2, 4, 'uploads/sample_loi_6.pdf', 'uploads/sample_end_6.pdf', 'uploads/sample_res_6.pdf', '', 'uploads/sample_pic_6.jpg', 'rejected', 'Incomplete documents', '2025-10-14', NULL),
(17, 17, 2, 3, 'uploads/sample_loi_7.pdf', 'uploads/sample_end_7.pdf', 'uploads/sample_res_7.pdf', '', 'uploads/sample_pic_7.jpg', 'pending', 'Test submission', '2025-10-18', NULL),
(18, 18, 5, 1, 'uploads/sample_loi_8.pdf', 'uploads/sample_end_8.pdf', 'uploads/sample_res_8.pdf', '', 'uploads/sample_pic_8.jpg', 'pending', 'Test submission', '2025-10-17', NULL),
(19, 19, 4, 2, 'uploads/sample_loi_9.pdf', 'uploads/sample_end_9.pdf', 'uploads/sample_res_9.pdf', '', 'uploads/sample_pic_9.jpg', 'pending', 'Test submission', '2025-10-19', NULL),
(20, 20, 1, 3, 'uploads/sample_loi_10.pdf', 'uploads/sample_end_10.pdf', 'uploads/sample_res_10.pdf', '', 'uploads/sample_pic_10.jpg', 'pending', 'Test submission', '2025-10-20', NULL),
(21, 21, 2, NULL, 'uploads/1761442672_slip.jpg', 'uploads/1761442672_slip.jpg', 'uploads/1761442672_slip.jpg', '', 'uploads/1761442672_slip.jpg', 'pending', NULL, '2025-10-26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
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

INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `last_name`, `address`, `contact_number`, `email`, `birthday`, `emergency_name`, `emergency_relation`, `emergency_contact`, `college`, `course`, `year_level`, `school_address`, `ojt_adviser`, `adviser_contact`, `total_hours_required`, `hours_rendered`, `status`) VALUES
(1, NULL, 'Jasmine', 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', NULL, 'memen', 'mother', '09134664654', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'pending'),
(2, NULL, 'Jasmine', 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', NULL, 'memen', 'mother', '09134664654', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'pending'),
(3, NULL, 'John Paul', 'Sayo', 'Pulilan', '09457842558', 'jasmine.santiago@bpc.edu.ph', NULL, 'Jampol', 'Father', '09345646546', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'pending'),
(4, 11, 'Jasmine', 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', NULL, 'memen', 'mother', '09134664654', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'ongoing'),
(5, NULL, 'Blair', 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', NULL, 'memen', 'mother', '09134664654', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'ongoing'),
(6, NULL, 'Jasmine', 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09457842558', 'santiagojasminem@gmail.com', NULL, 'memen', 'mother', '09134664654', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'ongoing'),
(7, 12, 'John Paul', 'Sayo', 'Pulilan', '09454659878', 'santiagojasminem@gmail.com', NULL, 'Jampol', 'Father', '09345646546', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'ongoing'),
(8, NULL, 'Jenny ', 'Robles', 'Sumapang Matanda, Malolos, Bulacan', '09454659878', 'jasmine.santiago@bpc.edu.ph', NULL, 'Jen', 'mother', '09134664654', 'Centro Escolar University – Malolos Campus', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 250, 0, 'pending'),
(9, 13, 'Jim Well', 'Diamante', 'Bagna', '09454659878', 'jimwell@gmail.com', NULL, 'Jampol', 'Father', '09345646546', 'Bulacan Polytechnic College', 'BSIS-4B', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'ongoing'),
(10, 14, 'Jim Well', 'Diamante', 'Bagna', '09454659878', 'jimwelldiamante@gmail.com', NULL, 'Jampol', 'Father', '09345646546', 'Bulacan Polytechnic College', 'BSIS-4B', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '08089989898', 500, 0, 'ongoing'),
(11, NULL, 'Arvin', 'Delos Santos', 'Blk 3 Lot 12, Brgy. San Rafael, Malolos, Bulacan', '09171230001', 'arvin.delossantos@example.com', NULL, 'Maribel Delos Santos', 'Mother', '09171230002', 'Bulacan Polytechnic College', 'BS Information Systems', '4', 'Brgy. San Rafael, Malolos', 'Dr. Liza Ramos', '09171230010', 500, 0, 'pending'),
(12, NULL, 'Beatriz', 'Mendoza', 'Poblacion West, Hagonoy, Bulacan', '09172230011', 'beatriz.mendoza@example.com', NULL, 'Ricardo Mendoza', 'Father', '09172230012', 'Bulacan State University', 'BS Accountancy', '4', 'Poblacion West, Hagonoy', 'Prof. Josephine Cruz', '09172230020', 500, 0, 'pending'),
(13, NULL, 'Carl', 'Reyes', 'Brgy. San Jose, Baliuag, Bulacan', '09173230021', 'carl.reyes@example.com', NULL, 'Lorna Reyes', 'Mother', '09173230022', 'Baliuag University', 'BS Information Technology', '3', 'Brgy. San Jose, Baliuag', 'Engr. Mark Dela Cruz', '09173230030', 500, 0, 'pending'),
(14, NULL, 'Diana', 'Lopez', 'Km 36 MacArthur Hi-way, Pulilan, Bulacan', '09174230031', 'diana.lopez@example.com', NULL, 'Ana Lopez', 'Mother', '09174230032', 'La Consolacion University Philippines', 'BS Nursing', '3', 'MacArthur Highway, Pulilan', 'Dr. Mary Ann Reyes', '09174230040', 500, 0, ''),
(15, NULL, 'Edgar', 'Garcia', 'Brgy. Poblacion, Meycauayan, Bulacan', '09175230041', 'edgar.garcia@example.com', NULL, 'Rosa Garcia', 'Mother', '09175230042', 'Meycauayan College', 'BS Accounting Information System', '4', 'Meycauayan City', 'Prof. Liza Cortez', '09175230050', 500, 120, 'ongoing'),
(16, NULL, 'Fatima', 'Santos', 'Blk 7, Brgy. Sta. Cruz, San Jose del Monte, Bulacan', '09176230051', 'fatima.santos@example.com', NULL, 'Jose Santos', 'Father', '09176230052', 'AMA Computer College – Malolos', 'BS Information Systems', '4', 'Malolos Campus', 'Dr. Romualdo', '09176230060', 500, 0, ''),
(17, NULL, 'Gino', 'Valdez', 'Brgy. San Isidro, City of Malolos, Bulacan', '09177230061', 'gino.valdez@example.com', NULL, 'Marta Valdez', 'Mother', '09177230062', 'Asian Institute of Computer Studies – Malolos', 'BS Computer Science', '2', 'Malolos', 'Prof. Allan Perez', '09177230070', 500, 0, 'pending'),
(18, NULL, 'Hannah', 'Ramos', 'Villasis St., Brgy. San Agustin, Calumpit, Bulacan', '09178230071', 'hannah.ramos@example.com', NULL, 'Liza Ramos', 'Mother', '09178230072', 'St. Mary’s College of Meycauayan', 'BS Tourism Management', '3', 'Meycauayan', 'Dr. Sheila Bautista', '09178230080', 500, 0, 'pending'),
(19, NULL, 'Ian', 'Delacruz', 'Purok 5, Brgy. San Roque, Plaridel, Bulacan', '09179230081', 'ian.delacruz@example.com', NULL, 'Nelly Delacruz', 'Mother', '09179230082', 'Immaculate Conception International College of Arts and Technology', 'BS Business Administration', '4', 'Plaridel Campus', 'Prof. Edwin Navarro', '09179230090', 500, 0, 'pending'),
(20, NULL, 'Joana', 'Velasco', 'Brgy. Baywalk, Hagonoy, Bulacan', '09170230091', 'joana.velasco@example.com', NULL, 'Rogelio Velasco', 'Father', '09170230092', 'La Verdad Christian College – Apalit', 'BS Criminology', '3', 'Apalit Campus', 'Dr. Teresa L. Cruz', '09170230100', 500, 0, 'pending'),
(21, NULL, 'Jasmin', 'Santiago', '#0547 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', NULL, 'memen', 'mother', '09134664654', 'Bulacan Polytechnic College', 'BSIS', '4', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
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

INSERT INTO `users` (`user_id`, `username`, `first_name`, `middle_name`, `last_name`, `password`, `role`, `office_name`, `status`, `date_created`) VALUES
(5, 'hrhead', 'Cecilia', NULL, 'Ramos', '123456', 'hr_head', NULL, 'active', '2025-10-12 13:34:28'),
(6, 'hrstaff', 'Andrea', NULL, 'Lopez', '123456', 'hr_staff', NULL, 'active', '2025-10-12 13:34:28'),
(7, 'ojtjuan', 'Juan', NULL, 'Dela Cruz', '123456', 'ojt', 'Accounting', 'active', '2025-10-12 13:34:28'),
(8, 'head_accounting', 'Maria', NULL, 'Santos', '123456', 'office_head', 'Accounting', 'active', '2025-10-12 13:34:28'),
(9, 'head_it', 'Carlo', NULL, 'Reyes', '123456', 'office_head', 'IT', 'active', '2025-10-12 13:34:28'),
(10, 'head_cityplanning', 'Angela', NULL, 'Bautista', '123456', 'office_head', 'City Planning', 'active', '2025-10-12 13:34:28'),
(11, 'santiagojasminem', NULL, NULL, NULL, '$2y$10$J9oKj44vbZTs9DlbPngg9OJwjxCTPvXjuM7B5lVx/PiSkkqHkvUUy', 'ojt', 'Accounting Office', 'active', '2025-10-19 03:15:03'),
(12, 'santiagojasminem1', NULL, NULL, NULL, '8fbe6a7954', 'ojt', 'Treasury Office', 'active', '2025-10-19 03:27:30'),
(13, 'jimwell', NULL, NULL, NULL, '60a69c38c2', 'ojt', 'IT Office', 'active', '2025-10-25 12:09:07'),
(14, 'jimwelldiamante', NULL, NULL, NULL, '25aa9957ea', 'ojt', 'IT Office', 'active', '2025-10-25 12:09:18');

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
  ADD KEY `office_head_id` (`office_head_id`);

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
-- Indexes for table `office_heads`
--
ALTER TABLE `office_heads`
  ADD PRIMARY KEY (`office_head_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `office_id` (`office_id`);

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

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dtr`
--
ALTER TABLE `dtr`
  MODIFY `dtr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
  MODIFY `moa_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `office_heads`
--
ALTER TABLE `office_heads`
  MODIFY `office_head_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_requests`
--
ALTER TABLE `office_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`office_head_id`) REFERENCES `office_heads` (`office_head_id`);

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
-- Constraints for table `office_heads`
--
ALTER TABLE `office_heads`
  ADD CONSTRAINT `office_heads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `office_heads_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`);

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
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  ADD CONSTRAINT `weekly_journal_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
