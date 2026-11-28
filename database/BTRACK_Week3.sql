-- =====================================================================
-- B-TRACK: Barangay Document Management System  |  IT116 Week 3
-- Schema follows the revised Week 2 ERD / data dictionary (MySQL 8.0+)
-- ID ranges: Committees 201+ | Users 301+ | Document_Types 401+
--            Storage_Locations 501+ | Documents 1001+ | History 5001+
-- =====================================================================
DROP DATABASE IF EXISTS btrack;
CREATE DATABASE btrack;
USE btrack;

-- ---------------------------------------------------------------------
-- 1. TABLES
-- ---------------------------------------------------------------------
CREATE TABLE Committees (
  committee_id   INT AUTO_INCREMENT PRIMARY KEY,
  committee_name VARCHAR(100) NOT NULL UNIQUE
) AUTO_INCREMENT=201;

CREATE TABLE Users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status        VARCHAR(10)  NOT NULL DEFAULT 'Active',
  CONSTRAINT chk_user_status CHECK (status IN ('Active','Inactive'))
) AUTO_INCREMENT=301;

CREATE TABLE Barangay_Profile (
  profile_id    TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  barangay_name VARCHAR(150) NOT NULL,
  location      VARCHAR(180) NOT NULL,
  office_address VARCHAR(255) NOT NULL,
  contact_number VARCHAR(40) NOT NULL,
  updated_by    INT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_single_barangay_profile CHECK (profile_id = 1),
  FOREIGN KEY (updated_by) REFERENCES Users(user_id) ON DELETE SET NULL
);

CREATE TABLE Document_Types (
  type_id      INT AUTO_INCREMENT PRIMARY KEY,
  type_name    VARCHAR(60) NOT NULL UNIQUE,
  is_financial BOOLEAN NOT NULL DEFAULT FALSE
) AUTO_INCREMENT=401;

CREATE TABLE Storage_Locations (
  location_id INT AUTO_INCREMENT PRIMARY KEY,
  type_id     INT NOT NULL,
  cabinet     VARCHAR(30) NOT NULL,
  drawer      VARCHAR(30) NOT NULL,
  folder      VARCHAR(30) NOT NULL,
  CONSTRAINT uq_location UNIQUE (cabinet, drawer, folder),
  FOREIGN KEY (type_id) REFERENCES Document_Types(type_id)
) AUTO_INCREMENT=501;

CREATE TABLE Documents (
  document_id      INT AUTO_INCREMENT PRIMARY KEY,
  tracking_number  VARCHAR(30)  NOT NULL UNIQUE,
  title            VARCHAR(150) NOT NULL,
  type_id          INT NOT NULL,
  committee_id     INT NULL,
  direction        VARCHAR(20)  NOT NULL,
  sender_recipient VARCHAR(100) NOT NULL,
  date_received    DATE NOT NULL,
  status           VARCHAR(30)  NOT NULL DEFAULT 'Pending',
  location_id      INT NOT NULL,
  recorded_by      INT NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_tracking  CHECK (tracking_number REGEXP '^C[1-9]-[1-9][0-9]{2,}$'),
  CONSTRAINT chk_direction CHECK (direction IN ('Incoming','Outgoing')),
  CONSTRAINT chk_sender    CHECK (CHAR_LENGTH(TRIM(sender_recipient)) > 0),
  CONSTRAINT chk_status    CHECK (status IN ('Pending','In Process','Completed')),
  FOREIGN KEY (type_id)      REFERENCES Document_Types(type_id),
  FOREIGN KEY (committee_id) REFERENCES Committees(committee_id),
  FOREIGN KEY (location_id)  REFERENCES Storage_Locations(location_id),
  FOREIGN KEY (recorded_by)  REFERENCES Users(user_id)
) AUTO_INCREMENT=1001;

