<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
session_start_secure();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('غير مسموح'); }
csrf_check($_POST['csrf'] ?? null);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php'); exit;
