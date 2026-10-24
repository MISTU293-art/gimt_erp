-- GIMT Enterprise ERP v3.0 Database Schema
-- Production Specification Architecture: PHP 8.x, MySQL 8.x / MariaDB

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Core Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) UNIQUE NOT NULL,
  `mobile` VARCHAR(20) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('chairman', 'principal', 'accounts', 'hr', 'hod', 'teacher', 'student') NOT NULL DEFAULT 'teacher',
  `status` ENUM('active', 'blocked') DEFAULT 'active',
  `is_blocked` TINYINT(1) DEFAULT 0,
  `blocked_at` DATETIME NULL,
  `blocked_by` INT UNSIGNED NULL,
  `address` VARCHAR(255) NULL,
  `qualification` VARCHAR(150) NULL,
  `bank_account` VARCHAR(150) NULL,
  `ifsc_code` VARCHAR(20) NULL,
  `bank_name` VARCHAR(150) NULL,
  `micr_code` VARCHAR(20) NULL,
  `branch_name` VARCHAR(150) NULL,
  `pan_id` VARCHAR(20) NULL,
  `aadhaar` VARCHAR(12) NULL,
  `staff_id` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (`role`),
  INDEX idx_users_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dept_code` VARCHAR(20) UNIQUE NOT NULL,
  `dept_name` VARCHAR(100) NOT NULL,
  `dept_capacity` INT DEFAULT 60,
  `hod_user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`hod_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Admissions Applications Master
CREATE TABLE IF NOT EXISTS `admissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(50) UNIQUE NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `dob` DATE NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL,
  `blood_group` VARCHAR(10) NULL,
  `category` VARCHAR(30) DEFAULT 'General',
  `aadhaar_no` VARCHAR(20) NULL,
  `guardian_name` VARCHAR(100) NOT NULL,
  `guardian_relation` VARCHAR(50) NOT NULL,
  `guardian_phone` VARCHAR(20) NOT NULL,
  `address` TEXT NOT NULL,
  `tenth_board` VARCHAR(100) NULL,
  `tenth_year` INT NULL,
  `tenth_percentage` DECIMAL(5,2) NULL,
  `twelfth_board` VARCHAR(100) NULL,
  `twelfth_year` INT NULL,
  `twelfth_percentage` DECIMAL(5,2) NULL,
  `department_id` INT UNSIGNED NOT NULL,
  `quota` ENUM('general', 'management', 'tfw') DEFAULT 'general',
  `batch_year` INT NOT NULL DEFAULT 2026,
  `doc_aadhaar` VARCHAR(255) NULL,
  `doc_tenth` VARCHAR(255) NULL,
  `doc_twelfth` VARCHAR(255) NULL,
  `doc_tc` VARCHAR(255) NULL,
  `doc_photo` VARCHAR(255) NULL,
  `application_fee` DECIMAL(10,2) DEFAULT 1000.00,
  `payment_status` ENUM('pending', 'success', 'failed') DEFAULT 'pending',
  `payment_reference` VARCHAR(100) NULL,
  `payment_date` DATETIME NULL,
  `status` ENUM('submitted', 'under_review', 'document_verified', 'fee_cleared', 'enrolled', 'rejected') DEFAULT 'submitted',
  `enrolled_student_id` INT UNSIGNED NULL,
  `admin_remarks` TEXT NULL,
  `reviewed_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admissions_status (`status`),
  INDEX idx_admissions_app_no (`application_no`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Students Table
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED UNIQUE NOT NULL,
  `roll_no` VARCHAR(50) UNIQUE NOT NULL,
  `reg_no` VARCHAR(50) UNIQUE NULL,
  `department_id` INT UNSIGNED NOT NULL,
  `batch_year` INT NOT NULL,
  `current_semester` INT DEFAULT 1,
  `phone` VARCHAR(20) NOT NULL,
  `guardian_name` VARCHAR(100) NULL,
  `guardian_phone` VARCHAR(20) NULL,
  `address` TEXT NOT NULL,
  `dob` DATE NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT 'male',
  `blood_group` VARCHAR(10) NULL,
  `quota` ENUM('general', 'management', 'tfw') DEFAULT 'general',
  `admission_status` ENUM('applied', 'verified', 'admitted', 'rejected') DEFAULT 'admitted',
  `admission_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_students_roll (`roll_no`),
  INDEX idx_students_dept_sem (`department_id`, `current_semester`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`admission_id`) REFERENCES `admissions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Courses Table
CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT UNSIGNED NOT NULL,
  `course_code` VARCHAR(20) UNIQUE NOT NULL,
  `course_name` VARCHAR(150) NOT NULL,
  `semester` INT NOT NULL,
  `credits` INT DEFAULT 3,
  `subject_type` ENUM('Theory', 'Practical', 'Elective', 'Audit') DEFAULT 'Theory',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_courses_dept_sem (`department_id`, `semester`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Faculty Workload & Course Assignment
CREATE TABLE IF NOT EXISTS `faculty_courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026-2027',
  `section` VARCHAR(10) DEFAULT 'A',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_course_teacher_sec` (`course_id`, `teacher_id`, `academic_year`, `section`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Fee Structures
CREATE TABLE IF NOT EXISTS `fee_structures` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT UNSIGNED NOT NULL,
  `quota` ENUM('general', 'management', 'tfw') DEFAULT 'general',
  `batch_year` INT NOT NULL,
  `semester` INT NOT NULL,
  `tuition_fee` DECIMAL(10,2) DEFAULT 0.00,
  `development_fee` DECIMAL(10,2) DEFAULT 0.00,
  `library_fee` DECIMAL(10,2) DEFAULT 0.00,
  `lab_fee` DECIMAL(10,2) DEFAULT 0.00,
  `hostel_fee` DECIMAL(10,2) DEFAULT 0.00,
  `bus_fee` DECIMAL(10,2) DEFAULT 0.00,
  `caution_deposit` DECIMAL(10,2) DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_fee_struct` (`department_id`, `quota`, `batch_year`, `semester`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Student Financial Ledger
CREATE TABLE IF NOT EXISTS `student_ledger` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `transaction_date` DATE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `type` ENUM('debit', 'credit', 'waiver') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_mode` ENUM('Cash', 'Cheque', 'Demand Draft', 'UPI', 'Net Banking', 'Adjustment', 'System') DEFAULT 'Cash',
  `reference_no` VARCHAR(100) NULL,
  `receipt_no` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ledger_student (`student_id`),
  INDEX idx_ledger_date (`transaction_date`),
  INDEX idx_ledger_receipt (`receipt_no`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Attendance Register
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `period` TINYINT DEFAULT 1,
  `status` ENUM('present', 'absent', 'late', 'on_duty') NOT NULL,
  `marked_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_student_course_date_period` (`student_id`, `course_id`, `attendance_date`, `period`),
  INDEX idx_attendance_student (`student_id`),
  INDEX idx_attendance_course_date (`course_id`, `attendance_date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Student Leave & Condonation Requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `leave_type` ENUM('Medical', 'Personal', 'Official Duty', 'Condonation') DEFAULT 'Medical',
  `reason` TEXT NOT NULL,
  `attachment` VARCHAR(255) NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED NULL,
  `review_remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_leave_student (`student_id`),
  INDEX idx_leave_status (`status`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Hall Ticket Overrides
CREATE TABLE IF NOT EXISTS `hall_ticket_overrides` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `exam_series` VARCHAR(50) NOT NULL,
  `semester` INT NOT NULL,
  `override_type` ENUM('attendance', 'fee', 'both') DEFAULT 'attendance',
  `reason` TEXT NOT NULL,
  `approved_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_override` (`student_id`, `exam_series`, `semester`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Assignments Master
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `attachment` VARCHAR(255) NULL,
  `max_marks` INT DEFAULT 100,
  `due_date` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_assignments_course (`course_id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Assignment Submissions
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_late` TINYINT(1) DEFAULT 0,
  `marks_obtained` DECIMAL(5,2) NULL,
  `feedback` TEXT NULL,
  `graded_by` INT UNSIGNED NULL,
  `graded_at` DATETIME NULL,
  UNIQUE KEY `uniq_assignment_student` (`assignment_id`, `student_id`),
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`graded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Exam Series Setup
CREATE TABLE IF NOT EXISTS `exam_series` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `series_name` VARCHAR(50) NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026-2027',
  `semester` INT NOT NULL,
  `max_marks` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  `passing_floor` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_exam_series` (`series_name`, `academic_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Internal Examinations
CREATE TABLE IF NOT EXISTS `internal_exams` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `exam_name` VARCHAR(50) NOT NULL,
  `marks_obtained` DECIMAL(5,2) NOT NULL,
  `max_marks` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `is_locked` TINYINT(1) DEFAULT 0,
  `entered_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_student_course_exam` (`student_id`, `course_id`, `exam_name`),
  INDEX idx_internal_exams_course (`course_id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`entered_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_action (`action`),
  INDEX idx_audit_entity (`entity_type`, `entity_id`),
  INDEX idx_audit_time (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
