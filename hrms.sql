-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 03, 2026 at 05:28 AM
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
-- Database: `hrms`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `working_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('Present','Absent','Half-day','Leave') DEFAULT 'Present',
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` enum('Paid','Sick','Unpaid') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_comment` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary`
--

CREATE TABLE `salary` (
  `salary_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) GENERATED ALWAYS AS ((`basic_salary` + `allowances`) - `deductions`) STORED,
  `currency` varchar(10) DEFAULT 'USD',
  `effective_date` date DEFAULT NULL,
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `date_of_joining` date DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Insert default admin user
-- Email: admin@hrms.com
-- Password: password (bcrypt hashed)
--

INSERT INTO `users` (`user_id`, `employee_id`, `name`, `email`, `password`, `role`, `department`, `position`, `status`) VALUES
(1, 'ADM001', 'Admin User', 'admin@hrms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'HR', 'HR Manager', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`);

--
-- Indexes for table `salary`
--
ALTER TABLE `salary`
  ADD PRIMARY KEY (`salary_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary`, AUTO_INCREMENT=2
--

ALTER TABLE `salary`
  MODIFY `salary_id` int(11) NOT NULL AUTO_INCREMENT;

-- Indexes for table `payroll_history`
--
ALTER TABLE `payroll_history`
  ADD PRIMARY KEY (`payroll_id`),
  ADD UNIQUE KEY `user_month` (`user_id`,`month`);

-- AUTO_INCREMENT for table `payroll_history`
--
ALTER TABLE `payroll_history`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_history`
--

CREATE TABLE `payroll_history` (
  `payroll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month` varchar(7) NOT NULL,
  `gross_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(10,2) NOT NULL DEFAULT 0.00,
  `base_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `attendance_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_payable` decimal(10,2) NOT NULL DEFAULT 0.00,
  `present_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `half_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `leave_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `absent_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `payable_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `generated_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
