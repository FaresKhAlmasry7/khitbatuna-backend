<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
security_headers(); require_admin();
try {
    $pdo = db();
    $counts = [
        'wishes' => (int)$pdo->query('SELECT COUNT(*) FROM wishes')->fetchColumn(),
        'pending' => (int)$pdo->query("SELECT COUNT(*) FROM wishes WHERE status='pending'")->fetchColumn(),
        'attending' => (int)$pdo->query("SELECT COUNT(*) FROM rsvps WHERE attending='yes'")->fetchColumn(),
        'declined' => (int)$pdo->query("SELECT COUNT(*) FROM rsvps WHERE attending='no'")->fetchColumn(),
    ];
    $wishes = $pdo->query('SELECT id, guest_name, message, status, created_at FROM wishes ORDER BY created_at DESC,id DESC LIMIT 500')->fetchAll();
    $rsvps = $pdo->query('SELECT id, guest_name, attending, created_at FROM rsvps ORDER BY created_at DESC,id DESC LIMIT 500')->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage()); http_response_code(503); exit('قاعدة البيانات غير متاحة. راجع إعدادات الاتصال والاستيراد.');
}
$tab = ($_GET['tab'] ?? 'wishes') === 'rsvps' ? 'rsvps' : 'wishes';
$csrf = csrf_token();
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>لوحة الإدارة | حكايتنا</title><link rel="stylesheet" href="admin.css"></head>
<body><div class="admin-shell">
<header class="admin-top"><div><span class="eyebrow">حكايتنا ♡</span><h1>لوحة إدارة الخطوبة</h1><p class="muted">أهلًا <?= h($_SESSION['admin_name'] ?? 'بالإدارة') ?>، هنا كل رسائل الضيوف وردود حضورهم.</p></div><div class="top-actions"><a class="outline" href="../index.html" target="_blank" rel="noopener">عرض الدعوة</a><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><button class="outline danger-outline">تسجيل الخروج</button></form></div></header>
<section class="stats"><article><span>كل التهاني</span><strong><?= $counts['wishes'] ?></strong></article><article><span>تنتظر الموافقة</span><strong><?= $counts['pending'] ?></strong></article><article><span>سيحضرون</span><strong><?= $counts['attending'] ?></strong></article><article><span>اعتذروا</span><strong><?= $counts['declined'] ?></strong></article></section>
<nav class="tabs"><a class="<?= $tab==='wishes'?'active':'' ?>" href="?tab=wishes">رسائل التهنئة</a><a class="<?= $tab==='rsvps'?'active':'' ?>" href="?tab=rsvps">تأكيد الحضور</a></nav>
<?php if ($tab==='wishes'): ?>
<div class="section-heading"><div><h2>التهاني الواردة</h2><p class="muted">الرسائل الجديدة لا تظهر في الدعوة إلا بعد موافقتك. تظهر آخر 500 رسالة هنا.</p></div><form method="post" action="export.php"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="kind" value="wishes"><button class="outline">تصدير كل التهاني CSV</button></form><form method="post" action="action.php" onsubmit="return confirm('هل أنت متأكد من حذف جميع التهاني نهائيًا؟ لا يمكن التراجع عن الحذف.')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="type" value="wish"><input type="hidden" name="action" value="delete_all"><button class="remove" type="submit">حذف جميع التهاني</button></form></div>
<div class="records">
<?php if (!$wishes): ?><p class="empty">لسه ما وصلتك تهاني. أول تهنئة رح تظهر هون ♡</p><?php endif; ?>
<?php foreach ($wishes as $wish): ?><article class="record"><div class="record-head"><strong><?= h($wish['guest_name']) ?></strong><span class="status <?= h($wish['status']) ?>"><?= h(['pending'=>'بانتظار الموافقة','approved'=>'منشورة','hidden'=>'مخفية'][$wish['status']] ?? '') ?></span></div><p class="message"><?= nl2br(h($wish['message'])) ?></p><time><?= h($wish['created_at']) ?></time><div class="record-actions">
<?php foreach (['approved'=>'نشر','hidden'=>'إخفاء','pending'=>'إعادة للمراجعة','delete'=>'حذف نهائي'] as $action=>$label): ?><form method="post" action="action.php" <?= $action==='delete'?'onsubmit="return confirm(\'حذف التهنئة نهائيًا؟\')"':'' ?>><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="type" value="wish"><input type="hidden" name="id" value="<?= (int)$wish['id'] ?>"><input type="hidden" name="action" value="<?= h($action) ?>"><button class="<?= $action==='delete'?'remove':'small' ?>"><?= h($label) ?></button></form><?php endforeach; ?>
</div></article><?php endforeach; ?></div>
<?php else: ?>
<div class="section-heading"><div><h2>ردود الحضور</h2><p class="muted">كل طلب منفصل كما أرسله الضيف. تظهر آخر 500 إجابة هنا.</p></div><form method="post" action="export.php"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="kind" value="rsvps"><button class="outline">تصدير كل الردود CSV</button></form><form method="post" action="action.php" onsubmit="return confirm('هل أنت متأكد من حذف جميع بيانات تأكيد الحضور نهائيًا؟ لا يمكن التراجع عن الحذف.')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="type" value="rsvp"><input type="hidden" name="action" value="delete_all"><button class="remove" type="submit">حذف جميع ردود الحضور</button></form></div>
<div class="records"><?php if (!$rsvps): ?><p class="empty">لسه ما وصلتك تأكيدات حضور.</p><?php endif; ?>
<?php foreach ($rsvps as $rsvp): ?><article class="record"><div class="record-head"><strong><?= h($rsvp['guest_name']) ?></strong><span class="status <?= $rsvp['attending']==='yes'?'approved':'hidden' ?>"><?= $rsvp['attending']==='yes'?'سأحضر':'أعتذر' ?></span></div><time><?= h($rsvp['created_at']) ?></time><div class="record-actions"><form method="post" action="action.php" onsubmit="return confirm('حذف رد الضيف نهائيًا؟')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="type" value="rsvp"><input type="hidden" name="id" value="<?= (int)$rsvp['id'] ?>"><input type="hidden" name="action" value="delete"><button class="remove">حذف الرد</button></form></div></article><?php endforeach; ?></div>
<?php endif; ?>
<footer class="admin-footer">حكايتنا ♡ • منطقة خاصة لا تظهر للمدعوين. بيانات التهاني والحضور محفوظة في قاعدة MySQL.</footer>
</div></body></html>
