-- =========================================================
-- CEDAR WELLNESS HUB - Database Schema
-- Employee Management & Attendance Monitoring System
-- Import this file via phpMyAdmin (XAMPP) to create the DB
-- =========================================================

CREATE DATABASE IF NOT EXISTS cedar_wellness_hub
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cedar_wellness_hub;

-- ---------------------------------------------------------
-- BRANCHES
-- ---------------------------------------------------------
CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_name VARCHAR(100) NOT NULL,
  hr_capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  address_line VARCHAR(100) NULL,
  barangay VARCHAR(80) NULL,
  city VARCHAR(80) NULL,
  province VARCHAR(80) NULL,
  postal_code VARCHAR(4) NULL,
  address VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  contact_number VARCHAR(30) DEFAULT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- USERS (login accounts for all 3 roles)
-- ---------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Employee ID Number for employees, chosen username for HR/Superadmin',
  email VARCHAR(120) DEFAULT NULL UNIQUE COMMENT 'Login email — employees may sign in with this OR their Employee ID',
  first_name VARCHAR(60) NULL,
  last_name VARCHAR(60) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('superadmin','hr','employee') NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- HR USER BRANCH ASSIGNMENT (one branch per HR account)
-- ---------------------------------------------------------
CREATE TABLE user_branch_assignments (
  user_id INT NOT NULL PRIMARY KEY,
  branch_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_branch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_branch_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- EMPLOYEES (profile linked to a user account)
-- ---------------------------------------------------------
CREATE TABLE employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  employee_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Auto-generated Employee ID Number, e.g. CWH-2026-00001',

  -- Personal information
  first_name VARCHAR(60) NOT NULL,
  middle_name VARCHAR(60) DEFAULT NULL,
  last_name VARCHAR(60) NOT NULL,
  suffix VARCHAR(10) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL COMMENT 'Path to uploaded ID/profile picture',
  date_of_birth DATE DEFAULT NULL,
  gender ENUM('Male','Female','Other') DEFAULT NULL,
  civil_status ENUM('Single','Married','Widowed','Separated','Divorced') DEFAULT NULL,
  nationality VARCHAR(60) DEFAULT 'Filipino',

  -- Contact
  email VARCHAR(100) DEFAULT NULL UNIQUE,
  contact_number VARCHAR(30) DEFAULT NULL,
  complete_address VARCHAR(255) DEFAULT NULL,
  emergency_contact_name VARCHAR(100) DEFAULT NULL,
  emergency_contact_number VARCHAR(30) DEFAULT NULL,

  -- Employment
  branch_id INT DEFAULT NULL,
  department VARCHAR(80) DEFAULT NULL,
  position VARCHAR(80) NOT NULL,
  employment_type ENUM('Regular','Probationary','Contractual','Part-Time') NOT NULL DEFAULT 'Probationary',
  employment_status ENUM('Active','Inactive','Resigned','Terminated') NOT NULL DEFAULT 'Active',
  hire_date DATE DEFAULT NULL,

  -- Government IDs (Philippines-style HRIS; optional)
  sss_number VARCHAR(30) DEFAULT NULL,
  philhealth_number VARCHAR(30) DEFAULT NULL,
  pagibig_number VARCHAR(30) DEFAULT NULL,
  tin_number VARCHAR(30) DEFAULT NULL,

  -- System
  qr_code_value VARCHAR(100) NOT NULL UNIQUE,
  on_field TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Simple active/inactive flag used for attendance/login gating',
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ATTENDANCE (QR scan: Time In / Time Out only; scheduled breaks are stored on schedules)
-- ---------------------------------------------------------
CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  time_in DATETIME DEFAULT NULL,
  break_out DATETIME DEFAULT NULL,
  break_in DATETIME DEFAULT NULL,
  time_out DATETIME DEFAULT NULL,
  total_hours DECIMAL(5,2) DEFAULT NULL,
  remarks ENUM('present','late','absent','half-day') NOT NULL DEFAULT 'present',
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_emp_date (employee_id, attendance_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- SCHEDULES (manual duty scheduling / day-off calendar)
-- ---------------------------------------------------------
CREATE TABLE schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  schedule_date DATE NOT NULL,
  shift_start TIME DEFAULT NULL,
  shift_end TIME DEFAULT NULL,
  break_start TIME DEFAULT NULL,
  break_end TIME DEFAULT NULL,
  is_day_off TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) DEFAULT NULL,
  created_by INT DEFAULT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_emp_sched_date (employee_id, schedule_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- LEAVE REQUESTS
-- ---------------------------------------------------------
CREATE TABLE leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  leave_type ENUM('sick','vacation','emergency','unpaid','other') NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  reason VARCHAR(500) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT DEFAULT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- BOOKINGS (manual encode of customer reservations via SMS/Messenger/etc.)
-- ---------------------------------------------------------
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(100) NOT NULL,
  contact_number VARCHAR(30) DEFAULT NULL,
  service VARCHAR(150) NOT NULL,
  branch_id INT DEFAULT NULL,
  booking_date DATE NOT NULL,
  booking_time TIME NOT NULL,
  source ENUM('sms','messenger','call','walk-in','other') NOT NULL DEFAULT 'sms',
  assigned_employee_id INT DEFAULT NULL,
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  encoded_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
  FOREIGN KEY (encoded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- BACKUP LOGS (records of auto/manual database backups)
-- ---------------------------------------------------------
CREATE TABLE backup_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  triggered_by ENUM('auto','manual') NOT NULL DEFAULT 'manual',
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- SEED DATA
-- =========================================================

-- Sample branches (placeholders - Cavite/Metro Manila area)
INSERT INTO branches (branch_name, address, latitude, longitude, contact_number) VALUES
('Cedar Wellness Hub - Indang', 'Brgy. Poblacion I, Indang, Cavite', 14.1962, 120.8781, '0917-000-1111'),
('Cedar Wellness Hub - Dasmarinas', 'Governor\'s Drive, Dasmarinas, Cavite', 14.3294, 120.9367, '0917-000-2222'),
('Cedar Wellness Hub - Tagaytay', 'Tagaytay-Nasugbu Hwy, Tagaytay City', 14.0997, 120.9394, '0917-000-3333'),
('Remantec Corporation - J.R. Borja', 'Burgos St, Brgy. 7, Cagayan de Oro City, 9000 Misamis Oriental', 8.4791980, 124.6416483, NULL);

-- NOTE: Default accounts (superadmin, hr01, emp01) are NOT inserted here because
-- passwords must be hashed with PHP's password_hash() to work with password_verify().
-- After importing this SQL file, open setup_seed.php ONCE in your browser
-- (e.g. http://localhost/cedar-wellness-hub/setup_seed.php) to auto-create:
--   superadmin / Cedar@123
--   hr01       / Cedar@123
--   emp01      / Cedar@123
-- setup_seed.php deletes itself after running successfully, for security.
