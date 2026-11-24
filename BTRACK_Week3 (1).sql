-- =====================================================================
-- B-TRACK: Barangay Document Management System  |  IT116 Week 3
-- Schema follows the Week 2 ERD / data dictionary (MySQL 8.0+)
-- =====================================================================
DROP DATABASE IF EXISTS btrack;
CREATE DATABASE btrack;
USE btrack;

-- ---------------------------------------------------------------------
-- 1. TABLES
-- ---------------------------------------------------------------------
CREATE TABLE Roles (
  role_id   INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE
) AUTO_INCREMENT=101;

CREATE TABLE Committees (
  committee_id   INT AUTO_INCREMENT PRIMARY KEY,
  committee_name VARCHAR(100) NOT NULL UNIQUE
) AUTO_INCREMENT=201;

CREATE TABLE Users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  role_id       INT NOT NULL,
  committee_id  INT NULL,
  full_name     VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  FOREIGN KEY (role_id)      REFERENCES Roles(role_id),
  FOREIGN KEY (committee_id) REFERENCES Committees(committee_id)
) AUTO_INCREMENT=301;

CREATE TABLE Document_Types (
  type_id      INT AUTO_INCREMENT PRIMARY KEY,
  type_name    VARCHAR(60) NOT NULL UNIQUE,
  is_financial BOOLEAN NOT NULL DEFAULT FALSE
) AUTO_INCREMENT=401;

CREATE TABLE Documents (
  document_id     INT AUTO_INCREMENT PRIMARY KEY,
  tracking_number VARCHAR(30)  NOT NULL UNIQUE,
  title           VARCHAR(150) NOT NULL,
  type_id         INT NOT NULL,
  committee_id    INT NULL,
  direction       VARCHAR(20)  NOT NULL,
  sender_recipient VARCHAR(100) NOT NULL,
  date_received   DATE NOT NULL,
  status          VARCHAR(30)  NOT NULL DEFAULT 'Pending',
  recorded_by     INT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_direction CHECK (direction IN ('Incoming','Outgoing')),
  CONSTRAINT chk_status    CHECK (status IN ('Pending','In Process','Completed')),
  FOREIGN KEY (type_id)      REFERENCES Document_Types(type_id),
  FOREIGN KEY (committee_id) REFERENCES Committees(committee_id),
  FOREIGN KEY (recorded_by)  REFERENCES Users(user_id)
) AUTO_INCREMENT=1001;

CREATE TABLE Processing_History (
  history_id  INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT NOT NULL,
  handled_by  INT NOT NULL,
  old_status  VARCHAR(30) NULL,
  new_status  VARCHAR(30) NOT NULL,
  action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  remarks     VARCHAR(255) NULL,
  CONSTRAINT chk_old_status CHECK (old_status IS NULL OR old_status IN ('Pending','In Process','Completed')),
  CONSTRAINT chk_new_status CHECK (new_status IN ('Pending','In Process','Completed')),
  FOREIGN KEY (document_id) REFERENCES Documents(document_id),
  FOREIGN KEY (handled_by)  REFERENCES Users(user_id)
) AUTO_INCREMENT=5001;

-- ---------------------------------------------------------------------
-- 2. TRIGGERS  (Objective 3 + Business Rule 2)
-- ---------------------------------------------------------------------
DELIMITER $$

-- 2a. When a document is recorded, write the first history entry.
CREATE TRIGGER trg_documents_after_insert
AFTER INSERT ON Documents
FOR EACH ROW
BEGIN
  INSERT INTO Processing_History (document_id, handled_by, old_status, new_status, remarks)
  VALUES (NEW.document_id, NEW.recorded_by, NULL, NEW.status, 'Document recorded');
END$$

-- 2b. REQUIRED TRIGGER: whenever the status changes, log it automatically.
--     The handler comes from @current_user_id (set by sp_update_document_status).
CREATE TRIGGER trg_documents_after_update
AFTER UPDATE ON Documents
FOR EACH ROW
BEGIN
  IF OLD.status <> NEW.status THEN
    INSERT INTO Processing_History (document_id, handled_by, old_status, new_status, remarks)
    VALUES (NEW.document_id,
            COALESCE(@current_user_id, NEW.recorded_by),
            OLD.status, NEW.status,
            COALESCE(@action_remarks, 'Status updated'));
  END IF;
END$$

-- 2c. Business Rule 2: history entries cannot be edited or deleted.
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
-- 3. STORED PROCEDURES  (Business Rules 1, 4, 5)
-- ---------------------------------------------------------------------

