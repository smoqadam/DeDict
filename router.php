<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== '/' && $path !== '/api') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    return;
}
require __DIR__ . '/lib.php';

$db = new SQLite3(__DIR__ . '/data/dedict.db');
$db->busyTimeout(3000);

$q = trim($_GET['q'] ?? '');

require __DIR__ . ($path === '/api' ? '/api.php' : '/index.php');
