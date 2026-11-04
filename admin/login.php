<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
security_headers(); session_start_secure();
if (!empty($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $user = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    try {
        $pdo = db(); $ipHash = client_key('login');
        $pdo->prepare('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)')->execute();
        $q = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND created_at >= DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $q->execute([$ipHash]);
        if ((int)$q->fetchColumn() >= 8) {
            $error = 'محاولات كثيرة. حاول بعد 15 دقيقة.';
        } else {
            $q = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username=? LIMIT 1');
            $q->execute([$user]); $admin = $q->fetch();
            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_name'] = $admin['username'];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash=?')->execute([$ipHash]);
                header('Location: index.php'); exit;
            }
            $pdo->prepare('INSERT INTO login_attempts(ip_hash) VALUES (?)')->execute([$ipHash]);
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحين.';
        }
    } catch (PDOException $e) {
        error_log($e->getMessage()); $error = 'تعذّر الاتصال بقاعدة البيانات. راجع الإعدادات.';
    }
}
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>دخول الإدارة | حكايتنا</title><link rel="stylesheet" href="admin.css"></head>
<body class="login-body"><main class="login-card"><div class="monogram">♡</div><p class="eyebrow">حكايتنا • لوحة خاصة</p><h1>دخول الإدارة</h1><p class="muted">هذه الصفحة مخصّصة لصاحب الدعوة فقط.</p>
<?php if ($error): ?><p class="alert" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><label>اسم المستخدم<input name="username" maxlength="80" autocomplete="username" required></label><label>كلمة المرور<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">دخول لوحة الإدارة</button></form><a class="backlink" href="../index.html">العودة إلى الدعوة</a></main></body></html>
