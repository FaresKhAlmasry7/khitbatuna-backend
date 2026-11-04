<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
security_headers();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['ok'=>false,'error'=>'غير مسموح'],405);
try {
    $items = db()->query("SELECT guest_name AS name, message AS text FROM wishes WHERE status = 'approved' ORDER BY created_at DESC, id DESC LIMIT 100")->fetchAll();
    json_response(['ok'=>true,'items'=>$items]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    json_response(['ok'=>false,'error'=>'تعذّر تحميل التهاني'],503);
}