-- 3a. Record a new document: checks the user's role, generates the tracking number.
CREATE PROCEDURE sp_add_document (
  IN p_user_id INT, IN p_title VARCHAR(150), IN p_type_id INT, IN p_committee_id INT,
  IN p_direction VARCHAR(20), IN p_sender_recipient VARCHAR(100), IN p_date_received DATE
)
BEGIN
  DECLARE v_role VARCHAR(50);
  DECLARE v_financial BOOLEAN;
  DECLARE v_next INT;
  DECLARE v_prefix VARCHAR(10);

  SELECT r.role_name INTO v_role
    FROM Users u JOIN Roles r ON r.role_id = u.role_id WHERE u.user_id = p_user_id;
  SELECT is_financial INTO v_financial FROM Document_Types WHERE type_id = p_type_id;

  IF v_role IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found.';
  ELSEIF v_role = 'Kagawad' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kagawad can only view documents.';
  ELSEIF v_role = 'Treasurer' AND v_financial = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Treasurer can only record financial documents.';
  END IF;

  SET v_prefix = CONCAT('BT-', YEAR(p_date_received), '-');
  SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_number, LENGTH(v_prefix) + 1) AS UNSIGNED)), 0) + 1
    INTO v_next FROM Documents WHERE tracking_number LIKE CONCAT(v_prefix, '%');

  INSERT INTO Documents (tracking_number, title, type_id, committee_id, direction,
                         sender_recipient, date_received, status, recorded_by)
  VALUES (CONCAT(v_prefix, LPAD(v_next, 4, '0')), p_title, p_type_id, p_committee_id,
          p_direction, p_sender_recipient, p_date_received, 'Pending', p_user_id);

  SELECT document_id, tracking_number, status FROM Documents WHERE document_id = LAST_INSERT_ID();
END$$

-- 3b. REQUIRED STORED PROCEDURE: update a document's status with role checks.
--     The trigger trg_documents_after_update writes the history row.
CREATE PROCEDURE sp_update_document_status (
  IN p_document_id INT, IN p_new_status VARCHAR(30), IN p_user_id INT, IN p_remarks VARCHAR(255)
)
BEGIN
  DECLARE v_role VARCHAR(50);
  DECLARE v_financial BOOLEAN;
  DECLARE v_old VARCHAR(30);

  SELECT r.role_name INTO v_role
    FROM Users u JOIN Roles r ON r.role_id = u.role_id WHERE u.user_id = p_user_id;
  SELECT d.status, t.is_financial INTO v_old, v_financial
    FROM Documents d JOIN Document_Types t ON t.type_id = d.type_id
   WHERE d.document_id = p_document_id;

  IF v_old IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document not found.';
  ELSEIF v_role IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found.';
  ELSEIF v_role = 'Kagawad' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kagawad can only view documents.';
  ELSEIF v_role = 'Treasurer' AND v_financial = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Treasurer can only update financial documents.';
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

DELIMITER ;

-- ---------------------------------------------------------------------
-- 4. VIEW  (REQUIRED)
-- ---------------------------------------------------------------------
CREATE VIEW vw_document_tracking AS
SELECT d.document_id, d.tracking_number, d.title,
       t.type_name, t.is_financial, d.direction,
       c.committee_name, d.sender_recipient, d.date_received, d.status,
       u.full_name AS recorded_by,
       (SELECT MAX(h.action_date) FROM Processing_History h
         WHERE h.document_id = d.document_id) AS last_action,
       CASE WHEN d.status = 'Completed' THEN NULL
            ELSE DATEDIFF(CURDATE(), d.date_received) END AS days_open
FROM Documents d
JOIN Document_Types t ON t.type_id = d.type_id
JOIN Users u          ON u.user_id = d.recorded_by
LEFT JOIN Committees c ON c.committee_id = d.committee_id;

-- ---------------------------------------------------------------------
-- 5. SAMPLE DATA
--    ID ranges:  Roles 101+ | Committees 201+ | Users 301+
--                Document_Types 401+ | Documents 1001+ | History 5001+
-- ---------------------------------------------------------------------
INSERT INTO Roles (role_id, role_name) VALUES
 (101,'Secretary'),(102,'Captain'),(103,'Treasurer'),(104,'Kagawad');

INSERT INTO Committees (committee_id, committee_name) VALUES
 (201,'Finance'),(202,'Peace and Order'),(203,'Health and Sanitation'),(204,'Infrastructure');

INSERT INTO Users (user_id, role_id, committee_id, full_name, username, password_hash) VALUES
 (301, 101, NULL, 'Reyes, Maria Lourdes', 'mreyes',      SHA2('secretary123', 256)),
 (302, 102, NULL, 'Santos, Roberto',      'rsantos',     SHA2('captain123', 256)),
 (303, 103, 201,  'Dela Cruz, Elena',     'edelacruz',   SHA2('treasurer123', 256)),
 (304, 104, 202,  'Garcia, Antonio',      'agarcia',     SHA2('kagawad123', 256)),
 (305, 104, 203,  'Mendoza, Teresita',    'tmendoza',    SHA2('kagawad123', 256)),
 (306, 104, 204,  'Villanueva, Ramon',    'rvillanueva', SHA2('kagawad123', 256));

