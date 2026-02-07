-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 07, 2026 at 07:07 AM
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
(11, NULL, 'BSOM'),
(12, NULL, 'Bachelor of Science in Public Administration');

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
(75, 54, '2025-08-21', '08:00', '12:00', NULL, NULL, 4, 0),
(76, 54, '2025-08-22', '07:51', '12:02', '12:56', '17:02', 8, 0),
(77, 54, '2025-08-25', '08:02', '12:06', '12:54', '17:11', 8, 0),
(78, 54, '2025-08-26', '07:57', '12:03', '12:51', '17:08', 8, 0),
(79, 54, '2025-08-27', '08:00', '12:00', '13:00', '17:00', 8, 0),
(80, 54, '2025-08-28', '07:51', '12:02', '12:56', '17:02', 8, 0),
(81, 54, '2025-08-29', '08:02', '12:06', '12:54', '17:11', 8, 0),
(82, 54, '2025-09-01', '07:57', '12:03', '12:51', '17:08', 8, 0),
(83, 54, '2025-09-02', '08:00', '12:00', '13:00', '17:00', 8, 0),
(84, 54, '2025-09-03', '07:51', '12:02', '12:56', '17:02', 8, 0),
(85, 54, '2025-09-04', '08:02', '12:06', '12:54', '17:11', 8, 0),
(86, 54, '2025-09-05', '07:57', '12:03', '12:51', '17:08', 8, 0),
(87, 54, '2025-09-08', '08:00', '12:00', '13:00', '17:00', 8, 0),
(88, 54, '2025-09-09', '07:51', '12:02', '12:56', '17:02', 8, 0),
(89, 54, '2025-09-10', '08:02', '12:06', '12:54', '17:11', 8, 0),
(90, 54, '2025-09-11', '07:57', '12:03', '12:51', '17:08', 8, 0),
(91, 54, '2025-09-12', '08:00', '12:00', '13:00', '17:00', 8, 0),
(92, 54, '2025-09-15', '07:51', '12:02', '12:56', '17:02', 8, 0),
(93, 54, '2025-09-16', '08:02', '12:06', '12:54', '17:11', 8, 0),
(94, 54, '2025-09-17', '07:57', '12:03', '12:51', '17:08', 8, 0),
(95, 54, '2025-09-18', '08:00', '12:00', '13:00', '17:00', 8, 0),
(96, 54, '2025-09-19', '07:51', '12:02', '12:56', '17:02', 8, 0),
(97, 54, '2025-09-22', '08:02', '12:06', '12:54', '17:11', 8, 0),
(98, 54, '2025-09-23', '07:57', '12:03', '12:51', '17:08', 8, 0),
(99, 54, '2025-09-24', '08:00', '12:00', '13:00', '17:00', 8, 0),
(100, 54, '2025-09-25', '07:51', '12:02', '12:56', '17:02', 8, 0),
(101, 54, '2025-09-26', '08:02', '12:06', '12:54', '17:11', 8, 0),
(102, 54, '2025-09-29', '07:57', '12:03', '12:51', '17:08', 8, 0),
(103, 54, '2025-09-30', '08:00', '12:00', '13:00', '17:00', 8, 0),
(104, 54, '2025-10-01', '07:51', '12:02', '12:56', '17:02', 8, 0),
(105, 54, '2025-10-02', '08:02', '12:06', '12:54', '17:11', 8, 0),
(106, 54, '2025-10-03', '07:57', '12:03', '12:51', '17:08', 8, 0),
(107, 54, '2025-10-06', '08:00', '12:00', '13:00', '17:00', 8, 0),
(108, 54, '2025-10-07', '07:51', '12:02', '12:56', '17:02', 8, 0),
(109, 54, '2025-10-08', '08:02', '12:06', '12:54', '17:11', 8, 0),
(110, 54, '2025-10-09', '07:57', '12:03', '12:51', '17:08', 8, 0),
(111, 54, '2025-10-10', '08:00', '12:00', '13:00', '17:00', 8, 0),
(112, 54, '2025-10-13', '07:51', '12:02', '12:56', '17:02', 8, 0),
(113, 54, '2025-10-14', '08:02', '12:06', '12:54', '17:11', 8, 0),
(114, 54, '2025-10-15', '07:57', '12:03', '12:51', '17:08', 8, 0),
(115, 54, '2025-10-16', '08:00', '12:00', '13:00', '17:00', 8, 0),
(116, 54, '2025-10-17', '07:51', '12:02', '12:56', '17:02', 8, 0),
(117, 54, '2025-10-20', '08:02', '12:06', '12:54', '17:11', 8, 0),
(118, 54, '2025-10-21', '07:57', '12:03', '12:51', '17:08', 8, 0),
(119, 54, '2025-10-22', '08:00', '12:00', '13:00', '17:00', 8, 0),
(120, 54, '2025-10-23', '07:51', '12:02', '12:56', '17:02', 8, 0),
(121, 54, '2025-10-24', '08:02', '12:06', '12:54', '17:11', 8, 0),
(122, 54, '2025-10-27', '07:57', '12:03', '12:51', '17:08', 8, 0),
(123, 54, '2025-10-28', '08:00', '12:00', '13:00', '17:00', 8, 0),
(124, 54, '2025-10-29', '07:51', '12:02', '12:56', '17:02', 8, 0),
(125, 54, '2025-10-30', '08:02', '12:06', '12:54', '17:11', 8, 0),
(126, 54, '2025-10-31', '07:57', '12:03', '12:51', '17:08', 8, 0),
(127, 54, '2025-11-03', '08:00', '12:00', '13:00', '17:00', 8, 0),
(128, 54, '2025-11-04', '07:51', '12:02', '12:56', '17:02', 8, 0),
(129, 54, '2025-11-05', '08:02', '12:06', '12:54', '17:11', 8, 0),
(130, 54, '2025-11-06', '07:57', '12:03', '12:51', '17:08', 8, 0),
(131, 54, '2025-11-07', '08:00', '12:00', '13:00', '17:00', 8, 0),
(132, 54, '2025-11-10', '07:51', '12:02', '12:56', '17:02', 8, 0),
(133, 54, '2025-11-11', '08:02', '12:06', '12:54', '17:11', 8, 0),
(134, 54, '2025-11-12', '07:57', '12:03', '12:51', '17:08', 8, 0),
(135, 54, '2025-11-13', '08:00', '12:00', '13:00', '17:00', 8, 0),
(136, 54, '2025-11-14', '07:51', '12:02', '12:56', '17:02', 8, 0),
(137, 54, '2025-11-17', '08:02', '12:06', '12:54', '17:11', 8, 0),
(140, 66, '2026-01-21', '10:15', '11:08', NULL, NULL, 0, 53),
(212, 65, '2026-01-23', '20:10', '20:15', NULL, NULL, 0, 0),
(215, 66, '2026-01-23', '23:30', NULL, NULL, NULL, 0, 0),
(216, 66, '2026-01-24', '11:32', '11:39', '13:10', '16:28', 3, 25),
(218, 66, '2026-01-25', '20:33', '21:34', NULL, NULL, 0, 0),
(225, 66, '2026-01-31', '09:25', NULL, NULL, NULL, 0, 0),
(226, 66, '2026-02-01', '15:23', '2:06', '15:23', NULL, 0, 0),
(235, 66, '2026-02-02', '23:26', '23:26', '23:26', '23:26', 0, 0);

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

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`eval_id`, `student_id`, `rating`, `feedback`, `school_eval`, `date_evaluated`, `user_id`, `rating_desc`) VALUES
(7, 66, 3.80, 'very good', '95', '2026-01-18', 27, '3.80 | Very Good');

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
(9, 66, 'uploads/faces/66_1769343559.jpg', '2026-01-25 20:19:19', '[-0.10345686227083206,0.03883334994316101,0.11358872801065445,-0.16147111356258392,-0.08993686735630035,-0.05733651667833328,0.03191419318318367,-0.11266396194696426,0.21220067143440247,-0.17047518491744995,0.2823519706726074,-0.09324987977743149,-0.2471305876970291,-0.008322727866470814,-0.03598950803279877,0.22777652740478516,-0.1326633095741272,-0.12323649972677231,-0.046812281012535095,-0.030820921063423157,0.03205057978630066,-0.09613224864006042,0.005402813665568829,0.11973358690738678,-0.11133702099323273,-0.38031789660453796,-0.11221674084663391,-0.07525315135717392,-0.06879927217960358,-0.0672120600938797,-0.036055002361536026,0.07637206465005875,-0.2608025372028351,-0.022277988493442535,-0.07306548953056335,0.06281781196594238,-0.06467051059007645,-0.12375983595848083,0.14491115510463715,0.048046596348285675,-0.2702522575855255,-0.06898366659879684,-0.018023593351244926,0.21099388599395752,0.16926519572734833,-0.01919759251177311,0.0073919822461903095,-0.04902924224734306,0.08469657599925995,-0.19230541586875916,0.06289448589086533,0.06613917648792267,0.0864308550953865,0.006425490137189627,0.025011414662003517,-0.13689354062080383,0.06643547862768173,0.14821738004684448,-0.24269740283489227,-0.01611640676856041,0.05992358922958374,-0.1489141881465912,-0.04264567419886589,-0.07733502984046936,0.3137941062450409,0.21652851998806,-0.14424753189086914,-0.09817340224981308,0.24825219810009003,-0.08448944240808487,0.018540285527706146,-0.013533198274672031,-0.17390252649784088,-0.18531130254268646,-0.3379392623901367,0.048064831644296646,0.37837788462638855,0.12750455737113953,-0.19885136187076569,0.022266028448939323,-0.12409377098083496,0.07879015803337097,0.11961513012647629,0.17679372429847717,-0.01767854578793049,0.048018552362918854,-0.0794731006026268,-0.02674088627099991,0.10102201998233795,-0.0170948076993227,-0.0829063206911087,0.25731801986694336,-0.0975470319390297,-0.008324094116687775,0.028631018474698067,-0.01171023491770029,-0.13820545375347137,-0.008326754905283451,-0.13472723960876465,-0.0311889611184597,-0.028557881712913513,-0.0351383276283741,-0.04990840330719948,0.14884130656719208,-0.18561138212680817,0.08064661920070648,-0.0021338430233299732,0.022457540035247803,0.008266564458608627,0.02535223215818405,-0.08767364174127579,-0.15290065109729767,0.049926646053791046,-0.2656191289424896,0.13289955258369446,0.1387559026479721,0.00671871192753315,0.15029141306877136,0.0970664694905281,0.07191099226474762,0.00016786900232546031,-0.054584261029958725,-0.2140897512435913,0.04879988357424736,0.15037941932678223,-0.01739525981247425,0.10518229007720947,0.03224494308233261]');

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
(18, 'Bulacan Polytechnic College', 'uploads/moa/moasample_1768401206.jpg', '2026-01-14', '2026-04-14');

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
(8, 'City Budget Office', 3, 0, NULL, NULL, 'Declined'),
(9, 'City Accounting Office', 5, 0, NULL, NULL, 'Approved'),
(14, 'City Admin Office', 0, 0, NULL, NULL, 'Approved');

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
(8, 9, 3),
(9, 9, 5),
(19, 14, 2),
(20, 14, 12);

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
(30, 8, NULL, 4, 'inc', 'rejected', '2026-01-15', '2026-01-19 21:18:03'),
(31, 8, NULL, 4, 'inc', 'rejected', '2026-01-19', '2026-01-19 21:26:39'),
(32, 8, NULL, 5, 'hdfgsdfg', 'rejected', '2026-01-19', '2026-01-19 21:30:25'),
(33, 8, NULL, 5, 'tr', 'rejected', '2026-01-19', '2026-01-19 21:34:51'),
(34, 8, NULL, 5, 'dgdsgsd', 'pending', '2026-01-19', NULL);

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
(56, 66, 8, NULL, 'uploads/1763467842_LETTER_OF_INTENT.pdf', 'uploads/1763467842_ENDORSEMENTLETTER.pdf', 'uploads/1763467842_RESUME.pdf', '', 'uploads/1763467842_formalpic.jpg', 'evaluated', 'Orientation/Start: November 26, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2025-11-18', '2026-01-18'),
(57, 67, 8, 9, 'uploads/1763468321_LETTER_OF_INTENT.pdf', 'uploads/1763468321_ENDORSEMENTLETTER.pdf', 'uploads/1763468321_RESUME.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1763468321_formalpic.jpg', 'rejected', 'incorrect requirements', '2025-11-18', '2025-11-18'),
(58, 68, 8, NULL, 'uploads/1763468527_LETTER_OF_INTENT.pdf', 'uploads/1763468527_ENDORSEMENTLETTER.pdf', 'uploads/1763468527_Resume.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1763468527_formalpic.jpg', 'approved', 'Orientation/Start: November 26, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2025-11-18', '2025-11-18'),
(59, 69, 9, NULL, 'uploads/1763505778_LETTER_OF_INTENT.pdf', 'uploads/1763505778_ENDORSEMENTLETTER.pdf', 'uploads/1763505778_RESUME.pdf', '', 'uploads/1763505778_formalpic.jpg', 'approved', 'Orientation/Start: November 26, 2025 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2025-11-18', '2025-11-18'),
(60, 70, 9, 8, 'uploads/1768055895_LETTER_OF_INTENT.pdf', 'uploads/1768055895_LETTER_OF_INTENT.pdf', 'uploads/1768055895_LETTER_OF_INTENT.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1768055895_formalpic.jpg', 'approved', 'Orientation/Start: January 18, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-01-10', '2026-01-10'),
(61, 71, 8, NULL, 'uploads/1768056652_LETTER_OF_INTENT.pdf', 'uploads/1768056652_LETTER_OF_INTENT.pdf', 'uploads/1768056652_LETTER_OF_INTENT.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1768056652_ai-generated-businessman-in-jacket-isolated-free-photo.jpg', 'approved', 'Orientation/Start: January 18, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-01-10', '2026-01-10'),
(62, 72, 9, NULL, 'uploads/1768115874_LETTER_OF_INTENT.pdf', 'uploads/1768115874_LETTER_OF_INTENT.pdf', 'uploads/1768115874_LETTER_OF_INTENT.pdf', '', 'uploads/1768115874_formalpic.jpg', 'approved', 'Orientation/Start: January 21, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-01-11', '2026-01-13'),
(63, 73, 9, 8, 'uploads/1768129814_LETTER_OF_INTENT.pdf', 'uploads/1768129814_LETTER_OF_INTENT.pdf', 'uploads/1768129814_LETTER_OF_INTENT.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1768129814_ai-generated-businessman-in-jacket-isolated-free-photo.jpg', 'approved', 'Orientation/Start: January 21, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-01-11', '2026-01-13'),
(64, 75, 9, 8, 'uploads/1768374409_LETTER_OF_INTENT.pdf', 'uploads/1768374409_LETTER_OF_INTENT.pdf', 'uploads/1768374409_LETTER_OF_INTENT.pdf', 'uploads/moa/Memorandum-of-Agreement-Template-1_1763107390.jpg', 'uploads/1768374409_formalpic.jpg', 'approved', 'Orientation/Start: January 22, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-01-14', '2026-01-14'),
(65, 77, 8, NULL, 'uploads/1768459341_LETTER_OF_INTENT.pdf', 'uploads/1768459341_Endorsementlettersample.pdf', 'uploads/1768459341_Resumesample.pdf', 'uploads/moa/moasample_1768401206.jpg', 'uploads/1768459341_ai-generated-businessman-in-jacket-isolated-free-photo.jpg', 'approved', 'Orientation/Start: January 21, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Budget Office', '2026-01-15', '2026-01-19'),
(66, 78, 9, NULL, 'uploads/1768811450_LETTER_OF_INTENT.pdf', 'uploads/1768811450_Endorsementlettersample.pdf', 'uploads/1768811450_Resumesample.pdf', 'uploads/moa/moasample_1768401206.jpg', 'uploads/1768811450_formalpic.jpg', 'ongoing', 'Orientation/Start: January 20, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Accounting Office', '2026-01-19', '2026-01-19'),
(69, 81, 14, NULL, 'uploads/1768904658_LETTER_OF_INTENT.pdf', 'uploads/1768904658_Endorsementlettersample.pdf', 'uploads/1768904658_Resumesample.pdf', 'uploads/moa/moasample_1768401206.jpg', 'uploads/1768904657_formalpic.jpg', 'approved', 'Orientation/Start: February 4, 2026 08:30 | Location: CHRMO/3rd Floor | Assigned Office: City Admin Office', '2026-01-20', '2026-02-03'),
(70, 82, 14, NULL, 'uploads/1770214052_LETTER_OF_INTENT.pdf', 'uploads/1770214052_Endorsementlettersample.pdf', 'uploads/1770214052_Resumesample.pdf', 'uploads/moa/moasample_1768401206.jpg', 'uploads/1770214052_formalpic.jpg', 'approved', 'Orientation/Start: February 9, 2026 09:30 | Location: CHRMO/3rd Floor | Assigned Office: City Admin Office', '2026-02-04', '2026-02-05');

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
(17, 9, 56, '2025-11-18 12:55:25', NULL),
(18, 9, 58, '2025-11-18 13:04:39', NULL),
(19, 9, 59, '2025-11-18 22:45:14', NULL),
(20, 10, 60, '2026-01-10 14:40:33', NULL),
(21, 10, 61, '2026-01-10 14:51:04', NULL),
(22, 11, 63, '2026-01-11 14:37:59', NULL),
(23, 35, 62, '2026-01-13 07:05:29', '2026-02-10'),
(24, 27, 63, '2026-01-13 07:09:27', NULL),
(25, 36, 64, '2026-01-14 08:31:30', '2026-02-09'),
(26, 34, 65, '2026-01-19 08:22:27', '2026-01-21'),
(27, 37, 66, '2026-01-19 08:31:04', '2026-02-19'),
(30, 38, 69, '2026-02-03 13:18:22', '2026-02-19'),
(31, 40, 70, '2026-02-05 07:08:49', '2026-02-09');

