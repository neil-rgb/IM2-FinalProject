-- Add optional soft copies to an existing B-TRACK database.
USE btrack;

CREATE TABLE IF NOT EXISTS Document_Soft_Copies (
  document_id INT PRIMARY KEY,
  storage_key CHAR(40) NOT NULL UNIQUE,
  file_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(32) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  FOREIGN KEY (document_id) REFERENCES Documents(document_id)
) ENGINE=InnoDB;
