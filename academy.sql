-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 17, 2026 at 04:04 PM
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
-- Database: `academy`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `call_number` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `shelf_location` varchar(50) DEFAULT NULL,
  `author` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `copyright_year` int(11) NOT NULL,
  `isbn` varchar(30) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `call_number`, `title`, `shelf_location`, `author`, `category`, `copyright_year`, `isbn`, `quantity`, `cover_image`, `created_at`) VALUES
(2, '3', 'Career Pathways in TLE', 'TLE/HELE', 'Rolando V. Cristobal', 'Applied Science', 2015, '640C86c', 0, '../uploads/books/book_2_1770861022.jpg', '2026-01-27 10:56:49'),
(3, '50', 'Kamalayang Panlipunan', 'TLE/HELE', 'Neil Alvin Nicerio', 'Geography and History', 2013, '9786214054022', 1, NULL, '2026-01-27 10:58:59'),
(4, '56', 'Skills for a Lifetime in TLE', 'TLE/HELE', 'Virginia Esmilla Sercado', 'Literature', 2025, '978971655441', 4, NULL, '2026-01-27 11:03:45'),
(6, '900-999', 'Sanayang Aklat Para sa Noli Me Tangere', NULL, 'sfds', 'Literature', 1900, '8657647476', 13, NULL, '2026-01-30 03:36:33'),
(7, '1', '1', '1', '1', 'Philosophy', 2000, '1', 110, '../uploads/books/book_new_1770860816.jpg', '2026-02-12 01:46:56'),
(8, '12', '12', '12', '12', 'Religion', 2000, '12', 5, '../uploads/books/book_new_1770870989.gif', '2026-02-12 04:36:29'),
(9, '600', 'Iure consequatur ea', 'Voluptatem quia qui', 'Rerum dolor et minim', 'Philosophy', 1993, 'Aut repudiandae ulla', 222, '', '2026-02-12 04:39:52'),
(10, '333', 'Rem non vitae animi', 'Deserunt placeat et', 'Enim molestias est c', 'Religion', 1975, 'Ipsum non aut exped', 911, '', '2026-02-12 04:39:55'),
(11, '686', 'Enim et omnis fugiat', 'Aliquam excepturi mo', 'Vel aut laboriosam', 'Literature', 2000, 'Totam laudantium do', 249, '', '2026-02-12 04:39:59'),
(12, '349', 'Labore dolor occaeca', 'Laborum esse nostru', 'Praesentium ullam in', 'Generalities', 2003, 'Eum sed quis animi', 975, '', '2026-02-12 04:40:02'),
(13, '169', 'Quae ea rem sit offi', 'Fugiat dignissimos d', 'Sed lorem ratione ve', 'Arts and Recreation', 2005, 'Praesentium voluptat', 323, '', '2026-02-12 04:40:07');

-- --------------------------------------------------------

--
-- Table structure for table `books_requests`
--

