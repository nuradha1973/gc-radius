<?php
// includes/cleanup_sessions.php
// Auto-cleanup zombie sessions - dipanggil dari dashboard
//
// Logic: Tutup session lama HANYA jika user+IP SAMA (reconnect device yang sama).
// Jika user sama tapi IP BEDA, pertahankan (2 device berbeda = 2 HP).

if (!isset($pdo) || $pdo === null) return;
if (!isset($_SESSION['admin_user'])) return;

$cleaned = 0;

try {
    // Tutup session duplikat: username + IP sama, ada >1 session aktif
    // → ini reconnect device yang sama, session lama = zombie
    $stmt = $pdo->query("
        UPDATE radacct ra1
        JOIN (
            SELECT username, framedipaddress, MAX(radacctid) AS max_id
            FROM radacct
            WHERE acctstoptime IS NULL
            GROUP BY username, framedipaddress
            HAVING COUNT(*) > 1
        ) ra2 ON ra1.username = ra2.username 
              AND ra1.framedipaddress = ra2.framedipaddress
        SET ra1.acctstoptime = NOW(),
            ra1.acctterminatecause = 'Session Duplicate (Auto-Cleanup)'
        WHERE ra1.acctstoptime IS NULL
        AND ra1.radacctid < ra2.max_id
    ");
    $cleaned = $stmt->rowCount();

} catch (Exception $e) {
    $cleaned = 0;
}
