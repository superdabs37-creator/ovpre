USE ovpre;

INSERT IGNORE INTO departments (id, department_name) VALUES
(1, 'College of Computer Studies and Technology'),
(2, 'College of Engineering'),
(3, 'College of Nursing and Allied Health Sciences'),
(4, 'College of Arts and Sciences'),
(5, 'College of Business Administration'),
(6, 'College of Human Kinetics'),
(7, 'College of Teacher Education'),
(8, 'College of Tourism and Hospitality Management'),
(9, 'College of Accountancy')
ON DUPLICATE KEY UPDATE department_name = VALUES(department_name);

INSERT IGNORE INTO categories (id, category_name) VALUES
(1, 'Research Proposal'), (2, 'Research Report'), (3, 'Extension Project'),
(4, 'Accomplishment Report'), (5, 'Publication'), (6, 'Memorandum'), (7, 'Administrative Record');

INSERT IGNORE INTO users (id, fullname, email, username, password, role, department_id, status) VALUES
(1, 'System Administrator', 'admin@plsp.edu.ph', 'admin', '$2y$10$ApePRZk2RoQdlV6WuRB6gOqViGhiMlIxFITLUdSvbVwekPgEgd3Dm', 'Super Admin', 1, 'Active');

INSERT IGNORE INTO announcements (id, title, content, audience, status, posted_by) VALUES
(1, 'Research proposal submissions are open', 'Faculty researchers may now submit new research proposals through I-Record.', 'Researchers', 'Published', 1),
(2, 'Extension accomplishment reports due this month', 'Coordinators are requested to upload signed accomplishment reports before month end.', 'Staff', 'Published', 1);
