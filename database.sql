-- ============================================================
--  GOVERNMENT FILE MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ============================================================

CREATE DATABASE IF NOT EXISTS gov_fms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gov_fms;

-- ----------------------------------------------------------
-- DEPARTMENTS
-- ----------------------------------------------------------
CREATE TABLE departments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    head_name   VARCHAR(100),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------
-- USERS  (staff / clerks / admins)
-- ----------------------------------------------------------
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT,
    full_name     VARCHAR(150) NOT NULL,
    username      VARCHAR(80)  NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','manager','staff') DEFAULT 'staff',
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------
-- FILE CATEGORIES
-- ----------------------------------------------------------
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------
-- FILES  (core table)
-- ----------------------------------------------------------
CREATE TABLE files (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    file_number     VARCHAR(50)  NOT NULL UNIQUE,   -- official reference number
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    category_id     INT,
    department_id   INT,
    created_by      INT,
    assigned_to     INT,
    status          ENUM('open','in_review','approved','rejected','archived','closed') DEFAULT 'open',
    priority        ENUM('low','normal','high','urgent') DEFAULT 'normal',
    file_path       VARCHAR(500),                   -- physical file on server
    file_size       BIGINT DEFAULT 0,
    original_name   VARCHAR(255),
    mime_type       VARCHAR(100),
    confidential    TINYINT(1) DEFAULT 0,
    due_date        DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id)   REFERENCES categories(id)   ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by)    REFERENCES users(id)        ON DELETE SET NULL,
    FOREIGN KEY (assigned_to)   REFERENCES users(id)        ON DELETE SET NULL
);

-- ----------------------------------------------------------
-- FILE MOVEMENTS / TRACKING
-- ----------------------------------------------------------
CREATE TABLE file_movements (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    file_id       INT NOT NULL,
    from_user_id  INT,
    to_user_id    INT,
    from_dept_id  INT,
    to_dept_id    INT,
    action        VARCHAR(100) NOT NULL,
    remarks       TEXT,
    moved_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id)       REFERENCES files(id)       ON DELETE CASCADE,
    FOREIGN KEY (from_user_id)  REFERENCES users(id)       ON DELETE SET NULL,
    FOREIGN KEY (to_user_id)    REFERENCES users(id)       ON DELETE SET NULL,
    FOREIGN KEY (from_dept_id)  REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (to_dept_id)    REFERENCES departments(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------
-- COMMENTS / NOTES on files
-- ----------------------------------------------------------
CREATE TABLE file_comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    file_id    INT NOT NULL,
    user_id    INT,
    comment    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------
-- AUDIT LOG
-- ----------------------------------------------------------
CREATE TABLE audit_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    action     VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id  INT,
    details    TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ----------------------------------------------------------
-- SEED DATA
-- ----------------------------------------------------------
INSERT INTO departments (name, code, head_name) VALUES
('Administration',          'ADM', 'Director General'),
('Finance & Accounts',      'FIN', 'Chief Finance Officer'),
('Human Resources',         'HRM', 'HR Director'),
('Legal Affairs',           'LEG', 'Chief Legal Counsel'),
('Public Works',            'PWD', 'Chief Engineer'),
('Information Technology',  'ITC', 'CIO');

INSERT INTO categories (name, description) VALUES
('Correspondence',  'Letters, memos, and official correspondence'),
('Contracts',       'Government contracts and agreements'),
('Reports',         'Annual, quarterly, and special reports'),
('Circulars',       'Internal circulars and notifications'),
('Tenders',         'Procurement and tender documents'),
('Personnel',       'HR and personnel related files'),
('Legal',           'Legal cases and court documents'),
('Financial',       'Budgets, audits, and financial records');

-- Default admin  (password: Admin@1234)
INSERT INTO users (department_id, full_name, username, email, password_hash, role) VALUES
(1, 'System Administrator', 'admin', 'admin@gov.et',
 '$2y$12$YourHashedPasswordHere', 'admin');
