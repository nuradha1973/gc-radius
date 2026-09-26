<?php
// index.php
require_once 'includes/header.php';

// Format Bytes
function formatBytesDash($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow]; 
} 

// Auto-cleanup zombie sessions (jalan tiap dashboard dibuka)
require_once 'includes/cleanup_sessions.php';

// Fetch quick stats
$totalUsers    = $pdo->query("SELECT COUNT(DISTINCT username) FROM radcheck WHERE attribute = 'Cleartext-Password'")->fetchColumn();
$disabledUsers = $pdo->query("SELECT COUNT(DISTINCT username) FROM radcheck WHERE attribute = 'Auth-Type' AND value = 'Reject'")->fetchColumn();
$activeSessions = $pdo->query("SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL")->fetchColumn();
$totalProfiles = $pdo->query("SELECT COUNT(DISTINCT groupname) FROM radgroupreply")->fetchColumn();
$todayAuthOk   = $pdo->query("SELECT COUNT(*) FROM radpostauth WHERE reply = 'Access-Accept' AND DATE(authdate) = CURDATE()")->fetchColumn();
$nasDevices    = $pdo->query("SELECT COUNT(*) FROM nas")->fetchColumn();
?>

<h2>Dashboard</h2>
<div class="cards">
    <div class="card glass">
        <h3><span class="icon-dot blue"></span> Total Users</h3>
        <p><?= htmlspecialchars($totalUsers) ?></p>
    </div>
    <div class="card glass">
        <h3><span class="icon-dot green"></span> Active Sessions</h3>
        <p><?= htmlspecialchars($activeSessions) ?></p>
    </div>
    <div class="card glass">
        <h3><span class="icon-dot red"></span> Disabled Users</h3>
        <p><?= htmlspecialchars($disabledUsers) ?></p>
    </div>
    <div class="card glass">
        <h3><span class="icon-dot purple"></span> Profiles</h3>
        <p><?= htmlspecialchars($totalProfiles) ?></p>
    </div>
    <div class="card glass">
        <h3><span class="icon-dot emerald"></span> Today Auth OK</h3>
        <p><?= htmlspecialchars($todayAuthOk) ?></p>
    </div>
    <div class="card glass">
        <h3><span class="icon-dot amber"></span> NAS Devices</h3>
        <p><?= htmlspecialchars($nasDevices) ?></p>
    </div>
</div>

