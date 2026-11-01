<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/helpers.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/index.php'), PHP_URL_PATH);
$requestPath = str_replace('\\', '/', rawurldecode((string) $requestPath));
$basePath = APP_BASE_URL;
if ($basePath !== '' && str_starts_with($requestPath, $basePath . '/')) {
    $requestPath = substr($requestPath, strlen($basePath));
} elseif ($basePath !== '' && $requestPath === $basePath) {
    $requestPath = '/';
}
$script = ltrim($requestPath, '/');
$script = $script === '' ? 'index.php' : $script;
$routes = [
    'index.php' => 'frontend/index.html',
    'privacy-policy.php' => 'frontend/privacy-policy.html',
    'terms.php' => 'frontend/terms.html',
    'login.php' => 'frontend/login.html',
    'register.php' => 'frontend/register.html',
    'student/dashboard.php' => 'frontend/student/dashboard.html',
    'student/complaints.php' => 'frontend/student/complaints.html',
    'student/create-complaint.php' => 'frontend/student/create-complaint.html',
    'student/complaint-details.php' => 'frontend/student/complaint-details.html',
    'student/notifications.php' => 'frontend/student/notifications.html',
    'student/profile.php' => 'frontend/student/profile.html',
    'admin/dashboard.php' => 'frontend/admin/dashboard.html',
    'admin/complaints.php' => 'frontend/admin/complaints.html',
    'admin/complaint-details.php' => 'frontend/admin/complaint-details.html',
    'admin/categories.php' => 'frontend/admin/categories.html',
    'admin/departments.php' => 'frontend/admin/departments.html',
    'admin/users.php' => 'frontend/admin/users.html',
    'admin/reports.php' => 'frontend/admin/reports.html',
];

$route = $script;
if (!isset($routes[$route])) {
    http_response_code(404);
    $notFoundPage = file_get_contents(__DIR__ . '/frontend/404.html');
    if ($notFoundPage === false) {
        throw new RuntimeException('The not-found page could not be loaded.');
    }
    echo str_replace(
        ['__APP_HOME__', '__APP_CSS__', '__APP_FAVICON__', '__APP_JS__'],
        [
            app_url('frontend/index.html'),
            app_url('frontend/assets/css/styles.css'),
            app_url('favicon.svg'),
            app_url('frontend/assets/js/app.js'),
        ],
        $notFoundPage
    );
    exit;
}
$destination = $routes[$route];
$query = [];
if (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    $query['id'] = (int) $_GET['id'];
}
if (in_array($destination, ['frontend/login.html', 'frontend/register.html'], true)
    && in_array($_GET['role'] ?? '', ['student', 'admin'], true)) {
    $query['role'] = $_GET['role'];
}
$url = app_url($destination);
if ($query) {
    $url .= '?' . http_build_query($query);
}
header('Location: ' . $url, true, 302);
exit;
