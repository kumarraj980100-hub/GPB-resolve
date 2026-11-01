<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$pdo = get_db();
$data = [
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Submitted', 'Under Review', 'Assigned')")->fetchColumn(),
    'in_progress' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn(),
    'resolved' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn(),
];

json_response(true, 'Public complaint statistics loaded.', $data, 200);
