<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}

validate_csrf();
enforce_rate_limit('register-ip', $_SERVER['REMOTE_ADDR'] ?? '', 120, 3600);

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$studentId = trim((string) ($_POST['student_identifier'] ?? ''));
$phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
$requestedRole = strtolower(trim((string) ($_POST['role'] ?? 'student')));
$otpCode = trim((string) ($_POST['otp_code'] ?? ''));

if (!in_array($requestedRole, ['student', 'admin'], true)) {
    $requestedRole = 'student';
}

if ($fullName === '' || $email === '' || $password === '') {
    json_response(false, 'Full name, email, and password are required.', null, 422);
}
if (!is_valid_email($email)) {
    json_response(false, 'Please provide a valid email address.', null, 422);
}
if (strlen($password) < 8) {
    json_response(false, 'Password must be at least 8 characters long.', null, 422);
}
$phoneDigits = preg_replace('/\D/', '', $phoneNumber);
if (!preg_match('/^\+?[0-9\s().-]+$/', $phoneNumber) || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
    json_response(false, 'Enter a valid phone number with 7 to 15 digits.', null, 422);
}
enforce_rate_limit('register-account', $email, 6, 3600);

$pdo = get_db();
$userExists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$userExists->execute(['email' => $email]);
if ($userExists->fetch()) {
    json_response(false, 'An account with that email already exists.', null, 409);
}

if ($requestedRole === 'admin') {
    $pendingExists = $pdo->prepare('SELECT id FROM admin_access_requests WHERE email = :email AND status = :status LIMIT 1');
    $pendingExists->execute(['email' => $email, 'status' => 'pending']);
    if ($pendingExists->fetch()) {
        json_response(false, 'An admin approval request for this email is already pending.', null, 409);
    }
}

$purpose = $requestedRole === 'admin' ? 'admin_registration' : 'student_registration';
if ($otpCode === '') {
    try {
        store_email_otp($email, $purpose, $requestedRole);
    } catch (RuntimeException $exception) {
        json_response(false, $exception->getMessage(), null, 503);
    }
    json_response(true, 'Verification code sent to your email. Please enter the OTP to complete your registration.', [
        'requires_otp' => true,
        'email' => $email,
        'role' => $requestedRole,
    ], 200);
}

if (!verify_email_otp($email, $purpose, $requestedRole, $otpCode)) {
    json_response(false, 'Invalid or expired OTP. Please request a new code.', null, 401);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

if ($requestedRole === 'admin') {
    $stmt = $pdo->prepare('INSERT INTO admin_access_requests (full_name, email, password_hash, phone_number, status, created_at, updated_at) VALUES (:full_name, :email, :password_hash, :phone_number, :status, NOW(), NOW())');
    $stmt->execute([
        'full_name' => $fullName,
        'email' => $email,
        'password_hash' => $hash,
        'phone_number' => $phoneNumber,
        'status' => 'pending',
    ]);

    json_response(true, 'Admin registration submitted successfully. An existing admin must approve your request before you can sign in.', [
        'redirect' => app_url('login.php?role=admin'),
    ], 201);
}

$stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, student_identifier, phone_number, is_active, created_at, updated_at) VALUES (:full_name, :email, :password_hash, :role, :student_identifier, :phone_number, 1, NOW(), NOW())');
$stmt->execute([
    'full_name' => $fullName,
    'email' => $email,
    'password_hash' => $hash,
    'role' => 'student',
    'student_identifier' => $studentId !== '' ? $studentId : null,
    'phone_number' => $phoneNumber,
]);

$userId = (int) $pdo->lastInsertId();
$_SESSION['user'] = [
    'id' => $userId,
    'full_name' => $fullName,
    'email' => $email,
    'role' => 'student',
    'student_identifier' => $studentId !== '' ? $studentId : null,
    'phone_number' => $phoneNumber,
];

session_regenerate_id(true);
json_response(true, 'Registration successful.', ['redirect' => app_url('student/dashboard.php')], 201);
