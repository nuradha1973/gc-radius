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

$backupMsg = '';
$restoreMsg = '';
$restoreMsgType = 'success';
$migrateMsg = '';
$migrateMsgType = 'success';

// ============================================================
// HANDLE BACKUP (Export SQL)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_db'])) {
    try {
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $sql = "-- GC Radius Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . ($settings['db_name'] ?? 'radius_db') . "\n";
        $sql .= "-- Host: " . ($settings['db_host'] ?? 'localhost') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            // Get CREATE TABLE statement
            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createStmt['Create Table'] . ";\n\n";

            // Get data
            $rows = $pdo->query("SELECT * FROM `$table`");
            if ($rows->rowCount() > 0) {
                $sql .= "LOCK TABLES `$table` WRITE;\n";
                $sql .= "INSERT INTO `$table` VALUES\n";

                $firstRow = true;
                while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                    if (!$firstRow) {
                        $sql .= ",\n";
                    }
                    $firstRow = false;
                    
                    $values = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote($val);
                        }
                    }
                    $sql .= "(" . implode(', ', $values) . ")";
                }
                $sql .= ";\n";
                $sql .= "UNLOCK TABLES;\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // Send as download
        $filename = ($settings['db_name'] ?? 'radius_db') . '_backup_' . date('Ymd_His') . '.sql';
        
        // Clear any previous output
        ob_clean();
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $sql;
        exit;
    } catch (Exception $e) {
        $backupMsg = '<div class="flash-msg" style="background:rgba(239,68,68,0.2);border:1px solid #ef4444;color:#991b1b;">Backup gagal: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// ============================================================
// HANDLE RESTORE (Import SQL)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_db'])) {
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $restoreMsg = 'Gagal upload file. Error code: ' . ($_FILES['sql_file']['error'] ?? 'unknown');
        $restoreMsgType = 'error';
    } else {
        $file = $_FILES['sql_file'];
        
        // Validate file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['sql', 'txt'])) {
            $restoreMsg = 'Hanya file .sql atau .txt yang diperbolehkan.';
            $restoreMsgType = 'error';
        } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB max
            $restoreMsg = 'File terlalu besar. Maksimal 50MB.';
            $restoreMsgType = 'error';
        } else {
            try {
                $sqlContent = file_get_contents($file['tmp_name']);
                
                if (empty(trim($sqlContent))) {
                    $restoreMsg = 'File SQL kosong.';
                    $restoreMsgType = 'error';
                } else {
                    // Disable foreign key checks before restore
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    
                    // Split by semicolons, respecting multiline statements
                    // Simple approach: split on ";\n" or "; " pattern
                    $statements = preg_split('/;\s*\n|;\s*$/m', $sqlContent);
                    
                    $executedCount = 0;
                    $errorCount = 0;
                    $errors = [];
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        
                        // Skip comments and empty statements
                        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
                            continue;
                        }
                        
                        // Skip SET and other non-data statements we handle separately
                        $upper = strtoupper(substr($statement, 0, 20));
                        if (strpos($upper, 'SET FOREIGN_KEY_CHECKS') === 0 ||
                            strpos($upper, 'SET SQL_MODE') === 0 ||
                            strpos($upper, 'SET NAMES') === 0) {
                            continue;
                        }
                        
                        try {
                            $pdo->exec($statement);
                            $executedCount++;
                        } catch (PDOException $e) {
                            $errorCount++;
                            if (count($errors) < 5) {
                                $errors[] = substr($statement, 0, 100) . '... - ' . $e->getMessage();
                            }
                        }
                    }
                    
                    // Re-enable foreign key checks
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                    
                    if ($errorCount === 0) {
                        $restoreMsg = "Restore berhasil! $executedCount statement dieksekusi.";
                        $restoreMsgType = 'success';
                    } else {
                        $restoreMsg = "Restore selesai dengan $errorCount error dari " . ($executedCount + $errorCount) . " statement. $executedCount berhasil.";
                        if (!empty($errors)) {
                            $restoreMsg .= "\nDetail error:\n" . implode("\n", $errors);
                        }
                        $restoreMsgType = 'error';
                    }
                }
            } catch (Exception $e) {
                $restoreMsg = 'Gagal restore: ' . $e->getMessage();
                $restoreMsgType = 'error';
            }
        }
    }
}

