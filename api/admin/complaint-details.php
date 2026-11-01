<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(false, 'Complaint ID is required.', null, 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT c.*, u.full_name AS student_name, u.email AS student_email, u.student_identifier AS student_identifier, cat.name AS category_name, d.name AS department_name FROM complaints c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN departments d ON d.id = c.department_id WHERE c.id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$complaint = $stmt->fetch();
if (!$complaint) {
    json_response(false, 'Complaint not found.', null, 404);
}

$updates = $pdo->prepare('SELECT u.*, actor.full_name AS actor_name FROM complaint_updates u LEFT JOIN users actor ON actor.id = u.actor_user_id WHERE u.complaint_id = :id ORDER BY u.created_at DESC');
$updates->execute(['id' => $id]);
$complaint['updates'] = $updates->fetchAll();

$attachments = $pdo->prepare('SELECT id, original_filename, mime_type, file_size, created_at FROM attachments WHERE complaint_id = :id ORDER BY created_at');
$attachments->execute(['id' => $id]);
$complaint['attachments'] = $attachments->fetchAll();
foreach ($complaint['attachments'] as &$attachment) {
    $attachment['url'] = app_url('api/attachments/view.php?id=' . (int) $attachment['id']);
}
unset($attachment);

$complaint['departments'] = $pdo->query('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();
json_response(true, 'Complaint detail loaded.', $complaint);
