<?php
const APP_NAME = 'GPB-Resolve';
const APP_ROOT = __DIR__ . '/..';

$envFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'gpb-resolve.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || str_starts_with($envLine, '#') || !str_contains($envLine, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $envLine, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name !== '' && getenv($name) === false) {
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
    ]);
    session_start();
}

$configuredBaseUrl = getenv('APP_BASE_URL');
if ($configuredBaseUrl !== false) {
    $baseUrl = trim($configuredBaseUrl);
} else {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $applicationRoot = realpath(APP_ROOT);

    if ($documentRoot !== false && $applicationRoot !== false) {
        $documentRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR);
        $rootPrefix = $documentRoot . DIRECTORY_SEPARATOR;
        if (strncasecmp($applicationRoot, $rootPrefix, strlen($rootPrefix)) === 0) {
            $baseUrl = substr($applicationRoot, strlen($rootPrefix));
            $baseUrl = str_replace(DIRECTORY_SEPARATOR, '/', $baseUrl);
        } elseif (strcasecmp($applicationRoot, $documentRoot) === 0) {
            $baseUrl = '';
        } else {
            $baseUrl = '';
        }
    } else {
        $baseUrl = '';
    }
}

$baseUrl = trim(str_replace('\\', '/', $baseUrl), '/');
define('APP_BASE_URL', $baseUrl === '' ? '' : '/' . $baseUrl);
const UPLOAD_PATH = APP_ROOT . '/storage/uploads';
const MAX_UPLOAD_SIZE = 2097152; // 2MB

if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0775, true);
}

if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
