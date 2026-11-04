<?php
declare(strict_types=1);
require dirname(__DIR__) . '/private/bootstrap.php';
security_headers();
post_only();
if (($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json' && !str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''),'application/json;')) {
    json_response(['ok'=>false,'error'=>'صيغة البيانات غير صحيحة'],415);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) json_response(['ok'=>false,'error'=>'حجم الطلب كبير'],413);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) json_response(['ok'=>false,'error'=>'طلب غير صالح'],400);
$type = $input['type'] ?? '';
$name = validate_text($input['name'] ?? null, 2, 80);
if (!$name) json_response(['ok'=>false,'error'=>'الاسم يجب أن يكون بين حرفين و80 حرفًا'],422);
try {
    if ($type === 'wish') {
        $message = validate_text($input['message'] ?? null, 2, 500);
        if (!$message) json_response(['ok'=>false,'error'=>'اكتب رسالة بين حرفين و500 حرف'],422);
        throttle('wish', 5, 60);
        $q = db()->prepare('INSERT INTO wishes (guest_name, message) VALUES (?, ?)');
        $q->execute([$name, $message]);
        json_response(['ok'=>true,'message'=>'وصلت تهنئتك وستظهر بعد مراجعتها ♡'],201);
    }
    if ($type === 'rsvp') {
        $attending = $input['attending'] ?? '';
        if (!in_array($attending, ['yes','no'], true)) json_response(['ok'=>false,'error'=>'اختر حالة الحضور'],422);
        throttle('rsvp', 5, 60);
        $q = db()->prepare('INSERT INTO rsvps (guest_name, attending) VALUES (?, ?)');
        $q->execute([$name, $attending]);
        json_response(['ok'=>true,'message'=>'تم تسجيل رد حضورك بنجاح ♡'],201);
    }
    json_response(['ok'=>false,'error'=>'نوع الطلب غير معروف'],400);
} catch (PDOException $e) {
    error_log($e->getMessage());
    json_response(['ok'=>false,'error'=>'تعذّر حفظ الرد في قاعدة البيانات. جرّب لاحقًا.'],503);
}
