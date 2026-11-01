<?php
require_once __DIR__ . '/auth.php';

function ensure_admin(): void
{
    if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}
