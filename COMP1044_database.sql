-- =============================================================================
-- COMP1044 Coursework: Internship Result Management System
-- Database schema for a web-based system that tracks university students
-- undergoing industrial training, the assessors supervising them, and the
-- weighted marks recorded against eight assessment criteria.
--
-- Entities:
--   users        - login accounts (Admin or Assessor role)
--   students     - student profiles
--   internships  - links one student to one assessor with dates and status
--   assessments  - eight criterion scores per internship, auto-computed final mark
--
-- Relationships (crow's foot):
--   users 1 ---- * internships      (one assessor supervises many internships)
--   students 1 ---- 1 internships   (one student has one internship record)
--   internships 1 ---- 1 assessments (one internship has one assessment record)
-- =============================================================================

DROP DATABASE IF EXISTS internship_result_management;
CREATE DATABASE internship_result_management;
USE internship_result_management;

-- -----------------------------------------------------------------------------
-- users: login accounts. Role controls access throughout the PHP layer.
--   role ENUM restricts values to 'Admin' or 'Assessor' at the database level.
--   username is UNIQUE so no two accounts can share a login name.
--   password_hash stores a bcrypt hash produced by PHP password_hash().
-- -----------------------------------------------------------------------------
CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('Admin', 'Assessor') NOT NULL
);

