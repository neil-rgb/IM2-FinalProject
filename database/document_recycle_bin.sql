-- Add the Recycle Bin to an existing B-TRACK database.
USE btrack;

CREATE TABLE IF NOT EXISTS Document_Recycle_Bin (
  document_id INT PRIMARY KEY,
  deleted_by  INT NOT NULL,
  deleted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recycle_bin_deleted_at (deleted_at, document_id),
  FOREIGN KEY (document_id) REFERENCES Documents(document_id),
  FOREIGN KEY (deleted_by)  REFERENCES Users(user_id)
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW vw_document_tracking AS
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
