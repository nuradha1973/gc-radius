<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';

// Auth check
if (!isset($_SESSION['admin_user'])) {
    header("Location: login.php");
    exit;
}

$settingsFile = __DIR__ . '/config/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true);
$dbMsg = '';
$dbSaveMsg = '';

// Save DB Config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_db'])) {
    $settings['db_host'] = $_POST['db_host'];
    $settings['db_name'] = $_POST['db_name'];
    $settings['db_user'] = $_POST['db_user'];
    if (!empty($_POST['db_pass'])) {
        $settings['db_pass'] = $_POST['db_pass'];
    }
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    $dbSaveMsg = "Database config saved! Changes apply on next page load.";
}

// Test DB Connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_db'])) {
    try {
        $pdo->query("SELECT 1");
        $start = microtime(true);
        $pdo->query("SELECT 1");
        $latency = round((microtime(true) - $start) * 1000, 1);
        $dbMsg = "<span style='color:#16a34a;'><i data-lucide='check-circle' style='width:14px;height:14px;vertical-align:middle;'></i> Koneksi berhasil!</span> Latency: {$latency} ms";
    } catch (Exception $e) {
        $dbMsg = "<span style='color:#ef4444;'><i data-lucide='x-circle' style='width:14px;height:14px;vertical-align:middle;'></i> Gagal: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
}

// Gather DB info
$dbVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
$dbSize = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();

$nasMsg = '';

// Handle Add NAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_nas'])) {
    $stmt = $pdo->prepare("INSERT INTO nas (nasname, shortname, type, secret, ports, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['nasname'],
        $_POST['shortname'],
        $_POST['type'] ?: 'other',
        $_POST['secret'],
        (int)$_POST['ports'] ?: null,
        $_POST['description'] ?: ''
    ]);
    $nasMsg = '<div class="flash-msg" style="background:rgba(34,197,94,0.2);border:1px solid #22c55e;color:#166534;"><i data-lucide="check-circle" class="flash-icon"></i> NAS berhasil ditambahkan.</div>';
}

// Handle Edit NAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_nas'])) {
    $stmt = $pdo->prepare("UPDATE nas SET nasname=?, shortname=?, type=?, secret=?, ports=?, description=? WHERE id=?");
    $stmt->execute([
        $_POST['nasname'],
        $_POST['shortname'],
        $_POST['type'] ?: 'other',
        $_POST['secret'],
        (int)$_POST['ports'] ?: null,
        $_POST['description'] ?: '',
        (int)$_POST['nas_id']
    ]);
    $nasMsg = '<div class="flash-msg" style="background:rgba(34,197,94,0.2);border:1px solid #22c55e;color:#166534;"><i data-lucide="check-circle" class="flash-icon"></i> NAS berhasil diupdate.</div>';
}

// Handle Delete NAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_nas'])) {
    $stmt = $pdo->prepare("DELETE FROM nas WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_nas']]);
    $nasMsg = '<div class="flash-msg" style="background:rgba(34,197,94,0.2);border:1px solid #22c55e;color:#166534;"><i data-lucide="check-circle" class="flash-icon"></i> NAS berhasil dihapus.</div>';
}

$adminPwMsg = '';
$adminPwMsgType = 'success';

// Handle Change Admin Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_pw'])) {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $adminPwMsg = 'Semua field wajib diisi.';
        $adminPwMsgType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $adminPwMsg = 'Password baru minimal 6 karakter.';
        $adminPwMsgType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $adminPwMsg = 'Password baru dan konfirmasi tidak cocok.';
        $adminPwMsgType = 'error';
    } elseif (!password_verify($oldPassword, $settings['admin_password'])) {
        $adminPwMsg = 'Password lama salah.';
        $adminPwMsgType = 'error';
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $settings['admin_password'] = $hash;
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        $adminPwMsg = 'Password berhasil diubah!';
        $adminPwMsgType = 'success';
    }
}