INSERT INTO Document_Types (type_id, type_name, is_financial) VALUES
 (401,'Request Letter', FALSE),(402,'Endorsement Letter', FALSE),(403,'Notice', FALSE),
 (404,'Financial Report', TRUE),(405,'Resolution', FALSE),(406,'Administrative Record', FALSE);

-- Documents inserted directly (the AFTER INSERT trigger creates the first history row)
INSERT INTO Documents (document_id, tracking_number, title, type_id, committee_id, direction, sender_recipient, date_received, status, recorded_by) VALUES
 (1001,'BT-2026-0001','Request for Barangay Clearance - J. Ramos', 401, NULL, 'Incoming','Juan Ramos',            '2026-11-03','Pending', 301),
 (1002,'BT-2026-0002','Endorsement of Scholarship Applicants',     402, NULL, 'Outgoing','Municipal Mayor Office','2026-11-04','Pending', 301),
 (1003,'BT-2026-0003','Notice of Barangay Assembly',               403, 202,  'Outgoing','All Residents',        '2026-11-05','Pending', 301),
 (1004,'BT-2026-0004','October 2026 Collections Report',           404, 201,  'Outgoing','Municipal Treasurer',  '2026-11-05','Pending', 303),
 (1005,'BT-2026-0005','Resolution on Drainage Improvement',        405, 204,  'Incoming','Sangguniang Barangay', '2026-11-10','Pending', 301),
 (1006,'BT-2026-0006','Request for Medical Mission Support',       401, 203,  'Incoming','Rural Health Unit',    '2026-11-10','Pending', 301),
 (1007,'BT-2026-0007','Q3 2026 Statement of Expenditures',         404, 201,  'Outgoing','COA Municipal Office', '2026-11-12','Pending', 303),
 (1008,'BT-2026-0008','Barangay Employee Records Update',          406, NULL, 'Incoming','Municipal HR',         '2026-11-17','Pending', 301);

-- Status changes go through the stored procedure (role-checked + logged by trigger)
-- Arguments: document_id, new_status, user_id, remarks
CALL sp_update_document_status(1001, 'In Process', 301, 'Received and under review');
CALL sp_update_document_status(1001, 'Completed',  302, 'Approved and signed by Captain');
CALL sp_update_document_status(1002, 'In Process', 301, 'Endorsement being prepared');
CALL sp_update_document_status(1003, 'Completed',  301, 'Notice posted and released');
CALL sp_update_document_status(1004, 'In Process', 303, 'Waiting for Captain signature');
CALL sp_update_document_status(1005, 'In Process', 301, 'For committee review');

-- =====================================================================
-- 6. FIVE SQL QUERIES  (screenshot each statement + output)
-- =====================================================================

-- Q1. List all users alphabetically with their roles
SELECT u.full_name, u.username, r.role_name, COALESCE(c.committee_name,'-') AS committee
FROM Users u
JOIN Roles r ON r.role_id = u.role_id
LEFT JOIN Committees c ON c.committee_id = u.committee_id
ORDER BY u.full_name;

-- Q2. Documents not yet completed, with type, status and who recorded them
SELECT d.tracking_number, d.title, t.type_name, d.status, u.full_name AS recorded_by
FROM Documents d
JOIN Document_Types t ON t.type_id = d.type_id
JOIN Users u ON u.user_id = d.recorded_by
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

-- =====================================================================
-- 7. DEMONSTRATIONS  (screenshot each statement + output)
-- =====================================================================

-- VIEW
SELECT * FROM vw_document_tracking;
SELECT * FROM vw_document_tracking WHERE status <> 'Completed' ORDER BY days_open DESC;

-- STORED PROCEDURE: record a new document, then update its status
CALL sp_add_document(301, 'Request for Street Lighting', 401, 204, 'Incoming', 'Purok 3 Residents', '2026-11-20');
CALL sp_update_document_status(1009, 'In Process', 301, 'Forwarded to Infrastructure committee');

-- TRIGGER: processing history was written automatically
SELECT h.history_id, d.tracking_number, h.old_status, h.new_status,
       u.full_name AS handled_by, h.action_date, h.remarks
FROM Processing_History h
JOIN Documents d ON d.document_id = h.document_id
JOIN Users u     ON u.user_id = h.handled_by
WHERE d.document_id IN (1001, 1009)
ORDER BY h.document_id, h.history_id;

-- BUSINESS RULE TESTS (each of these should return an error)
-- CALL sp_update_document_status(1005, 'Completed', 304, 'test');   -- Kagawad cannot update
-- CALL sp_update_document_status(1001, 'Pending', 303, 'test');     -- Treasurer on non-financial doc
-- UPDATE Processing_History SET remarks = 'x' WHERE history_id = 5001;  -- history is locked
-- DELETE FROM Processing_History WHERE history_id = 5001;               -- history is locked
-- UPDATE Documents SET status = 'Done' WHERE document_id = 1001;        -- violates CHECK
