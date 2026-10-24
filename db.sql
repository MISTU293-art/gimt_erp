
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


CREATE TABLE department(
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dept_id VARCHAR(150),
    dept_name VARCHAR (150),
    dept_capacity VARCHAR(150),
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
)