<!-- Row 1: Active Sessions | Top 6 Data Usage | Recent Authentication -->
<div class="dash-row-3">
    <!-- Active Sessions -->
    <div class="dash-col-3 glass panel-sm">
        <h3><i data-lucide="wifi" class="box-icon green"></i> Active Sessions</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>IP Address</th>
                        <th style="width:50px;">Up</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT ra.username, ra.framedipaddress,
                        TIMESTAMPDIFF(MINUTE, ra.acctstarttime, NOW()) AS uptime_min
                        FROM radacct ra
                        WHERE ra.acctstoptime IS NULL
                        ORDER BY ra.acctstarttime DESC LIMIT 10");
                    if ($stmt->rowCount() === 0) {
                        echo '<tr><td colspan="3" style="text-align:center;color:#999;">No active sessions</td></tr>';
                    }
                    while ($row = $stmt->fetch()) {
                        $uptime = $row['uptime_min'] >= 60
                            ? floor($row['uptime_min'] / 60) . 'h ' . ($row['uptime_min'] % 60) . 'm'
                            : $row['uptime_min'] . 'm';
                        echo "<tr>
                                <td class='td-username'>" . htmlspecialchars($row['username']) . "</td>
                                <td>" . htmlspecialchars($row['framedipaddress'] ?? '-') . "</td>
                                <td style='font-size:0.68rem;'>" . htmlspecialchars($uptime) . "</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top 6 Data Usage -->
    <div class="dash-col-3 glass panel-sm">
        <h3><i data-lucide="trending-up" class="box-icon blue"></i> Top 6 Data Usage <span style="font-size:0.65rem;color:#888;"><?= date('M Y') ?></span></h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Total Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT username,
                        SUM(acctinputoctets + acctoutputoctets) AS total_data
                        FROM radacct
                        WHERE acctstarttime >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                        GROUP BY username
                        ORDER BY total_data DESC LIMIT 6");
                    if ($stmt->rowCount() === 0) {
                        echo '<tr><td colspan="2" style="text-align:center;color:#999;">No data</td></tr>';
                    }
                    while ($row = $stmt->fetch()) {
                        echo "<tr>
                                <td class='td-username'>" . htmlspecialchars($row['username']) . "</td>
                                <td style='color: var(--primary); font-weight: bold;'>" . formatBytesDash($row['total_data']) . "</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Authentication -->
    <div class="dash-col-3 glass panel-sm">
        <h3><i data-lucide="shield-check" class="box-icon purple"></i> Recent Authentication</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Auth Date</th>
                        <th style="width:40px;text-align:center;">Reply</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT username, reply, authdate
                        FROM radpostauth
                        ORDER BY id DESC LIMIT 6");
                    if ($stmt->rowCount() === 0) {
                        echo '<tr><td colspan="3" style="text-align:center;color:#999;">No logs</td></tr>';
                    }
                    while ($row = $stmt->fetch()) {
                        $lucideIcon = ($row['reply'] === 'Access-Accept') ? 'circle-check' : 'circle-x';
                        $iconColor  = ($row['reply'] === 'Access-Accept') ? 'auth-ok' : 'auth-fail';
                        $title = htmlspecialchars($row['reply']);
                        echo "<tr>
                                <td class='td-username'>" . htmlspecialchars($row['username']) . "</td>
                                <td>" . htmlspecialchars($row['authdate']) . "</td>
                                <td style='text-align:center;' title='{$title}'><i data-lucide='{$lucideIcon}' class='{$iconColor}'></i></td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Row 2: Detail User List -->
<div class="glass panel-sm panel-fill">
    <h3 style="margin-top:0;"><i data-lucide="list" class="box-icon blue"></i> User List Detail</h3>
    <div class="table-scroll-lg">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>IP Address</th>
                    <th>Data In</th>
                    <th>Data Out</th>
                    <th>Total</th>
                    <th>Last Seen</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("
                    SELECT 
                        rc.username,
                        COALESCE(latest.framedipaddress, '-') AS ip_address,
                        COALESCE(latest.acctinputoctets, 0) AS data_in,
                        COALESCE(latest.acctoutputoctets, 0) AS data_out,
                        COALESCE(latest.acctstarttime, '-') AS last_seen,
                        CASE WHEN latest.acctstoptime IS NULL AND latest.acctstarttime IS NOT NULL THEN 'Online' ELSE 'Offline' END AS status
                    FROM radcheck rc
                    LEFT JOIN (
                        SELECT username, framedipaddress, acctinputoctets, acctoutputoctets, acctstarttime, acctstoptime
                        FROM radacct
                        WHERE radacctid IN (
                            SELECT MAX(radacctid) FROM radacct GROUP BY username
                        )
                    ) latest ON rc.username = latest.username
                    WHERE rc.attribute = 'Cleartext-Password'
                    GROUP BY rc.username
                    ORDER BY status DESC, last_seen DESC
                    LIMIT 50
                ");
                $no = 1;
                if ($stmt->rowCount() === 0) {
                    echo '<tr><td colspan="8" style="text-align:center;color:#999;padding:20px;">No users found</td></tr>';
                }
                while ($row = $stmt->fetch()) {
                    $statusClass = ($row['status'] === 'Online') ? 'status-online' : 'status-offline';
                    $totalData = $row['data_in'] + $row['data_out'];
                    echo "<tr>
                            <td>" . $no++ . "</td>
                            <td class='td-username'>" . htmlspecialchars($row['username']) . "</td>
                            <td>" . htmlspecialchars($row['ip_address']) . "</td>
                            <td style='color:#3b82f6;'>" . formatBytesDash($row['data_in']) . "</td>
                            <td style='color:#8b5cf6;'>" . formatBytesDash($row['data_out']) . "</td>
                            <td style='font-weight:bold;'>" . formatBytesDash($totalData) . "</td>
                            <td>" . htmlspecialchars($row['last_seen']) . "</td>
                            <td><span class='{$statusClass}'>" . htmlspecialchars($row['status']) . "</span></td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
