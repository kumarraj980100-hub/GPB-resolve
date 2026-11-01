<?php
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitize_string(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

function safe_string(string $value, int $maxLength = 255): string
{
    $value = trim($value);
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function limit_text_length(string $text, int $maxLength): string
{
    return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength) : $text;
}

function validate_image_upload(array $file): array
{
    $errors = [];
    if (!isset($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'filename' => null, 'errors' => $errors];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'filename' => null, 'errors' => ['Image upload failed.']];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $errors[] = 'Image exceeds the 2MB limit.';
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowed, true)) {
        $errors[] = 'Only JPG, PNG, and WebP images are allowed.';
    }

    if (preg_match('/\.(php|phtml|phar|exe|js|html|svg|xml)$/i', $file['name'])) {
        $errors[] = 'Executable or unsafe file names are not allowed.';
    }

    $name = bin2hex(random_bytes(12)) . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '', basename($file['name']));
    if ($errors) {
        return ['valid' => false, 'filename' => null, 'errors' => $errors];
    }

    return ['valid' => true, 'filename' => $name, 'mime' => $mime, 'errors' => []];
}
