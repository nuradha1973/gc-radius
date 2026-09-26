<!-- includes/sidebar.php -->
<?php
$isLoggedIn = isset($_SESSION['admin_user']);
?>
<div class="sidebar glass">
    <h2>GC Radius</h2>
    <a href="index.php">Dashboard</a>
    <?php if ($isLoggedIn): ?>
    <a href="users.php">Users Management</a>
    <a href="profiles.php">Profiles Management</a>
    <a href="accounting.php">Accounting</a>
    <a href="settings.php">Settings</a>
    <a href="backup.php">Backup &amp; Restore</a>
    <a href="logs.php">System Logs</a>
    <?php else: ?>
    <a href="login.php" style="color: var(--primary); font-weight: 600;">Login</a>
    <?php endif; ?>
</div>
