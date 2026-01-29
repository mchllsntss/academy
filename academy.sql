-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 29, 2026 at 03:23 PM
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
(2, '3', 'Wimpy Kid', '60', 'Me', 'Arts and Recreation', 2026, '123', 1, '2026-01-27 10:56:49'),
(3, '50', 'Ewan', 'dito lang', 'Siya', 'Geography and History', 2025, '50', 1, '2026-01-27 10:58:59'),
(4, '56', 'hi', 'shelf 50', '216', 'Literature', 2025, '45', 6, '2026-01-27 11:03:45');

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
(1, 5, 'dd', 'dd', 'dd', 'dd', '2026-01-28', 'approved', '2026-01-28 16:15:26', NULL),
(2, 5, '123', '123', '213', '123', '2026-01-28', 'pending', '2026-01-28 17:27:45', NULL),
(3, 3, '123444', '123', '123', '123444', '2026-01-28', 'rejected', '2026-01-28 17:28:18', NULL);

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
(2, 1, 3, 'borrow', '2026-01-27 11:32:03', 'approved', '2026-01-27 11:32:03', '2026-01-27 11:34:20', NULL),
(3, 5, 3, 'borrow', '2026-01-27 15:48:14', 'returned', '2026-01-27 15:48:14', '2026-01-28 18:52:20', NULL),
(4, 5, 3, 'borrow', '2026-01-28 16:41:39', 'returned', '2026-01-28 16:41:39', '2026-01-29 08:17:36', NULL),
(5, 5, 4, 'borrow', '2026-01-28 17:43:17', 'pending', '2026-01-28 17:43:17', '2026-01-28 17:43:17', NULL),
(6, 3, 4, 'borrow', '2026-01-28 17:58:13', 'pending', '2026-01-28 17:58:13', '2026-01-28 17:58:13', NULL),
(7, 3, 4, 'borrow', '2026-01-28 18:03:28', 'pending', '2026-01-28 18:03:28', '2026-01-28 18:03:28', NULL);

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
(2, 'student');

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
(1, 3, '2025-12314', 'gege', 'gege', 'g', NULL, '2026-01-27', 'active', '2026-01-27 11:46:47', '2026-01-27 11:46:47'),
(2, 5, '2025-12354', 'Naobito', 'Naoya', 'Z', 'uploads/students/stud_6978aa37d2654.jpg', '2026-01-27', 'active', '2026-01-27 12:06:15', '2026-01-27 12:06:15');

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
(5, 'naoya', 'kz@gmail.com', '$2y$10$4bYOFLOvVaLbVLA6szK9ZOFp5NQ0LdqaCrqL9JWdRMilK23tjqf.y', 'Naobito', 'Naoya', NULL, 'uploads/students/stud_6978aa37d2654.jpg', NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `books_requests`
--
ALTER TABLE `books_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `book_requests`
--
ALTER TABLE `book_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
