<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$pdo = get_db();
$stmt = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name');
json_response(true, 'Categories loaded.', $stmt->fetchAll(), 200);
