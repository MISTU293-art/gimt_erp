<?php
// setup_database.php - Enterprise Database Schema & Seeder for GIMT ERP v3.0 (MAKAUT Compliant)

require_once __DIR__ . '/src/bootstrap.php';

try {
    $pdo = getDb();
    echo "[OK] Connected to MySQL server & database\n";

    // 1. Add MAKAUT columns to students table if not exist
    $cols = $pdo->query("SHOW COLUMNS FROM `students` LIKE 'makaut_reg_no'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE `students` ADD COLUMN `makaut_reg_no` VARCHAR(30) NULL AFTER `roll_no`");
        $pdo->exec("ALTER TABLE `students` ADD COLUMN `makaut_roll_no` VARCHAR(30) NULL AFTER `makaut_reg_no`");
        $pdo->exec("ALTER TABLE `students` ADD COLUMN `mar_points` INT NOT NULL DEFAULT 0 AFTER `quota`");
        $pdo->exec("ALTER TABLE `students` ADD COLUMN `moocs_credits` INT NOT NULL DEFAULT 0 AFTER `mar_points`");
        echo "[OK] Added MAKAUT registration, roll, MAR points and MOOCs columns to students table\n";
    }

    // 2. Create MAKAUT MAR (Mandatory Additional Requirements) table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `makaut_mar_records` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026-2027',
            `category` VARCHAR(100) NOT NULL,
            `activity_name` VARCHAR(255) NOT NULL,
            `claimed_points` INT NOT NULL DEFAULT 10,
            `awarded_points` INT DEFAULT NULL,
            `certificate_file` VARCHAR(255) NULL,
            `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            `reviewed_by` INT UNSIGNED NULL,
            `review_notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mar_student (`student_id`),
            INDEX idx_mar_status (`status`),
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] MAKAUT MAR table created\n";

    // 3. Create MAKAUT MOOCs (SWAYAM / NPTEL) table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `makaut_moocs_records` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026-2027',
            `platform` ENUM('NPTEL', 'SWAYAM', 'Coursera', 'edX', 'Other') DEFAULT 'NPTEL',
            `course_title` VARCHAR(255) NOT NULL,
            `course_duration` VARCHAR(50) DEFAULT '12 Weeks',
            `credits_claimed` INT NOT NULL DEFAULT 3,
            `credits_awarded` INT DEFAULT NULL,
            `certificate_file` VARCHAR(255) NULL,
            `score_percentage` DECIMAL(5,2) NULL,
            `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            `reviewed_by` INT UNSIGNED NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_moocs_student (`student_id`),
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] MAKAUT MOOCs table created\n";

    // 4. Populate MAKAUT University Registration & Roll for existing students
    $students = $pdo->query("SELECT id, roll_no FROM students")->fetchAll();
    $updateStd = $pdo->prepare("UPDATE students SET makaut_reg_no = ?, makaut_roll_no = ?, mar_points = ? WHERE id = ?");
    foreach ($students as $st) {
        $reg = sprintf("249%s%04d", date('Y'), $st['id']);
        $uniRoll = sprintf("249001%02d%03d", (int)substr(date('Y'), 2, 2), $st['id']);
        $mar = ($st['id'] == 1) ? 45 : (($st['id'] == 2) ? 60 : 25);
        $updateStd->execute([$reg, $uniRoll, $mar, $st['id']]);
    }
    echo "[OK] MAKAUT university reg numbers and roll numbers assigned\n";

    // 4b. Add academic_session to student_ledger, attendance, internal_exams
    $colLedger = $pdo->query("SHOW COLUMNS FROM `student_ledger` LIKE 'academic_session'")->fetchAll();
    if (empty($colLedger)) {
        $pdo->exec("ALTER TABLE `student_ledger` ADD COLUMN `academic_session` VARCHAR(20) NOT NULL DEFAULT '2026-2027' AFTER `transaction_date`");
        $pdo->exec("ALTER TABLE `student_ledger` ADD INDEX `idx_sl_session` (`academic_session`)");
        echo "[OK] Added academic_session to student_ledger\n";
    }

    $colAtt = $pdo->query("SHOW COLUMNS FROM `attendance` LIKE 'academic_session'")->fetchAll();
    if (empty($colAtt)) {
        $pdo->exec("ALTER TABLE `attendance` ADD COLUMN `academic_session` VARCHAR(20) NOT NULL DEFAULT '2026-2027' AFTER `attendance_date`");
        $pdo->exec("ALTER TABLE `attendance` ADD INDEX `idx_att_session` (`academic_session`)");
        echo "[OK] Added academic_session to attendance\n";
    }

    $colExam = $pdo->query("SHOW COLUMNS FROM `internal_exams` LIKE 'academic_session'")->fetchAll();
    if (empty($colExam)) {
        $pdo->exec("ALTER TABLE `internal_exams` ADD COLUMN `academic_session` VARCHAR(20) NOT NULL DEFAULT '2026-2027' AFTER `course_id`");
        $pdo->exec("ALTER TABLE `internal_exams` ADD INDEX `idx_exam_session` (`academic_session`)");
        echo "[OK] Added academic_session to internal_exams\n";
    }

    // 5. Seed sample MAR activities for Student 1
    $s1 = (int)$pdo->query("SELECT id FROM students LIMIT 1")->fetchColumn();
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetchColumn();
    if ($s1 && $teacherId) {
        $marCount = $pdo->query("SELECT COUNT(*) FROM makaut_mar_records WHERE student_id = {$s1}")->fetchColumn();
        if ($marCount == 0) {
            $marStmt = $pdo->prepare("
                INSERT INTO makaut_mar_records (student_id, academic_year, category, activity_name, claimed_points, awarded_points, status, reviewed_by, review_notes)
                VALUES (?, '2026-2027', ?, ?, ?, ?, 'approved', ?, 'Verified official certificate.')
            ");
            $marStmt->execute([$s1, 'Tree Plantation & Environmental Drive', 'GIMT Green Campus Plantation Drive 2026', 15, 15, $teacherId]);
            $marStmt->execute([$s1, 'Technical Fest / Competition', 'MAKAUT Smart Bengal Hackathon 2026 Participation', 20, 20, $teacherId]);
            $marStmt->execute([$s1, 'Blood Donation / Social Outreach', 'NSS Blood Donation Camp Volunteer', 10, 10, $teacherId]);
            echo "[OK] MAKAUT MAR sample activities seeded\n";
        }
    }

    // 6. Update Courses to MAKAUT AICTE Model Curriculum Codes
    $cseDeptId = (int)$pdo->query("SELECT id FROM departments WHERE dept_code = 'CSE' OR dept_name LIKE '%Computer%' LIMIT 1")->fetchColumn();
    if ($cseDeptId > 0) {
        $makautCourses = [
            ['PCC-CS301', 'Data Structures & Algorithms', 3, 4, 'Theory'],
            ['PCC-CS302', 'Computer Organization & Architecture', 3, 3, 'Theory'],
            ['ESC-EE301', 'Basic Electronics Engineering', 3, 3, 'Theory'],
            ['BSC-M301', 'Mathematics-III (Differential Calculus)', 3, 4, 'Theory'],
            ['HSMC-301', 'Humanities-I (Values & Ethics in Profession)', 3, 3, 'Theory'],
            ['PCC-CS391', 'Data Structures & Algorithms Laboratory', 3, 2, 'Practical'],
            ['PCC-CS392', 'Digital System Design Laboratory', 3, 2, 'Practical'],
            ['MC-381', 'Constitution of India', 3, 0, 'Audit'],
        ];

        $stmtCourse = $pdo->prepare("
            INSERT INTO courses (department_id, course_code, course_name, semester, credits, subject_type)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), credits = VALUES(credits), subject_type = VALUES(subject_type)
        ");
        foreach ($makautCourses as $mc) {
            $stmtCourse->execute([$cseDeptId, $mc[0], $mc[1], $mc[2], $mc[3], $mc[4]]);
        }
        echo "[OK] MAKAUT AICTE Model Curriculum courses updated\n";
    }

    // 7. Seed MAKAUT Continuous Assessment Series: CA1, CA2, CA3, CA4, PCA1, PCA2
    $makautSeries = [
        ['Continuous Assessment 1 (CA-1)', 25.00, 10.00],
        ['Continuous Assessment 2 (CA-2)', 25.00, 10.00],
        ['Continuous Assessment 3 (CA-3)', 25.00, 10.00],
        ['Continuous Assessment 4 (CA-4)', 25.00, 10.00],
        ['Practical Continuous Assessment 1 (PCA-1)', 40.00, 16.00],
        ['Practical Continuous Assessment 2 (PCA-2)', 40.00, 16.00],
    ];

    $serStmt = $pdo->prepare("
        INSERT INTO exam_series (series_name, academic_year, semester, max_marks, passing_floor, is_active)
        VALUES (?, '2026-2027', 3, ?, ?, 1)
        ON DUPLICATE KEY UPDATE max_marks = VALUES(max_marks), passing_floor = VALUES(passing_floor)
    ");
    foreach ($makautSeries as $ms) {
        $serStmt->execute([$ms[0], $ms[1], $ms[2]]);
    }
    echo "[OK] MAKAUT CA-1, CA-2, CA-3, CA-4, PCA-1, PCA-2 series configured\n";

    // 8. Ensure all upload directories exist
    $uploadDirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/admissions',
        __DIR__ . '/uploads/assignments',
        __DIR__ . '/uploads/leaves',
        __DIR__ . '/uploads/mar',
        __DIR__ . '/uploads/moocs',
    ];
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    echo "[OK] Upload storage directories ready\n";

    echo "\n=== MAKAUT ERP SCHEMA UPGRADE COMPLETE ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
