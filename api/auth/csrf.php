<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/helpers.php';

json_response(true, 'CSRF token loaded.', ['csrf_token' => csrf_token()]);
