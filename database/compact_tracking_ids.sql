-- One-time migration: make the permanent document ID reflect its intake cabinet.
-- Example: the first record in Cabinet 1 becomes C1-101; Cabinet 2 starts at C2-201.
-- document_id and all processing-history relationships remain unchanged.

USE btrack;

CREATE TABLE IF NOT EXISTS Document_Tracking_Counter (
  counter_name VARCHAR(30) PRIMARY KEY,
  last_value   BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

SET @has_tracking_check = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'Documents'
    AND CONSTRAINT_NAME = 'chk_tracking'
    AND CONSTRAINT_TYPE = 'CHECK'
);
SET @drop_tracking_check = IF(@has_tracking_check > 0,
  'ALTER TABLE Documents DROP CONSTRAINT chk_tracking', 'SELECT 1');
PREPARE drop_tracking_check_stmt FROM @drop_tracking_check;
EXECUTE drop_tracking_check_stmt;
DEALLOCATE PREPARE drop_tracking_check_stmt;

DROP TEMPORARY TABLE IF EXISTS _tracking_id_map;
CREATE TEMPORARY TABLE _tracking_id_map (
  document_id INT NOT NULL PRIMARY KEY,
  tracking_number VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;
INSERT INTO _tracking_id_map (document_id, tracking_number)
SELECT document_id,
       CONCAT('C', cabinet_number, '-', LPAD(cabinet_number * 100 + cabinet_order, 3, '0'))
FROM (
  SELECT d.document_id,
         CAST(REPLACE(l.cabinet, 'Cabinet ', '') AS UNSIGNED) AS cabinet_number,
         ROW_NUMBER() OVER (
           PARTITION BY CAST(REPLACE(l.cabinet, 'Cabinet ', '') AS UNSIGNED)
           ORDER BY d.document_id
         ) AS cabinet_order
  FROM Documents d
  JOIN Storage_Locations l ON l.location_id = d.location_id
) ranked;

-- Temporarily remove the immutability guard only while IDs are migrated.
DROP TRIGGER IF EXISTS trg_documents_before_update;
START TRANSACTION;
UPDATE Documents d
JOIN _tracking_id_map m ON m.document_id = d.document_id
SET d.tracking_number = m.tracking_number;
COMMIT;
DROP TEMPORARY TABLE _tracking_id_map;

ALTER TABLE Documents
  ADD CONSTRAINT chk_tracking CHECK (tracking_number REGEXP '^C[1-9]-[1-9][0-9]{2,}$');

DELIMITER $$
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
DELIMITER ;

INSERT IGNORE INTO Document_Tracking_Counter (counter_name, last_value) VALUES
 ('cabinet-1',100),('cabinet-2',200),('cabinet-3',300),('cabinet-4',400),('cabinet-5',500);
DELETE FROM Document_Tracking_Counter WHERE counter_name = 'document';
UPDATE Document_Tracking_Counter counters
JOIN (
  SELECT CONCAT('cabinet-', SUBSTRING(SUBSTRING_INDEX(tracking_number, '-', 1), 2)) AS counter_name,
         MAX(CAST(SUBSTRING_INDEX(tracking_number, '-', -1) AS UNSIGNED)) AS last_value
  FROM Documents
  GROUP BY SUBSTRING_INDEX(tracking_number, '-', 1)
) ids ON ids.counter_name = counters.counter_name
SET counters.last_value = GREATEST(counters.last_value, ids.last_value);

DROP PROCEDURE IF EXISTS sp_add_document;
DELIMITER $$
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
DELIMITER ;
