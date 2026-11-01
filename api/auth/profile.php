<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}

$user = require_login();
validate_csrf();
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$studentId = trim((string) ($_POST['student_identifier'] ?? ''));

if ($fullName === '' || $email === '') {
    json_response(false, 'Full name and email are required.', null, 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email, student_identifier = :student_identifier, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
$stmt->execute(['full_name' => $fullName, 'email' => $email, 'student_identifier' => $studentId !== '' ? $studentId : null, 'id' => (int)$user['id']]);
$_SESSION['user']['full_name'] = $fullName;
$_SESSION['user']['email'] = $email;
$_SESSION['user']['student_identifier'] = $studentId !== '' ? $studentId : null;
json_response(true, 'Profile updated.', ['redirect' => app_url('student/profile.php')], 200);
