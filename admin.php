<?php
/**
 * admin.php
 * HotSpot 管理者介面：流量監測 & 流量/時間控管
 */
// include("config.php");  // 預覽模式：暫時關閉 DB 連線
session_start();

// ── 管理者密碼（請自行修改） ────────────────────────────────
define('ADMIN_PASSWORD', 'admin1234');

// ── 登出 ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// ── 登入驗證 ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (isset($_POST['admin_password']) && $_POST['admin_password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = '密碼錯誤！';
    }
}

// ── 未登入：顯示登入頁 ────────────────────────────────────────
if (!isset($_SESSION['admin_logged_in'])) { ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<title>Admin Login</title>
<style>
  body { font-family: Arial, sans-serif; background: #f0f0f0;
         display: flex; justify-content: center; padding-top: 100px; }
  .box { background: #fff; padding: 30px; border: 1px solid #ccc;
         border-radius: 5px; width: 320px; }
  h2 { text-align: center; margin-top: 0; }
  input[type=password] { width: 100%; padding: 8px; margin: 8px 0 16px;
                         box-sizing: border-box; border: 1px solid #ccc; border-radius: 3px; }
  input[type=submit] { width: 100%; padding: 9px; background: #333;
                       color: #fff; border: none; cursor: pointer; border-radius: 3px; }
  .err { color: red; font-size: 13px; }
</style>
</head>
<body>
<div class="box">
  <h2>管理者登入</h2>
  <?php if (isset($login_error)) echo '<p class="err">' . htmlspecialchars($login_error) . '</p>'; ?>
  <form method="post">
    <label>密碼：</label>
    <input type="password" name="admin_password" autofocus>
    <input type="submit" name="admin_login" value="登入">
  </form>
</div>
</body>
</html>
<?php
    exit();
}

// ════════════════════════════════════════════════════════════
// 管理員已登入 — 處理 POST 操作
// ════════════════════════════════════════════════════════════
$message      = '';
$message_type = 'success';

// ── 設定個人限制 ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_limit'])) {
    $target  = mysqli_real_escape_string($db, $_POST['target_user'] ?? '');
    $t_limit = max(0, intval($_POST['time_limit']    ?? 0));   // seconds
    $f_limit = max(0, intval($_POST['traffic_limit'] ?? 0));   // bytes

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

        $message = '已更新使用者 <strong>' . htmlspecialchars($target) . '</strong> 的限制設定';
    }
}

// ── 刪除使用者 ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target = mysqli_real_escape_string($db, $_POST['target_user'] ?? '');
    if ($target !== '') {
        mysqli_query($db, "DELETE FROM radcheck     WHERE username='$target'");
        mysqli_query($db, "DELETE FROM radusergroup WHERE username='$target'");
        mysqli_query($db, "DELETE FROM radreply     WHERE username='$target'");
        $message      = '已刪除使用者 <strong>' . htmlspecialchars($target) . '</strong>';
        $message_type = 'danger';
    }
}

// ════════════════════════════════════════════════════════════
// 查詢資料
// ════════════════════════════════════════════════════════════

// // 所有已註冊使用者
// $users = [];
// $res = mysqli_query($db, "SELECT username FROM radcheck
//                           WHERE attribute='Cleartext-Password'
//                           ORDER BY username");
// if ($res) while ($r = mysqli_fetch_assoc($res)) $users[] = $r['username'];

// // 每位使用者的累計流量與使用時間
// $acct = [];
// $res = mysqli_query($db, "SELECT username,
//                                   SUM(acctinputoctets + acctoutputoctets) AS total_traffic,
//                                   SUM(acctsessiontime) AS total_time
//                            FROM radacct
//                            GROUP BY username");
// if ($res) while ($r = mysqli_fetch_assoc($res)) $acct[$r['username']] = $r;

// // 個人限制（radreply）
// $ulimits = [];
// $res = mysqli_query($db, "SELECT username, attribute, value FROM radreply
//                           WHERE attribute IN
//                                 ('Session-Timeout','ChilliSpot-Max-Total-Octets')");
// if ($res) while ($r = mysqli_fetch_assoc($res))
//     $ulimits[$r['username']][$r['attribute']] = $r['value'];

