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
$check = $pdo->prepare('SELECT id FROM complaints WHERE id = :id AND user_id = :user_id LIMIT 1');
$check->execute(['id' => $id, 'user_id' => (int) $user['id']]);
if (!$check->fetch()) {
    json_response(false, 'Complaint not found.', null, 404);
}
$stmt = $pdo->prepare('SELECT * FROM complaint_updates WHERE complaint_id = :id ORDER BY created_at DESC');
$stmt->execute(['id' => $id]);
json_response(true, 'Complaint updates loaded.', $stmt->fetchAll(), 200);
