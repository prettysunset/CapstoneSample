-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2025 at 02:22 PM
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
-- Table structure for table `excel`
--

CREATE TABLE `excel` (
  `id` int(11) NOT NULL,
  `time` varchar(50) NOT NULL,
  `monday` varchar(255) DEFAULT NULL,
  `monday_color` varchar(7) DEFAULT NULL,
  `tuesday` varchar(255) DEFAULT NULL,
  `tuesday_color` varchar(7) DEFAULT NULL,
  `wednesday` varchar(255) DEFAULT NULL,
  `wednesday_color` varchar(7) DEFAULT NULL,
  `thursday` varchar(255) DEFAULT NULL,
  `thursday_color` varchar(7) DEFAULT NULL,
  `friday` varchar(255) DEFAULT NULL,
  `friday_color` varchar(7) DEFAULT NULL,
  `saturday` varchar(255) DEFAULT NULL,
  `saturday_color` varchar(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `excel`
--

INSERT INTO `excel` (`id`, `time`, `monday`, `monday_color`, `tuesday`, `tuesday_color`, `wednesday`, `wednesday_color`, `thursday`, `thursday_color`, `friday`, `friday_color`, `saturday`, `saturday_color`) VALUES
(1, '07:00', '', NULL, '', NULL, '', NULL, 'mmw', '#FFD700', '', NULL, '', NULL),
(2, '07:30', '', NULL, '', NULL, '', NULL, 'CL2', '#FFD700', '', NULL, '', NULL),
(3, '08:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(4, '08:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(5, '09:00', '', NULL, '', NULL, 'IS-CAP 413', '#FFD700', '', NULL, '', NULL, '', NULL),
(6, '09:30', '', NULL, '', NULL, 'CL2', '#FFD700', '', NULL, '', NULL, '', NULL),
(7, '10:00', '', NULL, '', NULL, 'BSIS 4B', '#FFD700', '', NULL, '', NULL, '', NULL),
(8, '10:30', '', NULL, '', NULL, '', '#FFD700', '', NULL, '', NULL, '', NULL),
(9, '11:00', '', NULL, '', NULL, '', '#FFD700', '', NULL, '', NULL, '', NULL),
(10, '11:30', '', NULL, '', NULL, '', '#FFD700', '', NULL, '', NULL, '', NULL),
(11, '12:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(12, '12:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(13, '01:00', '', NULL, '', NULL, 'IS-OJT 413', '#FFD700', '', NULL, '', NULL, '', NULL),
(14, '01:30', '', NULL, '', NULL, 'NTTLAB', '#FFD700', '', NULL, '', NULL, '', NULL),
(15, '02:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(16, '02:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(17, '03:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(18, '03:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(19, '04:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(20, '04:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(21, '05:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(22, '05:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(23, '06:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(24, '06:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(25, '07:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(26, '07:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(27, '08:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(28, '08:30', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL),
(29, '09:00', '', NULL, '', NULL, '', NULL, '', NULL, '', NULL, '', NULL);

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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `excel`
--
ALTER TABLE `excel`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `intern_stories`
--
ALTER TABLE `intern_stories`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `excel`
--
ALTER TABLE `excel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `intern_stories`
--
ALTER TABLE `intern_stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
