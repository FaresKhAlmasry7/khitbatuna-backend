<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
security_headers(); require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('غير مسموح'); }
csrf_check($_POST['csrf'] ?? null);
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$type = $_POST['type'] ?? '';
$action = $_POST['action'] ?? '';
if (!in_array($type, ['wish','rsvp'],true)) { http_response_code(400); exit('طلب غير صالح'); }
if ($action !== 'delete_all' && (!$id || $id < 1)) { http_response_code(400); exit('طلب غير صالح'); }
try {
    if ($action === 'delete_all') {
        db()->exec($type === 'wish' ? 'DELETE FROM wishes' : 'DELETE FROM rsvps');
        header('Location: index.php?tab=' . ($type === 'wish' ? 'wishes' : 'rsvps'), true, 303);
        exit;
    }
    if ($type === 'wish') {
        if (in_array($action, ['approved','pending','hidden'], true)) {
            $q = db()->prepare('UPDATE wishes SET status=? WHERE id=?');
            $q->execute([$action,$id]);
        } elseif ($action === 'delete') {
            $q = db()->prepare('DELETE FROM wishes WHERE id=?'); $q->execute([$id]);
        } else { http_response_code(400); exit('إجراء غير صالح'); }
    } else {
        if ($action !== 'delete') { http_response_code(400); exit('إجراء غير صالح'); }
        $q = db()->prepare('DELETE FROM rsvps WHERE id=?'); $q->execute([$id]);
    }
} catch (PDOException $e) { error_log($e->getMessage()); http_response_code(503); exit('تعذّر حفظ التغيير'); }
$tab = ($type === 'wish') ? 'wishes' : 'rsvps';
header('Location: index.php?tab='.$tab, true, 303); exit;
