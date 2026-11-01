<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();
$pdo = get_db();
$stmt = $pdo->query('SELECT reference_number, title, status, priority, created_at FROM complaints ORDER BY created_at DESC');
$rows = $stmt->fetchAll();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="gpb-resolve-reports.csv"');
$fp = fopen('php://output', 'w');
fputcsv($fp, ['reference_number', 'title', 'status', 'priority', 'created_at']);
foreach ($rows as $row) {
    fputcsv($fp, [$row['reference_number'], $row['title'], $row['status'], $row['priority'], $row['created_at']]);
}
fclose($fp);
exit;
