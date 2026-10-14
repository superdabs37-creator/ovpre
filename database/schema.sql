CREATE DATABASE IF NOT EXISTS ovpre CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ovpre;

CREATE TABLE departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_name VARCHAR(150) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(160) NOT NULL,
  gender VARCHAR(30) NULL,
  birthday DATE NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NULL,
  auth_user_id CHAR(36) NULL UNIQUE,
  role ENUM('Super Admin','OVPRE Admin','Research Coordinator','Extension Coordinator','Faculty Extensionist','Researcher') NOT NULL,
  department_id INT NULL,
  status ENUM('Pending Invitation','Pending Approval','Active','Inactive') NOT NULL DEFAULT 'Pending Approval',
  approved_at DATETIME NULL,
  approved_by INT NULL,
  rejected_at DATETIME NULL,
  rejected_by INT NULL,
  rejection_reason TEXT NULL,
  login_attempts INT DEFAULT 0,
  locked_until DATETIME NULL,
  reset_token VARCHAR(255) NULL,
  reset_expires DATETIME NULL,
  last_login_at DATETIME NULL,
  created_by INT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_approved_by (approved_by),
  INDEX idx_users_rejected_by (rejected_by),
  INDEX idx_users_status_created (status, created_at),
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(120) NOT NULL UNIQUE
);

CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  category_id INT NOT NULL,
  description TEXT NULL,
  author VARCHAR(160) NOT NULL,
  department_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  uploaded_by INT NOT NULL,
  assigned_to INT NULL,
  status ENUM('Pending','Under Review','Approved','Declined') DEFAULT 'Pending',
  status_remarks TEXT NULL,
  submission_date DATE NOT NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (department_id) REFERENCES departments(id),
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE document_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT NOT NULL,
  version_label VARCHAR(80) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  modified_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  FOREIGN KEY (modified_by) REFERENCES users(id)
);

CREATE TABLE archived_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT NOT NULL,
  archived_by INT NOT NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  content TEXT NOT NULL,
  audience ENUM('All','Faculty','Researchers','Staff') DEFAULT 'All',
  status ENUM('Draft','Published','Archived') DEFAULT 'Published',
  posted_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (posted_by) REFERENCES users(id)
);

CREATE TABLE activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  activity_type ENUM('Seminar','Training','Workshop','Community Outreach','Project Defense','Research Proposal','Research Colloquium','Research Completion and Final Presentation','Research Publication','Research Seminar','Research Monitoring and Evaluation') NOT NULL,
  date DATE NOT NULL,
  venue VARCHAR(180) NOT NULL,
  description TEXT NULL,
  approval_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(500) NULL,
  status ENUM('Unread','Read') DEFAULT 'Unread',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(255) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE review_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT NOT NULL,
  user_id INT NULL,
  action VARCHAR(80) NOT NULL,
  remarks TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_review_entity (entity_type, entity_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE login_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  username VARCHAR(80) NULL,
  status VARCHAR(30) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE research_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  researchers TEXT NOT NULL,
  abstract TEXT NOT NULL,
  funding_source VARCHAR(180) NULL,
  document_id INT NULL,
  submitted_by INT NOT NULL,
  status ENUM('Pending','Under Review','Approved','Declined') DEFAULT 'Pending',
  assigned_to INT NULL,
  status_remarks TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
  FOREIGN KEY (submitted_by) REFERENCES users(id)
);

CREATE TABLE extension_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_title VARCHAR(220) NOT NULL,
  coordinator VARCHAR(160) NOT NULL,
  community_partner VARCHAR(180) NOT NULL,
  budget DECIMAL(12,2) DEFAULT 0,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  progress_status ENUM('Planning','Ongoing','Completed') DEFAULT 'Planning',
  document_id INT NULL,
  assigned_to INT NULL,
  status_remarks TEXT NULL,
  submitted_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submitted_by) REFERENCES users(id),
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
);

CREATE TABLE accomplishment_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  project_id INT NULL,
  document_id INT NULL,
  submitted_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES extension_projects(id) ON DELETE SET NULL,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
  FOREIGN KEY (submitted_by) REFERENCES users(id)
);

