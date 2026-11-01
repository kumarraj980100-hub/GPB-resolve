<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, 405);
}

$user = require_login();
if (($user['role'] ?? '') !== 'student') {
    json_response(false, 'Student access required.', null, 403);
}
validate_csrf();
enforce_rate_limit('complaint-ip', $_SERVER['REMOTE_ADDR'] ?? '', 240, 3600);
enforce_rate_limit('complaint-student', (string) $user['id'], 30, 3600);

$lastPosted = $_SESSION['last_complaint_submit'] ?? 0;
if ((time() - (int) $lastPosted) < 10) {
    json_response(false, 'Please wait a moment before submitting another complaint.', null, 429);
}

$title = trim((string) ($_POST['title'] ?? ''));
$categoryId = (int) ($_POST['category_id'] ?? 0);
$description = trim((string) ($_POST['description'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$priority = trim((string) ($_POST['priority'] ?? 'Medium'));
$allowedPriorities = ['Low', 'Medium', 'High', 'Urgent'];
if (!in_array($priority, $allowedPriorities, true)) {
    $priority = 'Medium';
}

if ($title === '' || $description === '' || $location === '' || $categoryId <= 0) {
    json_response(false, 'Title, category, description, location, and priority are required.', null, 422);
}
if (mb_strlen($description) > 2000) {
    json_response(false, 'Description is too long.', null, 422);
}

$pdo = get_db();
$pdo->beginTransaction();
$ref = generate_reference_number();
$stmt = $pdo->prepare('INSERT INTO complaints (reference_number, user_id, category_id, title, description, location, priority, status, created_at, updated_at) VALUES (:reference_number, :user_id, :category_id, :title, :description, :location, :priority, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
$stmt->execute([
    'reference_number' => $ref,
    'user_id' => (int) $user['id'],
    'category_id' => $categoryId,
    'title' => $title,
    'description' => $description,
    'location' => $location,
    'priority' => $priority,
    'status' => 'Submitted',
]);
$complaintId = (int) $pdo->lastInsertId();

$updateStmt = $pdo->prepare('INSERT INTO complaint_updates (complaint_id, actor_user_id, previous_status, new_status, message, visibility, created_at) VALUES (:complaint_id, :actor_user_id, :previous_status, :new_status, :message, :visibility, CURRENT_TIMESTAMP)');
$updateStmt->execute([
    'complaint_id' => $complaintId,
    'actor_user_id' => (int) $user['id'],
    'previous_status' => null,
    'new_status' => 'Submitted',
    'message' => 'Complaint submitted by the student.',
    'visibility' => 'student',
]);

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = validate_image_upload($_FILES['image']);
    if (!$file['valid']) {
        $pdo->rollBack();
        json_response(false, implode(' ', $file['errors']), null, 422);
    }
    if ($file['filename']) {
        $target = UPLOAD_PATH . '/' . $file['filename'];
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $pdo->rollBack();
            json_response(false, 'Failed to store uploaded image.', null, 500);
        }
        $attach = $pdo->prepare('INSERT INTO attachments (complaint_id, uploaded_by, stored_filename, original_filename, mime_type, file_size, created_at) VALUES (:complaint_id, :uploaded_by, :stored_filename, :original_filename, :mime_type, :file_size, CURRENT_TIMESTAMP)');
        $attach->execute([
            'complaint_id' => $complaintId,
            'uploaded_by' => (int) $user['id'],
            'stored_filename' => $file['filename'],
            'original_filename' => $_FILES['image']['name'],
            'mime_type' => $file['mime'] ?? 'image/jpeg',
            'file_size' => (int) $_FILES['image']['size'],
        ]);
    }
}

$notification = $pdo->prepare('INSERT INTO notifications (user_id, title, message, related_complaint_id, created_at) VALUES (:user_id, :title, :message, :related_complaint_id, CURRENT_TIMESTAMP)');
$notification->execute([
    'user_id' => (int) $user['id'],
    'title' => 'Complaint submitted',
    'message' => 'Your complaint has been submitted successfully and is now under review.',
    'related_complaint_id' => $complaintId,
]);

$_SESSION['last_complaint_submit'] = time();
$pdo->commit();
json_response(true, 'Complaint submitted successfully.', [
    'complaint_id' => $complaintId,
    'reference_number' => $ref,
    'redirect' => app_url('student/complaint-details.php?id=' . $complaintId),
], 201);
