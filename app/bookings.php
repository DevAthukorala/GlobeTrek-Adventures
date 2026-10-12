<?php
require_once __DIR__ . '/bootstrap.php';

function create_booking(int $userId, int $packageId, string $date, int $travellers, string $token): int {
    travel_date($date);
    if ($travellers < 1 || $travellers > 100 || !preg_match('/^[a-f0-9]{64}$/', $token)) throw new InvalidArgumentException('Invalid booking details.');
    $db = db(); $db->beginTransaction();
    try {
        // Lock the package to serialize capacity checks for concurrent requests.
        $p = sql('SELECT * FROM tour_packages WHERE id=? FOR UPDATE', [$packageId])->fetch();
        if (!$p || !$p['active']) throw new InvalidArgumentException('This adventure is no longer available.');
        $existing = sql('SELECT id FROM bookings WHERE request_token=? AND user_id=?', [$token,$userId])->fetchColumn();
        if ($existing) { $db->commit(); return (int)$existing; }
        $reserved = (int)sql("SELECT COALESCE(SUM(travellers),0) FROM bookings WHERE package_id=? AND travel_date=? AND status IN ('pending','confirmed','completed')", [$packageId,$date])->fetchColumn();
        if ($reserved + $travellers > (int)$p['capacity']) throw new InvalidArgumentException('There are not enough spaces on that date. Try fewer travellers or a different date.');
        sql('INSERT INTO bookings (user_id,package_id,package_title,travel_date,travellers,unit_price_cents,total_cents,request_token) VALUES (?,?,?,?,?,?,?,?)', [$userId,$packageId,$p['title'],$date,$travellers,$p['price_cents'],(int)$p['price_cents']*$travellers,$token]);
        $id = (int)$db->lastInsertId(); $db->commit(); return $id;
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
function change_booking(int $id, string $next): void {
    $db = db(); $db->beginTransaction();
    try {
        $b = sql('SELECT * FROM bookings WHERE id=? FOR UPDATE', [$id])->fetch();
        $allowed = ['pending'=>['confirmed','cancelled'], 'confirmed'=>['completed','cancelled'], 'completed'=>[], 'cancelled'=>[]];
        if (!$b || !in_array($next, $allowed[$b['status']], true)) throw new InvalidArgumentException('That booking status transition is not allowed. Refresh and try again.');
        if ($next === 'completed' && $b['travel_date'] > date('Y-m-d')) throw new InvalidArgumentException('A future trip cannot be marked completed.');
        sql('UPDATE bookings SET status=? WHERE id=?', [$next,$id]); $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
