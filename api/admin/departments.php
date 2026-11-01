<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}
validate_csrf();
$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
if ($name === '') {
    json_response(false, 'Department name is required.', null, 422);
}
$pdo = get_db();
$stmt = $pdo->prepare('INSERT INTO departments (name, description, is_active, created_at) VALUES (:name, :description, 1, CURRENT_TIMESTAMP)');
$stmt->execute(['name' => $name, 'description' => $description]);
$departmentId = (int) $pdo->lastInsertId();
$actorId = (int) ($_SESSION['user']['id'] ?? 0);
$pdo->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) VALUES (:actor_user_id, :action, :entity_type, :entity_id, :metadata, CURRENT_TIMESTAMP)')
    ->execute([
        'actor_user_id' => $actorId,
        'action' => 'create_department',
        'entity_type' => 'departments',
        'entity_id' => $departmentId,
        'metadata' => json_encode(['name' => $name]),
    ]);
json_response(true, 'Department added.', ['redirect' => app_url('admin/departments.php')], 201);
