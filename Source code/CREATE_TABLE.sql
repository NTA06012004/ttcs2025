CREATE TABLE events (
    id INT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255),
    description TEXT,
    start_datetime DATETIME,
    end_datetime DATETIME,
    type VARCHAR(50),
    related_class_id INT,
    created_at DATETIME
);

CREATE TABLE assignments (
    id INT PRIMARY KEY,
    class_id INT,
    title VARCHAR(255),
    description TEXT,
    due_date DATETIME,
    file_path VARCHAR(255),
    created_at DATETIME
);

CREATE TABLE classes (
    id INT PRIMARY KEY,
    class_name VARCHAR(255),
    class_code VARCHAR(50),
    teacher_id INT,
    description TEXT,
    created_at DATETIME
);

CREATE TABLE enrollments (
    id INT PRIMARY KEY,
    user_id INT,
    class_id INT,
    enrollment_date DATETIME
);

CREATE TABLE submissions (
    id INT PRIMARY KEY,
    assignment_id INT,
    student_id INT,
    submission_text TEXT,
    file_path VARCHAR(255),
    submitted_at DATETIME,
    grade INT,
    feedback TEXT,
    status VARCHAR(50)
);

CREATE TABLE users (
    id INT PRIMARY KEY,
    full_name VARCHAR(255),
    dob DATE,
    gender VARCHAR(50),
    email VARCHAR(255),
    password VARCHAR(255),
    profile_picture VARCHAR(255),
    email_verification_code VARCHAR(50),
    email_verified_at DATETIME,
    password_reset_token VARCHAR(255)
);