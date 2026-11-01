<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$user = require_login();
$attachmentId = (int) ($_GET['id'] ?? 0);
if ($attachmentId <= 0) {
    http_response_code(404);
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT a.stored_filename, a.mime_type, a.original_filename, c.user_id FROM attachments a INNER JOIN complaints c ON c.id = a.complaint_id WHERE a.id = :id LIMIT 1');
$stmt->execute(['id' => $attachmentId]);
$attachment = $stmt->fetch();
if (!$attachment || (($user['role'] ?? '') !== 'admin' && (int) $attachment['user_id'] !== (int) $user['id'])) {
    http_response_code(404);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$mimeType = (string) ($attachment['mime_type'] ?? '');
if (!in_array($mimeType, $allowedTypes, true)) {
    http_response_code(415);
    exit;
}

$basePath = realpath(UPLOAD_PATH);
$filePath = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . basename($attachment['stored_filename']));
if ($basePath === false || $filePath === false || !is_file($filePath)) {
    http_response_code(404);
    exit;
}
$basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strncasecmp($filePath, $basePath, strlen($basePath)) !== 0) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;
