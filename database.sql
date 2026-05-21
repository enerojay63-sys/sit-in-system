-- ============================================
-- CCS Sit-in Monitoring System — Full Database
-- ============================================

CREATE DATABASE IF NOT EXISTS ccs_sitin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ccs_sitin;

-- Students
CREATE TABLE IF NOT EXISTS students (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  id_number         VARCHAR(20) UNIQUE NOT NULL,
  lastname          VARCHAR(60) NOT NULL,
  firstname         VARCHAR(60) NOT NULL,
  midname           VARCHAR(60) DEFAULT '',
  course            VARCHAR(20) NOT NULL,
  year_level        TINYINT NOT NULL DEFAULT 1,
  email             VARCHAR(100) UNIQUE NOT NULL,
  address           VARCHAR(255) DEFAULT '',
  password          VARCHAR(255) NOT NULL,
  profile_pic       VARCHAR(255) DEFAULT NULL,
  remaining_session INT DEFAULT 30,
  total_points      INT DEFAULT 0,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admins
CREATE TABLE IF NOT EXISTS admins (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(60) UNIQUE NOT NULL,
  password   VARCHAR(255) NOT NULL,
  name       VARCHAR(120) DEFAULT 'CCS Admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin (password: admin123)
INSERT IGNORE INTO admins (username, password, name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CCS Admin');

-- Sit-in Records
CREATE TABLE IF NOT EXISTS sitin_records (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  student_id     INT NOT NULL,
  id_number      VARCHAR(20) NOT NULL,
  student_name   VARCHAR(150) NOT NULL,
  purpose        VARCHAR(100) NOT NULL,
  lab            VARCHAR(20) NOT NULL,
  session        INT DEFAULT 30,
  status         ENUM('active','done') DEFAULT 'active',
  time_in        DATETIME DEFAULT CURRENT_TIMESTAMP,
  time_out       DATETIME DEFAULT NULL,
  pc_no          INT DEFAULT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Reservations
CREATE TABLE IF NOT EXISTS reservations (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  student_id   INT NOT NULL,
  id_number    VARCHAR(20) NOT NULL,
  student_name VARCHAR(150) NOT NULL,
  purpose      VARCHAR(100) NOT NULL,
  lab          VARCHAR(20) NOT NULL,
  time_in      TIME NOT NULL,
  date         DATE NOT NULL,
  status       ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Feedback
CREATE TABLE IF NOT EXISTS feedback (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  sitin_id   INT NOT NULL,
  rating     TINYINT NOT NULL DEFAULT 5,
  comment    TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (sitin_id)   REFERENCES sitin_records(id) ON DELETE CASCADE
);

-- Testimonials
CREATE TABLE IF NOT EXISTS testimonials (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  message    TEXT NOT NULL,
  rating     TINYINT DEFAULT 5,
  status     ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Announcements
CREATE TABLE IF NOT EXISTS announcements (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  content    TEXT NOT NULL,
  posted_by  VARCHAR(100) DEFAULT 'CCS Admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PC Control
CREATE TABLE IF NOT EXISTS pc_control (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  lab        VARCHAR(20) NOT NULL,
  pc_number  INT NOT NULL,
  status     ENUM('available','occupied','maintenance','locked') DEFAULT 'available',
  student_id INT DEFAULT NULL,
  UNIQUE KEY lab_pc (lab, pc_number),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- Insert default PCs for Labs 524, 526, 528 (50 PCs each)
INSERT IGNORE INTO pc_control (lab, pc_number, status)
SELECT l.lab_name, p.pc_num, 'available'
FROM
  (SELECT '524' AS lab_name UNION SELECT '526' UNION SELECT '528') l,
  (SELECT seq AS pc_num FROM (
    SELECT 1 AS seq UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
    UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
    UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
    UNION SELECT 31 UNION SELECT 32 UNION SELECT 33 UNION SELECT 34 UNION SELECT 35
    UNION SELECT 36 UNION SELECT 37 UNION SELECT 38 UNION SELECT 39 UNION SELECT 40
    UNION SELECT 41 UNION SELECT 42 UNION SELECT 43 UNION SELECT 44 UNION SELECT 45
    UNION SELECT 46 UNION SELECT 47 UNION SELECT 48 UNION SELECT 49 UNION SELECT 50
  ) nums) p;

-- Software
CREATE TABLE IF NOT EXISTS software (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  lab         VARCHAR(20) NOT NULL,
  name        VARCHAR(100) NOT NULL,
  version     VARCHAR(50) DEFAULT '',
  description TEXT,
  status      ENUM('available','unavailable') DEFAULT 'available',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO software (lab, name, version, description) VALUES
('524', 'Visual Studio Code', '1.88', 'Code editor by Microsoft'),
('524', 'XAMPP', '8.2', 'PHP/MySQL local server'),
('524', 'PHP', '8.2', 'Server-side scripting language'),
('526', 'Android Studio', '2023.2', 'Android development IDE'),
('526', 'Java JDK', '21', 'Java Development Kit'),
('526', 'Python', '3.12', 'Programming language'),
('528', 'MySQL Workbench', '8.0', 'Database management tool'),
('528', 'Postman', '11', 'API testing tool'),
('528', 'Figma', 'Web', 'UI/UX Design tool');

-- System Settings
CREATE TABLE IF NOT EXISTS system_settings (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(100) UNIQUE NOT NULL,
  setting_value VARCHAR(255) NOT NULL
);

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('reservation_enabled', '1'),
('maintenance_mode', '0');

-- Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  actor      VARCHAR(100) NOT NULL,
  action     TEXT NOT NULL,
  target     VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rewards
CREATE TABLE IF NOT EXISTS rewards (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  points     INT DEFAULT 0,
  reason     VARCHAR(255) DEFAULT '',
  given_by   VARCHAR(100) DEFAULT 'CCS Admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);