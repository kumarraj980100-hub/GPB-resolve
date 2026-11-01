<?php
function app_url(string $path = ''): string
{
    $base = rtrim(APP_BASE_URL, '/');
    if ($path === '') {
        return $base === '' ? '/' : $base;
    }

    $clean = '/' . ltrim($path, '/');
    return ($base === '' ? '' : $base) . $clean;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function json_response(bool $success, string $message, mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        json_response(false, 'Authentication required.', null, 401);
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? '') !== 'admin') {
        json_response(false, 'Admin access required.', null, 403);
    }
    return $user;
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

function enforce_rate_limit(string $scope, string $identity, int $limit, int $windowSeconds): void
{
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remoteAddress === '' || $limit < 1 || $windowSeconds < 1) {
        error_log('GPB-Resolve rate limiting could not be initialized.');
        json_response(false, 'The service is temporarily unavailable. Please try again later.', null, 503);
    }

    $applicationRoot = realpath(APP_ROOT);
    if ($applicationRoot === false) {
        error_log('GPB-Resolve application root could not be resolved for rate-limit storage.');
        json_response(false, 'The service is temporarily unavailable. Please try again later.', null, 503);
    }
    $directory = dirname($applicationRoot, 2) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'gpb-resolve-rate-limits';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('GPB-Resolve rate-limit storage could not be created.');
        json_response(false, 'The service is temporarily unavailable. Please try again later.', null, 503);
    }

    $key = hash('sha256', $scope . "\0" . strtolower(trim($identity)));
    $file = $directory . DIRECTORY_SEPARATOR . $key . '.json';
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        error_log('GPB-Resolve rate-limit storage could not be locked.');
        json_response(false, 'The service is temporarily unavailable. Please try again later.', null, 503);
    }

    $contents = stream_get_contents($handle);
    $state = $contents === '' ? null : json_decode($contents, true);
    if ($contents === false || ($contents !== '' && (!is_array($state)
        || !isset($state['started_at'], $state['count'])
        || !is_int($state['started_at'])
        || !is_int($state['count'])))) {
        flock($handle, LOCK_UN);
        fclose($handle);
        error_log('GPB-Resolve rate-limit state is invalid.');
        json_response(false, 'The service is temporarily unavailable. Please try again later.', null, 503);
    }

    $now = time();
    if ($state === null || $now - $state['started_at'] >= $windowSeconds) {
        $state = ['started_at' => $now, 'count' => 0];
    }

    if ($state['count'] >= $limit) {
        $retryAfter = max(1, $windowSeconds - ($now - $state['started_at']));
        flock($handle, LOCK_UN);
        fclose($handle);
        header('Retry-After: ' . $retryAfter);
        json_response(false, 'Too many attempts. Please try again later.', null, 429);
    }

    $state['count']++;
    rewind($handle);
    $written = ftruncate($handle, 0) && fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR)) !== false && fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!$written) {
        error_log('GPB-Resolve rate-limit state could not be saved.');
        json_response(false, 'The service is temporarily unavailable. Please try again later.', null, 503);
    }
}

function validate_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_response(false, 'Invalid CSRF token.', null, 419);
    }
}

function format_date(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $date = new DateTime($value, new DateTimeZone('UTC'));
    return $date->format('d M Y, H:i');
}

function slugify(string $str): string
{
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim((string) $str, '-');
}

function generate_reference_number(): string
{
    $prefix = 'GPB';
    $suffix = strtoupper(bin2hex(random_bytes(3))); 
    return $prefix . '-' . date('Ymd') . '-' . $suffix;
}

