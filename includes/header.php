<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// --- Authentication Check ---
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$isLoggedIn = isset($_SESSION['admin_user']);

// Dashboard (index.php) dan login.php bisa diakses tanpa login
// Halaman lain hanya bisa diakses setelah login
$publicPages = ['index.php', 'login.php'];
if (!in_array($currentPage, $publicPages) && !$isLoggedIn) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit;
}

// Cek apakah admin perlu first-time setup
$dbSettingsFile = __DIR__ . '/../config/settings.json';
$needsSetup = false;
if (file_exists($dbSettingsFile)) {
    $settingsData = json_decode(file_get_contents($dbSettingsFile), true);
    $needsSetup = empty($settingsData['admin_user']);
}

// Determine base URL
$base = dirname($_SERVER['SCRIPT_NAME']);
if ($base == '/' || $base == '\\') $base = '';
$base_url = $base . '/';

// --- Handle DB config save from error page ---
$dbSettingsFile = __DIR__ . '/../config/settings.json';
$dbConfigSaved = false;

if ($pdo === null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_db_config'])) {
    $currentSettings = json_decode(file_get_contents($dbSettingsFile), true) ?: [];
    $currentSettings['db_host'] = $_POST['db_host'];
    $currentSettings['db_name'] = $_POST['db_name'];
    $currentSettings['db_user'] = $_POST['db_user'];
    if (!empty($_POST['db_pass'])) {
        $currentSettings['db_pass'] = $_POST['db_pass'];
    }
    file_put_contents($dbSettingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT));
    $dbConfigSaved = true;
}

// --- Handle test connection from error page ---
$dbTestResult = '';
if ($pdo === null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_db_config'])) {
    try {
        $testHost = $_POST['db_host'];
        $testDb   = $_POST['db_name'];
        $testUser = $_POST['db_user'];
        $testPass = $_POST['db_pass'] ?: $pass;
        $testDsn  = "mysql:host=$testHost;dbname=$testDb;charset=utf8mb4";
        $testPdo  = new PDO($testDsn, $testUser, $testPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $start = microtime(true);
        $testPdo->query("SELECT 1");
        $latency = round((microtime(true) - $start) * 1000, 1);
        $dbTestResult = "<span style='color:#16a34a;'><i data-lucide='check-circle' style='width:14px;height:14px;vertical-align:middle;'></i> Koneksi berhasil!</span> Latency: {$latency} ms";
    } catch (Exception $e) {
        $dbTestResult = "<span style='color:#ef4444;'><i data-lucide='x-circle' style='width:14px;height:14px;vertical-align:middle;'></i> Gagal: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GC Radius - Mikrotik Hotspot Manager</title>
    <link rel="icon" type="image/png" href="<?= $base_url ?>assets/logo.png">

    <!-- PWA -->
    <link rel="manifest" href="<?= $base_url ?>manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GC Radius">
    <link rel="apple-touch-icon" href="<?= $base_url ?>assets/logo.png">

    <link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css?v=26">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= $base_url ?>sw.js').then(function(reg) {
                    console.log('SW registered:', reg.scope);
                }).catch(function(err) {
                    console.log('SW failed:', err);
                });
            });
        }
    </script>
</head>
<body>

