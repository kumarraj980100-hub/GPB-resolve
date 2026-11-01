<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}
$pdo = get_db();
$notificationId = (int) ($_POST['id'] ?? 0);
if ($notificationId <= 0) {
    json_response(false, 'Notification ID is required.', null, 422);
}
$stmt = $pdo->prepare('UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id');
$stmt->execute(['id' => $notificationId, 'user_id' => (int)$user['id']]);
json_response(true, 'Notification marked as read.', null, 200);
