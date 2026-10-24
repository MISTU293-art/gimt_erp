
CREATE DATABASE IF NOT EXISTS gimt_erp;

USE gimt_erp;

-- USER TABLE FOR LOGIN AND REGISTRATION
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mobile VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    address VARCHAR(255),

    role ENUM(
        'chairman',
        'principal',
        'accounts',
        'hr',
        'hod',
        'teacher'
    ) NOT NULL DEFAULT 'teacher',

    qualification VARCHAR(150),

    bank_account VARCHAR(150),
    ifsc_code VARCHAR(20),
    bank_name VARCHAR(150),
    micr_code VARCHAR(20),
    branch_name VARCHAR(150),

    pan_id VARCHAR(20),
    aadhaar VARCHAR(12),

    status ENUM('active', 'blocked') NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS department(
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dept_id VARCHAR(150),
    dept_name VARCHAR (150),
    dept_capacity VARCHAR(150),
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS academic_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NOT NULL,
    batch_name VARCHAR(100) NOT NULL,
    start_year SMALLINT UNSIGNED NOT NULL,
    end_year SMALLINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_department_batch (department_id, start_year, end_year),
    CONSTRAINT fk_academic_batches_department
        FOREIGN KEY (department_id) REFERENCES department(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS academic_semesters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    semester_number TINYINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_batch_semester (batch_id, semester_number),
    CONSTRAINT fk_academic_semesters_batch
        FOREIGN KEY (batch_id) REFERENCES academic_batches(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS academic_syllabi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    semester_number TINYINT UNSIGNED NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    topics TEXT NOT NULL,
    learning_outcomes TEXT NULL,
    references_text TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_batch_semester_subject (batch_id, semester_number, subject_code),
    CONSTRAINT fk_academic_syllabi_batch
        FOREIGN KEY (batch_id) REFERENCES academic_batches(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_academic_syllabi_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
);