<?php if ($pdo === null): ?>
<!-- ===== DB ERROR PAGE ===== -->
<div style="display:flex;justify-content:center;align-items:center;min-height:100vh;width:100%;padding:20px;">
    <div class="glass" style="max-width:560px;width:100%;padding:24px;">

        <div style="text-align:center;margin-bottom:16px;">
            <div style="font-size:2.5rem;margin-bottom:6px;">⚠️</div>
            <h2 style="margin:0 0 4px 0;color:#dc2626;">Database Connection Error</h2>
            <p style="margin:0;font-size:0.72rem;color:#888;">GC Radius tidak dapat terhubung ke database.</p>
        </div>

        <?php if ($dbConfigSaved): ?>
        <div style="background:rgba(34,197,94,0.2);border:1px solid #22c55e;color:#166534;padding:8px 12px;border-radius:5px;margin-bottom:12px;font-size:0.72rem;">
            <i data-lucide="check-circle" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i> Konfigurasi tersimpan. <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="color:#166534;font-weight:600;">Muat ulang</a> untuk mencoba kembali.
        </div>
        <?php endif; ?>

        <div style="background:rgba(239,68,68,0.12);border:1px solid #fca5a5;color:#991b1b;padding:8px 12px;border-radius:5px;margin-bottom:16px;font-size:0.72rem;word-break:break-all;">
            <strong>Error:</strong> <?= htmlspecialchars($dbError ?? 'Unknown error') ?>
        </div>

        <div style="background:rgba(59,130,246,0.08);border:1px solid #93c5fd;border-radius:5px;padding:12px;margin-bottom:16px;">
            <h4 style="margin:0 0 8px 0;font-size:0.75rem;color:#1e40af;"><i data-lucide="info" style="width:14px;height:14px;vertical-align:middle;margin-right:3px;"></i> Current Configuration</h4>
            <div style="font-size:0.72rem;display:grid;grid-template-columns:auto 1fr;gap:3px 12px;">
                <span style="color:#555;">Host:</span>
                <span style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($host) ?></span>
                <span style="color:#555;">Database:</span>
                <span style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($db) ?></span>
                <span style="color:#555;">User:</span>
                <span style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($user) ?></span>
                <span style="color:#555;">Password:</span>
                <span style="font-family:monospace;font-weight:600;"><?= $pass ? '********' : '(empty)' ?></span>
            </div>
        </div>

        <form method="POST">
            <h4 style="margin:0 0 8px 0;font-size:0.75rem;"><i data-lucide="database" style="width:14px;height:14px;vertical-align:middle;margin-right:3px;"></i> Ubah Konfigurasi Database</h4>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <div style="display:flex;gap:6px;">
                    <input type="text" name="db_host" placeholder="Host" value="<?= htmlspecialchars($host) ?>" required style="flex:1;font-size:0.72rem;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-family:monospace;">
                    <input type="text" name="db_name" placeholder="Database" value="<?= htmlspecialchars($db) ?>" required style="flex:1;font-size:0.72rem;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-family:monospace;">
                </div>
                <div style="display:flex;gap:6px;">
                    <input type="text" name="db_user" placeholder="Username" value="<?= htmlspecialchars($user) ?>" required style="flex:1;font-size:0.72rem;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-family:monospace;">
                    <div class="pw-wrapper" style="flex:1;">
                        <input type="password" name="db_pass" placeholder="Password (isi jika diganti)" style="flex:1;font-size:0.72rem;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-family:monospace;" autocomplete="off">
                        <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:6px;margin-top:10px;">
                <button type="submit" name="save_db_config" style="font-size:0.72rem;padding:6px 14px;background:var(--primary);color:#fff;border:none;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:4px;">
                    <i data-lucide="save" style="width:13px;height:13px;"></i> Simpan
                </button>
                <button type="submit" name="test_db_config" style="font-size:0.72rem;padding:6px 14px;background:#6b7280;color:#fff;border:none;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:4px;">
                    <i data-lucide="plug" style="width:13px;height:13px;"></i> Test Koneksi
                </button>
                <?php if ($dbTestResult): ?>
                    <span style="font-size:0.68rem;display:flex;align-items:center;"><?= $dbTestResult ?></span>
                <?php endif; ?>
            </div>
        </form>

        <div style="text-align:center;margin-top:16px;padding-top:12px;border-top:1px solid #eee;font-size:0.65rem;color:#999;">
            GC Radius &copy; <?= date('Y') ?> — Pastikan MySQL server aktif di <strong><?= htmlspecialchars($host) ?></strong>
        </div>
    </div>
</div>
<script>lucide.createIcons();

