-- Run on an existing B-TRACK database to correct storage choices.
-- Legacy Cabinet A/B rows are retained for processing-history references.

SET @has_storage_type_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'Storage_Locations'
      AND COLUMN_NAME = 'type_id'
);
SET @storage_type_sql = IF(
    @has_storage_type_id = 0,
    'ALTER TABLE Storage_Locations ADD COLUMN type_id INT NULL AFTER folder',
    'SELECT 1'
);
PREPARE storage_type_stmt FROM @storage_type_sql;
EXECUTE storage_type_stmt;
DEALLOCATE PREPARE storage_type_stmt;

SET @has_storage_type_fk = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'Storage_Locations'
      AND CONSTRAINT_NAME = 'fk_storage_locations_type'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @storage_type_fk_sql = IF(
    @has_storage_type_fk = 0,
    'ALTER TABLE Storage_Locations ADD CONSTRAINT fk_storage_locations_type FOREIGN KEY (type_id) REFERENCES Document_Types(type_id)',
    'SELECT 1'
);
PREPARE storage_type_fk_stmt FROM @storage_type_fk_sql;
EXECUTE storage_type_fk_stmt;
DEALLOCATE PREPARE storage_type_fk_stmt;

-- Three folder slots per category cabinet, reusing the existing Drawer/Folder labels.
INSERT INTO Storage_Locations (type_id, cabinet, drawer, folder) VALUES
 (401,'Cabinet 01','Drawer 1','Folder 001'),(401,'Cabinet 01','Drawer 1','Folder 002'),(401,'Cabinet 01','Drawer 2','Folder 001'),
 (402,'Cabinet 02','Drawer 1','Folder 001'),(402,'Cabinet 02','Drawer 1','Folder 002'),(402,'Cabinet 02','Drawer 2','Folder 001'),
 (403,'Cabinet 03','Drawer 1','Folder 001'),(403,'Cabinet 03','Drawer 1','Folder 002'),(403,'Cabinet 03','Drawer 2','Folder 001'),
 (404,'Cabinet 04','Drawer 1','Folder 001'),(404,'Cabinet 04','Drawer 1','Folder 002'),(404,'Cabinet 04','Drawer 2','Folder 001'),
 (405,'Cabinet 05','Drawer 1','Folder 001'),(405,'Cabinet 05','Drawer 1','Folder 002'),(405,'Cabinet 05','Drawer 2','Folder 001')
ON DUPLICATE KEY UPDATE type_id = VALUES(type_id);

-- Move existing records out of the generic sample cabinets and into the cabinet
-- that corresponds to their document category. The trigger appends an immutable
-- location-change history row; old paths remain available to that history.
SET @current_user_id = NULL;
SET @action_remarks = 'Storage path aligned with the document category cabinet.';
UPDATE Documents AS d
JOIN Storage_Locations AS old_location
  ON old_location.location_id = d.location_id
JOIN Storage_Locations AS new_location
  ON new_location.type_id = d.type_id
 AND new_location.cabinet = CASE d.type_id
       WHEN 401 THEN 'Cabinet 01'
       WHEN 402 THEN 'Cabinet 02'
       WHEN 403 THEN 'Cabinet 03'
       WHEN 404 THEN 'Cabinet 04'
       WHEN 405 THEN 'Cabinet 05'
     END
 AND new_location.drawer = old_location.drawer
 AND new_location.folder = old_location.folder
SET d.location_id = new_location.location_id
WHERE old_location.type_id IS NULL;
SET @action_remarks = NULL;

DROP PROCEDURE IF EXISTS sp_add_document;
DROP PROCEDURE IF EXISTS sp_move_document;
DELIMITER $$

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
