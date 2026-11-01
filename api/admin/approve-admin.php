<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}

$admin = require_admin_session();
validate_csrf();

$pdo = get_db();
$requestId = (int) ($_POST['request_id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? 'approve')));

if ($requestId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    json_response(false, 'Invalid admin approval action.', null, 422);
}

$request = $pdo->prepare('SELECT * FROM admin_access_requests WHERE id = :id AND status = :status LIMIT 1');
$request->execute([
    'id' => $requestId,
    'status' => 'pending',
]);
$request = $request->fetch();
if (!$request) {
    json_response(false, 'No pending admin request found.', null, 404);
}

if ($action === 'approve') {
    $existing = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $existing->execute(['email' => $request['email']]);
    if ($existing->fetch()) {
        json_response(false, 'This email already has an active account.', null, 409);
    }

    $insertUser = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, phone_number, is_active, created_at, updated_at) VALUES (:full_name, :email, :password_hash, :role, :phone_number, 1, NOW(), NOW())');
    $insertUser->execute([
        'full_name' => $request['full_name'],
        'email' => $request['email'],
        'password_hash' => $request['password_hash'],
        'role' => 'admin',
        'phone_number' => $request['phone_number'],
    ]);

    $pdo->prepare('UPDATE admin_access_requests SET status = :status, approved_by = :approved_by, approved_at = NOW(), updated_at = NOW() WHERE id = :id')->execute([
        'status' => 'approved',
        'approved_by' => (int) $admin['id'],
        'id' => $requestId,
    ]);

    json_response(true, 'Admin access approved successfully.', ['redirect' => app_url('admin/dashboard.php')], 200);
}

$pdo->prepare('UPDATE admin_access_requests SET status = :status, approved_by = :approved_by, approved_at = NOW(), updated_at = NOW() WHERE id = :id')->execute([
    'status' => 'rejected',
    'approved_by' => (int) $admin['id'],
    'id' => $requestId,
]);

json_response(true, 'Admin access request rejected.', ['redirect' => app_url('admin/dashboard.php')], 200);
