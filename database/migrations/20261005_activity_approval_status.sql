ALTER TABLE activities
  ADD COLUMN approval_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved' AFTER description;