$nasEdit = null;
if (isset($_GET['edit_nas'])) {
    $stmt = $pdo->prepare("SELECT * FROM nas WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_nas']]);
    $nasEdit = $stmt->fetch();
}

require_once 'includes/header.php';
?>

<h2>Settings</h2>

<?php if ($dbSaveMsg): ?>
<div class="flash-msg" style="background:rgba(34,197,94,0.2);border:1px solid #22c55e;color:#166534;">
    <i data-lucide="check-circle" class="flash-icon"></i> <?= htmlspecialchars($dbSaveMsg) ?>
</div>
<?php endif; ?>

<div class="settings-grid">

<div class="glass panel-sm">
    <h3><i data-lucide="database" style="width:17px;height:17px;vertical-align:middle;margin-right:5px;"></i> Database Connection</h3>
    <form method="POST">
        <div class="form-row" style="margin-bottom:4px;">
            <input type="text" name="db_host" placeholder="Host" value="<?= htmlspecialchars($settings['db_host'] ?? $host) ?>" required style="flex:1;">
            <input type="text" name="db_name" placeholder="Database" value="<?= htmlspecialchars($settings['db_name'] ?? $db) ?>" required style="flex:1;">
            <input type="text" name="db_user" placeholder="Username" value="<?= htmlspecialchars($settings['db_user'] ?? $user) ?>" required style="flex:1;">
            <div class="pw-wrapper" style="flex:1;">
                <input type="password" name="db_pass" placeholder="Password" style="flex:1;" autocomplete="off">
                <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
            </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-top:6px;">
            <button type="submit" name="save_db" style="font-size:0.7rem;padding:5px 12px;"><i data-lucide="save" style="width:13px;height:13px;vertical-align:middle;margin-right:3px;"></i> Save</button>
            <button type="submit" name="test_db" style="font-size:0.7rem;padding:5px 12px;background:#6b7280;"><i data-lucide="plug" style="width:13px;height:13px;vertical-align:middle;margin-right:3px;"></i> Test Connection</button>
            <?php if ($dbMsg): ?>
                <span style="font-size:0.68rem;"><?= $dbMsg ?></span>
            <?php endif; ?>
        </div>
    </form>
    <div class="db-info-row">
        <span>Version: <b style="color:#555;"><?= htmlspecialchars($dbVersion) ?></b></span>
        <span>Size: <b style="color:#555;"><?= htmlspecialchars($dbSize) ?> MB</b></span>
        <span>Charset: <b style="color:#555;"><?= htmlspecialchars($charset) ?></b></span>
    </div>
</div>

<!-- NAS Management -->
<?= $nasMsg ?>
<div class="glass panel-sm">
    <h3><i data-lucide="server" style="width:16px;height:16px;vertical-align:middle;margin-right:5px;"></i> NAS Devices</h3>

    <?php if ($nasEdit): ?>
    <form method="POST" class="form-row" style="margin-bottom:8px;">
        <input type="hidden" name="nas_id" value="<?= htmlspecialchars($nasEdit['id']) ?>">
        <input type="text" name="nasname" placeholder="NAS IP/Hostname" value="<?= htmlspecialchars($nasEdit['nasname']) ?>" required>
        <input type="text" name="shortname" placeholder="Short Name" value="<?= htmlspecialchars($nasEdit['shortname']) ?>" required>
        <input type="text" name="type" placeholder="Type" value="<?= htmlspecialchars($nasEdit['type']) ?>" style="min-width:80px;">
        <input type="text" name="secret" placeholder="Secret" value="<?= htmlspecialchars($nasEdit['secret']) ?>" required>
        <button type="submit" name="edit_nas">Update</button>
        <a href="settings.php" class="btn btn-danger">Cancel</a>
    </form>
    <?php else: ?>
    <form method="POST" class="form-row" style="margin-bottom:8px;">
        <input type="text" name="nasname" placeholder="NAS IP/Hostname" required>
        <input type="text" name="shortname" placeholder="Short Name" required>
        <input type="text" name="type" placeholder="Type" value="other" style="min-width:80px;">
        <input type="text" name="secret" placeholder="Secret" required>
        <button type="submit" name="add_nas">Add NAS</button>
    </form>
    <?php endif; ?>

    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>NAS Name</th>
                    <th>Short Name</th>
                    <th>Type</th>
                    <th>Secret</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $nasList = $pdo->query("SELECT id, nasname, shortname, type, secret FROM nas ORDER BY id");
                if ($nasList->rowCount() === 0) {
                    echo '<tr><td colspan="5" style="text-align:center;color:#999;padding:12px;">No NAS devices configured</td></tr>';
                }
                while ($nas = $nasList->fetch()) {
                    $enc = urlencode($nas['id']);
                    echo "<tr>
                            <td><strong>" . htmlspecialchars($nas['nasname']) . "</strong></td>
                            <td>" . htmlspecialchars($nas['shortname']) . "</td>
                            <td>" . htmlspecialchars($nas['type']) . "</td>
                            <td>" . htmlspecialchars($nas['secret']) . "</td>
                            <td class='action-links'>
                                <a href='?edit_nas={$enc}' title='Edit' class='action-icon'><i data-lucide='pencil'></i></a>
                                <form method='POST' style='display:inline;' onsubmit='event.preventDefault();'>
                                    <input type='hidden' name='delete_nas' value='" . htmlspecialchars($nas['id']) . "'>
                                    <button type='submit' title='Hapus' class='action-icon danger' onclick=\"showConfirm(event, '" . htmlspecialchars(addslashes($nas['shortname'])) . "', 'NAS')\"><i data-lucide='trash-2'></i></button>
                                </form>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Admin Account -->
<div class="glass panel-sm">
    <h3><i data-lucide="shield" style="width:17px;height:17px;vertical-align:middle;margin-right:5px;"></i> Admin Account</h3>

    <?php if ($adminPwMsg): ?>
    <div class="flash-msg" style="margin-bottom:10px;
        background: <?= $adminPwMsgType === 'success' ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)' ?>;
        border: 1px solid <?= $adminPwMsgType === 'success' ? '#22c55e' : '#ef4444' ?>;
        color: <?= $adminPwMsgType === 'success' ? '#166534' : '#991b1b' ?>;">
        <i data-lucide="<?= $adminPwMsgType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="flash-icon"></i>
        <?= htmlspecialchars($adminPwMsg) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <label for="old_password_pw">Password Lama</label>
        <div class="pw-wrapper">
            <input type="password" id="old_password_pw" name="old_password" placeholder="Masukkan password saat ini" required autocomplete="off">
            <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
        </div>

        <div style="display:flex;gap:8px;">
            <div style="flex:1;">
                <label for="new_password_pw">Password Baru</label>
                <div class="pw-wrapper">
                    <input type="password" id="new_password_pw" name="new_password" placeholder="Minimal 6 karakter" required autocomplete="off">
                    <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
                </div>
            </div>
            <div style="flex:1;">
                <label for="confirm_password_pw">Konfirmasi Password Baru</label>
                <div class="pw-wrapper">
                    <input type="password" id="confirm_password_pw" name="confirm_password" placeholder="Ulangi password baru" required autocomplete="off">
                    <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
                </div>
            </div>
        </div>

        <button type="submit" name="change_admin_pw" style="margin-top:10px;">
            <i data-lucide="key-round" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i>
            Ubah Password
        </button>
    </form>
</div>

</div><!-- end settings-grid -->

<?php require_once 'includes/footer.php'; ?>
