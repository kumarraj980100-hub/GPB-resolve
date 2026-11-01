<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_admin();
$pdo = get_db();
$users = $pdo->query('SELECT id, full_name, email, role, student_identifier, is_active, created_at FROM users ORDER BY created_at DESC')->fetchAll();
json_response(true, 'Users loaded.', $users);
