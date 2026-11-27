-- Cedar Wellness Hub backup generated 2026-07-24 09:47:03
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `break_out` datetime DEFAULT NULL,
  `break_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `remarks` enum('present','late','absent','half-day') NOT NULL DEFAULT 'present',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_emp_date` (`employee_id`,`attendance_date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) NOT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `service` varchar(150) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `source` enum('sms','messenger','call','walk-in','other') NOT NULL DEFAULT 'sms',
  `assigned_employee_id` int(11) DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `encoded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  KEY `assigned_employee_id` (`assigned_employee_id`),
  KEY `encoded_by` (`encoded_by`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`assigned_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`encoded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `branches` (`id`,`branch_name`,`address`,`latitude`,`longitude`,`contact_number`,`is_deleted`,`created_at`) VALUES ('1','Cedar Wellness Hub - Indang','Brgy. Poblacion I, Indang, Cavite','14.1962000','120.8781000','0917-000-1111','0','2026-07-24 15:04:58');
INSERT INTO `branches` (`id`,`branch_name`,`address`,`latitude`,`longitude`,`contact_number`,`is_deleted`,`created_at`) VALUES ('2','Cedar Wellness Hub - Dasmarinas','Governor\'s Drive, Dasmarinas, Cavite','14.3294000','120.9367000','0917-000-2222','0','2026-07-24 15:04:58');
INSERT INTO `branches` (`id`,`branch_name`,`address`,`latitude`,`longitude`,`contact_number`,`is_deleted`,`created_at`) VALUES ('3','Cedar Wellness Hub - Tagaytay','Tagaytay-Nasugbu Hwy, Tagaytay City','14.0997000','120.9394000','0917-000-3333','0','2026-07-24 15:04:58');

DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_code` varchar(20) NOT NULL COMMENT 'Auto-generated Employee ID Number, e.g. CWH-2026-00001',
  `first_name` varchar(60) NOT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded ID/profile picture',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Divorced') DEFAULT NULL,
  `nationality` varchar(60) DEFAULT 'Filipino',
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `complete_address` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(30) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `department` varchar(80) DEFAULT NULL,
  `position` varchar(80) NOT NULL,
  `employment_type` enum('Regular','Probationary','Contractual','Part-Time') NOT NULL DEFAULT 'Probationary',
  `employment_status` enum('Active','Inactive','Resigned','Terminated') NOT NULL DEFAULT 'Active',
  `hire_date` date DEFAULT NULL,
  `sss_number` varchar(30) DEFAULT NULL,
  `philhealth_number` varchar(30) DEFAULT NULL,
  `pagibig_number` varchar(30) DEFAULT NULL,
  `tin_number` varchar(30) DEFAULT NULL,
  `qr_code_value` varchar(100) NOT NULL,
  `on_field` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Simple active/inactive flag used for attendance/login gating',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `qr_code_value` (`qr_code_value`),
  UNIQUE KEY `email` (`email`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employees` (`id`,`user_id`,`employee_code`,`first_name`,`middle_name`,`last_name`,`suffix`,`photo`,`date_of_birth`,`gender`,`civil_status`,`nationality`,`email`,`contact_number`,`complete_address`,`emergency_contact_name`,`emergency_contact_number`,`branch_id`,`department`,`position`,`employment_type`,`employment_status`,`hire_date`,`sss_number`,`philhealth_number`,`pagibig_number`,`tin_number`,`qr_code_value`,`on_field`,`status`,`is_deleted`,`deleted_at`,`created_at`) VALUES ('1','3','CWH-2026-00001','Maria','Reyes','Santos','','uploads/employees/cwh_2026_00001_1784877625.jpg','1996-04-12','Female','Single','Filipino','maria.santos@cedarwellness.test','0917-123-4567','Blk 5 Lot 12, Brgy. Poblacion, Indang, Cavite','Juan Santos','0917-765-4321','1','Wellness Services','Massage Therapist','Regular','Active','2026-07-24','','','','','CWH-QR-F839BE55','0','active','0',NULL,'2026-07-24 15:05:10');
INSERT INTO `employees` (`id`,`user_id`,`employee_code`,`first_name`,`middle_name`,`last_name`,`suffix`,`photo`,`date_of_birth`,`gender`,`civil_status`,`nationality`,`email`,`contact_number`,`complete_address`,`emergency_contact_name`,`emergency_contact_number`,`branch_id`,`department`,`position`,`employment_type`,`employment_status`,`hire_date`,`sss_number`,`philhealth_number`,`pagibig_number`,`tin_number`,`qr_code_value`,`on_field`,`status`,`is_deleted`,`deleted_at`,`created_at`) VALUES ('2','4','CWH-2026-00002','Christian','Solomon','Pablo','','uploads/employees/cwh_2026_00002_1784877752.jpg','1997-01-24','Male','Single','Filipino','christian@haha.com','12314134','asdasdasd','asdasdasd','3123123','2','Wellness Services','Massage Therapist','Contractual','Active','2026-07-24','','','','','CWH-QR-2F099D96','0','active','0',NULL,'2026-07-24 15:22:32');
INSERT INTO `employees` (`id`,`user_id`,`employee_code`,`first_name`,`middle_name`,`last_name`,`suffix`,`photo`,`date_of_birth`,`gender`,`civil_status`,`nationality`,`email`,`contact_number`,`complete_address`,`emergency_contact_name`,`emergency_contact_number`,`branch_id`,`department`,`position`,`employment_type`,`employment_status`,`hire_date`,`sss_number`,`philhealth_number`,`pagibig_number`,`tin_number`,`qr_code_value`,`on_field`,`status`,`is_deleted`,`deleted_at`,`created_at`) VALUES ('3','5','CWH-2026-00003','asdasd','asd','asd','asdasd',NULL,NULL,NULL,NULL,'Filipino','asdasd@asdasd','','','','',NULL,'','asdasd','Regular','Active','2026-07-24','','','','','CWH-QR-E54861E7','0','active','0',NULL,'2026-07-24 15:31:24');

DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('sick','vacation','emergency','unpaid','other') NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `is_day_off` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_emp_sched_date` (`employee_id`,`schedule_date`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT 'Employee ID Number for employees, chosen username for HR/Superadmin',
  `email` varchar(120) DEFAULT NULL COMMENT 'Login email — employees may sign in with this OR their Employee ID',
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','hr','employee') NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`role`,`must_change_password`,`status`,`created_at`,`updated_at`) VALUES ('1','superadmin','superadmin@cedarwellness.test','$2y$10$/fXYTAEctm0qYT1crlNkDOKe3qSBnkUZCo6qc9JnW5UPSTNKgAliO','superadmin','0','active','2026-07-24 15:05:10','2026-07-24 15:05:10');
INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`role`,`must_change_password`,`status`,`created_at`,`updated_at`) VALUES ('2','hr01','hr01@cedarwellness.test','$2y$10$/fXYTAEctm0qYT1crlNkDOKe3qSBnkUZCo6qc9JnW5UPSTNKgAliO','hr','0','active','2026-07-24 15:05:10','2026-07-24 15:05:10');
INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`role`,`must_change_password`,`status`,`created_at`,`updated_at`) VALUES ('3','CWH-2026-00001','maria.santos@cedarwellness.test','$2y$10$/fXYTAEctm0qYT1crlNkDOKe3qSBnkUZCo6qc9JnW5UPSTNKgAliO','employee','1','active','2026-07-24 15:05:10','2026-07-24 15:05:10');
INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`role`,`must_change_password`,`status`,`created_at`,`updated_at`) VALUES ('4','CWH-2026-00002','christian@haha.com','$2y$10$cevXaK/ObdBaalH2VHE/Nee/6yKV/3opWBYuEvM8BM8/THs3kqcwy','employee','1','active','2026-07-24 15:22:32','2026-07-24 15:22:32');
INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`role`,`must_change_password`,`status`,`created_at`,`updated_at`) VALUES ('5','CWH-2026-00003','asdasd@asdasd','$2y$10$kFlTqxSH5O7GrGgdibiiIeQnBuGSmdrCZLkUXmZIcz4FI8izQ/xRu','employee','1','active','2026-07-24 15:31:24','2026-07-24 15:31:24');

SET FOREIGN_KEY_CHECKS=1;
