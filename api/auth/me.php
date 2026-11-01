<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    json_response(false, 'Not authenticated.', null, 401);
}
json_response(true, 'User profile loaded.', [
    'user' => $user,
    'csrf_token' => csrf_token(),
], 200);
