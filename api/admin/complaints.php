<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();
$pdo = get_db();
$where = '1=1';
$params = [];
if (!empty($_GET['status'])) {
    $where .= ' AND status = :status';
    $params['status'] = $_GET['status'];
}
if (!empty($_GET['priority'])) {
    $where .= ' AND priority = :priority';
    $params['priority'] = $_GET['priority'];
}
$stmt = $pdo->prepare('SELECT c.*, cat.name AS category_name, u.full_name AS student_name FROM complaints c LEFT JOIN categories cat ON cat.id = c.category_id LEFT JOIN users u ON u.id = c.user_id WHERE ' . $where . ' ORDER BY c.created_at DESC LIMIT 100');
$stmt->execute($params);
json_response(true, 'Admin complaint list loaded.', $stmt->fetchAll(), 200);
