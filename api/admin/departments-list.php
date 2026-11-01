<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();
$pdo = get_db();
$items = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();
json_response(true, 'Departments loaded.', $items);
