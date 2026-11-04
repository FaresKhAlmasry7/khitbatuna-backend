<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'], $config['db_port'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function session_start_secure(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('khitbatuna_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/','secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function csrf_token(): string {
    session_start_secure();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): void {
    session_start_secure();
    if (!$token || !hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
        http_response_code(403); exit('Ø§Ù„Ø·Ù„Ø¨ ØºÙŠØ± ØµØ§Ù„Ø­. Ø­Ø¯Ù‘Ø« Ø§Ù„ØµÙØ­Ø© ÙˆØ­Ø§ÙˆÙ„ Ù…Ø¬Ø¯Ø¯Ù‹Ø§.');
    }
}

function require_admin(): void {
    session_start_secure();

    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php', true, 302); exit;
    }
}

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function post_only(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'error'=>'Ø§Ù„Ø·Ù„Ø¨ ØºÙŠØ± Ù…Ø³Ù…ÙˆØ­'],405);
}

function client_key(string $purpose): string {
    global $config;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash_hmac('sha256', $purpose . '|' . $ip, $config['app_key']);
}

function throttle(string $purpose, int $max, int $minutes): void {
    $pdo = db();
    $key = client_key($purpose);
    $pdo->prepare('DELETE FROM request_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)')->execute();
    $query = $pdo->prepare("SELECT COUNT(*) FROM request_limits WHERE request_key = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $minutes MINUTE)");
    $query->execute([$key]);
    if ((int)$query->fetchColumn() >= $max) json_response(['ok'=>false,'error'=>'Ø£Ø±Ø³Ù„Øª Ø·Ù„Ø¨Ø§Øª ÙƒØ«ÙŠØ±Ø©. Ø¬Ø±Ù‘Ø¨ Ù„Ø§Ø­Ù‚Ù‹Ø§.'],429);
    $pdo->prepare('INSERT INTO request_limits (request_key) VALUES (?)')->execute([$key]);
}

function validate_text(mixed $value, int $min, int $max): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) return null;
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
    return $length >= $min && $length <= $max ? $value : null;
}

function security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}
