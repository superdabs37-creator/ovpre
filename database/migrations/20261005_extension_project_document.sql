ALTER TABLE extension_projects
  ADD COLUMN document_id INT NULL,
  ADD CONSTRAINT fk_extension_projects_document
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL;
