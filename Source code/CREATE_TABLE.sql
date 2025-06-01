CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    dob DATE,
    gender VARCHAR(10), -- Giảm độ dài nếu chỉ lưu 'male', 'female', 'other'
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- Sẽ lưu mật khẩu đã hash
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    email_verification_code VARCHAR(64) NULL, -- Có thể cần dài hơn nếu mã phức tạp
    email_verified_at DATETIME NULL,
    password_reset_token VARCHAR(64) NULL, -- Token thường là chuỗi hex
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- Tự động cập nhật khi bản ghi thay đổi
);

CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(255) NOT NULL,
    class_code VARCHAR(10) UNIQUE NOT NULL, -- Mã lớp thường ngắn gọn
    teacher_id INT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE -- Nếu xóa user giáo viên, các lớp của họ cũng bị xóa (cân nhắc)
);

CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_class_enrollment (user_id, class_id), -- Đảm bảo mỗi user chỉ enroll 1 lần/lớp
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE -- Nếu xóa lớp, các enrollment liên quan cũng bị xóa
);

CREATE TABLE assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATETIME NOT NULL,
    file_path VARCHAR(255) NULL, -- Đường dẫn tệp đính kèm của bài tập
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE -- Nếu xóa lớp, bài tập cũng bị xóa
);

CREATE TABLE submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT NULL,
    submission_file VARCHAR(255) NULL, -- Đường dẫn tệp bài nộp của học sinh
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    grade VARCHAR(50) NULL, -- Sử dụng VARCHAR để linh hoạt (vd: "8.5/10", "Đạt")
    feedback TEXT NULL,
    status VARCHAR(20) DEFAULT 'submitted', -- vd: 'submitted', 'late', 'graded', 'pending_grading'
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE, -- Nếu xóa bài tập, bài nộp cũng bị xóa
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE, -- Nếu xóa user học sinh, bài nộp cũng bị xóa
    UNIQUE KEY assignment_student_submission (assignment_id, student_id) -- Đảm bảo mỗi HS chỉ nộp 1 lần/bài tập
);

CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL, -- Người tạo sự kiện hoặc người liên quan chính
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME,
    type VARCHAR(50), -- vd: 'assignment_due', 'class_meeting', 'personal'
    related_class_id INT NULL, -- Có thể liên quan đến một lớp cụ thể
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_class_id) REFERENCES classes(id) ON DELETE SET NULL -- Nếu lớp bị xóa, sự kiện không nhất thiết phải xóa
);