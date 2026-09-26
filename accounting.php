<?php
// accounting.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';

// Auth check
if (!isset($_SESSION['admin_user'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/header.php';

// Format Bytes
function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow]; 
} 
?>

<h2>Accounting</h2>

<div class="filter-bar">
    <div class="filter-row">
        <input type="text" class="filter-search" placeholder="Cari username / IP...">
        <button class="filter-clear sort-btn" style="color:#ef4444;">Reset</button>
    </div>
    <div class="filter-dates">
        <input type="date" class="filter-date-start" title="Dari tanggal" value="<?= date('Y-m-d', strtotime('-1 month')) ?>">
        <input type="date" class="filter-date-end" title="Sampai tanggal" value="<?= date('Y-m-d') ?>">
    </div>
    <span class="filter-result-info"></span>
</div>

<div class="table-scroll-xl">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th class="sortable-th" data-col="1">Username <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="2">Session Time <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="3">Upload <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="4">Download <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="5">Status <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="6">Auth Date <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="7">IP Address <span class="sort-arrow"></span></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT username, acctsessiontime, acctinputoctets, acctoutputoctets, acctstoptime, acctstarttime, framedipaddress FROM radacct ORDER BY radacctid DESC");
            $totalSession = 0;
            $totalUpload = 0;
            $totalDownload = 0;
            $no = 1;
            while ($row = $stmt->fetch()) {
                $status = is_null($row['acctstoptime']) ? '<span style="color: green; font-weight: bold;">Online</span>' : 'Offline';
                $time = gmdate("H:i:s", $row['acctsessiontime']);
                $authDate = $row['acctstarttime'] ? date("Y-m-d H:i", strtotime($row['acctstarttime'])) : '-';
                $totalSession += $row['acctsessiontime'];
                $totalUpload += $row['acctinputoctets'];
                $totalDownload += $row['acctoutputoctets'];
                echo "<tr>
                        <td>" . $no++ . "</td>
                        <td class='td-username'><a href=\"#\" class=\"filter-user-link\" onclick=\"filterByUser('" . htmlspecialchars(addslashes($row['username'])) . "'); return false;\" title=\"Klik untuk filter user ini\">" . htmlspecialchars($row['username']) . "</a></td>
                        <td data-seconds=\"{$row['acctsessiontime']}\">" . $time . "</td>
                        <td data-bytes=\"{$row['acctinputoctets']}\">" . formatBytes($row['acctinputoctets']) . "</td>
                        <td data-bytes=\"{$row['acctoutputoctets']}\">" . formatBytes($row['acctoutputoctets']) . "</td>
                        <td>" . $status . "</td>
                        <td data-date=\"" . htmlspecialchars($row['acctstarttime'] ?? '') . "\">" . htmlspecialchars($authDate) . "</td>
                        <td>" . htmlspecialchars($row['framedipaddress'] ?? '-') . "</td>
                      </tr>";
            }
            ?>
        </tbody>
        <tfoot>
            <tr class="summary-row">
                <td></td>
                <td><strong>Total</strong></td>
                <td id="totalSession"><strong><?php 
    $d = floor($totalSession / 86400);
    $h = gmdate("H:i:s", $totalSession % 86400);
    echo $d > 0 ? $d . 'd ' . $h : $h;
?></strong></td>
                <td id="totalUpload"><strong><?= formatBytes($totalUpload) ?></strong></td>
                <td id="totalDownload"><strong><?= formatBytes($totalDownload) ?></strong></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