// ============================================================
// HANDLE MIGRATE TO ANOTHER DATABASE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate_db'])) {
    $targetHost = $_POST['target_host'] ?? '';
    $targetDb   = $_POST['target_db'] ?? '';
    $targetUser = $_POST['target_user'] ?? '';
    $targetPass = $_POST['target_pass'] ?? '';

    if (empty($targetHost) || empty($targetDb) || empty($targetUser)) {
        $migrateMsg = 'Host, database, dan user tujuan wajib diisi.';
        $migrateMsgType = 'error';
    } else {
        try {
            // Connect to target database
            $targetDsn = "mysql:host=$targetHost;dbname=$targetDb;charset=utf8mb4";
            $targetPdo = new PDO($targetDsn, $targetUser, $targetPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Test connection
            $targetPdo->query("SELECT 1");

            // Get all tables from source
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            // Disable FK checks on target
            $targetPdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            $migratedTables = 0;
            $migratedRows = 0;

            foreach ($tables as $table) {
                // Get CREATE TABLE
                $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                
                // Drop existing and recreate
                $targetPdo->exec("DROP TABLE IF EXISTS `$table`");
                $targetPdo->exec($createStmt['Create Table']);
                $migratedTables++;

                // Copy data in chunks for large tables
                $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $totalRows = $countStmt->fetchColumn();

                if ($totalRows > 0) {
                    $offset = 0;
                    $chunkSize = 500;
                    
                    while ($offset < $totalRows) {
                        $rows = $pdo->query("SELECT * FROM `$table` LIMIT $offset, $chunkSize");
                        $batch = $rows->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($batch)) {
                            $columns = array_keys($batch[0]);
                            $colList = '`' . implode('`, `', $columns) . '`';
                            
                            $values = [];
                            $params = [];
                            foreach ($batch as $i => $row) {
                                $placeholders = [];
                                foreach ($row as $col => $val) {
                                    $param = ":v_{$i}_" . preg_replace('/[^a-zA-Z0-9]/', '_', $col);
                                    $placeholders[] = $param;
                                    $params[$param] = $val;
                                }
                                $values[] = '(' . implode(', ', $placeholders) . ')';
                            }
                            
                            $insertSql = "INSERT INTO `$table` ($colList) VALUES " . implode(', ', $values);
                            $targetStmt = $targetPdo->prepare($insertSql);
                            $targetStmt->execute($params);
                            
                            $migratedRows += count($batch);
                        }
                        
                        $offset += $chunkSize;
                    }
                }
            }

            // Re-enable FK checks on target
            $targetPdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $migrateMsg = "Migrasi berhasil! $migratedTables tabel dan $migratedRows baris data telah dipindahkan ke <b>$targetDb@$targetHost</b>.";
            $migrateMsgType = 'success';

        } catch (PDOException $e) {
            $migrateMsg = 'Gagal koneksi ke database tujuan: ' . $e->getMessage();
            $migrateMsgType = 'error';
        }
    }
}

// Gather DB info
$dbVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
$dbSizeResult = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()");
$dbSize = $dbSizeResult->fetchColumn();

$tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();

// Fetch table names with accurate row counts
$tableNames = $pdo->query("SELECT TABLE_NAME, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1) AS size_kb 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    ORDER BY TABLE_NAME")->fetchAll();

$tableStats = [];
foreach ($tableNames as $tbl) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM `" . $tbl['TABLE_NAME'] . "`")->fetchColumn();
    $tableStats[] = [
        'TABLE_NAME' => $tbl['TABLE_NAME'],
        'TABLE_ROWS' => $cnt,
        'size_kb'    => $tbl['size_kb'],
    ];
}

require_once 'includes/header.php';
?>

<h2>Backup &amp; Restore</h2>

<?php if ($backupMsg) echo $backupMsg; ?>

<div class="backup-grid">

<!-- Database Info -->
<div class="glass panel-sm">
    <h3><i data-lucide="database" style="width:17px;height:17px;vertical-align:middle;margin-right:5px;"></i> Database Info</h3>
    <div class="db-info-row">
        <span>Host: <b style="color:#555;"><?= htmlspecialchars($settings['db_host'] ?? 'localhost') ?></b></span>
        <span>Database: <b style="color:#555;"><?= htmlspecialchars($settings['db_name'] ?? 'radius_db') ?></b></span>
        <span>Version: <b style="color:#555;"><?= htmlspecialchars($dbVersion) ?></b></span>
        <span>Size: <b style="color:#555;"><?= htmlspecialchars($dbSize) ?> MB</b></span>
        <span>Tables: <b style="color:#555;"><?= htmlspecialchars($tableCount) ?></b></span>
    </div>
    <div class="table-scroll" style="max-height:180px;margin-top:8px;">
        <table>
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th style="text-align:right;width:80px;">Rows</th>
                    <th style="text-align:right;width:80px;">Size</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableStats as $tbl): ?>
                <tr>
                    <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($tbl['TABLE_NAME']) ?></td>
                    <td style="text-align:right;font-size:0.72rem;"><?= number_format($tbl['TABLE_ROWS']) ?></td>
                    <td style="text-align:right;font-size:0.72rem;"><?= $tbl['size_kb'] ?> KB</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Backup & Restore Section -->