-- --------------------------------------------------------

--
-- Table structure for table `orientation_sessions`
--

CREATE TABLE `orientation_sessions` (
  `session_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `rescheduled_from` date DEFAULT NULL,
  `session_time` time NOT NULL,
  `location` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orientation_sessions`
--

INSERT INTO `orientation_sessions` (`session_id`, `session_date`, `rescheduled_from`, `session_time`, `location`) VALUES
(1, '2025-11-13', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(2, '2025-11-14', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(3, '2025-11-17', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(4, '2025-11-18', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(5, '2025-11-19', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(6, '2025-11-23', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(7, '2025-11-24', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(8, '2025-11-25', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(9, '2025-11-26', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(10, '2026-01-18', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(11, '2026-01-19', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(16, '2026-02-04', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(17, '2026-02-05', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(18, '2026-02-06', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(27, '2026-02-19', '2026-02-18', '08:30:00', 'CHRMO/3rd Floor'),
(28, '2026-02-18', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(29, '2026-02-20', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(30, '2026-02-24', NULL, '08:30:00', 'CHRMO/3rd Floor'),
(34, '2026-03-03', '2026-01-21', '08:30:00', 'CHRMO/3rd Floor'),
(35, '2026-02-09', '2026-02-19', '09:30:00', 'CHRMO/3rd Floor'),
(36, '2026-02-10', '2026-02-09', '10:30:00', 'CHRMO/3rd Floor'),
(37, '2026-02-09', '2026-02-19', '08:30:00', 'CHRMO/3rd Floor'),
(38, '2026-02-09', '2026-02-19', '09:00:00', 'CHRMO/3rd Floor'),
(40, '2026-02-19', '2026-02-09', '09:00:00', 'CHRMO/3rd Floor');

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
(12, 5, 'santiagojasminem@gmail.com', '870522', '2026-02-03 04:29:39', 1, '2026-02-03 03:26:39');

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
(66, 54, 'Elisha', NULL, 'Lumanlan', 'Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2007-10-15', 'Ann Lumanlan', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '3', '2025-2026', '1st Semester', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 500, 'evaluated', NULL),
(67, NULL, 'Angel', NULL, 'Mendoza', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2007-09-08', 'Maria Mendoza', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', 'incorrect requirements'),
(68, 55, 'Mikaili', NULL, 'Mesia', 'Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2007-11-17', 'Maria Rosario', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', NULL),
(69, 58, 'Blair', NULL, 'Waldorf', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2007-11-11', 'Eleanor Waldorf', 'Mother', '09134664654', 'Bulacan State University', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', NULL),
(70, 59, 'Jasmine', NULL, 'Santiago', 'Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2008-01-10', 'Rosaly Santiago', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, '', NULL),
(71, 60, 'Arvin', NULL, 'Ong', 'Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2008-01-10', 'Janice Ong', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'BSBA Major in Financial Management', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 200, 0, 'pending', NULL),
(72, 62, 'Krystal', NULL, 'Mendoza', 'Sumapang Matanda, Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2008-01-11', 'Kurt Mendoza', 'Brother', '09134664654', 'AMA Computer College – Malolos', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 100, 0, 'pending', NULL),
(73, 61, 'John ', NULL, 'Sayo', 'Malolos, Bulacan', '09454659878', 'santiagojasminem4@gmail.com', '2008-01-11', 'Maria Sayo', 'Brother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 300, 0, 'pending', NULL),
(74, NULL, 'Minmin', '', 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'jasmine.santiago1@bpc.edu.ph', '2008-01-14', 'Myrna  Santiago', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '4', '2025-2026', '', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', NULL),
(75, 64, 'Minmin', NULL, 'Santiago', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'jasmine.santiago1@bpc.edu.ph', '2008-01-14', 'Myrna Santiago', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accountancy', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 200, 0, 'pending', NULL),
(76, NULL, 'Nateee', '', 'Mesia', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2008-01-15', 'Jampol  Ong', 'Father', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '3', '2025-2026', '', 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 500, 0, 'pending', NULL),
(77, 65, 'Nate', NULL, 'Mesia', '#0546 Peter Street, Phase 2, Caingin, Malolos, Bulacan', '09454659878', 'santiagojasminem21@gmail.com', '2008-01-15', 'Jampol Ong', 'Father', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 80, 0, 'pending', NULL),
(78, 66, 'Joaquin', NULL, 'Mendoza', 'Malolos, Bulacan', '09454659878', 'santiagojasminem1@gmail.com', '2008-01-16', 'Angel Mendoza', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Accounting Information System', '3', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 200, 0, 'ongoing', NULL),
(81, 69, 'Maxine', NULL, 'Gaspar', 'Malolos, Bulacan', '09454659878', 'santiagojasminem@gmail.com', '2008-01-13', 'Janice Waldorf', 'Mother', '09134664654', 'Bulacan Polytechnic College', 'Bachelor of Science in Office Administration', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 200, 0, 'pending', NULL),
(82, 70, 'Janice', NULL, 'Santiago', 'Malolos, Bulacan', '09454659878', 'santiagojasminem22@gmail.com', '2008-02-04', 'Jampol Santiago', 'Father', '09454659879', 'Bulacan Polytechnic College', 'Bachelor of Science in Office Administration', '4', '2025-2026', NULL, 'Bulihan, Malolos, Bulacan', 'Rhey Santos', '09234342354', 200, 0, 'pending', NULL);

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
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `first_name`, `middle_name`, `last_name`, `avatar`, `force_password_change`, `password_changed_at`, `password`, `role`, `office_name`, `status`, `date_created`) VALUES
(5, 'hrhead', 'santiagojasminem@gmail.com', 'Cecilia', '', 'Ramos', '../uploads/avatars/user_5_1770017450.jpg', 0, NULL, '$2y$10$/hoM2h1oQez1f1omUf8eCuXkf5lg6rIN3UDP7q6M7VC.qZReuTWQO', 'hr_head', NULL, 'active', '2025-10-12 13:34:28'),
(6, 'hrstaff', 'santiagojasminem3@gmail.com', 'Andrea', NULL, 'Lopez', NULL, 0, NULL, '123456', 'hr_staff', NULL, 'active', '2025-10-12 13:34:28'),
(27, 'headbudget', 'santiagojasminem@gmail.com', 'Layla', NULL, 'Garcia', NULL, 0, NULL, '$2y$10$CQnZlBZHwy1iD8Bplg7yNOBA1qOlQFArvMP6MR6GEAHvdknZe3lgO', 'office_head', 'City Budget Office', 'active', '2025-11-07 09:58:55'),
(29, 'cbass610', 'santiagojasminem5@gmail.com', 'Charles', NULL, 'Bass', NULL, 0, NULL, '222222', 'office_head', 'City Accounting Office', 'active', '2025-11-08 07:58:24'),
(50, 'jdiamante370', 'jenny.robles@bpc.edu.ph', 'Jimwell', NULL, 'Diamante', NULL, 0, NULL, '%BCJbqY3U4', 'office_head', 'City Admin Office', 'active', '2025-11-17 01:56:34'),
(54, 'santiagojasminem', NULL, NULL, NULL, NULL, NULL, 0, NULL, '123456', 'ojt', 'City Budget Office', 'evaluated', '2025-10-18 12:55:25'),
(55, 'santiagojasminem1', NULL, NULL, NULL, NULL, NULL, 0, NULL, '03e3822a6d', 'ojt', 'City Budget Office', 'approved', '2025-11-18 13:04:39'),
(58, 'santiagojasminem2', NULL, NULL, NULL, NULL, NULL, 0, NULL, '222222', 'ojt', 'City Accounting Office', 'approved', '2025-11-11 22:45:14'),
(59, 'santiagojasminem3', NULL, NULL, NULL, NULL, NULL, 0, NULL, '9400931838', 'ojt', 'City Accounting Office', 'approved', '2026-01-10 14:40:33'),
(60, 'santiagojasminem4', NULL, NULL, NULL, NULL, NULL, 0, NULL, 'ce85d92c8a', 'ojt', 'City Budget Office', 'approved', '2026-01-10 14:51:04'),
(61, 'santiagojasminem5', NULL, NULL, NULL, NULL, NULL, 0, NULL, 'e513ca9f3a', 'ojt', 'City Accounting Office', 'approved', '2026-01-11 14:37:59'),
(62, 'santiagojasminem6', NULL, NULL, NULL, NULL, NULL, 0, NULL, 'b778d8906a', 'ojt', 'City Accounting Office', 'approved', '2026-01-13 07:05:29'),
(64, 'jasmine.santiago1', NULL, NULL, NULL, NULL, NULL, 0, NULL, '111111', 'ojt', 'City Accounting Office', '', '2026-01-14 08:31:30'),
(65, 'santiagojasminem21', NULL, NULL, NULL, NULL, NULL, 0, NULL, '222222', 'ojt', 'City Budget Office', 'ongoing', '2026-01-06 08:22:27'),
(66, 'santiagojasminem7', NULL, NULL, NULL, NULL, NULL, 0, NULL, '111111', 'ojt', 'City Accounting Office', 'ongoing', '2026-01-06 08:31:04'),
(69, 'santiagojasminem11', NULL, NULL, NULL, NULL, NULL, 0, NULL, '9ad25f00fe', 'ojt', 'City Admin Office', 'approved', '2026-02-03 13:18:22'),
(70, 'santiagojasminem22', NULL, NULL, NULL, NULL, NULL, 0, NULL, '7bb14e7411', 'ojt', 'City Admin Office', 'approved', '2026-02-05 07:08:49');

-- --------------------------------------------------------

--
-- Table structure for table `webauthn_credentials`
--

CREATE TABLE `webauthn_credentials` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cred_id` varchar(255) NOT NULL,
  `attestation` text DEFAULT NULL,
  `clientdata` text DEFAULT NULL,
  `public_key` text DEFAULT NULL,
  `sign_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `webauthn_credentials`
--

INSERT INTO `webauthn_credentials` (`id`, `user_id`, `cred_id`, `attestation`, `clientdata`, `public_key`, `sign_count`, `created_at`) VALUES
(1, 66, 'p-Bw6LUkGfzpLLHVU0KUavrFWq3MogwxFUnlbKSeAEM', 'o2NmbXRjdHBtZ2F0dFN0bXSmY2FsZzn__mNzaWdZAQBCkqwcHDSpaM8YfSaquCLMSturdLmn32lzQbykIs278zqdkaNzRttNPNflw7kgvnB0YzeGWBXgdEMxFuKKi-GCS5sWsP3JjDArWIFJ7FJvYxR-vhCEr1-rmSV2A1HZe7aeCnoNwUIIemCMacgxeMgh6v3aML6DnTfI_kACMh4dvDn5GYQp0O2x7-qU1xLXpLkLG0bGNb2YmBzCYEstBCwjrvm_gsX_UCqPESRzcvx_RMra-wehOKPZmQ6QU_36Y-8bOR1lU8E_pZwCRx887JWMK1fEAGZa0-h6XFNhcczng_9NCARvOlVT0z4fLaLrbf09uvV0QDZ9QDyH6iD0Z0GqY3ZlcmMyLjBjeDVjglkFtzCCBbMwggOboAMCAQICEEH4PsWOUETbh5DpA8r0Vc8wDQYJKoZIhvcNAQELBQAwPjE8MDoGA1UEAxMzSU5UQy1LRVlJRC1CMDY2RDk2OTdGNUQzQTA3QjQyNUMxMEY1ODdDQ0VFQ0YxNkZGRTU4MB4XDTI0MTIwNzA1MTkzN1oXDTMwMDUyMTE5MTMwNlowADCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAJzHRGw9Jd8do1xXV1EBblyXoqVf07sFaS9YQDwVwOwXQEjKU0jpFnL4SiGX2PJH5YnkMzV4zXlNesXJpSFZ7gFioy8wDApksEOwNOp3Ol_zIS7u3aLQwl8Nvur7QFWnCMUW7s4xS6-c7q1xzZ4cJehnuDzJuTLCm7sSzWgB7uVaXoTzKZErwSxG_t4i6bWTrhPgj1d4AD3SW7PNcr9-pm5amSwz4XNZ9FZ6swf8Ulf3H4kpmxwkFToM5mb3_yuxADBOG2VB0SIo0a5ONCw-TFwxD_L1s85HYZkoaVR3TJ1otsLFGM24FR79a2c7ytY74P-iQwf-OWfOdiIiKPFJ98MCAwEAAaOCAekwggHlMA4GA1UdDwEB_wQEAwIHgDAMBgNVHRMBAf8EAjAAMG0GA1UdIAEB_wRjMGEwXwYJKwYBBAGCNxUfMFIwUAYIKwYBBQUHAgIwRB5CAFQAQwBQAEEAIAAgAFQAcgB1AHMAdABlAGQAIAAgAFAAbABhAHQAZgBvAHIAbQAgACAASQBkAGUAbgB0AGkAdAB5MBAGA1UdJQQJMAcGBWeBBQgDMFAGA1UdEQEB_wRGMESkQjBAMRYwFAYFZ4EFAgEMC2lkOjQ5NEU1NDQzMQ4wDAYFZ4EFAgIMA1RHTDEWMBQGBWeBBQIDDAtpZDowMjU4MDAwNzAfBgNVHSMEGDAWgBRmc3EidiwpbiygB9uKIK23BMFeejAdBgNVHQ4EFgQUEZOmCkgP-gl-zlvnneGlqSruc1owgbEGCCsGAQUFBwEBBIGkMIGhMIGeBggrBgEFBQcwAoaBkWh0dHA6Ly9hemNzcHJvZGV1c2Fpa3B1Ymxpc2guYmxvYi5jb3JlLndpbmRvd3MubmV0L2ludGMta2V5aWQtYjA2NmQ5Njk3ZjVkM2EwN2I0MjVjMTBmNTg3Y2NlZWNmMTZmZmU1OC1jLzQzMDVmZDk3LTc3M2QtNDRjYy1hYzE1LTMxNjM2NzE3MDk3Yi5jZXIwDQYJKoZIhvcNAQELBQADggIBAFDGlS9mHTtZUrYBVLPzDWVqUQIAfKTo3S-X2LwDJiOv-A619QBi0Wm7g3Hjp6HHHomPOMFc0ApxIin1GCmOZ22yomjvLUHlNRxVSU2RsyWHccxJ3rbbUsKp8sjAAcS6dBsIcWo9QTM2mvDwnKzhueVjyX0WPhNCynR_Cs8R31LqDvIAM-6UwMpZnK5qRIuMGWFOudCOz8gG2EjR8eEtAAprYXhqJnxdobg5Vnkhu6AeKnCH54Exm2UWlLPraKMNQPEsQ4Zl8NAqK2YP4VOEi-chAdEDmO9_PXDT4i4AzqO1_LbYC5W3Q4RhTOXKDoTjuNav1L54NtCPseAkxRB1sG403BDAnItkI4fhjyrOUbTfZOk7YRE7SbMzuJkPYF6NDBK7QgBVdRR2Vw6pXgycktUpti4VSY7JoHraYozOCkXKVc4VqUoZS_DPNcYk1YtZdM9iiT1EHtSx0pTz6fodgtbO1tZd_z9WRUF7XhE-1sm8I8tLByw_2Uxfdvu1k9b1VZpSrcJM9HBZFayba1Vegrst5OG0BwvRrhbdKgbVvwH4Alrrk16y7xDXOlP4ssaGIVLUESjpOksb2qB0-Hrie5Y62UyKvfUHujZRup_Q6ka7MuE9vfqAujAsZM3030fKXeIYtb58fFbLFL1YSHY7ug8A9mgFAKIn0JWJINfVDa9PWQbsMIIG6DCCBNCgAwIBAgITMwAACNG_8yWGYsiviQAAAAAI0TANBgkqhkiG9w0BAQsFADCBjDELMAkGA1UEBhMCVVMxEzARBgNVBAgTCldhc2hpbmd0b24xEDAOBgNVBAcTB1JlZG1vbmQxHjAcBgNVBAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjE2MDQGA1UEAxMtTWljcm9zb2Z0IFRQTSBSb290IENlcnRpZmljYXRlIEF1dGhvcml0eSAyMDE0MB4XDTI0MDUyMTE5MTMwNloXDTMwMDUyMTE5MTMwNlowPjE8MDoGA1UEAxMzSU5UQy1LRVlJRC1CMDY2RDk2OTdGNUQzQTA3QjQyNUMxMEY1ODdDQ0VFQ0YxNkZGRTU4MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAiAH3nMFlMglGBKuU2ES3T4okWWueZ4f2eEYrz3qp0yeot9fDVtGei0OUvE8Ou3BvaEnYZhdJuRBfOg2i8NGFsDJ0LLbZafoZEPY1lRKUMsvHtpYec7ViptdUS0eEQmm1VffBRA0SqB_Jh1pOZYD3IX1-VNl4KUKgwu8sSy7oo5SkXjf6kVUjlz_3jFIM7N4hBWG0xZApMrCY2TCSVONXbIZ4WeIUG3V8rKemvZTt_FkrurAygPh403rDz1Gr9UuRvJ16o2MjalWZCunyxknM8mrgtUtZztl_5Upkl_zLpYCnIQGZeHoORkiU4cDaqk-Z1MXlbvlIsPiWy-Yk5U0OxyJOpz06bzeJoh5fPvCLTP0Ry2B2WVn38g-596LtieZ0O-_mOXyBCSXDyRSGFj2EAGRfluPu25BWW1Au2WpJntB7eqUUFwuiRHjEMbp_69TaIhTHA6iDpMA7xIua70Vdf_4NIW5JO3Kwwj3gwjHZOFkTRJIYSyY0WsHm4don_Tk3Y1onY0vH9feLp_1on-vetaYFiWEGSLYgU4o8oP_apN3V_y10Falf1VdJ94lL7eBoYWroqoI3Gdzmwjkl1P0IcBdHDBishEw5nV7XznpceMdj6yjHo_mmyouFay0qkMyRX0FW_iPPzxY-XwBbFVUypfQ0-uD11mTnu6Gu6EpdgWkCAwEAAaOCAY4wggGKMA4GA1UdDwEB_wQEAwIChDAbBgNVHSUEFDASBgkrBgEEAYI3FSQGBWeBBQgDMBYGA1UdIAQPMA0wCwYJKwYBBAGCNxUfMBIGA1UdEwEB_wQIMAYBAf8CAQAwHQYDVR0OBBYEFGZzcSJ2LCluLKAH24ogrbcEwV56MB8GA1UdIwQYMBaAFHqMCs4vSGIX4pTRrlXBUuxxdKRWMHAGA1UdHwRpMGcwZaBjoGGGX2h0dHA6Ly93d3cubWljcm9zb2Z0LmNvbS9wa2lvcHMvY3JsL01pY3Jvc29mdCUyMFRQTSUyMFJvb3QlMjBDZXJ0aWZpY2F0ZSUyMEF1dGhvcml0eSUyMDIwMTQuY3JsMH0GCCsGAQUFBwEBBHEwbzBtBggrBgEFBQcwAoZhaHR0cDovL3d3dy5taWNyb3NvZnQuY29tL3BraW9wcy9jZXJ0cy9NaWNyb3NvZnQlMjBUUE0lMjBSb290JTIwQ2VydGlmaWNhdGUlMjBBdXRob3JpdHklMjAyMDE0LmNydDANBgkqhkiG9w0BAQsFAAOCAgEAGnEAtHIXaMn3s4MtCky-4kL1t9BE1Grsb6XGJSF7qgxQfGTGEltjIqlOo0snoMTU1MX55tADTt6cyacDf1646XazElQKRQHIqFnBuHUiZ6dEcPF-BipBndFkGBCcn8KVkWBDw1TJoi6m4Qgg05wJb2L1A66zJ-FeumYrs0OsVgdkxa-jHiloojRWL2nCpUUYXo0N-XzED5DTX3f-uH18Lzagcslyk2IbUkk8Sxdvj9BQEVIIJFYav1ktQzfrSb2dy5TygE9IGzhCcnY11WsfQe-bOH8H0rfVGSHejFAtzT4-0wGTtlL6qZXyYqYUxAO4yCyMv0UbsZYoholW8ZJsujkatcHQVE0W09BIkLy_SJk7VCglM7LpV0stKZOEbeawUTH3wnSlhg3vXob7sPJ0M6BZff8EMOPfNGt1hoqGw3K2Z__tgHTI5AY1-6sujhpz0huhJlQ-1J6CGYpmrV35i4HIvsvoiJl34wVNgNERuGbt2m6VJO6yUhQ51HvWnRv_Bt8QfWZ74f667BLbCUPnhuP-3qdkj7iYJKe3NcXPEYJAyz12pv8Uf_fZ899Qm4D27V-RNBFxn28eZCTjXbVyA5sYBVOtTW9J2NR_rY4B2XueREjY_B4nOOo1aPJMpyrbIZDt4N-RGV8GTYXpgYYBd4VeB_Iurp8ZpE6jn6wLf4tncHViQXJlYVh2ACMACwAEAHIAIJ3_y_NsODrmmfuYaNxty4nXFTiEvigDkiwSQVi_rSKuABAAEAADABAAIJJNL_AgGW-ALbJvOSYj1bvZ12NMKthOgE7F8eqg2YmLACCER4lhQ7NLxX0Ci3zH1AvtHPb2w53IJjQwbMt9KikFsWhjZXJ0SW5mb1ih_1RDR4AXACIAC0BYgulMmQBaK-v_uYyTCHi4xBrYHJeySgOUObJG7hDpABSVYxzxSqp05ISfH0ykuvruHpiI-QAAAAK_OwV-J4sp1xBJvpMBBYxIJTh2KYYAIgALLNMeIdPd7HaDmGD8WYDxX3lzdrG75kwovY7NXYCJOycAIgALdf3hkvMYg7jfQuFtXtOhXpPTFc_tR5itk6LT9UyNQSZoYXV0aERhdGFYpEmWDeWIDoxodDQXD2R2YFuP5K65ooYyx5lc87qDHZdjRQAAAAAImHBYytxLgbbhMN5Q3L6WACCn4HDotSQZ_OkssdVTQpRq-sVarcyiDDEVSeVspJ4AQ6UBAgMmIAEhWCCSTS_wIBlvgC2ybzkmI9W72ddjTCrYToBOxfHqoNmJiyJYIIRHiWFDs0vFfQKLfMfUC-0c9vbDncgmNDBsy30qKQWx', 'eyJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIiwiY2hhbGxlbmdlIjoiTUFhUk82ZGFtX19zN3BsRXlEMHllUnZNRmxHNEk4bHBqSDF6MmZ0anM2QSIsIm9yaWdpbiI6Imh0dHA6Ly9sb2NhbGhvc3QiLCJjcm9zc09yaWdpbiI6ZmFsc2UsIm90aGVyX2tleXNfY2FuX2JlX2FkZGVkX2hlcmUiOiJkbyBub3QgY29tcGFyZSBjbGllbnREYXRhSlNPTiBhZ2FpbnN0IGEgdGVtcGxhdGUuIFNlZSBodHRwczovL2dvby5nbC95YWJQZXgifQ', NULL, 0, '2026-01-21 02:15:50');

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
(11, 75, 'Week 1 (2026-01-09|2026-01-13)', '2026-01-14', 'uploads/journals/1768389198_d9c75e12b72e_Weeklyjournal1.docx', '2026-01-09', '2026-01-13'),
(12, 69, 'Week 1 (2026-01-05|2026-01-12)', '2026-01-14', 'uploads/journals/1768398438_d4c5053dfa45_WeeklyJournalSample.docx', '2026-01-05', '2026-01-12');

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cred_id` (`cred_id`),
  ADD KEY `fk_webauthn_user` (`user_id`);

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
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `dtr`
--
ALTER TABLE `dtr`
  MODIFY `dtr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=236;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `face_templates`
--
ALTER TABLE `face_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
  MODIFY `moa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `office_courses`
--
ALTER TABLE `office_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `office_requests`
--
ALTER TABLE `office_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `ojt_applications`
--
ALTER TABLE `ojt_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `orientation_assignments`
--
ALTER TABLE `orientation_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `orientation_sessions`
--
ALTER TABLE `orientation_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
-- Constraints for table `face_templates`
--
ALTER TABLE `face_templates`
  ADD CONSTRAINT `face_templates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
-- Constraints for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD CONSTRAINT `fk_webauthn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `weekly_journal`
--
ALTER TABLE `weekly_journal`
  ADD CONSTRAINT `weekly_journal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `students` (`student_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
