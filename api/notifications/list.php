<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_login();
$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 25');
$stmt->execute(['user_id' => (int) $user['id']]);
json_response(true, 'Notifications loaded.', $stmt->fetchAll(), 200);
