<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
security_headers(); require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
csrf_check($_POST['csrf'] ?? null);
$kind = $_POST['kind'] ?? '';
if (!in_array($kind,['wishes','rsvps'],true)) { http_response_code(400); exit; }
if ($kind === 'rsvps') {
    $q = db()->query('SELECT guest_name, attending, created_at FROM rsvps ORDER BY id DESC');
    $headers = ['اسم الضيف','الحضور','تاريخ الإرسال'];
} else {
    $q = db()->query('SELECT guest_name, message, status, created_at FROM wishes ORDER BY id DESC');
    $headers = ['اسم الضيف','التهنئة','الحالة','تاريخ الإرسال'];
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="khitbatuna-'.$kind.'.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output','w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out,$headers);
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    if ($kind === 'rsvps') $row['attending'] = $row['attending'] === 'yes' ? 'سأحضر' : 'أعتذر';
    else $row['status'] = ['approved'=>'موافق عليها','pending'=>'بانتظار المراجعة','hidden'=>'مخفية'][$row['status']] ?? $row['status'];
    foreach ($row as &$cell) {
        $cell = (string)$cell;
        // منع تفسير النصوص التي يبدأ بها الضيف كصيغ في Excel.
        if (preg_match('/^[\s]*[=+\-@\t\r]/u', $cell)) $cell = "'" . $cell;
    }
    unset($cell);
    fputcsv($out, array_values($row));
}
fclose($out); exit;
