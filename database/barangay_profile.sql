-- Safe migration for existing B-TRACK databases.
-- Creates one barangay profile row structure without dropping existing records.
USE btrack;

CREATE TABLE IF NOT EXISTS Barangay_Profile (
  profile_id     TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  barangay_name  VARCHAR(150) NOT NULL,
  location       VARCHAR(180) NOT NULL,
  office_address VARCHAR(255) NOT NULL,
  contact_number VARCHAR(40) NOT NULL,
  updated_by     INT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_single_barangay_profile CHECK (profile_id = 1),
  FOREIGN KEY (updated_by) REFERENCES Users(user_id) ON DELETE SET NULL
);
