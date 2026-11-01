<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}

validate_csrf();
enforce_rate_limit('login-ip', $_SERVER['REMOTE_ADDR'] ?? '', 300, 900);

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$requestedRole = strtolower(trim((string) ($_POST['role'] ?? 'student')));
$otpCode = trim((string) ($_POST['otp_code'] ?? ''));

if (!is_valid_email($email) || $password === '') {
    json_response(false, 'Invalid email or password.', null, 422);
}
enforce_rate_limit('login-account', $email, 15, 900);

if (!in_array($requestedRole, ['student', 'admin'], true)) {
    $requestedRole = 'student';
}

$pdo = get_db();
$user = $pdo->prepare('SELECT * FROM users WHERE email = :email AND role = :role AND is_active = 1 LIMIT 1');
$user->execute([
    'email' => $email,
    'role' => $requestedRole,
]);
$user = $user->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(false, 'Invalid email or password.', null, 401);
}

if ($otpCode === '') {
    try {
        store_email_otp($email, 'login', $requestedRole);
    } catch (RuntimeException $exception) {
        json_response(false, $exception->getMessage(), null, 503);
    }
    json_response(true, 'Verification code sent to your email. Please enter the OTP to continue.', [
        'requires_otp' => true,
        'email' => $email,
        'role' => $requestedRole,
    ], 200);
}

if (!verify_email_otp($email, 'login', $requestedRole, $otpCode)) {
    json_response(false, 'Invalid or expired OTP. Please request a new code.', null, 401);
}

$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'full_name' => $user['full_name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'student_identifier' => $user['student_identifier'],
];

session_regenerate_id(true);
$redirect = $user['role'] === 'admin' ? app_url('admin/dashboard.php') : app_url('student/dashboard.php');
json_response(true, 'Login successful.', ['redirect' => $redirect], 200);
