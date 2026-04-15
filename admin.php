<?php
/**
 * admin.php
 * HotSpot Admin Dashboard — traffic monitoring & per-user quota control
 */
include("config.php");  // Sets $db; if connection fails $db === false → mock data kicks in
session_start();

// ── Admin password (change before production deployment) ─────
define('ADMIN_PASSWORD', 'admin1234');

// ── Logout handler ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// ── Login handler ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (isset($_POST['admin_password']) && $_POST['admin_password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = 'Incorrect password. Please try again.';
    }
}

// ── Show login page if not authenticated ─────────────────────
if (!isset($_SESSION['admin_logged_in'])) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — HotSpot</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  }
  .glass {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 20px;
    padding: 44px 40px 40px;
    width: 360px;
    backdrop-filter: blur(16px);
    box-shadow: 0 8px 40px rgba(0,0,0,.45);
  }
  .logo { text-align: center; font-size: 38px; margin-bottom: 6px; }
  h2 {
    text-align: center; color: #fff; font-size: 20px;
    font-weight: 700; margin-bottom: 6px;
  }
  .subtitle { text-align: center; color: rgba(255,255,255,.4); font-size: 13px; margin-bottom: 28px; }
  label {
    display: block; font-size: 11px; font-weight: 700; letter-spacing: .6px;
    text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 8px;
  }
  input[type=password] {
    width: 100%; padding: 11px 14px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px; color: #e2e8f0; font-size: 15px;
    outline: none; transition: border-color .2s; margin-bottom: 20px;
  }
  input[type=password]:focus { border-color: #74b9ff; }
  input[type=submit] {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, #e94560, #c0392b);
    color: #fff; border: none; border-radius: 10px;
    font-size: 15px; font-weight: 700; cursor: pointer;
    letter-spacing: .4px; transition: opacity .2s;
  }
  input[type=submit]:hover { opacity: .88; }
  .err { color: #ff7090; font-size: 13px; text-align: center; margin-bottom: 16px; }
</style>
</head>
<body>
<div class="glass">
  <div class="logo">🛰</div>
  <h2>HotSpot Admin</h2>
  <p class="subtitle">Sign in to manage users &amp; quotas</p>
  <?php if (isset($login_error)) echo '<p class="err">⚠ ' . htmlspecialchars($login_error) . '</p>'; ?>
  <form method="post">
    <label>Password</label>
    <input type="password" name="admin_password" autofocus placeholder="Enter admin password">
    <input type="submit" name="admin_login" value="Sign In →">
  </form>
</div>
</body>
</html>
<?php
    exit();
}

// ════════════════════════════════════════════════════════════
// Admin is authenticated — handle POST actions
// ════════════════════════════════════════════════════════════
$message      = '';
$message_type = 'success';

// ── Set per-user quota limits ────────────────────────────────
if ($db && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_limit'])) {
    $target  = mysqli_real_escape_string($db, $_POST['target_user'] ?? '');
    $t_limit = max(0, intval($_POST['time_limit']    ?? 0));  // seconds
    $f_limit = max(0, intval($_POST['traffic_limit'] ?? 0));  // bytes

    if ($target !== '') {
        // Upsert Session-Timeout
        $res = mysqli_query($db, "SELECT id FROM radreply
                                  WHERE username='$target'
                                    AND attribute='Session-Timeout'");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            mysqli_query($db, "UPDATE radreply SET value=" . intval($t_limit)
                            . " WHERE id=" . intval($row['id']));
        } elseif ($t_limit > 0) {
            mysqli_query($db, "INSERT INTO radreply (username, attribute, op, value)
                               VALUES ('$target','Session-Timeout',':=','$t_limit')");
        }

        // Upsert ChilliSpot-Max-Total-Octets
        $res = mysqli_query($db, "SELECT id FROM radreply
                                  WHERE username='$target'
                                    AND attribute='ChilliSpot-Max-Total-Octets'");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            mysqli_query($db, "UPDATE radreply SET value=" . intval($f_limit)
                            . " WHERE id=" . intval($row['id']));
        } elseif ($f_limit > 0) {
            mysqli_query($db, "INSERT INTO radreply (username, attribute, op, value)
                               VALUES ('$target','ChilliSpot-Max-Total-Octets',':=','$f_limit')");
        }

        $message = 'Quota updated for user <strong>' . htmlspecialchars($target) . '</strong>.';
    }
}

// ── Delete a user account ────────────────────────────────────
if ($db && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target = mysqli_real_escape_string($db, $_POST['target_user'] ?? '');
    if ($target !== '') {
        mysqli_query($db, "DELETE FROM radcheck     WHERE username='$target'");
        mysqli_query($db, "DELETE FROM radusergroup WHERE username='$target'");
        mysqli_query($db, "DELETE FROM radreply     WHERE username='$target'");
        $message      = 'User <strong>' . htmlspecialchars($target) . '</strong> has been deleted.';
        $message_type = 'danger';
    }
}

// ════════════════════════════════════════════════════════════
// Fetch data for dashboard display
// ════════════════════════════════════════════════════════════

if (!isset($db) || $db === false) {
    // ── Mock data for local preview (no DB) ──────────────────
    $users = ['alice', 'bob', 'charlie'];

    $acct = [
        'alice'   => ['total_traffic' => 52428800,  'total_time' => 3612],
        'bob'     => ['total_traffic' => 104857600, 'total_time' => 7234],
        'charlie' => ['total_traffic' => 1048576,   'total_time' => 310],
    ];

    $ulimits = [
        'alice' => [
            'ChilliSpot-Max-Total-Octets' => '209715200',  // personal limit: 200 MB
            'Session-Timeout'             => '7200',        // personal limit: 2 hours
        ],
    ];

    $glimits = [
        'ChilliSpot-Max-Total-Octets' => '104857600',  // group default: 100 MB
        'Session-Timeout'             => '3600',        // group default: 1 hour
    ];

    $online = [
        [
            'username'        => 'alice',
            'framedipaddress' => '192.168.1.101',
            'acctstarttime'   => date('Y-m-d H:i:s', time() - 1800),
            'traffic'         => 52428800,
        ],
    ];
} else {
    // ── Live data from RADIUS DB ──────────────────────────────

    // Currently online sessions (acctstoptime IS NULL)
    $online = [];
    $res = mysqli_query($db, "SELECT username, framedipaddress, acctstarttime,
                                      acctinputoctets + acctoutputoctets AS traffic
                               FROM radacct
                               WHERE acctstoptime IS NULL
                               ORDER BY acctstarttime DESC");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $online[] = $r;

    // All registered users — UNION ensures every registered user appears
    $users = [];
    $res = mysqli_query($db, "SELECT DISTINCT username FROM radusergroup
                              UNION
                              SELECT DISTINCT username FROM radcheck
                                WHERE attribute='Cleartext-Password'
                              ORDER BY username");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $users[] = $r['username'];

    // Ensure every online user also appears in \$users (prevents Online > All Users)
    foreach (array_column($online, 'username') as $oname) {
        if (!in_array($oname, $users, true)) $users[] = $oname;
    }
    sort($users);

    // Cumulative traffic and session time per user
    $acct = [];
    $res = mysqli_query($db, "SELECT username,
                                      SUM(acctinputoctets + acctoutputoctets) AS total_traffic,
                                      SUM(acctsessiontime) AS total_time
                               FROM radacct
                               GROUP BY username");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $acct[$r['username']] = $r;

    // Per-user quota overrides (radreply)
    $ulimits = [];
    $res = mysqli_query($db, "SELECT username, attribute, value FROM radreply
                              WHERE attribute IN
                                    ('Session-Timeout','ChilliSpot-Max-Total-Octets')");
    if ($res) while ($r = mysqli_fetch_assoc($res))
        $ulimits[$r['username']][$r['attribute']] = $r['value'];

    // Group-level default limits (radgroupreply)
    $glimits = [];
    $res = mysqli_query($db, "SELECT attribute, value FROM radgroupreply
                              WHERE attribute IN
                                    ('Session-Timeout','ChilliSpot-Max-Total-Octets')");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $glimits[$r['attribute']] = $r['value'];
}

// ── Helper functions ───────────────────────────────────────────
function fmt_bytes(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576,    2) . ' MB';
    if ($b >= 1024)       return round($b / 1024,       2) . ' KB';
    return $b . ' B';
}
function fmt_time(int $s): string {
    return sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
}
function limit_badge(string $type): string {
    return $type === 'personal'
        ? '<span class="badge personal">Personal</span>'
        : '<span class="badge group">Group</span>';
}
function get_traffic_limit(string $uname, array $ulimits, array $glimits): array {
    if (isset($ulimits[$uname]['ChilliSpot-Max-Total-Octets']))
        return ['val' => intval($ulimits[$uname]['ChilliSpot-Max-Total-Octets']), 'type' => 'personal'];
    if (isset($glimits['ChilliSpot-Max-Total-Octets']))
        return ['val' => intval($glimits['ChilliSpot-Max-Total-Octets']), 'type' => 'group'];
    return ['val' => 0, 'type' => 'none'];
}
function get_time_limit(string $uname, array $ulimits, array $glimits): array {
    if (isset($ulimits[$uname]['Session-Timeout']))
        return ['val' => intval($ulimits[$uname]['Session-Timeout']), 'type' => 'personal'];
    if (isset($glimits['Session-Timeout']))
        return ['val' => intval($glimits['Session-Timeout']), 'type' => 'group'];
    return ['val' => 0, 'type' => 'none'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard — HotSpot</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  /* ── Base layout ── */
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #0f0f1a; color: #e2e8f0;
    min-height: 100vh; display: flex; flex-direction: column;
  }

  /* ── Top navigation bar ── */
  nav {
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 0 28px;
    display: flex; align-items: center; justify-content: space-between;
    height: 60px; position: sticky; top: 0; z-index: 100;
    backdrop-filter: blur(12px);
  }
  .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 700; color: #fff; }
  .nav-brand span { font-size: 22px; }
  .nav-right { display: flex; align-items: center; gap: 16px; }
  .nav-time { font-size: 12px; color: rgba(255,255,255,0.45); font-variant-numeric: tabular-nums; }
  .btn-logout {
    background: rgba(233,69,96,0.15); border: 1px solid rgba(233,69,96,0.4);
    color: #ff7090; padding: 7px 16px; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s;
  }
  .btn-logout:hover { background: rgba(233,69,96,0.3); }

  /* ── Main content area ── */
  main { padding: 32px 28px; max-width: 1200px; margin: 0 auto; width: 100%; }

  /* ── Summary stat cards ── */
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 16px; margin-bottom: 36px;
  }
  .stat-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px; padding: 20px 24px;
    display: flex; align-items: center; gap: 16px;
  }
  .stat-icon {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 22px;
  }
  .stat-icon.green  { background: rgba(39,174,96,.18); }
  .stat-icon.blue   { background: rgba(41,128,185,.18); }
  .stat-icon.orange { background: rgba(230,126,34,.18); }
  .stat-icon.red    { background: rgba(233,69,96,.18); }
  .stat-value { font-size: 26px; font-weight: 700; color: #fff; line-height: 1; }
  .stat-label { font-size: 12px; color: rgba(255,255,255,0.45); margin-top: 4px; }

  /* ── Section headers ── */
  .section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; margin-top: 36px; }
  .section-header h2 { font-size: 16px; font-weight: 700; color: #fff; }
  .count-badge {
    background: rgba(233,69,96,0.2); color: #ff7090;
    border: 1px solid rgba(233,69,96,0.3);
    border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: 700;
  }

  /* ── Flash messages ── */
  .alert {
    padding: 12px 18px; border-radius: 10px; margin-bottom: 24px;
    font-size: 14px; display: flex; align-items: center; gap: 10px;
  }
  .alert.success { background: rgba(39,174,96,.12); border: 1px solid rgba(39,174,96,.3); color: #6fcf97; }
  .alert.danger  { background: rgba(233,69,96,.12); border: 1px solid rgba(233,69,96,.3); color: #ff7090; }

  /* ── Data tables ── */
  .table-wrap {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px; overflow: hidden; margin-bottom: 8px;
  }
  table { width: 100%; border-collapse: collapse; }
  th {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.5); font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    padding: 11px 16px; text-align: left; white-space: nowrap;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }
  td { padding: 13px 16px; font-size: 13.5px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
  tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: rgba(255,255,255,0.03); }
  .empty-row td { text-align: center; color: rgba(255,255,255,0.3); padding: 32px; }

  /* ── Status & limit badges ── */
  .badge {
    display: inline-flex; align-items: center;
    padding: 2px 9px; border-radius: 20px;
    font-size: 11px; font-weight: 700; margin-left: 6px; vertical-align: middle;
  }
  .badge.online   { background: rgba(39,174,96,.2);   color: #6fcf97; border: 1px solid rgba(39,174,96,.3); }
  .badge.offline  { background: rgba(255,255,255,.06); color: rgba(255,255,255,.35); }
  .badge.personal { background: rgba(41,128,185,.2);  color: #74b9ff; border: 1px solid rgba(41,128,185,.3); }
  .badge.group    { background: rgba(255,255,255,.08); color: rgba(255,255,255,.45); }

  /* ── Traffic progress bar ── */
  .prog-wrap { background: rgba(255,255,255,0.1); height: 5px; border-radius: 3px; min-width: 70px; margin-top: 5px; }
  .prog-bar  { height: 5px; border-radius: 3px; background: #27ae60; transition: width .4s; }
  .prog-bar.warn   { background: #e67e22; }
  .prog-bar.danger { background: #e94560; }

  /* ── Action buttons ── */
  .btn { padding: 5px 13px; border: none; border-radius: 7px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .2s; }
  .btn-primary { background: rgba(41,128,185,.25); color: #74b9ff; border: 1px solid rgba(41,128,185,.3); }
  .btn-primary:hover { background: rgba(41,128,185,.45); }
  .btn-danger  { background: rgba(233,69,96,.15); color: #ff7090; border: 1px solid rgba(233,69,96,.3); }
  .btn-danger:hover  { background: rgba(233,69,96,.35); }

  /* ── Quota settings form ── */
  .form-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px; padding: 28px 32px; max-width: 520px;
  }
  .form-group { margin-bottom: 18px; }
  .form-card label {
    display: block; font-size: 12px; font-weight: 700;
    color: rgba(255,255,255,0.55); text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 8px;
  }
  .form-card select,
  .form-card input[type=number] {
    width: 100%; padding: 10px 14px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px; color: #e2e8f0; font-size: 14px;
    outline: none; transition: border-color .2s; appearance: none;
  }
  .form-card select:focus,
  .form-card input[type=number]:focus { border-color: #74b9ff; }
  .form-card select option { background: #1a1a2e; }
  .hint { font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 5px; }
  .btn-save {
    padding: 11px 28px;
    background: linear-gradient(135deg, #2980b9, #1a6fa8);
    border: none; border-radius: 8px; color: #fff;
    font-size: 14px; font-weight: 600; cursor: pointer; transition: opacity .2s;
  }
  .btn-save:hover { opacity: .85; }

  /* ── Group defaults note ── */
  .defaults-note { font-size: 12px; color: rgba(255,255,255,0.3); margin-top: 14px; }

  code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px; }
</style>
</head>
<body>

<!-- Top navigation bar -->
<nav>
  <div class="nav-brand"><span>🛰</span> HotSpot Admin Dashboard</div>
  <div class="nav-right">
    <span class="nav-time" id="clock"></span>
    <form method="post" style="margin:0">
      <button type="submit" name="admin_logout" class="btn-logout">Sign Out</button>
    </form>
  </div>
</nav>

<main>

  <!-- Flash message -->
  <?php if ($message !== ''): ?>
    <div class="alert <?= $message_type ?>">
      <?= $message_type === 'success' ? '✅' : '🗑' ?> <?= $message ?>
    </div>
  <?php endif; ?>

  <!-- Summary stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon green">👥</div>
      <div><div class="stat-value"><?= count($users) ?></div><div class="stat-label">Registered Users</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue">🟢</div>
      <div><div class="stat-value"><?= count($online) ?></div><div class="stat-label">Online Now</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange">📊</div>
      <div>
        <?php $total_all = array_sum(array_column($acct, 'total_traffic')); ?>
        <div class="stat-value"><?= fmt_bytes($total_all) ?></div>
        <div class="stat-label">Total Traffic</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon red">⏱</div>
      <div>
        <?php $total_sess = array_sum(array_column($acct, 'total_time')); ?>
        <div class="stat-value"><?= fmt_time($total_sess) ?></div>
        <div class="stat-label">Total Session Time</div>
      </div>
    </div>
  </div>

  <!-- Online users section -->
  <div class="section-header">
    <h2>Online Users</h2>
    <span class="count-badge"><?= count($online) ?> active</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Username</th><th>IP Address</th><th>Connected Since</th>
          <th>Traffic Used</th><th>Traffic Limit</th><th>Time Limit</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($online)): ?>
          <tr class="empty-row"><td colspan="6">No users are currently online.</td></tr>
        <?php else: ?>
          <?php foreach ($online as $u):
            $fl  = get_traffic_limit($u['username'], $ulimits, $glimits);
            $tl  = get_time_limit($u['username'], $ulimits, $glimits);
            $pct = ($fl['val'] > 0) ? min(100, round(intval($u['traffic']) / $fl['val'] * 100)) : 0;
            $bar = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warn' : '');
          ?>
          <tr data-user="<?= htmlspecialchars($u['username']) ?>">
            <td><strong style="color:#fff"><?= htmlspecialchars($u['username']) ?></strong>
                <span class="badge online">Online</span></td>
            <td><code style="color:#74b9ff"><?= htmlspecialchars($u['framedipaddress'] ?? '—') ?></code></td>
            <td><?= htmlspecialchars($u['acctstarttime'] ?? '—') ?></td>
            <td>
              <span class="live-traffic"><?= fmt_bytes(intval($u['traffic'])) ?></span>
              <?php if ($fl['val'] > 0): ?>
                <div class="prog-wrap" title="<?= $pct ?>% used">
                  <div class="prog-bar live-bar <?= $bar ?>" style="width:<?= $pct ?>%"></div>
                </div>
              <?php endif; ?>
            </td>
            <td class="traffic-limit-cell"><?= $fl['val'] > 0 ? fmt_bytes($fl['val']) . limit_badge($fl['type']) : '<span style="color:rgba(255,255,255,.3)">—</span>' ?></td>
            <td><?= $tl['val'] > 0 ? fmt_time($tl['val'])  . limit_badge($tl['type']) : '<span style="color:rgba(255,255,255,.3)">—</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- All users section -->
  <div class="section-header">
    <h2>All Users</h2>
    <span class="count-badge"><?= count($users) ?> total</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Username</th><th>Status</th><th>Total Traffic</th><th>Total Time</th>
          <th>Traffic Limit</th><th>Time Limit</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr class="empty-row"><td colspan="7">No registered users found.</td></tr>
        <?php else: ?>
          <?php
          $online_names = array_column($online, 'username');
          foreach ($users as $uname):
            $fl      = get_traffic_limit($uname, $ulimits, $glimits);
            $tl      = get_time_limit($uname, $ulimits, $glimits);
            $total_t = intval($acct[$uname]['total_traffic'] ?? 0);
            $total_s = intval($acct[$uname]['total_time']    ?? 0);
            $is_on   = in_array($uname, $online_names, true);
          ?>
          <tr>
            <td><strong style="color:#fff"><?= htmlspecialchars($uname) ?></strong></td>
            <td><?= $is_on ? '<span class="badge online">● Online</span>' : '<span class="badge offline">Offline</span>' ?></td>
            <td><?= fmt_bytes($total_t) ?></td>
            <td><?= fmt_time($total_s) ?></td>
            <td><?= $fl['val'] > 0 ? fmt_bytes($fl['val']) . limit_badge($fl['type']) : '<span style="color:rgba(255,255,255,.3)">—</span>' ?></td>
            <td><?= $tl['val'] > 0 ? fmt_time($tl['val'])  . limit_badge($tl['type']) : '<span style="color:rgba(255,255,255,.3)">—</span>' ?></td>
            <td style="white-space:nowrap">
              <button type="button" class="btn btn-primary"
                onclick="fillForm(
                  <?= json_encode($uname) ?>,
                  <?= $fl['type'] === 'personal' ? intval($ulimits[$uname]['ChilliSpot-Max-Total-Octets']) : 0 ?>,
                  <?= $tl['type'] === 'personal' ? intval($ulimits[$uname]['Session-Timeout']) : 0 ?>
                )">⚙ Set Quota</button>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Delete user <?= htmlspecialchars($uname, ENT_QUOTES) ?>? This cannot be undone.')">
                <input type="hidden" name="target_user" value="<?= htmlspecialchars($uname) ?>">
                <button type="submit" name="delete_user" class="btn btn-danger">🗑 Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Per-user quota settings form -->
  <div class="section-header" id="set-limit-section">
    <h2>Set User Quota</h2>
  </div>
  <div class="form-card">
    <form method="post" id="limit-form">
      <div class="form-group">
        <label>Username</label>
        <select name="target_user" id="form-user">
          <option value="">— Select a user —</option>
          <?php foreach ($users as $uname): ?>
            <option value="<?= htmlspecialchars($uname) ?>"><?= htmlspecialchars($uname) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Time Limit (seconds)</label>
        <input type="number" name="time_limit" id="form-time" min="0"
               placeholder="0 = use group default or unlimited">
        <p class="hint">Example: 3600 = 1 hour &nbsp;|&nbsp; Set 0 to revert to group default.</p>
      </div>
      <div class="form-group">
        <label>Traffic Limit (bytes)</label>
        <input type="number" name="traffic_limit" id="form-traffic" min="0"
               placeholder="0 = use group default or unlimited">
        <p class="hint">Example: 104857600 = 100 MB &nbsp;|&nbsp; Set 0 to revert to group default.</p>
      </div>
      <button type="submit" name="set_limit" class="btn-save">💾 Save Quota</button>
    </form>
  </div>

  <!-- Group defaults reference -->
  <p class="defaults-note">
    Group defaults —
    Traffic: <?= isset($glimits['ChilliSpot-Max-Total-Octets']) ? fmt_bytes(intval($glimits['ChilliSpot-Max-Total-Octets'])) : 'not set' ?>
    &nbsp;/&nbsp;
    Time: <?= isset($glimits['Session-Timeout']) ? fmt_time(intval($glimits['Session-Timeout'])) : 'not set' ?>
  </p>

</main>

<script>
  // Live clock in navigation bar
  function updateClock() {
    document.getElementById('clock').textContent = new Date().toLocaleTimeString();
  }
  updateClock();
  setInterval(updateClock, 1000);

  // Pre-fill quota form when "Set Quota" is clicked
  function fillForm(username, traffic, time) {
    document.getElementById('form-user').value    = username;
    document.getElementById('form-traffic').value = traffic;
    document.getElementById('form-time').value    = time;
    document.getElementById('set-limit-section')
            .scrollIntoView({ behavior: 'smooth' });
  }

  // Live traffic polling for online users (every 10 seconds)
  function fmtBytes(b) {
    b = parseInt(b, 10);
    if (b >= 1073741824) return (b/1073741824).toFixed(2) + " GB";
    if (b >= 1048576)    return (b/1048576).toFixed(2)    + " MB";
    if (b >= 1024)       return (b/1024).toFixed(1)       + " KB";
    return b + " B";
  }

  function pollTraffic() {
    document.querySelectorAll('tr[data-user]').forEach(function(row) {
      var user = row.getAttribute('data-user');
      fetch('check_status.php?username=' + encodeURIComponent(user))
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (typeof d.traffic === 'undefined') return;
          var t   = parseInt(d.traffic, 10);
          var lim = parseInt(d.traffic_limit, 10);

          // Update traffic text
          var tEl = row.querySelector('.live-traffic');
          if (tEl) tEl.textContent = fmtBytes(t);

          // Update progress bar
          var bar = row.querySelector('.live-bar');
          if (bar && lim > 0) {
            var pct = Math.min(100, Math.round(t / lim * 100));
            bar.style.width = pct + '%';
            bar.className = 'prog-bar live-bar' +
              (pct >= 90 ? ' danger' : (pct >= 70 ? ' warn' : ''));
            bar.parentElement.title = pct + '% used';
          }
        })
        .catch(function() {});
    });
  }

  // Poll immediately on load, then every 10 seconds
  pollTraffic();
  setInterval(pollTraffic, 10000);
</script>
</body>
</html>
