<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}
validate_csrf();
$complaintId = (int) ($_POST['complaint_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));
$priority = trim((string) ($_POST['priority'] ?? 'Medium'));
$message = trim((string) ($_POST['message'] ?? ''));
$departmentId = (int) ($_POST['department_id'] ?? 0);

if ($complaintId <= 0 || $status === '') {
    json_response(false, 'Complaint and status are required.', null, 422);
}
if ($status === 'Rejected' && $message === '') {
    json_response(false, 'A rejection reason is required.', null, 422);
}
if ($status === 'Resolved' && $message === '') {
    json_response(false, 'Resolution notes are required before closing the case.', null, 422);
}

$allowedStatuses = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Rejected'];
if (!in_array($status, $allowedStatuses, true)) {
    json_response(false, 'Invalid complaint status.', null, 422);
}

$pdo = get_db();
$existing = $pdo->prepare('SELECT status, priority, department_id, resolution_notes FROM complaints WHERE id = :complaint_id LIMIT 1');
$existing->execute(['complaint_id' => $complaintId]);
$existing = $existing->fetch();
if (!$existing) {
    json_response(false, 'Complaint not found.', null, 404);
}

$pdo->beginTransaction();
$stmt = $pdo->prepare('UPDATE complaints SET status = :status, priority = :priority, department_id = :department_id, resolution_notes = :resolution_notes, updated_at = CURRENT_TIMESTAMP, resolved_at = ' . ($status === 'Resolved' ? 'CURRENT_TIMESTAMP' : 'resolved_at') . ' WHERE id = :complaint_id');
$stmt->execute([
    'status' => $status,
    'priority' => $priority,
    'department_id' => $departmentId > 0 ? $departmentId : null,
    'resolution_notes' => $status === 'Resolved' || $status === 'Rejected' ? $message : ($existing['resolution_notes'] ?? null),
    'complaint_id' => $complaintId,
]);

$updateStmt = $pdo->prepare('INSERT INTO complaint_updates (complaint_id, actor_user_id, previous_status, new_status, message, visibility, created_at) VALUES (:complaint_id, :actor_user_id, :previous_status, :new_status, :message, :visibility, CURRENT_TIMESTAMP)');
$updateStmt->execute([
    'complaint_id' => $complaintId,
    'actor_user_id' => (int) $_SESSION['user']['id'],
    'previous_status' => $existing['status'],
    'new_status' => $status,
    'message' => $message,
    'visibility' => 'student',
]);

$actorId = (int) ($_SESSION['user']['id'] ?? 0);
$pdo->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) VALUES (:actor_user_id, :action, :entity_type, :entity_id, :metadata, CURRENT_TIMESTAMP)')
    ->execute([
        'actor_user_id' => $actorId,
        'action' => 'update_status',
        'entity_type' => 'complaints',
        'entity_id' => $complaintId,
        'metadata' => json_encode(['status' => $status, 'priority' => $priority, 'department_id' => $departmentId]),
    ]);

$studentUser = $pdo->prepare('SELECT user_id FROM complaints WHERE id = :complaint_id LIMIT 1');
$studentUser->execute(['complaint_id' => $complaintId]);
$studentUser = $studentUser->fetch();
if ($studentUser) {
    $pdo->prepare('INSERT INTO notifications (user_id, title, message, related_complaint_id, created_at) VALUES (:user_id, :title, :message, :related_complaint_id, CURRENT_TIMESTAMP)')
        ->execute([
            'user_id' => (int) $studentUser['user_id'],
            'title' => 'Complaint update',
            'message' => 'Status changed to ' . $status . '. ' . ($message !== '' ? $message : 'Please review the latest update.'),
            'related_complaint_id' => $complaintId,
        ]);
}

$pdo->commit();
json_response(true, 'Complaint status updated successfully.', ['redirect' => app_url('admin/complaint-details.php?id=' . $complaintId)], 200);