-- A single, atomic sequence keeps displayed IDs short and unique across years.
CREATE TABLE Document_Tracking_Counter (
  counter_name VARCHAR(30) PRIMARY KEY,
  last_value   BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT INTO Document_Tracking_Counter (counter_name, last_value) VALUES
 ('cabinet-1',100),('cabinet-2',200),('cabinet-3',300),('cabinet-4',400),('cabinet-5',500);

CREATE TABLE Document_Soft_Copies (
  document_id INT PRIMARY KEY,
  storage_key CHAR(40) NOT NULL UNIQUE,
  file_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(32) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  FOREIGN KEY (document_id) REFERENCES Documents(document_id)
) ENGINE=InnoDB;

CREATE TABLE Document_Recycle_Bin (
  document_id INT PRIMARY KEY,
  deleted_by  INT NOT NULL,
  deleted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recycle_bin_deleted_at (deleted_at, document_id),
  FOREIGN KEY (document_id) REFERENCES Documents(document_id),
  FOREIGN KEY (deleted_by)  REFERENCES Users(user_id)
) ENGINE=InnoDB;

CREATE TABLE Processing_History (
  history_id      INT AUTO_INCREMENT PRIMARY KEY,
  document_id     INT NOT NULL,
  handled_by      INT NOT NULL,
  action_type     VARCHAR(20) NOT NULL,
  old_status      VARCHAR(30) NULL,
  new_status      VARCHAR(30) NULL,
  old_location_id INT NULL,
  new_location_id INT NULL,
  action_date     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  remarks         VARCHAR(255) NULL,
  CONSTRAINT chk_action_type CHECK (action_type IN ('Status Change','Location Change')),
  CONSTRAINT chk_h_old       CHECK (old_status IS NULL OR old_status IN ('Pending','In Process','Completed')),
  CONSTRAINT chk_h_new       CHECK (new_status IS NULL OR new_status IN ('Pending','In Process','Completed')),
  CONSTRAINT chk_h_status_req CHECK (action_type <> 'Status Change'   OR new_status IS NOT NULL),
  CONSTRAINT chk_h_loc_req    CHECK (action_type <> 'Location Change' OR new_location_id IS NOT NULL),
  FOREIGN KEY (document_id)     REFERENCES Documents(document_id),
  FOREIGN KEY (handled_by)      REFERENCES Users(user_id),
  FOREIGN KEY (old_location_id) REFERENCES Storage_Locations(location_id),
  FOREIGN KEY (new_location_id) REFERENCES Storage_Locations(location_id)
) AUTO_INCREMENT=5001;

-- ---------------------------------------------------------------------
-- 2. TRIGGERS  (business rules 2, 7, 11, 12, 13, 14, 15, 16)
-- ---------------------------------------------------------------------
DELIMITER $$

-- Validate a new document: Active user, not a future date, starts as Pending.
CREATE TRIGGER trg_documents_before_insert
BEFORE INSERT ON Documents
FOR EACH ROW
BEGIN
  IF COALESCE((SELECT status FROM Users WHERE user_id = NEW.recorded_by), '') <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only Active accounts can record or update documents.';
  END IF;
  IF NEW.date_received > CURDATE() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Date received cannot be a future date.';
  END IF;
  IF NEW.status <> 'Pending' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A new document must start as Pending.';
  END IF;
END$$

-- First history entries: initial status and initial storage location.
CREATE TRIGGER trg_documents_after_insert
AFTER INSERT ON Documents
FOR EACH ROW
BEGIN
  INSERT INTO Processing_History (document_id, handled_by, action_type, old_status, new_status, remarks)
  VALUES (NEW.document_id, NEW.recorded_by, 'Status Change', NULL, NEW.status, 'Document recorded');
  INSERT INTO Processing_History (document_id, handled_by, action_type, old_location_id, new_location_id, remarks)
  VALUES (NEW.document_id, NEW.recorded_by, 'Location Change', NULL, NEW.location_id, 'Initial storage location');
END$$

-- Tracking number is permanent; status must move Pending -> In Process -> Completed.
CREATE TRIGGER trg_documents_before_update
BEFORE UPDATE ON Documents
FOR EACH ROW
BEGIN
  IF NEW.tracking_number <> OLD.tracking_number THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tracking number cannot be changed.';
  END IF;
  IF NEW.status <> OLD.status THEN
    IF OLD.status = 'Completed' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A Completed document cannot be changed back.';
    ELSEIF NOT ((OLD.status = 'Pending' AND NEW.status = 'In Process')
             OR (OLD.status = 'In Process' AND NEW.status = 'Completed')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Status must follow the order Pending, In Process, Completed.';
    END IF;
  END IF;
END$$

-- REQUIRED TRIGGER: log every status change and every location change automatically.
-- The responsible user comes from @current_user_id (set by the stored procedures).
CREATE TRIGGER trg_documents_after_update
AFTER UPDATE ON Documents
FOR EACH ROW
BEGIN
  IF OLD.status <> NEW.status THEN
    INSERT INTO Processing_History (document_id, handled_by, action_type, old_status, new_status, remarks)
    VALUES (NEW.document_id, COALESCE(@current_user_id, NEW.recorded_by), 'Status Change',
            OLD.status, NEW.status, COALESCE(@action_remarks, 'Status updated'));
  END IF;
  IF OLD.location_id <> NEW.location_id THEN
    INSERT INTO Processing_History (document_id, handled_by, action_type, old_location_id, new_location_id, remarks)
    VALUES (NEW.document_id, COALESCE(@current_user_id, NEW.recorded_by), 'Location Change',
            OLD.location_id, NEW.location_id, COALESCE(@action_remarks, 'Location updated'));
  END IF;
END$$

-- Rule 14: documents are never deleted.
CREATE TRIGGER trg_documents_before_delete
BEFORE DELETE ON Documents
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document records cannot be deleted.';
END$$

-- Rule 16: a user who recorded a document or appears in history cannot be deleted.
CREATE TRIGGER trg_users_before_delete
BEFORE DELETE ON Users
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM Documents WHERE recorded_by = OLD.user_id)
     OR EXISTS (SELECT 1 FROM Processing_History WHERE handled_by = OLD.user_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This user has records and cannot be deleted. Set the account to Inactive instead.';
  END IF;
END$$

-- Rule 13: history entries cannot be edited or deleted.
CREATE TRIGGER trg_history_block_update
BEFORE UPDATE ON Processing_History
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Processing history entries cannot be edited.';
END$$

CREATE TRIGGER trg_history_block_delete
BEFORE DELETE ON Processing_History
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Processing history entries cannot be deleted.';
END$$

-- ---------------------------------------------------------------------
-- 3. STORED PROCEDURES
-- ---------------------------------------------------------------------

-- 3a. Record a new document: generates a cabinet-based permanent ID such as C1-101.
CREATE PROCEDURE sp_add_document (
  IN p_user_id INT, IN p_title VARCHAR(150), IN p_type_id INT, IN p_committee_id INT,
  IN p_direction VARCHAR(20), IN p_sender_recipient VARCHAR(100), IN p_date_received DATE,
  IN p_location_id INT
)
BEGIN
  DECLARE v_status VARCHAR(10);
  DECLARE v_sequence BIGINT UNSIGNED;
  DECLARE v_cabinet VARCHAR(30);
  DECLARE v_cabinet_number INT UNSIGNED;
  DECLARE v_counter_name VARCHAR(30);
  DECLARE v_tracking VARCHAR(30);

  SELECT status INTO v_status FROM Users WHERE user_id = p_user_id;
  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found.';
  ELSEIF v_status <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only Active accounts can record or update documents.';
  ELSEIF p_date_received > CURDATE() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Date received cannot be a future date.';
  ELSEIF NOT EXISTS (SELECT 1 FROM Document_Types WHERE type_id = p_type_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document type not found.';
  ELSEIF NOT EXISTS (
    SELECT 1 FROM Storage_Locations WHERE location_id = p_location_id AND type_id = p_type_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Choose a storage location inside the cabinet for this document category.';
  ELSEIF p_committee_id IS NOT NULL
         AND NOT EXISTS (SELECT 1 FROM Committees WHERE committee_id = p_committee_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Committee not found.';
  END IF;

  SELECT cabinet INTO v_cabinet
  FROM Storage_Locations
  WHERE location_id = p_location_id AND type_id = p_type_id;
  SET v_cabinet_number = CAST(REPLACE(v_cabinet, 'Cabinet ', '') AS UNSIGNED);
  IF v_cabinet_number < 1 OR v_cabinet_number > 9 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected cabinet does not have a valid cabinet number.';
  END IF;
  SET v_counter_name = CONCAT('cabinet-', v_cabinet_number);
  UPDATE Document_Tracking_Counter
  SET last_value = LAST_INSERT_ID(last_value + 1)
  WHERE counter_name = v_counter_name;
  IF ROW_COUNT() <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The document ID sequence is unavailable.';
  END IF;
  SET v_sequence = LAST_INSERT_ID();
  SET v_tracking = CONCAT('C', v_cabinet_number, '-', LPAD(v_sequence, 3, '0'));

  INSERT INTO Documents (tracking_number, title, type_id, committee_id, direction,
                         sender_recipient, date_received, status, location_id, recorded_by)
  VALUES (v_tracking, p_title, p_type_id, p_committee_id, p_direction,
          p_sender_recipient, p_date_received, 'Pending', p_location_id, p_user_id);

  SELECT document_id, tracking_number, status FROM Documents WHERE tracking_number = v_tracking;
END$$

-- 3b. REQUIRED STORED PROCEDURE: update a document's status (trigger writes the history).
CREATE PROCEDURE sp_update_document_status (
  IN p_document_id INT, IN p_new_status VARCHAR(30), IN p_user_id INT, IN p_remarks VARCHAR(255)
)
BEGIN
  DECLARE v_ustatus VARCHAR(10);
  DECLARE v_old VARCHAR(30);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN SET @current_user_id = NULL; SET @action_remarks = NULL; RESIGNAL; END;

  SELECT status INTO v_ustatus FROM Users WHERE user_id = p_user_id;
  SELECT status INTO v_old FROM Documents WHERE document_id = p_document_id;

  IF v_old IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document not found.';
  ELSEIF v_ustatus IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found.';
  ELSEIF v_ustatus <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only Active accounts can record or update documents.';
  ELSEIF p_new_status NOT IN ('Pending','In Process','Completed') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid status.';
  ELSEIF v_old = p_new_status THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document already has that status.';
  END IF;

  SET @current_user_id = p_user_id;
  SET @action_remarks  = p_remarks;
  UPDATE Documents SET status = p_new_status WHERE document_id = p_document_id;
  SET @current_user_id = NULL;
  SET @action_remarks  = NULL;

  SELECT document_id, tracking_number, status FROM Documents WHERE document_id = p_document_id;
END$$

-- 3c. Move the original paper copy to another storage location (logged by the trigger).
CREATE PROCEDURE sp_move_document (
  IN p_document_id INT, IN p_location_id INT, IN p_user_id INT, IN p_remarks VARCHAR(255)
)
BEGIN
  DECLARE v_ustatus VARCHAR(10);
  DECLARE v_old INT;
  DECLARE v_type_id INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN SET @current_user_id = NULL; SET @action_remarks = NULL; RESIGNAL; END;

  SELECT status INTO v_ustatus FROM Users WHERE user_id = p_user_id;
  SELECT location_id, type_id INTO v_old, v_type_id FROM Documents WHERE document_id = p_document_id;

  IF v_old IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document not found.';
  ELSEIF v_ustatus IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found.';
  ELSEIF v_ustatus <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only Active accounts can record or update documents.';
  ELSEIF NOT EXISTS (
    SELECT 1 FROM Storage_Locations WHERE location_id = p_location_id AND type_id = v_type_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Choose a storage location inside the cabinet for this document category.';
  ELSEIF v_old = p_location_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document is already in that location.';
  END IF;

  SET @current_user_id = p_user_id;
  SET @action_remarks  = p_remarks;
  UPDATE Documents SET location_id = p_location_id WHERE document_id = p_document_id;
  SET @current_user_id = NULL;
  SET @action_remarks  = NULL;

  SELECT d.document_id, d.tracking_number,
         CONCAT(l.cabinet, ', ', l.drawer, ', ', l.folder) AS storage_location
  FROM Documents d JOIN Storage_Locations l ON l.location_id = d.location_id
  WHERE d.document_id = p_document_id;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 4. VIEW  (REQUIRED)
-- ---------------------------------------------------------------------
CREATE VIEW vw_document_tracking AS
SELECT d.document_id, d.tracking_number, d.title,
       t.type_name, t.is_financial, d.direction,
       c.committee_name, d.sender_recipient, d.date_received, d.status,
       CONCAT(l.cabinet, ', ', l.drawer, ', ', l.folder) AS storage_location,
       u.full_name AS recorded_by,
       (SELECT MAX(h.action_date) FROM Processing_History h
         WHERE h.document_id = d.document_id) AS last_action,
       CASE WHEN d.status = 'Completed' THEN NULL
            ELSE DATEDIFF(CURDATE(), d.date_received) END AS days_open
FROM Documents d
JOIN Document_Types t     ON t.type_id = d.type_id
JOIN Users u              ON u.user_id = d.recorded_by
JOIN Storage_Locations l  ON l.location_id = d.location_id
LEFT JOIN Committees c    ON c.committee_id = d.committee_id
WHERE NOT EXISTS (SELECT 1 FROM Document_Recycle_Bin b WHERE b.document_id = d.document_id);

-- ---------------------------------------------------------------------
-- 5. SAMPLE DATA
-- ---------------------------------------------------------------------
INSERT INTO Committees (committee_id, committee_name) VALUES
 (201,'Finance'),(202,'Peace and Order'),(203,'Health and Sanitation'),(204,'Infrastructure');

INSERT INTO Users (user_id, full_name, username, password_hash, status) VALUES
 (301, 'Reyes, Maria Lourdes', 'mreyes',   '$2y$10$xM0K2RA15fCBFuPw3z8tyO74icHTRdYYT2n6CPzaIkY91E9y1yC5K', 'Active'),
 (302, 'Cruz, Angelica',       'acruz',    '$2y$10$xM0K2RA15fCBFuPw3z8tyO74icHTRdYYT2n6CPzaIkY91E9y1yC5K', 'Active'),
 (303, 'Navarro, Jose',        'jnavarro', '$2y$10$xM0K2RA15fCBFuPw3z8tyO74icHTRdYYT2n6CPzaIkY91E9y1yC5K', 'Inactive');

INSERT INTO Document_Types (type_id, type_name, is_financial) VALUES
 (401,'Finance', TRUE),(402,'Resolutions', FALSE),(403,'Requests & Endorsements', FALSE),
 (404,'Notices & Communications', FALSE),(405,'Administrative Records', FALSE);

INSERT INTO Storage_Locations (location_id, type_id, cabinet, drawer, folder) VALUES
 (501,401,'Cabinet 01','Drawer 1','Folder 001'),(502,401,'Cabinet 01','Drawer 1','Folder 002'),(503,401,'Cabinet 01','Drawer 2','Folder 001'),
 (504,402,'Cabinet 02','Drawer 1','Folder 001'),(505,402,'Cabinet 02','Drawer 1','Folder 002'),(506,402,'Cabinet 02','Drawer 2','Folder 001'),
 (507,403,'Cabinet 03','Drawer 1','Folder 001'),(508,403,'Cabinet 03','Drawer 1','Folder 002'),(509,403,'Cabinet 03','Drawer 2','Folder 001'),
 (510,404,'Cabinet 04','Drawer 1','Folder 001'),(511,404,'Cabinet 04','Drawer 1','Folder 002'),(512,404,'Cabinet 04','Drawer 2','Folder 001'),
 (513,405,'Cabinet 05','Drawer 1','Folder 001'),(514,405,'Cabinet 05','Drawer 1','Folder 002'),(515,405,'Cabinet 05','Drawer 2','Folder 001');

-- Fictional demonstration records based on document types in official sources.
-- Names, titles, reference numbers and dates are illustrative B-TRACK data.
-- These are metadata examples, not copies of actual San Roque records.
-- Sources checked on 2026-11-28
-- DILG Bulacan templates (resolutions, notices, minutes, attendance, GAD and waste reports)
-- https://www.lgrcbulacan.region3.dilg.gov.ph/e-library/audit-templates/for-barangay
-- DILG financial disclosure document types
-- https://calabarzon.dilg.gov.ph/dilg-over-38k-barangays-compliant-with-full-disclosure-policy/
-- DBM Barangay Budgeting Manual, 2026 edition (Quarterly Report of Receipts)
-- https://www.dbm.gov.ph/wp-content/uploads/Issuances/2026/Local-Budget-Circular/LOCAL-BUDGET-CIRCULAR-NO-170-BARANGAY%20BUDGETING%20MANUAL%2C%202026%20EDITION.pdf
-- Hindang Citizen's Charter (budget transmittal and supporting documents)
-- https://hindang.gov.ph/index.php/internal-services/
-- Ormoc Citizen's Charter (streetlight request letters and barangay resolutions)
-- https://ormoc.gov.ph/assets/docs/citizens_charter/City%20General%20Services%20Office%20-%20Citizens%20Charter.pdf
--
-- Incoming identifies the sender; Outgoing identifies the recipient.
-- The secretary records every document, including reports prepared by other officials.
-- created_at and history action_date use the import time, when these samples are recorded.
-- The AFTER INSERT trigger writes the initial Pending and storage history entries.
INSERT INTO Documents (document_id, tracking_number, title, type_id, committee_id, direction, sender_recipient, date_received, status, location_id, recorded_by) VALUES
 (1001,'C3-301','Request Letter for Drainage Clearing in Purok 2',403,204,'Incoming','Purok 2 Residents','2026-11-03','Pending',507,301),
 (1002,'C3-302','Endorsement Letter for Purok 3 Streetlight Repair',403,204,'Outgoing','Municipal Engineering Office','2026-11-04','Pending',508,301),
 (1003,'C4-401','Notice of Barangay Solid Waste Management Committee Meeting - November 2026',404,203,'Outgoing','Barangay Solid Waste Management Committee','2026-11-05','Pending',510,301),
 (1004,'C1-101','Itemized Monthly Collections and Disbursements - October 2026',401,201,'Outgoing','Municipal Accounting Office','2026-11-05','Pending',501,301),
 (1005,'C2-201','Resolution No. 018, s. 2026 - Requesting Funds for Drainage Rehabilitation',402,204,'Outgoing','Office of the Municipal Mayor','2026-11-10','Pending',505,301),
 (1006,'C3-303','Request Letter for Community Health Outreach Assistance',403,203,'Outgoing','Municipal Health Office','2026-11-10','Pending',509,301),
 (1007,'C1-102','Quarterly Report of Receipts - Third Quarter 2026',401,201,'Outgoing','Municipal Accounting Office','2026-11-12','Pending',503,301),
 (1008,'C5-501','Minutes and Attendance Sheet - Regular Sangguniang Barangay Session, November 16, 2026',405,NULL,'Incoming','Office of the Sangguniang Barangay','2026-11-17','Pending',513,301),
 (1009,'C3-304','Proposed Barangay Annual Budget - FY 2027',401,201,'Outgoing','Municipal Budget Office','2026-11-18','Pending',502,301),
 (1010,'C5-502','Annual Procurement Plan - FY 2027',401,201,'Outgoing','Municipal Budget Office','2026-11-18','Pending',502,301),
 (1011,'C3-305','Resolution No. 019, s. 2026 - Approving the Solid Waste Management Action Plan',402,203,'Outgoing','Municipal Environment and Natural Resources Office','2026-11-19','Pending',504,301),
 (1012,'C3-306','Resolution No. 020, s. 2026 - Supporting Barangay Road Clearing Operations',402,204,'Outgoing','Office of the Municipal Mayor','2026-11-19','Pending',506,301),
 (1013,'C4-402','Resolution No. 021, s. 2026 - Adopting the Barangay GAD Plan and Budget for FY 2027',402,NULL,'Outgoing','Municipal GAD Focal Point System','2026-11-20','Pending',505,301),
 (1014,'C1-103','Request Letter for Use of the Barangay Covered Court',403,NULL,'Incoming','Purok 1 Youth Organization','2026-11-20','Pending',507,301),
 (1015,'C2-202','Invitation to Municipal Disaster Preparedness Coordination Meeting',404,NULL,'Incoming','Municipal Disaster Risk Reduction and Management Office','2026-11-23','Pending',511,301),
 (1016,'C3-307','Notice of Barangay Council for the Protection of Children Meeting',404,NULL,'Outgoing','Barangay Council for the Protection of Children Members','2026-11-23','Pending',510,301),
 (1017,'C1-104','Transmittal Letter for the Proposed Barangay Annual Budget - FY 2027',404,201,'Outgoing','Office of the Sangguniang Bayan','2026-11-24','Pending',512,301),
 (1018,'C5-503','Minutes and Attendance Sheet - Barangay Solid Waste Management Committee Meeting',405,203,'Incoming','Barangay Solid Waste Management Committee Secretariat','2026-11-24','Pending',514,301),
 (1019,'C1-105','Barangay GAD Accomplishment Report - Third Quarter 2026',405,NULL,'Outgoing','Municipal GAD Focal Point System','2026-11-25','Pending',515,301),
 (1020,'C1-106','Barangay Solid Waste Management Accomplishment Report - October 2026',405,203,'Outgoing','Municipal Environment and Natural Resources Office','2026-11-25','Pending',514,301);

UPDATE Document_Tracking_Counter
JOIN (
  SELECT CONCAT('cabinet-', SUBSTRING(SUBSTRING_INDEX(tracking_number, '-', 1), 2)) AS counter_name,
         MAX(CAST(SUBSTRING_INDEX(tracking_number, '-', -1) AS UNSIGNED)) AS last_value
  FROM Documents
  GROUP BY SUBSTRING_INDEX(tracking_number, '-', 1)
) ids ON ids.counter_name = Document_Tracking_Counter.counter_name
SET Document_Tracking_Counter.last_value = GREATEST(Document_Tracking_Counter.last_value, ids.last_value);

-- Processing outcomes are recorded by the secretary through the existing procedures.
CALL sp_update_document_status(1001, 'In Process', 301, 'Request referred for inspection and preparation of an endorsement.');
CALL sp_update_document_status(1001, 'Completed', 301, 'Endorsement transmitted to the Municipal Engineering Office; receiving copy filed.');
CALL sp_update_document_status(1002, 'In Process', 301, 'Preparing the endorsement and checking the reported streetlight locations.');
CALL sp_update_document_status(1003, 'In Process', 301, 'Preparing meeting notices for distribution.');
CALL sp_update_document_status(1003, 'Completed', 301, 'Signed meeting notices distributed; office copy filed.');
CALL sp_update_document_status(1004, 'In Process', 301, 'Awaiting the signed report from the barangay treasurer for transmittal.');
CALL sp_update_document_status(1005, 'In Process', 301, 'Preparing the certified resolution and transmittal copy.');
CALL sp_update_document_status(1007, 'In Process', 301, 'Checking the signed report and supporting attachments.');
CALL sp_update_document_status(1007, 'Completed', 301, 'Report transmitted to the Municipal Accounting Office; acknowledged copy filed.');
CALL sp_update_document_status(1008, 'In Process', 301, 'Checking the minutes and attendance sheet for signatures.');
CALL sp_update_document_status(1008, 'Completed', 301, 'Signed minutes and attendance sheet filed in the session records folder.');
CALL sp_update_document_status(1011, 'In Process', 301, 'Checking the certified resolution and approved action plan before release.');
CALL sp_update_document_status(1014, 'In Process', 301, 'Verifying venue availability before referring the request for approval.');
CALL sp_update_document_status(1015, 'In Process', 301, 'Invitation received and forwarded to the Punong Barangay for scheduling.');
CALL sp_update_document_status(1015, 'Completed', 301, 'Attendance confirmation sent; invitation and reply filed.');
CALL sp_update_document_status(1018, 'In Process', 301, 'Collecting committee signatures for the minutes and attendance sheet.');
CALL sp_update_document_status(1018, 'Completed', 301, 'Signed committee meeting records filed.');
CALL sp_move_document(1002, 509, 301, 'Endorsement filed in Cabinet 03, Drawer 2, Folder 001.');

-- =====================================================================
-- 6. FIVE SQL QUERIES  (screenshot each statement + output)
-- =====================================================================

-- Q1. List all user accounts alphabetically with their status
SELECT user_id, full_name, username, status
FROM Users
ORDER BY full_name;

-- Q2. Documents not yet completed, with type, status and where the paper copy is kept
SELECT d.tracking_number, d.title, t.type_name, d.status,
       CONCAT(l.cabinet, ', ', l.drawer, ', ', l.folder) AS storage_location
FROM Documents d
JOIN Document_Types t    ON t.type_id = d.type_id
JOIN Storage_Locations l ON l.location_id = d.location_id
WHERE d.status <> 'Completed'
ORDER BY d.date_received;

-- Q3. Number of documents received per day
SELECT date_received, COUNT(*) AS documents_received
FROM Documents
GROUP BY date_received
ORDER BY date_received;

-- Q4. Documents still pending / in process, with number of days waiting
SELECT tracking_number, title, status,
       DATEDIFF(CURDATE(), date_received) AS days_waiting
FROM Documents
WHERE status IN ('Pending','In Process')
ORDER BY days_waiting DESC;

-- Q5. Number of documents per type, broken down by status
SELECT t.type_name,
       COUNT(d.document_id)         AS total_documents,
       SUM(d.status = 'Pending')    AS pending,
       SUM(d.status = 'In Process') AS in_process,
       SUM(d.status = 'Completed')  AS completed
FROM Document_Types t
LEFT JOIN Documents d ON d.type_id = t.type_id
GROUP BY t.type_id, t.type_name
ORDER BY total_documents DESC;

-- Bonus: how many documents are filed in each storage location
SELECT l.cabinet, l.drawer, l.folder, COUNT(d.document_id) AS documents_filed
FROM Storage_Locations l
LEFT JOIN Documents d ON d.location_id = l.location_id
GROUP BY l.location_id, l.cabinet, l.drawer, l.folder
ORDER BY l.cabinet, l.drawer, l.folder;

-- =====================================================================
-- 7. DEMONSTRATIONS  (screenshot each statement + output)
-- =====================================================================

-- VIEW
SELECT * FROM vw_document_tracking;
SELECT tracking_number, title, status, storage_location, days_open
FROM vw_document_tracking WHERE status <> 'Completed' ORDER BY days_open DESC;

-- STORED PROCEDURES: record a document, update its status, move its paper copy
CALL sp_add_document(301, 'Request Letter for Repair of Streetlights in Purok 4', 403, 204, 'Incoming', 'Purok 4 Residents', '2026-11-26', 507);
CALL sp_update_document_status(1021, 'In Process', 301, 'Request referred to the Infrastructure Committee for inspection.');
CALL sp_move_document(1006, 508, 301, 'Health outreach request filed in Cabinet 03, Drawer 1, Folder 002.');

-- TRIGGER: processing history was written automatically (status AND location changes)
SELECT h.history_id, d.tracking_number, h.action_type,
       h.old_status, h.new_status,
       CONCAT_WS(', ', ol.cabinet, ol.drawer, ol.folder) AS old_location,
       CONCAT_WS(', ', nl.cabinet, nl.drawer, nl.folder) AS new_location,
       u.full_name AS handled_by, h.action_date, h.remarks
FROM Processing_History h
JOIN Documents d ON d.document_id = h.document_id
JOIN Users u     ON u.user_id = h.handled_by
LEFT JOIN Storage_Locations ol ON ol.location_id = h.old_location_id
LEFT JOIN Storage_Locations nl ON nl.location_id = h.new_location_id
WHERE d.document_id IN (1001, 1002, 1021)
ORDER BY h.document_id, h.history_id;

-- BUSINESS RULE TESTS (each of these should return an error)
-- CALL sp_update_document_status(1006, 'Completed', 301, 'test');  -- cannot skip In Process
-- CALL sp_update_document_status(1001, 'Pending', 301, 'test');    -- Completed cannot go back
-- CALL sp_update_document_status(1006, 'In Process', 303, 'test'); -- Inactive account
-- CALL sp_add_document(301, 'x', 401, NULL, 'Incoming', 'x', '2099-01-01', 501); -- future date
-- UPDATE Documents SET tracking_number = 'C1-999' WHERE document_id = 1001; -- permanent
-- DELETE FROM Documents WHERE document_id = 1006;                  -- documents are never deleted
-- DELETE FROM Users WHERE user_id = 301;                           -- user has records
-- UPDATE Processing_History SET remarks = 'x' WHERE history_id = 5001;  -- history is locked
-- DELETE FROM Processing_History WHERE history_id = 5001;               -- history is locked