function togglePassword(btn) {
    var input = btn.parentElement.querySelector('input');
    var eye = btn.querySelector('.pw-eye');
    var eyeOff = btn.querySelector('.pw-eye-off');
    if (input.type === 'password') {
        input.type = 'text';
        eye.style.display = 'none';
        eyeOff.style.display = '';
    } else {
        input.type = 'password';
        eye.style.display = '';
        eyeOff.style.display = 'none';
    }
}
</script>
</body>
</html>
<?php
exit;
endif; ?>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="sidebar glass" id="sidebar">
    <button class="sidebar-close" onclick="closeSidebar()" aria-label="Close menu">
        <i data-lucide="x"></i>
    </button>
    <div class="sidebar-brand">
        <img src="<?= $base_url ?>assets/gclogo.png" alt="GC Radius" class="logo" onerror="this.style.display='none'">
        <h2>GC Radius</h2>
    </div>
    <a href="<?= $base_url ?>index.php" onclick="closeSidebar()"><i data-lucide="layout-dashboard" class="nav-icon"></i> Dashboard</a>
    <?php if ($isLoggedIn): ?>
    <a href="<?= $base_url ?>users.php" onclick="closeSidebar()"><i data-lucide="users" class="nav-icon"></i> Users</a>
    <a href="<?= $base_url ?>profiles.php" onclick="closeSidebar()"><i data-lucide="layers" class="nav-icon"></i> Profiles</a>
    <a href="<?= $base_url ?>accounting.php" onclick="closeSidebar()"><i data-lucide="bar-chart-3" class="nav-icon"></i> Accounting</a>
    <a href="<?= $base_url ?>settings.php" onclick="closeSidebar()"><i data-lucide="settings" class="nav-icon"></i> Settings</a>
    <a href="<?= $base_url ?>backup.php" onclick="closeSidebar()"><i data-lucide="database-backup" class="nav-icon"></i> Backup &amp; Restore</a>
    <a href="<?= $base_url ?>logs.php" onclick="closeSidebar()"><i data-lucide="scroll-text" class="nav-icon"></i> System Logs</a>
    <?php else: ?>
    <a href="#" onclick="openLoginModal(); return false;" style="color: var(--primary); font-weight: 600;"><i data-lucide="log-in" class="nav-icon"></i> Login</a>
    <?php endif; ?>

    <?php if ($isLoggedIn): ?>
    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <i data-lucide="user-circle" style="width:18px;height:18px;stroke:#888;"></i>
            <span class="sidebar-username"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></span>
        </div>
        <a href="<?= $base_url ?>logout.php" class="logout-link">
            <i data-lucide="log-out" class="nav-icon" style="stroke:#ef4444;"></i> Logout
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Login / Setup Modal -->
<div class="modal-overlay" id="loginModal">
    <div class="modal-box login-modal-box">
        <?php if ($needsSetup): ?>
        <!-- ===== FIRST-TIME SETUP ===== -->
        <div class="modal-icon">
            <span style="font-size:2rem;">⚙️</span>
        </div>
        <h4>First-Time Setup</h4>
        <p style="margin:0 0 14px 0;font-size:0.72rem;color:#888;">Buat akun administrator pertama</p>
        <div id="loginError" style="display:none;"></div>
        <form id="setupForm" onsubmit="return false;">
            <input type="text" name="username" id="setupUsername" placeholder="Username" required autocomplete="username" style="width:100%;padding:9px 12px;margin-bottom:8px;border:1px solid rgba(0,0,0,0.12);border-radius:5px;font-size:0.8rem;font-family:inherit;">
            <div class="pw-wrapper" style="margin-bottom:8px;">
                <input type="password" name="password" id="setupPassword" placeholder="Password (min. 6 karakter)" required autocomplete="new-password" style="width:100%;padding:9px 12px;border:1px solid rgba(0,0,0,0.12);border-radius:5px;font-size:0.8rem;font-family:inherit;">
                <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
            </div>
            <div class="pw-wrapper" style="margin-bottom:12px;">
                <input type="password" name="confirm_password" id="setupConfirm" placeholder="Konfirmasi Password" required autocomplete="new-password" style="width:100%;padding:9px 12px;border:1px solid rgba(0,0,0,0.12);border-radius:5px;font-size:0.8rem;font-family:inherit;">
                <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
            </div>
            <div class="modal-actions" style="justify-content:stretch;">
                <button type="submit" id="setupSubmitBtn" onclick="submitSetup()" style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:5px;padding:8px;cursor:pointer;font-size:0.78rem;font-weight:600;">Buat Akun & Masuk</button>
            </div>
        </form>
        <?php else: ?>
        <!-- ===== LOGIN ===== -->
        <div class="modal-icon">
            <i data-lucide="log-in" style="width:36px;height:36px;stroke:var(--primary);"></i>
        </div>
        <h4>Login</h4>
        <p style="margin:0 0 14px 0;font-size:0.72rem;color:#888;">Masuk untuk mengakses semua menu</p>
        <div id="loginError" style="display:none;"></div>
        <form id="loginForm" onsubmit="return false;">
            <input type="text" name="username" id="modalUsername" placeholder="Username" required autocomplete="username" style="width:100%;padding:9px 12px;margin-bottom:8px;border:1px solid rgba(0,0,0,0.12);border-radius:5px;font-size:0.8rem;font-family:inherit;">
            <div class="pw-wrapper" style="margin-bottom:12px;">
                <input type="password" name="password" id="modalPassword" placeholder="Password" required autocomplete="current-password" style="width:100%;padding:9px 12px;border:1px solid rgba(0,0,0,0.12);border-radius:5px;font-size:0.8rem;font-family:inherit;">
                <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
            </div>
            <div class="modal-actions" style="justify-content:stretch;">
                <button type="button" class="modal-btn-cancel" onclick="closeLoginModal()" style="flex:1;">Batal</button>
                <button type="submit" id="loginSubmitBtn" onclick="submitLogin()" style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:5px;padding:8px;cursor:pointer;font-size:0.78rem;font-weight:600;">Masuk</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <div class="header glass">
        <div style="display:flex;align-items:center;gap:8px;">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
                <i data-lucide="menu"></i>
            </button>
            <img src="<?= $base_url ?>assets/logo.png" alt="GC Radius" class="logo-header" onerror="this.style.display='none'">
            <h1>GC Radius - GC Network Home Lab</h1>
        </div>
        <div style="font-size:0.7rem;color:#888;">FreeRadius Mikrotik Hotspot Manager</div>
    </div>

    <div class="content glass">
