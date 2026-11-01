<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_login();
if (($user['role'] ?? '') !== 'student') {
    json_response(false, 'Student access required.', null, 403);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(false, 'Complaint ID is required.', null, 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT c.*, cat.name AS category_name, d.name AS department_name FROM complaints c LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN departments d ON d.id = c.department_id WHERE c.id = :id AND c.user_id = :user_id LIMIT 1');
$stmt->execute(['id' => $id, 'user_id' => (int) $user['id']]);
$complaint = $stmt->fetch();
if (!$complaint) {
    json_response(false, 'Complaint not found.', null, 404);
}

$updates = $pdo->prepare('SELECT * FROM complaint_updates WHERE complaint_id = :id ORDER BY created_at DESC');
$updates->execute(['id' => $id]);
$complaint['updates'] = $updates->fetchAll();
$attachments = $pdo->prepare('SELECT id, original_filename, mime_type, file_size, created_at FROM attachments WHERE complaint_id = :id ORDER BY created_at');
$attachments->execute(['id' => $id]);
$complaint['attachments'] = $attachments->fetchAll();
foreach ($complaint['attachments'] as &$attachment) {
    $attachment['url'] = app_url('api/attachments/view.php?id=' . (int) $attachment['id']);
}
unset($attachment);
json_response(true, 'Complaint detail loaded.', $complaint, 200);
