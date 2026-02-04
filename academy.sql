-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 04, 2026 at 04:32 PM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `call_number`, `title`, `shelf_location`, `author`, `category`, `copyright_year`, `isbn`, `quantity`, `created_at`) VALUES
(2, '3', 'Career Pathways in TLE', 'TLE/HELE', 'Rolando V. Cristobal', 'Applied Science', 2015, '640C86c', 1, '2026-01-27 10:56:49'),
(3, '50', 'Kamalayang Panlipunan', 'TLE/HELE', 'Neil Alvin Nicerio', 'Geography and History', 2013, '9786214054022', 1, '2026-01-27 10:58:59'),
(4, '56', 'Skills for a Lifetime in TLE', 'TLE/HELE', 'Virginia Esmilla Sercado', 'Literature', 2025, '978971655441', 4, '2026-01-27 11:03:45'),
(6, '900-999', 'Sanayang Aklat Para sa Noli Me Tangere', NULL, 'sfds', 'Literature', 1900, '8657647476', 22, '2026-01-30 03:36:33');

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
  `return_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_requests`
--

INSERT INTO `book_requests` (`id`, `student_id`, `book_id`, `request_type`, `request_date`, `status`, `created_at`, `updated_at`, `return_date`) VALUES
(1, 1, 3, 'reserve', '2026-01-27 11:32:00', 'pending', '2026-01-27 11:32:00', '2026-01-27 11:32:00', NULL),
(2, 1, 3, 'reserve', '2026-01-27 11:32:03', 'approved', '2026-01-27 11:32:03', '2026-01-30 03:20:39', NULL),
(3, 5, 3, 'borrow', '2026-01-27 15:48:14', 'returned', '2026-01-27 15:48:14', '2026-01-28 18:52:20', NULL),
(4, 5, 3, 'borrow', '2026-01-28 16:41:39', 'returned', '2026-01-28 16:41:39', '2026-01-29 08:17:36', NULL),
(5, 5, 4, 'borrow', '2026-01-28 17:43:17', 'pending', '2026-01-28 17:43:17', '2026-01-28 17:43:17', NULL),
(6, 3, 4, 'borrow', '2026-01-28 17:58:13', 'approved', '2026-01-28 17:58:13', '2026-01-30 03:37:54', NULL),
(7, 3, 4, 'borrow', '2026-01-28 18:03:28', 'rejected', '2026-01-28 18:03:28', '2026-01-30 03:37:49', NULL),
(8, 5, 6, 'reserve', '2026-01-30 03:40:13', 'approved', '2026-01-30 03:40:13', '2026-01-30 03:43:22', NULL),
(9, 5, 4, 'borrow', '2026-01-30 03:42:44', 'approved', '2026-01-30 03:42:44', '2026-01-30 03:43:20', NULL);

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
(1, 9, '43455', 'non', 'faculty', 'm', 'IT', 'uploads/members/non-faculty_69835e54e16df.jpg', 'active', '2026-02-04 14:57:25');

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
(5, 11, '0896768', 'lanie', 'santos', 's', 'uploads/members/faculty_69836226bbe52.jpg', '2026-02-04', 'active', '2026-02-04 15:13:42', '2026-02-04 15:13:42');

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
(2, 'student ', 'student@gmail.com', '$2a$12$Yuyt.sWbyege5Flw0STI7uzDGGK456bojHgrLkOehrDxdKSKDAyQ2', NULL, NULL, NULL, NULL, 2),
(3, 'sigelang', '', '$2y$10$HToXGEfBWKiRIKTVcsnxw.n4l/gLh6wUhFuogG2IPMjmMJHM0MOg2', 'gege', 'gege', NULL, NULL, NULL),
(5, 'naoya', 'kz@gmail.com', '$2y$10$4bYOFLOvVaLbVLA6szK9ZOFp5NQ0LdqaCrqL9JWdRMilK23tjqf.y', 'Naobito', 'Naoya', NULL, 'uploads/students/stud_6978aa37d2654.jpg', NULL),
(7, 'faculty', 'faculty@gmail.com', '$2y$10$E/ExSaHEpMhq81FQoLIh4uM7mVlzbiLe1nkfwxwH33Q.JnQRGtX8C', 'faculty', NULL, NULL, NULL, NULL),
(9, 'nonfaculty', 'nonfaculty@gmail.com', '$2y$10$fA2WmCyT3Kn1ypcrE9lT2OZMk9pHKkbw5JEPohfCrdKggiNxXxnua', 'non', 'faculty', '09516325287', 'uploads/members/non-faculty_69835e54e16df.jpg', NULL),
(10, 'sisa', 'ssasdasd@gmail.com', '$2y$10$RbaPrNzfp2UkdzwJ6W1bVOz.1syS0r.6F1w/WGLKPkJuqd.bEx0li', 's', 's', '09766564542', 'uploads/members/faculty_69835fc398796.jpg', NULL),
(11, 'laniesantos', 'laniesantos@gmail.com', '$2y$10$CAg.TGHD9GXjElVdn3LinOhWzAZmcpxNov1J3I6sCVA8diqc5l1D.', 'lanie', 'santos', '0998657978', 'uploads/members/faculty_69836226bbe52.jpg', NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `books_requests`
--
ALTER TABLE `books_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `book_requests`
--
ALTER TABLE `book_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `non_faculty`
--
ALTER TABLE `non_faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
