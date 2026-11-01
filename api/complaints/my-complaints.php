<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_login();
if (($user['role'] ?? '') !== 'student') {
    json_response(false, 'Student access required.', null, 403);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT c.*, cat.name AS category_name FROM complaints c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.user_id = :user_id ORDER BY c.created_at DESC');
$stmt->execute(['user_id' => (int) $user['id']]);
json_response(true, 'Complaints loaded.', $stmt->fetchAll(), 200);
