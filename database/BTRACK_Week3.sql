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
  CONSTRAINT chk_tracking  CHECK (tracking_number REGEXP '^DOC-[0-9]{4}-[0-9]{3}$'),
  CONSTRAINT chk_direction CHECK (direction IN ('Incoming','Outgoing')),
  CONSTRAINT chk_sender    CHECK (CHAR_LENGTH(TRIM(sender_recipient)) > 0),
  CONSTRAINT chk_status    CHECK (status IN ('Pending','In Process','Completed')),
  FOREIGN KEY (type_id)      REFERENCES Document_Types(type_id),
  FOREIGN KEY (committee_id) REFERENCES Committees(committee_id),
  FOREIGN KEY (location_id)  REFERENCES Storage_Locations(location_id),
  FOREIGN KEY (recorded_by)  REFERENCES Users(user_id)
) AUTO_INCREMENT=1001;

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

-- 3a. Record a new document: generates DOC-YYYY-NNN, stores the paper's location.
CREATE PROCEDURE sp_add_document (
  IN p_user_id INT, IN p_title VARCHAR(150), IN p_type_id INT, IN p_committee_id INT,
  IN p_direction VARCHAR(20), IN p_sender_recipient VARCHAR(100), IN p_date_received DATE,
  IN p_location_id INT
)
BEGIN
  DECLARE v_status VARCHAR(10);
  DECLARE v_next INT;
  DECLARE v_prefix VARCHAR(10);
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

  SET v_prefix = CONCAT('DOC-', YEAR(p_date_received), '-');
  SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_number, 10) AS UNSIGNED)), 0) + 1
    INTO v_next FROM Documents WHERE tracking_number LIKE CONCAT(v_prefix, '%');
  SET v_tracking = CONCAT(v_prefix, LPAD(v_next, 3, '0'));

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
LEFT JOIN Committees c    ON c.committee_id = d.committee_id;

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

-- Documents inserted directly (the AFTER INSERT trigger writes the first history rows)
INSERT INTO Documents (document_id, tracking_number, title, type_id, committee_id, direction, sender_recipient, date_received, status, location_id, recorded_by) VALUES
 (1001,'DOC-2026-001','Request for Barangay Clearance - J. Ramos', 403, NULL, 'Incoming','Juan Ramos',            '2026-11-03','Pending', 507, 301),
 (1002,'DOC-2026-002','Endorsement of Scholarship Applicants',     403, NULL, 'Outgoing','Municipal Mayor Office','2026-11-04','Pending', 508, 301),
 (1003,'DOC-2026-003','Notice of Barangay Assembly',               404, 202,  'Outgoing','All Residents',        '2026-11-05','Pending', 510, 301),
 (1004,'DOC-2026-004','October 2026 Collections Report',           401, 201,  'Outgoing','Municipal Treasurer',  '2026-11-05','Pending', 501, 302),
 (1005,'DOC-2026-005','Resolution on Drainage Improvement',        402, 204,  'Incoming','Sangguniang Barangay', '2026-11-10','Pending', 505, 301),
 (1006,'DOC-2026-006','Request for Medical Mission Support',       403, 203,  'Incoming','Rural Health Unit',    '2026-11-10','Pending', 509, 301),
 (1007,'DOC-2026-007','Q3 2026 Statement of Expenditures',         401, 201,  'Outgoing','COA Municipal Office', '2026-11-12','Pending', 503, 302),
 (1008,'DOC-2026-008','Barangay Employee Records Update',          405, NULL, 'Incoming','Municipal HR',         '2026-11-17','Pending', 515, 301);

-- Status/location changes go through the stored procedures (validated + logged by trigger)
-- Arguments: document_id, new_status | new_location_id, user_id, remarks
CALL sp_update_document_status(1001, 'In Process', 301, 'Received and under review');
CALL sp_update_document_status(1001, 'Completed',  302, 'Approved and signed; copy filed');
CALL sp_update_document_status(1002, 'In Process', 301, 'Endorsement being prepared');
CALL sp_update_document_status(1003, 'In Process', 302, 'For posting');
CALL sp_update_document_status(1003, 'Completed',  302, 'Notice posted and released');
CALL sp_update_document_status(1004, 'In Process', 302, 'Waiting for Captain signature');
CALL sp_update_document_status(1005, 'In Process', 301, 'For committee review');
CALL sp_move_document(1002, 509, 301, 'Refiled to Cabinet 03, Drawer 2');

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
CALL sp_add_document(301, 'Request for Street Lighting', 403, 204, 'Incoming', 'Purok 3 Residents', '2026-11-18', 502);
CALL sp_update_document_status(1009, 'In Process', 301, 'Forwarded to Infrastructure committee');
CALL sp_move_document(1006, 504, 301, 'Refiled to Cabinet B, Drawer 1');

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
WHERE d.document_id IN (1001, 1002, 1009)
ORDER BY h.document_id, h.history_id;

-- BUSINESS RULE TESTS (each of these should return an error)
-- CALL sp_update_document_status(1006, 'Completed', 301, 'test');  -- cannot skip In Process
-- CALL sp_update_document_status(1001, 'Pending', 301, 'test');    -- Completed cannot go back
-- CALL sp_update_document_status(1006, 'In Process', 303, 'test'); -- Inactive account
-- CALL sp_add_document(301, 'x', 401, NULL, 'Incoming', 'x', '2099-01-01', 501); -- future date
-- UPDATE Documents SET tracking_number = 'DOC-2026-999' WHERE document_id = 1001; -- permanent
-- DELETE FROM Documents WHERE document_id = 1006;                  -- documents are never deleted
-- DELETE FROM Users WHERE user_id = 301;                           -- user has records
-- UPDATE Processing_History SET remarks = 'x' WHERE history_id = 5001;  -- history is locked
-- DELETE FROM Processing_History WHERE history_id = 5001;               -- history is locked