// // 群組預設限制（radgroupreply）
// $glimits = [];
// $res = mysqli_query($db, "SELECT attribute, value FROM radgroupreply
//                           WHERE attribute IN
//                                 ('Session-Timeout','ChilliSpot-Max-Total-Octets')");
// if ($res) while ($r = mysqli_fetch_assoc($res)) $glimits[$r['attribute']] = $r['value'];

// // 目前線上使用者（acctstoptime 為 NULL）
// $online = [];
// $res = mysqli_query($db, "SELECT username, framedipaddress, acctstarttime,
//                                   acctinputoctets + acctoutputoctets AS traffic
//                            FROM radacct
//                            WHERE acctstoptime IS NULL
//                            ORDER BY acctstarttime DESC");
// if ($res) while ($r = mysqli_fetch_assoc($res)) $online[] = $r;

// ════════════════════════════════════════════════════════════
// 假資料（預覽前端用，正式部署時恢復 DB 查詢）
// ════════════════════════════════════════════════════════════
$users = ['alice', 'bob', 'charlie'];

$acct = [
    'alice'   => ['total_traffic' => 52428800,  'total_time' => 3612],
    'bob'     => ['total_traffic' => 104857600, 'total_time' => 7234],
    'charlie' => ['total_traffic' => 1048576,   'total_time' => 310],
];

$ulimits = [
    'alice' => [
        'ChilliSpot-Max-Total-Octets' => '209715200',  // 200 MB 個人上限
        'Session-Timeout'             => '7200',        // 2 小時個人上限
    ],
];

$glimits = [
    'ChilliSpot-Max-Total-Octets' => '104857600',  // 100 MB 群組預設
    'Session-Timeout'             => '3600',        // 1 小時群組預設
];

$online = [
    [
        'username'        => 'alice',
        'framedipaddress' => '192.168.1.101',
        'acctstarttime'   => date('Y-m-d H:i:s', time() - 1800),
        'traffic'         => 52428800,
    ],
];