function strip_or_null(string $value): ?string
{
    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

function generate_otp(): string
{
    return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function send_email_otp(string $email, string $code, string $purpose): bool
{
    $host = getenv('SMTP_HOST') ?: '';
    $port = (int) (getenv('SMTP_PORT') ?: 0);
    $username = getenv('SMTP_USERNAME') ?: '';
    $password = getenv('SMTP_PASSWORD') ?: '';
    $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $username;
    $fromName = getenv('SMTP_FROM_NAME') ?: APP_NAME;

    if (
        $host === ''
        || $port < 1
        || $username === ''
        || $password === ''
        || !in_array($encryption, ['tls', 'ssl'], true)
        || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || str_contains($fromName, "\r")
        || str_contains($fromName, "\n")
    ) {
        error_log('GPB-Resolve SMTP configuration is incomplete or invalid.');
        return false;
    }

    $socket = false;
    try {
        $timeout = 15;
        $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = stream_socket_client(
            $transport . $host . ':' . $port,
            $errorNumber,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $host,
                ],
            ])
        );
        if ($socket === false) {
            throw new RuntimeException('SMTP connection failed.');
        }

        stream_set_timeout($socket, $timeout);
        smtp_expect_response($socket, [220]);
        smtp_send_command($socket, 'EHLO localhost', [250]);

        if ($encryption === 'tls') {
            smtp_send_command($socket, 'STARTTLS', [220]);
            if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new RuntimeException('SMTP TLS negotiation failed.');
            }
            smtp_send_command($socket, 'EHLO localhost', [250]);
        }

        smtp_send_command($socket, 'AUTH LOGIN', [334]);
        smtp_send_command($socket, base64_encode($username), [334]);
        smtp_send_command($socket, base64_encode($password), [235]);
        smtp_send_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_send_command($socket, 'RCPT TO:<' . $email . '>', [250, 251]);
        smtp_send_command($socket, 'DATA', [354]);

        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedName . ' <' . $fromEmail . '>',
            'To: <' . $email . '>',
            'Subject: Your GPB-Resolve verification code',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $body = "Your GPB-Resolve {$purpose} verification code is: {$code}\r\n\r\n"
            . "This code expires in 10 minutes. If you did not request it, you can ignore this email.";
        $message = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $body));
        fwrite($socket, $message . "\r\n.\r\n");
        smtp_expect_response($socket, [250]);
        smtp_send_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (RuntimeException $exception) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        error_log('GPB-Resolve OTP email delivery failed.');
        return false;
    }
}

function smtp_send_command($socket, string $command, array $expectedCodes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('SMTP command could not be sent.');
    }
    return smtp_expect_response($socket, $expectedCodes);
}

function smtp_expect_response($socket, array $expectedCodes): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('SMTP server returned no response.');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP server rejected a command.');
    }

    return $response;
}

function store_email_otp(string $email, string $purpose, string $role): array
{
    $pdo = get_db();
    $code = generate_otp();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $pdo->prepare('INSERT INTO email_otps (email, purpose, role, code, expires_at, created_at, attempts, verified_at) VALUES (:email, :purpose, :role, :code, :expires_at, NOW(), 0, NULL)');
    $stmt->execute([
        'email' => strtolower(trim($email)),
        'purpose' => $purpose,
        'role' => $role,
        'code' => $code,
        'expires_at' => $expiresAt,
    ]);
    $otpId = (int) $pdo->lastInsertId();

    if (!send_email_otp($email, $code, $purpose)) {
        $pdo->prepare('DELETE FROM email_otps WHERE id = :id')->execute(['id' => $otpId]);
        throw new RuntimeException('Unable to send the verification email. Check the SMTP settings and try again.');
    }

    return [
        'code' => $code,
        'expires_at' => $expiresAt,
    ];
}

function verify_email_otp(string $email, string $purpose, string $role, string $code): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM email_otps WHERE email = :email AND purpose = :purpose AND role = :role AND verified_at IS NULL ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([
        'email' => strtolower(trim($email)),
        'purpose' => $purpose,
        'role' => $role,
    ]);

    $otp = $stmt->fetch();
    if (!$otp) {
        return false;
    }
    if ((int) $otp['attempts'] >= 5) {
        return false;
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $expiresAt = new DateTime($otp['expires_at'], new DateTimeZone('UTC'));
    if ($now > $expiresAt) {
        return false;
    }

    if (!hash_equals((string) $otp['code'], $code)) {
        $attempts = (int) $otp['attempts'] + 1;
        $pdo->prepare('UPDATE email_otps SET attempts = :attempts WHERE id = :id')->execute([
            'attempts' => $attempts,
            'id' => (int) $otp['id'],
        ]);
        return false;
    }

    $pdo->prepare('UPDATE email_otps SET verified_at = NOW() WHERE id = :id')->execute([
        'id' => (int) $otp['id'],
    ]);
    return true;
}