-- -----------------------------------------------------------------------------
-- students: student profile records managed by Admin users.
--   student_id is the natural primary key (e.g. APU TP-number).
--   created_at defaults to the current timestamp for audit purposes.
-- -----------------------------------------------------------------------------
CREATE TABLE students (
  student_id VARCHAR(20) PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  programme VARCHAR(100) NOT NULL,
  company_name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- internships: assignment of a student to an assessor plus placement details.
--   student_id is UNIQUE so each student has at most one internship record
--     (enforces the 1:1 cardinality shown on the ERD).
--   assessor_id references users(user_id); the PHP layer ensures only users
--     with role='Assessor' are chosen via the dropdown.
--   status ENUM keeps the lifecycle values consistent; defaults to 'Active'.
--   chk_internship_dates prevents end_date from falling before start_date.
-- -----------------------------------------------------------------------------
CREATE TABLE internships (
  internship_id INT PRIMARY KEY AUTO_INCREMENT,
  student_id VARCHAR(20) NOT NULL UNIQUE,
  assessor_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('Active', 'Completed') NOT NULL DEFAULT 'Active',
  notes VARCHAR(255),
  CONSTRAINT fk_internships_student
    FOREIGN KEY (student_id) REFERENCES students (student_id),
  CONSTRAINT fk_internships_assessor
    FOREIGN KEY (assessor_id) REFERENCES users (user_id),
  CONSTRAINT chk_internship_dates
    CHECK (end_date >= start_date)
);

-- -----------------------------------------------------------------------------
-- assessments: the eight criterion scores plus the derived final mark.
--   internship_id is UNIQUE so each internship has at most one assessment
--     (enforces the 1:1 cardinality shown on the ERD).
--   Each score column is constrained to 0..100 via a CHECK constraint.
--   final_mark is a STORED generated column that applies the faculty weightages
--     exactly as specified in the assignment brief:
--       Undertaking Tasks          10%
--       Health & Safety            10%
--       Theoretical Knowledge      10%
--       Written Report             15%
--       Language & Clarity         10%
--       Lifelong Learning          15%
--       Project Management         15%
--       Time Management            15%
--     Storing the computation in the database guarantees users cannot alter the
--     weightages from the application layer, satisfying the requirement that
--     the calculation be standardised and tamper-proof.
-- -----------------------------------------------------------------------------
CREATE TABLE assessments (
  assessment_id INT PRIMARY KEY AUTO_INCREMENT,
  internship_id INT NOT NULL UNIQUE,
  undertaking_tasks DECIMAL(5,2) NOT NULL,
  health_safety DECIMAL(5,2) NOT NULL,
  theoretical_knowledge DECIMAL(5,2) NOT NULL,
  written_report DECIMAL(5,2) NOT NULL,
  language_clarity DECIMAL(5,2) NOT NULL,
  lifelong_learning DECIMAL(5,2) NOT NULL,
  project_management DECIMAL(5,2) NOT NULL,
  time_management DECIMAL(5,2) NOT NULL,
  -- Generated column: weighted sum, computed and stored by MySQL.
  final_mark DECIMAL(6,2) GENERATED ALWAYS AS (
    undertaking_tasks * 0.10 +
    health_safety * 0.10 +
    theoretical_knowledge * 0.10 +
    written_report * 0.15 +
    language_clarity * 0.10 +
    lifelong_learning * 0.15 +
    project_management * 0.15 +
    time_management * 0.15
  ) STORED,
  comments TEXT,
  assessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_assessments_internship
    FOREIGN KEY (internship_id) REFERENCES internships (internship_id),
  -- Faculty scoring scale: every criterion must be between 0 and 100.
  CONSTRAINT chk_undertaking_tasks CHECK (undertaking_tasks BETWEEN 0 AND 100),
  CONSTRAINT chk_health_safety CHECK (health_safety BETWEEN 0 AND 100),
  CONSTRAINT chk_theoretical_knowledge CHECK (theoretical_knowledge BETWEEN 0 AND 100),
  CONSTRAINT chk_written_report CHECK (written_report BETWEEN 0 AND 100),
  CONSTRAINT chk_language_clarity CHECK (language_clarity BETWEEN 0 AND 100),
  CONSTRAINT chk_lifelong_learning CHECK (lifelong_learning BETWEEN 0 AND 100),
  CONSTRAINT chk_project_management CHECK (project_management BETWEEN 0 AND 100),
  CONSTRAINT chk_time_management CHECK (time_management BETWEEN 0 AND 100)
);

-- =============================================================================
-- Sample data
-- Passwords below are bcrypt hashes. Plain-text equivalents for testing:
--   admin01     / Admin@123
--   assessor01  / Assess@123
--   assessor02  / Assess@123
-- =============================================================================

INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin01', '$2y$10$AG/6mCKGKlT3fLYiwpY2tu1Gcp1oZyl1oUkpU9IaJTA5d2Mg.v6D2', 'System Administrator', 'Admin'),
('assessor01', '$2y$10$Qwlustr6nlrTMcBqQMMIAOoXKuSLjKW5E4DEHG66RTkfeKRrKcirC', 'Dr. Lee Kah Wei', 'Assessor'),
('assessor02', '$2y$10$6h/0SwbkzabJrqMkUgwd/eufEjLIhC/lClwegMaumPBrOMxg.8leq', 'Ms. Lim Siew Ling', 'Assessor');

INSERT INTO students (student_id, student_name, programme, company_name) VALUES
('TP067890', 'Nur Aina Binti Rahman', 'Diploma in Information Technology', 'Data Matrix Solutions'),
('TP068321', 'Adam Tan Wei Jian', 'Diploma in Software Engineering', 'NextWave Systems'),
('TP069114', 'Siti Noor Iman', 'Diploma in Information Systems', 'Cloud Axis Sdn Bhd');

INSERT INTO internships (student_id, assessor_id, start_date, end_date, status, notes) VALUES
('TP067890', 2, '2026-01-05', '2026-05-05', 'Active', 'Assigned to application support team'),
('TP068321', 3, '2026-01-12', '2026-05-12', 'Completed', 'Assigned to frontend dashboard project'),
('TP069114', 2, '2026-02-01', '2026-06-01', 'Active', 'Working in business analytics unit');

-- final_mark is intentionally omitted from the INSERT list because MySQL
-- computes it automatically from the eight criterion scores above.
INSERT INTO assessments (
  internship_id,
  undertaking_tasks,
  health_safety,
  theoretical_knowledge,
  written_report,
  language_clarity,
  lifelong_learning,
  project_management,
  time_management,
  comments
) VALUES
(1, 82, 88, 80, 84, 81, 86, 83, 87, 'Consistent performance and good attitude.'),
(2, 76, 80, 78, 82, 79, 81, 84, 80, 'Solid technical delivery with clear reporting.');

-- -----------------------------------------------------------------------------
-- vw_student_assessment_summary: flattens the four tables into one row per
-- student for the Result Viewing page. A LEFT JOIN on assessments is used so
-- that students with no assessment yet still appear (final_mark and score
-- columns will be NULL for those rows).
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_student_assessment_summary AS
SELECT
  s.student_id,
  s.student_name,
  s.programme,
  s.company_name,
  u.user_id AS assessor_id,
  u.full_name AS assessor_name,
  i.internship_id,
  i.status AS internship_status,
  a.assessment_id,
  a.undertaking_tasks,
  a.health_safety,
  a.theoretical_knowledge,
  a.written_report,
  a.language_clarity,
  a.lifelong_learning,
  a.project_management,
  a.time_management,
  a.final_mark,
  a.comments,
  a.assessed_at
FROM students s
JOIN internships i ON i.student_id = s.student_id
JOIN users u ON u.user_id = i.assessor_id
LEFT JOIN assessments a ON a.internship_id = i.internship_id;

-- Quick sanity check: confirm the view returns one row per student.
SELECT * FROM vw_student_assessment_summary;
