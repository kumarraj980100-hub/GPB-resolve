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
$departmentId = (int) ($_POST['department_id'] ?? 0);
if ($complaintId <= 0) {
    json_response(false, 'Complaint ID is required.', null, 422);
}
$pdo = get_db();
$stmt = $pdo->prepare('UPDATE complaints SET department_id = :department_id, updated_at = CURRENT_TIMESTAMP WHERE id = :complaint_id');
$stmt->execute(['department_id' => $departmentId > 0 ? $departmentId : null, 'complaint_id' => $complaintId]);
$actorId = (int) ($_SESSION['user']['id'] ?? 0);
$pdo->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) VALUES (:actor_user_id, :action, :entity_type, :entity_id, :metadata, CURRENT_TIMESTAMP)')
    ->execute([
        'actor_user_id' => $actorId,
        'action' => 'assign_department',
        'entity_type' => 'complaints',
        'entity_id' => $complaintId,
        'metadata' => json_encode(['department_id' => $departmentId]),
    ]);
json_response(true, 'Complaint assigned to department.', ['redirect' => app_url('admin/complaint-details.php?id=' . $complaintId)], 200);
