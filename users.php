<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';

// Auth check
if (!isset($_SESSION['admin_user'])) {
    header("Location: login.php");
    exit;
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $profile  = trim($_POST['profile']);

    // Cek duplikat username
    $stmt = $pdo->prepare("SELECT username FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password' LIMIT 1");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();

    if ($existing) {
        $_SESSION['flash'] = "User '" . htmlspecialchars($existing['username']) . "' sudah ada. Gunakan nama lain.";
        $_SESSION['flash_type'] = 'error';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value, created_at) VALUES (?, 'Cleartext-Password', ':=', ?, NOW())");
    $stmt->execute([$username, $password]);

    if (!empty($profile)) {
        $stmt = $pdo->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
        $stmt->execute([$username, $profile]);
    }
    $_SESSION['flash'] = "User '$username' berhasil ditambahkan.";
    $_SESSION['flash_type'] = 'success';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $old_username = $_POST['old_username'];
    $username     = trim($_POST['username']);
    $password     = trim($_POST['password']);
    $profile      = trim($_POST['profile']);

    // Cek duplikat username (skip jika tidak berubah)
    if ($username !== $old_username) {
        $stmt = $pdo->prepare("SELECT username FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password' LIMIT 1");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();
        if ($existing) {
            $_SESSION['flash'] = "User '" . htmlspecialchars($existing['username']) . "' sudah ada.";
            $_SESSION['flash_type'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE radcheck SET username = ?, value = ? WHERE username = ? AND attribute = 'Cleartext-Password'");
    $stmt->execute([$username, $password, $old_username]);

    $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE username = ?");
    $stmt->execute([$old_username]);

    if (!empty($profile)) {
        $stmt = $pdo->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
        $stmt->execute([$username, $profile]);
    }

    $stmt = $pdo->prepare("UPDATE radacct SET username = ? WHERE username = ?");
    $stmt->execute([$username, $old_username]);

    $_SESSION['flash'] = "User '$username' berhasil diupdate.";
    $_SESSION['flash_type'] = 'success';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Delete User (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $username = trim($_POST['delete_user']);

    $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ?");
    $stmt->execute([$username]);

    $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE username = ?");
    $stmt->execute([$username]);

    $_SESSION['flash'] = "User '$username' berhasil dihapus.";
    $_SESSION['flash_type'] = 'success';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Toggle State (Active/Disabled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_state'])) {
    $username = trim($_POST['toggle_state']);

    // Check current state
    $stmt = $pdo->prepare("SELECT id FROM radcheck WHERE username = ? AND attribute = 'Auth-Type' AND value = 'Reject'");
    $stmt->execute([$username]);
    $isDisabled = $stmt->fetch();

    if ($isDisabled) {
        // Enable user: remove Auth-Type := Reject
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Auth-Type' AND value = 'Reject'");
        $stmt->execute([$username]);
        $_SESSION['flash'] = "User '$username' diaktifkan.";
    } else {
        // Disable user: add Auth-Type := Reject
        $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')");
        $stmt->execute([$username]);
        $_SESSION['flash'] = "User '$username' dinonaktifkan.";
    }
    $_SESSION['flash_type'] = 'success';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Flash message
$msg = $_SESSION['flash'] ?? '';
$msg_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

require_once 'includes/header.php';

$editData = null;
if (isset($_GET['edit'])) {
    $username = $_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT c.username, c.value as password, g.groupname 
        FROM radcheck c 
        LEFT JOIN radusergroup g ON c.username = g.username 
        WHERE c.attribute = 'Cleartext-Password' AND c.username = ?
    ");
    $stmt->execute([$username]);
    $editData = $stmt->fetch();
}
?>

<?php if ($msg): ?>
<div class="flash-msg" style="
    background: <?= $msg_type === 'success' ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)' ?>;
    border: 1px solid <?= $msg_type === 'success' ? '#22c55e' : '#ef4444' ?>;
    color: <?= $msg_type === 'success' ? '#166534' : '#991b1b' ?>;">
    <i data-lucide="check-circle" class="flash-icon"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<h2>Users Management</h2>

<div class="filter-bar">
    <div class="filter-row">
        <input type="text" class="filter-search" placeholder="Cari username / password / profile...">
        <button class="filter-clear sort-btn" style="color:#ef4444;">Reset</button>
    </div>
    <span class="filter-result-info"></span>
</div>

<div class="glass panel-sm" style="margin-bottom:8px;">
    <?php if ($editData): ?>
        <h3 style="margin:0 0 6px 0;">Edit: <?= htmlspecialchars($editData['username']) ?></h3>
        <form method="POST" class="form-row">
            <input type="hidden" name="old_username" value="<?= htmlspecialchars($editData['username']) ?>">
            <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($editData['username']) ?>" required>
            <input type="text" name="password" placeholder="Password" value="<?= htmlspecialchars($editData['password']) ?>" required>
            <select name="profile">
                <option value="">-- Profile --</option>
                <?php
                $profiles = $pdo->query("SELECT DISTINCT groupname FROM radgroupreply")->fetchAll();
                foreach ($profiles as $p) {
                    $selected = ($editData['groupname'] === $p['groupname']) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($p['groupname']) . "' $selected>" . htmlspecialchars($p['groupname']) . "</option>";
                }
                ?>
            </select>
            <button type="submit" name="edit_user">Update</button>
            <a href="users.php" class="btn btn-danger">Cancel</a>
        </form>
    <?php else: ?>
        <h3 style="margin:0 0 6px 0;">Add New User</h3>
        <form method="POST" class="form-row">
            <input type="text" name="username" placeholder="Username" required>
            <input type="text" name="password" placeholder="Password" required>
            <select name="profile">
                <option value="">-- Profile --</option>
                <?php
                $profiles = $pdo->query("SELECT DISTINCT groupname FROM radgroupreply")->fetchAll();
                foreach ($profiles as $p) {
                    echo "<option value='" . htmlspecialchars($p['groupname']) . "'>" . htmlspecialchars($p['groupname']) . "</option>";
                }
                ?>
            </select>
            <button type="submit" name="add_user">Add User</button>
        </form>
    <?php endif; ?>
</div>

<div class="table-scroll-xl">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th class="sortable-th" data-col="1">Username <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="2">Password <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="3">Profile <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="4">Created At <span class="sort-arrow"></span></th>
                <th>State</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $pdo->query("
            SELECT c.username, c.value as password, g.groupname,
                   (SELECT COUNT(*) FROM radcheck WHERE username = c.username AND attribute = 'Auth-Type' AND value = 'Reject') AS is_disabled,
                   c.created_at AS registered
            FROM radcheck c 
            LEFT JOIN radusergroup g ON c.username = g.username 
            WHERE c.attribute = 'Cleartext-Password'
        ");
        $no = 1;
        while ($row = $stmt->fetch()) {
            $u   = htmlspecialchars($row['username']);
            $enc = urlencode($row['username']);
            $disabled = $row['is_disabled'] > 0;
            $stateLabel = $disabled ? 'Disabled' : 'Active';
            $stateClass = $disabled ? 'state-disabled' : 'state-active';
            $registered = $row['registered'] ? date('Y-m-d', strtotime($row['registered'])) : '-';
            echo "<tr class='clickable-row' onclick=\"window.location='?edit=$enc'\">
                    <td>" . $no++ . "</td>
                    <td class='td-username'>$u</td>
                    <td>" . htmlspecialchars($row['password']) . "</td>
                    <td>" . htmlspecialchars($row['groupname'] ?? 'N/A') . "</td>
                    <td style='font-size:0.7rem;'>" . htmlspecialchars($registered) . "</td>
                    <td onclick='event.stopPropagation()'>
                        <form method='POST' style='display:inline;'>
                            <input type='hidden' name='toggle_state' value='" . htmlspecialchars($row['username']) . "'>
                            <button type='submit' class='{$stateClass}' style='cursor:pointer;border:none;font-family:inherit;font-size:inherit;' onclick=\"showToggleConfirm(event, '" . htmlspecialchars(addslashes($row['username'])) . "', '{$stateLabel}')\">{$stateLabel}</button>
                        </form>
                    </td>
                    <td class='action-links' onclick='event.stopPropagation()'>
                        <form method='POST' style='display:inline;' onsubmit='event.preventDefault();'>
                            <input type='hidden' name='delete_user' value='" . htmlspecialchars($row['username']) . "'>
                            <button type='submit' title='Hapus' class='action-icon danger' onclick=\"showConfirm(event, '" . htmlspecialchars(addslashes($row['username'])) . "', 'user')\"><i data-lucide='trash-2'></i></button>
                        </form>
                    </td>
                  </tr>";
        }
        ?>
    </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