<div class="glass panel-sm">
    <h3><i data-lucide="database-backup" style="width:17px;height:17px;vertical-align:middle;margin-right:5px;color:#3b82f6;"></i> Backup &amp; Restore Database</h3>
    
    <?php if ($restoreMsg): ?>
    <div class="flash-msg" style="background:rgba(<?= $restoreMsgType === 'success' ? '34,197,94' : '239,68,68' ?>,0.2);border:1px solid <?= $restoreMsgType === 'success' ? '#22c55e' : '#ef4444' ?>;color:<?= $restoreMsgType === 'success' ? '#166534' : '#991b1b' ?>;white-space:pre-wrap;">
        <i data-lucide="<?= $restoreMsgType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="flash-icon" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"></i> <?= htmlspecialchars($restoreMsg) ?>
    </div>
    <?php endif; ?>

    <!-- Backup -->
    <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid rgba(0,0,0,0.08);">
        <h4 style="font-size:0.75rem;margin:0 0 4px 0;color:#555;"><i data-lucide="download" style="width:13px;height:13px;vertical-align:middle;margin-right:3px;color:#3b82f6;"></i> Download Backup</h4>
        <p style="font-size:0.7rem;color:#888;margin:0 0 6px 0;">
            Download salinan lengkap database dalam format SQL.
        </p>
        <form method="POST">
            <button type="submit" name="backup_db" style="font-size:0.72rem;padding:6px 12px;">
                <i data-lucide="download" style="width:13px;height:13px;vertical-align:middle;margin-right:3px;"></i> Download .sql
            </button>
        </form>
    </div>

    <!-- Restore -->
    <div>
        <h4 style="font-size:0.75rem;margin:0 0 4px 0;color:#555;"><i data-lucide="upload" style="width:13px;height:13px;vertical-align:middle;margin-right:3px;color:#f59e0b;"></i> Upload Restore</h4>
        <p style="font-size:0.7rem;color:#888;margin:0 0 6px 0;">
            Upload file .sql untuk mengembalikan database. <b style="color:#ef4444;">Peringatan:</b> data akan ditimpa!
        </p>
        <form method="POST" enctype="multipart/form-data">
            <div style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1;min-width:160px;">
                    <input type="file" name="sql_file" accept=".sql,.txt" required style="margin:0;padding:3px;font-size:0.7rem;">
                </div>
                <button type="submit" name="restore_db" onclick="return confirm('Peringatan: Data yang ada akan ditimpa. Lanjutkan?')" style="background:#f59e0b;font-size:0.72rem;padding:6px 12px;flex-shrink:0;">
                    <i data-lucide="upload" style="width:13px;height:13px;vertical-align:middle;margin-right:3px;"></i> Restore
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Migrate to Another Database -->
<div class="glass panel-sm">
    <h3><i data-lucide="arrow-right-left" style="width:17px;height:17px;vertical-align:middle;margin-right:5px;color:#8b5cf6;"></i> Migrate ke Database Lain</h3>
    <p style="font-size:0.73rem;color:#666;margin:0 0 10px 0;">
        Salin semua tabel dan data dari database saat ini ke database tujuan secara langsung (tanpa file).
    </p>

    <?php if ($migrateMsg): ?>
    <div class="flash-msg" style="background:rgba(<?= $migrateMsgType === 'success' ? '34,197,94' : '239,68,68' ?>,0.2);border:1px solid <?= $migrateMsgType === 'success' ? '#22c55e' : '#ef4444' ?>;color:<?= $migrateMsgType === 'success' ? '#166534' : '#991b1b' ?>;">
        <i data-lucide="<?= $migrateMsgType === 'success' ? 'check-circle' : 'alert-circle' ?>" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"></i> <?= $migrateMsg ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row" style="margin-bottom:6px;">
            <input type="text" name="target_host" placeholder="Host (contoh: 192.168.1.100)" required style="flex:2;">
            <input type="text" name="target_db" placeholder="Nama Database" required style="flex:2;">
        </div>
        <div class="form-row" style="margin-bottom:6px;">
            <input type="text" name="target_user" placeholder="Username" required style="flex:1;">
            <div class="pw-wrapper" style="flex:1;">
                <input type="password" name="target_pass" placeholder="Password" style="flex:1;" autocomplete="off">
                <button type="button" class="pw-toggle" onclick="togglePassword(this)" tabindex="-1"><i data-lucide="eye" class="pw-eye"></i><i data-lucide="eye-off" class="pw-eye-off" style="display:none;"></i></button>
            </div>
        </div>
        <button type="submit" name="migrate_db" onclick="return confirm('Peringatan: Data di database tujuan akan ditimpa. Lanjutkan?')" style="background:#8b5cf6;font-size:0.75rem;padding:8px 16px;">
            <i data-lucide="arrow-right-left" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i> Mulai Migrasi
        </button>
    </form>
</div>

</div>

<?php require_once 'includes/footer.php'; ?>