CREATE TABLE `books_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `request_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books_requests`
--

INSERT INTO `books_requests` (`id`, `student_id`, `book_title`, `author`, `isbn`, `notes`, `request_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 5, 'Tagumpay Bunga ng Edukasyon at Pangkabuhayan', 'Josephina Mallari', NULL, 'book for TLE', '2026-01-28', 'pending', '2026-01-28 16:15:26', NULL),
(2, 5, 'Expeditions in Mapeh', 'Ferdilyn Lacia', '', 'Mapeh Book', '2026-01-28', 'pending', '2026-01-28 17:27:45', NULL),
(3, 3, 'Wikang Sarili', 'Joel Malabanan ', '', 'Filipino Book', '2026-01-28', 'approved', '2026-01-28 17:28:18', NULL),
(4, 5, 'Wimpy Kid', 'Joshua', '09082634', 'need for acads', '2026-02-10', 'pending', '2026-01-30 03:41:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `book_requests`
--

CREATE TABLE `book_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `request_type` enum('reserve','borrow') NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','borrowed','return_pending','returned') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_notes` text DEFAULT NULL,
  `fine` decimal(10,2) DEFAULT 0.00 COMMENT 'Overdue fine in PHP',
  `return_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_requests`
--

INSERT INTO `book_requests` (`id`, `student_id`, `book_id`, `request_type`, `request_date`, `status`, `created_at`, `updated_at`, `admin_notes`, `fine`, `return_date`) VALUES
(10, 11, 6, 'borrow', '2026-02-12 00:43:45', 'returned', '2026-02-12 00:43:45', '2026-02-12 02:24:53', 'alr', 0.00, '2026-02-19 00:00:00'),
(11, 14, 6, 'borrow', '2026-02-12 00:52:10', 'returned', '2026-02-12 00:52:10', '2026-02-12 00:53:04', NULL, 0.00, '2026-02-19 00:00:00'),
(17, 14, 6, 'borrow', '2026-02-12 01:08:06', 'returned', '2026-02-12 01:08:06', '2026-02-12 01:08:29', NULL, 0.00, '2026-02-19 00:00:00'),
(23, 14, 7, 'borrow', '2026-02-12 01:52:27', 'returned', '2026-02-12 01:52:27', '2026-02-12 02:20:14', 'd', 0.00, '2026-02-19 00:00:00'),
(24, 14, 7, 'borrow', '2026-02-12 02:05:39', 'returned', '2026-02-12 02:05:39', '2026-02-12 02:05:55', NULL, 0.00, '2026-02-19 00:00:00'),
(25, 9, 6, 'borrow', '2026-02-12 02:24:20', 'returned', '2026-02-12 02:24:20', '2026-02-12 02:34:59', 'ok', 0.00, '2026-03-14 00:00:00'),
(26, 14, 7, 'borrow', '2026-02-12 02:26:05', 'returned', '2026-02-12 02:26:05', '2026-02-12 02:45:18', '', 0.00, '2026-02-19 00:00:00'),
(27, 9, 7, 'borrow', '2026-02-12 02:35:25', 'returned', '2026-02-12 02:35:25', '2026-02-12 02:35:33', '', 0.00, '2026-03-14 00:00:00'),
(28, 10, 7, 'borrow', '2026-02-12 02:36:00', 'returned', '2026-02-12 02:36:00', '2026-02-12 02:36:07', '', 0.00, '2026-03-14 00:00:00'),
(29, 9, 2, 'borrow', '2026-02-12 02:36:24', 'returned', '2026-02-12 02:36:24', '2026-02-12 02:48:07', '', 0.00, '2026-03-14 00:00:00'),
(30, 9, 3, 'borrow', '2026-02-12 02:47:03', 'returned', '2026-02-12 02:47:03', '2026-02-12 02:48:18', '', 0.00, '2026-03-14 00:00:00'),
(31, 14, 7, 'borrow', '2026-02-12 03:41:26', 'returned', '2026-02-12 03:41:26', '2026-02-12 03:41:47', '', 0.00, '2026-02-19 00:00:00'),
(32, 16, 11, 'borrow', '2026-02-15 13:07:10', 'returned', '2026-02-15 13:07:10', '2026-02-17 08:35:30', 'Damaged book reported – additional fine ₱500.00', 500.00, '2026-03-17 00:00:00'),
(33, 3, 7, 'borrow', '2026-02-11 08:25:02', 'returned', '2026-02-11 08:25:02', '2026-02-17 08:26:19', '', 0.00, '2026-02-11 00:00:00'),
(34, 15, 8, 'borrow', '2026-02-18 08:26:34', 'returned', '2026-02-18 08:26:34', '2026-02-17 08:30:57', '', 0.00, '2026-02-18 00:00:00'),
(35, 11, 7, 'borrow', '2026-02-17 08:31:20', 'returned', '2026-02-17 08:31:20', '2026-02-17 08:32:30', '', 10.00, '2026-02-15 00:00:00'),
(36, 11, 7, 'borrow', '2026-02-17 08:33:02', 'returned', '2026-02-17 08:33:02', '2026-02-17 08:38:04', 'Damaged book reported – additional fine ₱550.00', 560.00, '2026-02-15 00:00:00'),
(37, 15, 2, 'borrow', '2026-02-17 08:38:15', 'approved', '2026-02-17 08:38:15', '2026-02-17 08:38:15', NULL, 0.00, '2026-02-24 00:00:00'),
(38, 14, 3, 'borrow', '2026-02-17 08:40:36', 'returned', '2026-02-17 08:40:36', '2026-02-17 08:42:40', 'Damaged book reported – additional fine ₱550.00', 570.00, '2026-02-13 00:00:00'),
(39, 16, 9, 'borrow', '2026-02-17 09:03:34', 'approved', '2026-02-17 09:03:34', '2026-02-17 09:03:34', NULL, 0.00, '2026-03-19 00:00:00'),
(40, 14, 7, 'borrow', '2026-02-17 09:04:22', 'approved', '2026-02-17 09:04:22', '2026-02-17 09:04:28', NULL, 0.00, '2026-02-24 00:00:00'),
(41, 16, 7, 'borrow', '2026-02-17 09:21:51', 'returned', '2026-02-17 09:21:51', '2026-02-17 09:21:58', '', 0.00, '2026-03-19 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_initial` char(1) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`id`, `user_id`, `faculty_id`, `first_name`, `last_name`, `middle_initial`, `department`, `profile_image`, `status`, `created_at`) VALUES
(1, 10, '54656', 's', 's', 's', 'TLE', 'uploads/members/faculty_69835fc398796.jpg', 'active', '2026-02-04 15:03:31');

-- --------------------------------------------------------

--
-- Table structure for table `fines`
--

CREATE TABLE `fines` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `calculated_at` datetime DEFAULT current_timestamp(),
  `paid` tinyint(1) DEFAULT 0,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `non_faculty`
--

CREATE TABLE `non_faculty` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_initial` char(1) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `non_faculty`
--

INSERT INTO `non_faculty` (`id`, `user_id`, `employee_id`, `first_name`, `last_name`, `middle_initial`, `department`, `profile_image`, `status`, `created_at`) VALUES
(1, 9, '43455', 'non', 'faculty', 'm', 'IT', 'uploads/members/non-faculty_69835e54e16df.jpg', 'active', '2026-02-04 14:57:25'),
(2, 13, '1234', 'Sodeha', 'Hadi', 'D', 'Library', 'uploads/members/non-faculty_698d2022edd61.jpg', 'active', '2026-02-12 00:34:43'),
(3, 16, '66-666', 'Casca', 'Berserk', 'N', 'Eclipse', 'uploads/members/non-faculty_698d5690de0b9.gif', 'active', '2026-02-12 04:26:56');

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

CREATE TABLE `profile` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profile`
--

INSERT INTO `profile` (`id`, `name`) VALUES
(1, 'Administrator'),
(3, 'Faculty'),
(4, 'Non-Faculty\r\n'),
(2, 'Student');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_initial` varchar(5) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_id`, `first_name`, `last_name`, `middle_initial`, `profile_image`, `join_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-12314', 'Michaella', 'Santos', 'S', NULL, '2026-01-27', 'active', '2026-01-27 11:46:47', '2026-01-30 03:21:01'),
(2, 5, '2025-12354', 'Joshua ', 'Jabinal', 'M', 'uploads/students/stud_6978aa37d2654.jpg', '2026-01-27', 'active', '2026-01-27 12:06:15', '2026-01-30 03:21:18'),
(5, 11, '0896768', 'lanie', 'santos', 's', 'uploads/members/faculty_69836226bbe52.jpg', '2026-02-04', 'active', '2026-02-04 15:13:42', '2026-02-04 15:13:42'),
(7, 14, '2025-58154', 'Punpun', 'Onodera', 'D', 'uploads/members/student_698d24300626f.jpg', '2026-02-12', 'active', '2026-02-12 00:52:00', '2026-02-12 00:52:00'),
(8, 15, '2025-58153', 'Lexus', 'Monte', 'F', 'uploads/members/student_698d5636a7479.jpg', '2026-02-12', 'active', '2026-02-12 04:25:26', '2026-02-12 04:25:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `profile_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `profile_image`, `profile_id`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$5xKmcOOTqEztpzDnFP7BC.c6O1tZ9P2n9yngnhiot2juHL56c0V9e', NULL, NULL, NULL, NULL, 1),
(3, 'sigelang', '', '$2y$10$HToXGEfBWKiRIKTVcsnxw.n4l/gLh6wUhFuogG2IPMjmMJHM0MOg2', 'gege', 'gege', NULL, NULL, 2),
(5, 'naoya', 'kz@gmail.com', '$2y$10$4bYOFLOvVaLbVLA6szK9ZOFp5NQ0LdqaCrqL9JWdRMilK23tjqf.y', 'Naobito', 'Naoya', NULL, 'uploads/students/stud_6978aa37d2654.jpg', 2),
(9, 'nonfaculty', 'nonfaculty@gmail.com', '$2y$10$fA2WmCyT3Kn1ypcrE9lT2OZMk9pHKkbw5JEPohfCrdKggiNxXxnua', 'non', 'faculty', '09516325287', 'uploads/members/non-faculty_69835e54e16df.jpg', 4),
(10, 'sisa', 'ssasdasd@gmail.com', '$2y$10$RbaPrNzfp2UkdzwJ6W1bVOz.1syS0r.6F1w/WGLKPkJuqd.bEx0li', 's', 's', '09766564542', 'uploads/members/faculty_69835fc398796.jpg', 3),
(11, 'laniesantos', 'laniesantos@gmail.com', '$2y$10$CAg.TGHD9GXjElVdn3LinOhWzAZmcpxNov1J3I6sCVA8diqc5l1D.', 'lanie', 'santos', '0998657978', 'uploads/members/faculty_69836226bbe52.jpg', 2),
(13, 'sodeha', 'asd@yahoo.com', '$2y$10$9rOWvv/lGMUC62bUzbcxWOLpIk4zUXvV0g8qXfJVHr1jS/A5wegjK', 'Sodeha', 'Hadi', '09658475444', 'uploads/members/non-faculty_698d2022edd61.jpg', 4),
(14, 'punpun', 'punpun@gmail.com', '$2y$10$pt63bZ2hbW/ZlNmOmRaj0OLqVxoARxR5M.EltSCGw.K4lbsx1uHz.', 'Punpun', 'Onodera', '09659547221', 'uploads/members/student_698d24300626f.jpg', 2),
(15, 'lexus', 'lexus@gmail.com', '$2y$10$NhUSIWHiO5E0ahHlBL4eU.G7ztNFsa6A/RayFeMTupMnUF8I9ZRKe', 'Lexus', 'Monte', '09056548444', 'uploads/members/student_698d5636a7479.jpg', 2),
(16, 'casca', 'casca@gmail.com', '$2y$10$tsEDk45tuxipScUpin8u2e364pjNzwROnL6GvWUEe.yMdTPUuUssy', 'Casca', 'Berserk', '09666666666', 'uploads/members/non-faculty_698d5690de0b9.gif', 4);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_title` (`title`),
  ADD KEY `idx_author` (`author`);

--
-- Indexes for table `books_requests`
--
ALTER TABLE `books_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `book_requests`
--
ALTER TABLE `book_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `faculty_id` (`faculty_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `fines`
--
ALTER TABLE `fines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `non_faculty`
--
ALTER TABLE `non_faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `profile`
--
ALTER TABLE `profile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_profile_id` (`profile_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `books_requests`
--
ALTER TABLE `books_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `book_requests`
--
ALTER TABLE `book_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fines`
--
ALTER TABLE `fines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `non_faculty`
--
ALTER TABLE `non_faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `books_requests`
--
ALTER TABLE `books_requests`
  ADD CONSTRAINT `books_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `book_requests`
--
ALTER TABLE `book_requests`
  ADD CONSTRAINT `book_requests_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty`
--
ALTER TABLE `faculty`
  ADD CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fines`
--
ALTER TABLE `fines`
  ADD CONSTRAINT `fines_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `book_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `non_faculty`
--
ALTER TABLE `non_faculty`
  ADD CONSTRAINT `non_faculty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`profile_id`) REFERENCES `profile` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