// ── 工具函式 ───────────────────────────────────────────────
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
        ? '<span class="badge personal">個人</span>'
        : '<span class="badge group">群組</span>';
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
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard – HotSpot</title>
<style>
  * { box-sizing: border-box; }
  body  { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; color: #333; }
  header { background: #2c3e50; color: #fff; padding: 12px 24px;
           display: flex; justify-content: space-between; align-items: center; }
  header h1 { margin: 0; font-size: 20px; }
  .logout-btn { background: #c0392b; color: #fff; border: none;
                padding: 6px 14px; cursor: pointer; border-radius: 3px; font-size: 13px; }
  main  { padding: 24px; max-width: 1100px; margin: 0 auto; }
  h2    { border-bottom: 2px solid #2c3e50; padding-bottom: 6px; margin-top: 32px; font-size: 17px; }
  .alert { padding: 10px 16px; border-radius: 4px; margin-bottom: 16px; }
  .alert.success { background: #dff0d8; color: #3c763d; border: 1px solid #c3e6cb; }
  .alert.danger  { background: #f2dede; color: #a94442; border: 1px solid #f5c6cb; }
  /* Tables */
  table { width: 100%; border-collapse: collapse; background: #fff;
          margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  th { background: #2c3e50; color: #fff; padding: 9px 12px;
       text-align: left; font-size: 13px; white-space: nowrap; }
  td { padding: 8px 12px; border-bottom: 1px solid #e8e8e8; font-size: 13px; }
  tr:hover td { background: #f9f9f9; }
  .empty { color: #888; font-size: 13px; padding: 24px 0; }
  /* Badges */
  .badge { display: inline-block; padding: 1px 7px; border-radius: 10px;
           font-size: 11px; margin-left: 4px; vertical-align: middle; }
  .badge.online   { background: #27ae60; color: #fff; }
  .badge.personal { background: #2980b9; color: #fff; }
  .badge.group    { background: #95a5a6; color: #fff; }
  /* Buttons */
  .btn { padding: 5px 12px; border: none; border-radius: 3px;
         cursor: pointer; font-size: 12px; }
  .btn-primary { background: #2980b9; color: #fff; }
  .btn-danger  { background: #c0392b; color: #fff; }
  /* Set limits form */
  .form-card { background: #fff; padding: 24px 28px; border: 1px solid #ddd;
               border-radius: 5px; max-width: 500px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .form-card label { display: block; font-weight: bold; font-size: 13px;
                     margin-bottom: 4px; margin-top: 14px; }
  .form-card label:first-child { margin-top: 0; }
  .form-card select,
  .form-card input[type=number] { width: 100%; padding: 7px 10px; border: 1px solid #ccc;
                                   border-radius: 3px; font-size: 13px; }
  .hint { font-size: 11px; color: #888; margin: 3px 0 0; }
  .form-card .btn-primary { margin-top: 18px; padding: 8px 20px; font-size: 14px; }
  /* Progress bar */
  .prog-wrap { background: #eee; height: 8px; border-radius: 4px; min-width: 80px; }
  .prog-bar  { height: 8px; border-radius: 4px; background: #27ae60; }
  .prog-bar.warn  { background: #e67e22; }
  .prog-bar.danger { background: #c0392b; }
</style>
</head>
<body>
<header>
  <h1>🛰 HotSpot 管理者介面</h1>
  <form method="post" style="margin:0">
    <button type="submit" name="admin_logout" class="logout-btn">登出</button>
  </form>
</header>

<main>
<?php if ($message !== ''): ?>
  <div class="alert <?= $message_type ?>"><?= $message ?></div>
<?php endif; ?>

<!-- ────────────────────────────────────────────────
     線上使用者
     ──────────────────────────────────────────────── -->
<h2>目前線上使用者
  <span class="badge online" style="font-size:14px"><?= count($online) ?></span>
</h2>

<?php if (empty($online)): ?>
  <p class="empty">目前無線上使用者。</p>
<?php else: ?>
<table>
  <tr>
    <th>使用者名稱</th>
    <th>IP 位址</th>
    <th>連線開始時間</th>
    <th>已用流量</th>
    <th>流量上限</th>
    <th>時間上限</th>
  </tr>
  <?php foreach ($online as $u):
    $fl = get_traffic_limit($u['username'], $ulimits, $glimits);
    $tl = get_time_limit($u['username'], $ulimits, $glimits);
    $pct = ($fl['val'] > 0) ? min(100, round(intval($u['traffic']) / $fl['val'] * 100)) : 0;
    $bar_class = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warn' : '');
  ?>
  <tr>
    <td><strong><?= htmlspecialchars($u['username']) ?></strong>
        <span class="badge online">線上</span></td>
    <td><?= htmlspecialchars($u['framedipaddress'] ?? '—') ?></td>
    <td><?= htmlspecialchars($u['acctstarttime']   ?? '—') ?></td>
    <td>
      <?= fmt_bytes(intval($u['traffic'])) ?>
      <?php if ($fl['val'] > 0): ?>
        <div class="prog-wrap" title="<?= $pct ?>%">
          <div class="prog-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
        </div>
      <?php endif; ?>
    </td>
    <td>
      <?= $fl['val'] > 0 ? fmt_bytes($fl['val']) . limit_badge($fl['type']) : '—' ?>
    </td>
    <td>
      <?= $tl['val'] > 0 ? fmt_time($tl['val']) . limit_badge($tl['type']) : '—' ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- ────────────────────────────────────────────────
     所有使用者
     ──────────────────────────────────────────────── -->
<h2>所有使用者（<?= count($users) ?> 人）</h2>

<?php if (empty($users)): ?>
  <p class="empty">尚無任何已註冊使用者。</p>
<?php else: ?>
<table>
  <tr>
    <th>使用者名稱</th>
    <th>狀態</th>
    <th>累計流量</th>
    <th>累計時間</th>
    <th>流量上限</th>
    <th>時間上限</th>
    <th>操作</th>
  </tr>
  <?php
  $online_names = array_column($online, 'username');
  foreach ($users as $uname):
    $fl = get_traffic_limit($uname, $ulimits, $glimits);
    $tl = get_time_limit($uname, $ulimits, $glimits);
    $total_t = intval($acct[$uname]['total_traffic'] ?? 0);
    $total_s = intval($acct[$uname]['total_time']    ?? 0);
    $is_on   = in_array($uname, $online_names, true);
  ?>
  <tr>
    <td><strong><?= htmlspecialchars($uname) ?></strong></td>
    <td><?= $is_on ? '<span class="badge online">線上</span>' : '離線' ?></td>
    <td><?= fmt_bytes($total_t) ?></td>
    <td><?= fmt_time($total_s) ?></td>
    <td><?= $fl['val'] > 0 ? fmt_bytes($fl['val']) . limit_badge($fl['type']) : '—' ?></td>
    <td><?= $tl['val'] > 0 ? fmt_time($tl['val'])  . limit_badge($tl['type']) : '—' ?></td>
    <td>
      <button type="button" class="btn btn-primary"
        onclick="fillForm(
          <?= json_encode($uname) ?>,
          <?= $fl['type'] === 'personal' ? intval($ulimits[$uname]['ChilliSpot-Max-Total-Octets']) : 0 ?>,
          <?= $tl['type'] === 'personal' ? intval($ulimits[$uname]['Session-Timeout']) : 0 ?>
        )">設定限制</button>
      <form method="post" style="display:inline"
            onsubmit="return confirm('確定要刪除使用者 <?= htmlspecialchars($uname, ENT_QUOTES) ?>？')">
        <input type="hidden" name="target_user" value="<?= htmlspecialchars($uname) ?>">
        <button type="submit" name="delete_user" class="btn btn-danger">刪除</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- ────────────────────────────────────────────────
     設定使用者限制表單
     ──────────────────────────────────────────────── -->
<h2 id="set-limit-section">設定使用者限制</h2>
<div class="form-card">
  <form method="post" id="limit-form">
    <label>使用者名稱：</label>
    <select name="target_user" id="form-user">
      <option value="">-- 請選擇 --</option>
      <?php foreach ($users as $uname): ?>
        <option value="<?= htmlspecialchars($uname) ?>"><?= htmlspecialchars($uname) ?></option>
      <?php endforeach; ?>
    </select>

    <label>時間上限（秒）：</label>
    <input type="number" name="time_limit" id="form-time" min="0" placeholder="0 = 使用群組設定或不限制">
    <p class="hint">例：3600 = 1 小時；輸入 0 可移除個人設定（恢復群組預設）</p>

    <label>流量上限（Bytes）：</label>
    <input type="number" name="traffic_limit" id="form-traffic" min="0" placeholder="0 = 使用群組設定或不限制">
    <p class="hint">例：104857600 = 100 MB；輸入 0 可移除個人設定（恢復群組預設）</p>

    <button type="submit" name="set_limit" class="btn btn-primary">儲存設定</button>
  </form>
</div>

<!-- 群組預設值參考 -->
<p style="margin-top:12px; font-size:12px; color:#888">
  群組預設流量：<?= isset($glimits['ChilliSpot-Max-Total-Octets'])
    ? fmt_bytes(intval($glimits['ChilliSpot-Max-Total-Octets'])) : '未設定' ?> ／
  群組預設時間：<?= isset($glimits['Session-Timeout'])
    ? fmt_time(intval($glimits['Session-Timeout'])) : '未設定' ?>
</p>

</main>

<script>
function fillForm(username, traffic, time) {
  document.getElementById('form-user').value    = username;
  document.getElementById('form-traffic').value = traffic;
  document.getElementById('form-time').value    = time;
  document.getElementById('set-limit-section')
          .scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>
