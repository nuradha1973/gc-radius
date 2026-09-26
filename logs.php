<?php
// logs.php
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
?>

<h2>System Logs (Authentication)</h2>

<div class="filter-bar">
    <div class="filter-row">
        <input type="text" class="filter-search" placeholder="Cari username...">
        <button class="filter-clear sort-btn" style="color:#ef4444;">Reset</button>
    </div>
    <select class="filter-select" data-col="3">
        <option value="">Semua Reply</option>
        <option value="access-accept">Access-Accept</option>
        <option value="access-reject">Access-Reject</option>
    </select>
    <span class="filter-result-info"></span>
</div>

<div class="table-scroll-xl">
    <table>
        <thead>
            <tr>
                <th class="sortable-th" data-col="0">ID <span class="sort-arrow"></span></th>
                <th class="sortable-th" data-col="1">Username <span class="sort-arrow"></span></th>
                <th>Password</th>
                <th class="sortable-th" data-col="3">Reply <span class="sort-arrow"></span></th>
                <th>IP Address</th>
                <th>MAC Address</th>
                <th class="sortable-th" data-col="6">Auth Date <span class="sort-arrow"></span></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT rp.id, rp.username, rp.pass, rp.reply, rp.authdate,
                    ra.framedipaddress,
                    ra.callingstationid
                FROM radpostauth rp
                LEFT JOIN radacct ra ON ra.username = rp.username
                    AND ra.acctstarttime <= rp.authdate
                    AND ra.radacctid = (
                        SELECT MAX(ra2.radacctid)
                        FROM radacct ra2
                        WHERE ra2.username = rp.username
                        AND ra2.acctstarttime <= rp.authdate
                    )
                ORDER BY rp.id DESC LIMIT 100
            ");
            while ($row = $stmt->fetch()) {
                $color = ($row['reply'] === 'Access-Accept') ? 'green' : 'red';
                $ip = $row['framedipaddress'] ?: '-';
                $mac = $row['callingstationid'] ?: '-';
                // Format MAC: uppercase with colons
                if ($mac !== '-' && preg_match('/^[0-9a-fA-F]{12}$/', $mac)) {
                    $mac = strtoupper(implode(':', str_split($mac, 2)));
                }
                echo "<tr>
                        <td>" . htmlspecialchars($row['id']) . "</td>
                        <td class='td-username'>" . htmlspecialchars($row['username']) . "</td>
                        <td>" . htmlspecialchars($row['pass']) . "</td>
                        <td style='color: {$color}; font-weight: bold;'>" . htmlspecialchars($row['reply']) . "</td>
                        <td style='font-size:0.7rem;'>" . htmlspecialchars($ip) . "</td>
                        <td style='font-size:0.68rem;'>" . htmlspecialchars($mac) . "</td>
                        <td>" . htmlspecialchars($row['authdate']) . "</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
