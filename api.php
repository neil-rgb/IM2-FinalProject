<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if (query_value('auth') === '1') {
    echo json_encode(['csrf' => csrf_token()]);
    exit;
}
if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['message' => 'Sign in to continue.']);
    exit;
}
try {
    $db = db();
    $active = $db->prepare('SELECT status FROM Users WHERE user_id = ?');
    $active->execute([current_user()['id']]);
    if ($active->fetchColumn() !== 'Active') {
        http_response_code(403);
        echo json_encode(['message' => 'Your account is inactive.']);
        exit;
    }
    if (query_value('record') !== '') {
        $id = filter_var(query_value('record'), FILTER_VALIDATE_INT);
        $record = $db->prepare('SELECT d.*, b.deleted_at, u.full_name AS recorded_by_name, a.file_name AS soft_copy_name FROM Documents d JOIN Users u ON u.user_id=d.recorded_by LEFT JOIN Document_Recycle_Bin b ON b.document_id=d.document_id LEFT JOIN Document_Soft_Copies a ON a.document_id=d.document_id WHERE d.document_id=?');
        $record->execute([$id ?: 0]);
        $document = $record->fetch();
        if (!$document) {
            http_response_code(404);
            echo json_encode(['message' => 'Document not found.']);
            exit;
        }
        $history = $db->prepare('SELECT h.*, u.full_name AS handled_by_name FROM Processing_History h JOIN Users u ON u.user_id=h.handled_by WHERE h.document_id=? ORDER BY h.action_date DESC, h.history_id DESC');
        $history->execute([$id]);
        echo json_encode(['document' => $document, 'history' => $history->fetchAll()]);
        exit;
    }
    echo json_encode([
        'user' => current_user(), 'csrf' => csrf_token(), 'today' => date('Y-m-d'),
        'documents' => fetch_documents(),
        'types' => $db->query('SELECT * FROM Document_Types ORDER BY type_id')->fetchAll(),
        'locations' => $db->query('SELECT * FROM Storage_Locations WHERE type_id IS NOT NULL ORDER BY type_id,cabinet,drawer,folder')->fetchAll(),
        'profile' => $db->query('SELECT barangay_name,location,office_address,contact_number FROM Barangay_Profile WHERE profile_id=1')->fetch() ?: null,
        'recycled' => $db->query('SELECT d.document_id,d.tracking_number,d.title,b.deleted_at FROM Documents d JOIN Document_Recycle_Bin b ON b.document_id=d.document_id ORDER BY b.deleted_at DESC')->fetchAll(),
        'notice' => pull_flash()
    ]);
} catch (PDOException $error) {
    error_log('B-TRACK React API: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['message' => 'Records could not be loaded. Check MySQL and try again.']);
}
