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

// Handle Add Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_profile'])) {
    $groupname  = trim($_POST['groupname']);
    $attribute  = trim($_POST['attribute'] ?? 'Mikrotik-Group');
    $rate_limit = trim($_POST['rate_limit']);

    if (!empty($groupname) && !empty($rate_limit) && !empty($attribute)) {
        $stmt = $pdo->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, ?, '=', ?)");
        $stmt->execute([$groupname, $attribute, $rate_limit]);
    }
    $_SESSION['flash'] = "Profile '$groupname' berhasil ditambahkan.";
    $_SESSION['flash_type'] = 'success';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Edit Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $old_groupname = $_POST['old_groupname'];
    $old_attribute = $_POST['old_attribute'] ?? 'Mikrotik-Group';
    $groupname     = trim($_POST['groupname']);
    $attribute     = trim($_POST['attribute'] ?? 'Mikrotik-Group');
    $rate_limit    = trim($_POST['rate_limit']);

    // Update attribute + value: sesuaikan groupname, attribute, dan value
    $stmt = $pdo->prepare("UPDATE radgroupreply SET groupname = ?, attribute = ?, value = ? WHERE groupname = ? AND attribute = ?");
    $stmt->execute([$groupname, $attribute, $rate_limit, $old_groupname, $old_attribute]);

    if ($old_groupname !== $groupname) {
        $stmt = $pdo->prepare("UPDATE radusergroup SET groupname = ? WHERE groupname = ?");
        $stmt->execute([$groupname, $old_groupname]);
    }

    $_SESSION['flash'] = "Profile '$groupname' berhasil diupdate.";
    $_SESSION['flash_type'] = 'success';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Delete Profile (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile'])) {
    $groupname = trim($_POST['delete_profile']);

    $stmt = $pdo->prepare("DELETE FROM radgroupreply WHERE groupname = ?");
    $stmt->execute([$groupname]);
    $stmt = $pdo->prepare("DELETE FROM radgroupcheck WHERE groupname = ?");
    $stmt->execute([$groupname]);

    $_SESSION['flash'] = "Profile '$groupname' berhasil dihapus.";
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
    $groupname = $_GET['edit'];

    // Cek apakah profile ada (ambil attribute + value dari radgroupreply)
    $stmt = $pdo->prepare("SELECT groupname, attribute, value FROM radgroupreply WHERE groupname = ? LIMIT 1");
    $stmt->execute([$groupname]);
    $editData = $stmt->fetch();

    if ($editData) {
    }
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

<h2>Profiles Management</h2>

<div class="filter-bar">
    <div class="filter-row">
        <input type="text" class="filter-search" placeholder="Cari profile / rate limit...">
        <button class="filter-clear sort-btn" style="color:#ef4444;">Reset</button>
    </div>
    <span class="filter-result-info"></span>
</div>

<div class="glass panel-sm" style="margin-bottom:8px;">
    <?php if ($editData): ?>
        <h3 style="margin:0 0 6px 0;">Edit: <?= htmlspecialchars($editData['groupname']) ?></h3>
        <form method="POST" class="form-row">
            <input type="hidden" name="old_groupname" value="<?= htmlspecialchars($editData['groupname']) ?>">
            <input type="hidden" name="old_attribute" value="<?= htmlspecialchars($editData['attribute']) ?>">
            <input type="text" name="groupname" placeholder="Profile Name (label)" value="<?= htmlspecialchars($editData['groupname']) ?>" required>
            <select name="attribute" required>
                <option value="Mikrotik-Rate-Limit" <?= ($editData['attribute'] ?? '') === 'Mikrotik-Rate-Limit' ? 'selected' : '' ?>>Mikrotik-Rate-Limit</option>
                <option value="Mikrotik-Group" <?= ($editData['attribute'] ?? '') === 'Mikrotik-Group' ? 'selected' : '' ?>>Mikrotik-Group</option>
            </select>
            <input type="text" name="rate_limit" placeholder="Value / Rate Limit (e.g. 10M/10M)" value="<?= htmlspecialchars($editData['value']) ?>" required>
            <button type="submit" name="edit_profile">Update</button>
            <a href="profiles.php" class="btn btn-danger">Cancel</a>
        </form>
    <?php else: ?>
        <h3 style="margin:0 0 6px 0;">Add New Profile</h3>
        <form method="POST" class="form-row">
            <input type="text" name="groupname" placeholder="Profile Name (label)" required>
            <select name="attribute" required>
                <option value="Mikrotik-Rate-Limit">Mikrotik-Rate-Limit</option>
                <option value="Mikrotik-Group" selected>Mikrotik-Group</option>
            </select>
            <input type="text" name="rate_limit" placeholder="Value / Rate Limit (e.g. 10M/10M)" required>
            <button type="submit" name="add_profile">Add Profile</button>
        </form>
    <?php endif; ?>
</div>

<div class="table-scroll-xl">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th class="sortable-th" data-col="1">Profile Name <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="2">Attribute <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="3">Value / Rate Limit <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="4">Users <span class="sort-arrow"></span></th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $pdo->query("
            SELECT g.groupname,
                   COALESCE(r.attribute, '-') AS attribute,
                   COALESCE(r.value, '-') AS rate_limit,
                   COUNT(u.username) AS user_count
            FROM (SELECT DISTINCT groupname FROM radgroupreply) g
            LEFT JOIN radgroupreply r ON r.groupname = g.groupname AND r.id = (
                SELECT MIN(id) FROM radgroupreply WHERE groupname = g.groupname
            )
            LEFT JOIN radusergroup u ON g.groupname = u.groupname
            GROUP BY g.groupname, r.attribute, r.value
            ORDER BY g.groupname
        ");
        $no = 1;
        while ($row = $stmt->fetch()) {
            $g   = htmlspecialchars($row['groupname']);
            $enc = urlencode($row['groupname']);
            echo "<tr class='clickable-row' onclick=\"window.location='?edit=$enc'\">
                    <td>" . $no++ . "</td>
                    <td>$g</td>
                    <td>" . htmlspecialchars($row['attribute']) . "</td>
                    <td>" . htmlspecialchars($row['rate_limit']) . "</td>
                    <td>" . $row['user_count'] . " user(s)</td>
                    <td class='action-links' onclick='event.stopPropagation()'>
                        <form method='POST' style='display:inline;' onsubmit='event.preventDefault();'>
                            <input type='hidden' name='delete_profile' value='" . htmlspecialchars($row['groupname']) . "'>
                            <button type='submit' title='Hapus' class='action-icon danger' onclick=\"showConfirm(event, '" . htmlspecialchars(addslashes($row['groupname'])) . "', 'profile')\"><i data-lucide='trash-2'></i></button>
                        </form>
                    </td>
                  </tr>";
        }
        ?>
    </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
