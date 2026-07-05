<?php
// init.php — DB connection + shared site data.
// Credentials live ABOVE the webroot in rbh-db-config.php (boring name on
// purpose: Hostinger's malware scanner deletes files named secrets.php).
// local-config.php in the repo root (gitignored) is the local-dev fallback.

$above_webroot = dirname(__DIR__, 2);

$config_file = $above_webroot . '/rbh-db-config.php';
if (!is_file($config_file)) {
    $config_file = dirname(__DIR__) . '/local-config.php';
}

if (is_file($config_file)) {
    require_once $config_file;
}

if (!defined('RBH_DB_HOST')) {
    http_response_code(503);
    exit('The directory is temporarily unavailable. Please try again shortly. (ref C1)');
}

if (!class_exists('mysqli')) {
    error_log('[rbh] mysqli extension missing');
    http_response_code(503);
    exit('The directory is temporarily unavailable. Please try again shortly. (ref C0)');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(RBH_DB_HOST, RBH_DB_USER, RBH_DB_PASS, RBH_DB_NAME);

if ($conn->connect_error) {
    error_log('[rbh] DB connect failed: ' . $conn->connect_error);
    http_response_code(503);
    exit('The directory is temporarily unavailable. Please try again shortly. (ref C2)');
}

$conn->set_charset('utf8mb4');

// Single source of truth for categories: the submit form and the sidebar
// filter both render from this list.
$categories = [
    'Food & Dining',
    'Home Services',
    'Retail',
    'Automotive',
    'Consulting',
];
