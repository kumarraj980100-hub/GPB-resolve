<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();
$pdo = get_db();
$data = [
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn(),
    'new' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Submitted','Under Review')")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Submitted','Under Review','Assigned')")->fetchColumn(),
    'in_progress' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn(),
    'resolved' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn(),
    'recent' => $pdo->query('SELECT c.id, c.reference_number, c.title, c.status, c.priority, c.created_at, u.full_name AS student_name, cat.name AS category_name FROM complaints c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN categories cat ON cat.id = c.category_id ORDER BY c.created_at DESC LIMIT 7')->fetchAll(),
    'pending_admin_requests' => $pdo->query("SELECT id, full_name, email, phone_number, created_at FROM admin_access_requests WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(),
];
json_response(true, 'Dashboard data loaded.', $data, 200);
