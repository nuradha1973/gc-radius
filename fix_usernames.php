<?php
// fix_usernames.php - Normalisasi case username di radacct & radpostauth
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['admin_user'])) {
    header("Location: login.php");
    exit;
}

$results = [];
$fixed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_fix'])) {
    // 1. Ambil semua canonical username dari radcheck
    $users = $pdo->query("SELECT username FROM radcheck WHERE attribute = 'Cleartext-Password' GROUP BY username")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($users as $canonical) {
        $canonicalLower = strtolower($canonical);

        // 2. Update radacct: username yang LOWER() sama tapi casing berbeda
        $stmt = $pdo->prepare("
            UPDATE radacct 
            SET username = ? 
            WHERE LOWER(username) = ? AND username COLLATE utf8mb4_bin != ?
        ");
        $stmt->execute([$canonical, $canonicalLower, $canonical]);
        $countAcct = $stmt->rowCount();
        $fixed += $countAcct;

        // 3. Update radpostauth
        $stmt = $pdo->prepare("
            UPDATE radpostauth 
            SET username = ? 
            WHERE LOWER(username) = ? AND username COLLATE utf8mb4_bin != ?
        ");
        $stmt->execute([$canonical, $canonicalLower, $canonical]);
        $countAuth = $stmt->rowCount();
        $fixed += $countAuth;

        if ($countAcct > 0 || $countAuth > 0) {
            $results[] = [
                'canonical' => $canonical,
                'radacct'   => $countAcct,
                'radpostauth' => $countAuth,
            ];
        }
    }
}

require_once 'includes/header.php';
?>

<h2>Fix Username Casing</h2>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_fix'])): ?>
    <?php if ($fixed > 0): ?>
        <div class="flash-msg" style="background:rgba(34,197,94,0.2);border:1px solid #22c55e;color:#166534;">
            <i data-lucide="check-circle" class="flash-icon"></i>
            <?= $fixed ?> row berhasil dinormalisasi.
        </div>

        <div class="glass panel-sm" style="margin-bottom:10px;">
            <h3>Detail Perubahan</h3>
            <table>
                <thead>
                    <tr>
                        <th>Username Canonical</th>
                        <th>radacct (diperbaiki)</th>
                        <th>radpostauth (diperbaiki)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['canonical']) ?></strong></td>
                        <td><?= $r['radacct'] ?> row</td>
                        <td><?= $r['radpostauth'] ?> row</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="index.php" class="btn">← Kembali ke Dashboard</a>

    <?php else: ?>
        <div class="flash-msg" style="background:rgba(59,130,246,0.12);border:1px solid #93c5fd;color:#1e40af;">
            <i data-lucide="info" class="flash-icon"></i>
            Tidak ada username yang perlu dinormalisasi. Semua sudah rapi! ✅
        </div>
        <a href="index.php" class="btn">← Kembali ke Dashboard</a>
    <?php endif; ?>

<?php else: ?>
    <div class="glass panel-sm" style="max-width:550px;">
        <p style="margin:0 0 12px 0;font-size:0.78rem;">
            Script ini akan menormalisasi semua username di tabel <strong>radacct</strong> dan <strong>radpostauth</strong>
            agar casing-nya mengikuti canonical username di <strong>radcheck</strong>.
        </p>

        <p style="margin:0 0 16px 0;font-size:0.73rem;color:#888;">
            Contoh: Jika di radcheck ada <strong>Budi</strong>, maka semua "BUDI", "budi", "BuDi" 
            di radacct & radpostauth akan diubah menjadi <strong>Budi</strong>.
        </p>

        <form method="POST">
            <button type="submit" name="run_fix" class="btn" style="background:#10b981;">
                <i data-lucide="wrench" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i>
                Normalisasi Username
            </button>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
