<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/validation.php';

function require_session_user(): array
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function require_student(): array
{
    $user = require_session_user();
    if (($user['role'] ?? '') !== 'student') {
        redirect('index.php');
    }
    return $user;
}

function require_admin_session(): array
{
    $user = require_session_user();
    if (($user['role'] ?? '') !== 'admin') {
        redirect('index.php');
    }
    return $user;
}

function current_user_id(): ?int
{
    $user = $_SESSION['user'] ?? null;
    return $user['id'] ?? null;
}
