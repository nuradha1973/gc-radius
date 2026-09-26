<?php
// login.php - GC Radius Authentication
session_start();

$settingsFile = __DIR__ . '/config/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true);

$error = '';
$setupMode = empty($settings['admin_user']);

// Handle First-Time Setup (create admin account)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Password dan konfirmasi tidak cocok.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $settings['admin_user'] = $username;
        $settings['admin_password'] = $hash;
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

        $_SESSION['admin_user'] = $username;

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
            exit;
        }
        header("Location: index.php");
        exit;
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Jika dalam setup mode, arahkan ke setup form (jangan proses login)
    if (empty($settings['admin_user'])) {
        $error = 'Admin belum disetup. Silakan isi form First-Time Setup di bawah.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (empty($username) || empty($password)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Username dan password wajib diisi.']);
                exit;
            }
            $error = 'Username dan password wajib diisi.';
        } elseif ($username === $settings['admin_user'] && password_verify($password, $settings['admin_password'])) {
            $_SESSION['admin_user'] = $settings['admin_user'];

            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => $redirect]);
                exit;
            }
            header("Location: " . $redirect);
            exit;
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Username atau password salah.']);
                exit;
            }
            $error = 'Username atau password salah.';
        }
    }
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_user']) && !$setupMode) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GC Radius - Login</title>
    <link rel="stylesheet" href="assets/css/style.css?v=26">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
    .login-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        width: 100%;
        padding: 20px;
    }

    .login-card {
        width: 100%;
        max-width: 400px;
        padding: 28px;
    }

    .login-card h2 {
        text-align: center;
        margin: 0 0 4px 0;
        font-size: 1.2rem;
        color: var(--primary);
    }

    .login-card .subtitle {
        text-align: center;
        font-size: 0.72rem;
        color: #888;
        margin: 0 0 20px 0;
    }

    .login-card .brand-icon {
        display: flex;
        justify-content: center;
        margin-bottom: 12px;
    }

    .login-card .brand-icon svg {
        width: 48px;
        height: 48px;
        stroke: var(--primary);
    }

    .login-card label {
        display: block;
        margin-bottom: 3px;
        font-size: 0.73rem;
        color: #555;
        font-weight: 500;
    }

    .login-card input[type="text"],
    .login-card input[type="password"] {
        width: 100%;
        padding: 9px 12px;
        margin-bottom: 12px;
        border: 1px solid rgba(0,0,0,0.12);
        border-radius: 5px;
        background: rgba(255,255,255,0.8);
        font-size: 0.8rem;
        font-family: inherit;
        transition: border 0.2s ease, box-shadow 0.2s ease;
    }

    .login-card input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
    }

    .login-card .btn-login {
        width: 100%;
        padding: 10px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 5px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.1s ease;
        margin-top: 4px;
    }

    .login-card .btn-login:hover {
        background: var(--primary-hover);
    }

    .login-card .btn-login:active {
        transform: scale(0.98);
    }

    .login-error {
        background: rgba(239,68,68,0.12);
        border: 1px solid #fca5a5;
        color: #991b1b;
        padding: 8px 12px;
        border-radius: 5px;
        margin-bottom: 16px;
        font-size: 0.73rem;
        text-align: center;
    }

    .login-success {
        background: rgba(34,197,94,0.12);
        border: 1px solid #86efac;
        color: #166534;
        padding: 8px 12px;
        border-radius: 5px;
        margin-bottom: 16px;
        font-size: 0.73rem;
        text-align: center;
    }

    .login-footer {
        text-align: center;
        margin-top: 18px;
        font-size: 0.68rem;
        color: #aaa;
    }

    .setup-badge {
        display: inline-block;
        background: rgba(245,158,11,0.2);
        color: #92400e;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-bottom: 12px;
    }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card glass">
        <div class="brand-icon">
            <i data-lucide="wifi" style="width:48px;height:48px;stroke:var(--primary);"></i>
        </div>
        <h2>GC Radius</h2>
        <p class="subtitle">Mikrotik Hotspot Manager</p>

        <?php if ($setupMode): ?>
            <div style="text-align:center;">
                <span class="setup-badge">⚙️ First-Time Setup</span>
            </div>

            <?php if ($error): ?>
                <div class="login-error"><?= $error ?></div>
            <?php endif; ?>

            <p style="font-size:0.72rem;color:#666;text-align:center;margin:0 0 12px 0;">
                Buat akun administrator pertama untuk mengelola GC Radius.
            </p>

            <form method="POST" autocomplete="off">
                <label for="username">Username <span style="color:#ef4444;">*</span></label>
                <input type="text" id="username" name="username" placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="off">

                <label for="password">Password <span style="color:#ef4444;">*</span></label>
                <div class="pw-wrapper">
                    <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
                </div>

                <label for="confirm_password">Konfirmasi Password <span style="color:#ef4444;">*</span></label>
                <div class="pw-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
                </div>

                <button type="submit" name="setup_admin" class="btn-login">
                    <i data-lucide="user-plus" style="width:15px;height:15px;vertical-align:middle;margin-right:5px;"></i>
                    Buat Akun & Masuk
                </button>
            </form>

        <?php else: ?>
            <?php if ($error): ?>
                <div class="login-error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus autocomplete="username">

                <label for="password">Password</label>
                <div class="pw-wrapper">
                    <input type="password" id="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
                </div>

                <button type="submit" name="login" class="btn-login">
                    <i data-lucide="log-in" style="width:15px;height:15px;vertical-align:middle;margin-right:5px;"></i>
                    Masuk
                </button>
            </form>

            <div class="login-footer">
                &copy; <?= date('Y') ?> GC Radius | GC Network Home Lab
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
lucide.createIcons();

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
