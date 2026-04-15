<?php
   include("config.php");
   session_start();

   // Default values (prevent undefined-variable warnings in preview mode)
   $error = '';
   $log   = '';

   function get_milliseconds()
   {
      $chunks = explode(' ', microtime());
      return sprintf('%d%d', $chunks[1], $chunks[0] * 1000);
   }

   if($_SERVER["REQUEST_METHOD"] == "POST") {
      if (!isset($db) || $db === false) {
         // Preview mode: DB not connected, skip registration logic
         $log = '[Preview] Registration skipped — no database connection.';
      } else {
         // Sanitise inputs
         $myusername = mysqli_real_escape_string($db, trim($_POST['username']));
         $mypassword = mysqli_real_escape_string($db, $_POST['password']);

         if ($myusername === '' || $mypassword === '') {
            $error = 'Username and password cannot be empty.';
         } elseif (isset($_POST['command'])) {  // any submit from the register form
            // Check for duplicate username before inserting
            $dup = mysqli_query($db, "SELECT COUNT(*) AS cnt FROM radcheck
                                      WHERE username = '$myusername'");
            $dup_row = mysqli_fetch_assoc($dup);

            if ($dup_row && $dup_row['cnt'] > 0) {
               $error = "Username \"$myusername\" is already taken. Please choose another.";
            } else {
               // Detect which group existing accounts use (fallback: 'user')
               $grp_res = mysqli_query($db, "SELECT groupname FROM radusergroup LIMIT 1");
               $grp_row = mysqli_fetch_assoc($grp_res);
               $groupname = $grp_row['groupname'] ?? 'user';

               $ok1 = mysqli_query($db,
                  "INSERT INTO radcheck (username, attribute, op, value)
                   VALUES ('$myusername', 'Cleartext-Password', ':=', '$mypassword')");
               $ok2 = $ok1 ? mysqli_query($db,
                  "INSERT INTO radusergroup (username, groupname)
                   VALUES ('$myusername', '$groupname')") : false;

               if ($ok1 && $ok2) {
                  $log = "Account \"$myusername\" created! You can now sign in.";
               } else {
                  $error = 'Registration failed: ' . mysqli_error($db);
               }
            }
         }
      }
   }

   // Build "Back to Login" URL using params passed from hotspotlogin.php
   // Check GET first (direct link), then POST hidden fields (after form submit)
   $uamip   = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_GET['uamip']    ?? $_POST['_uamip']   ?? '');
   $uamport = intval($_GET['uamport'] ?? $_POST['_uamport'] ?? 0);
   $userurl  = htmlspecialchars($_GET['userurl'] ?? $_POST['_userurl'] ?? '', ENT_QUOTES);
   // /prelogin asks ChilliSpot for a fresh challenge → cleanest re-entry point
   $login_url = ($uamip && $uamport)
       ? 'http://' . $uamip . ':' . $uamport . '/prelogin'
       : '';
?>


<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register — HotSpot</title>
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
    .subtitle {
      text-align: center; color: rgba(255,255,255,.4);
      font-size: 13px; margin-bottom: 28px;
    }
    .form-group { margin-bottom: 18px; }
    label {
      display: block; font-size: 11px; font-weight: 700; letter-spacing: .6px;
      text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 8px;
    }
    input[type=text], input[type=password] {
      width: 100%; padding: 11px 14px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 10px; color: #e2e8f0; font-size: 15px;
      outline: none; transition: border-color .2s;
    }
    input[type=text]:focus, input[type=password]:focus { border-color: #74b9ff; }
    input[type=submit] {
      width: 100%; padding: 12px; margin-top: 6px;
      background: linear-gradient(135deg, #2980b9, #1a6fa8);
      color: #fff; border: none; border-radius: 10px;
      font-size: 15px; font-weight: 700; cursor: pointer;
      letter-spacing: .4px; transition: opacity .2s;
    }
    input[type=submit]:hover { opacity: .88; }
    .msg-error   { color: #ff7090; font-size: 13px; text-align: center; margin-top: 14px; }
    .msg-success { color: #6fcf97; font-size: 13px; text-align: center; margin-top: 14px; font-weight: 600; }
    .btn-back {
      display: block; text-align: center; margin-top: 16px;
      padding: 10px; border-radius: 10px;
      background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
      color: #e2e8f0; font-size: 14px; font-weight: 600; text-decoration: none;
      transition: background .2s;
    }
    .btn-back:hover { background: rgba(255,255,255,0.15); }
  </style>
</head>
<body>
  <div class="glass">
    <div class="logo">🛰</div>
    <h2>Create Account</h2>
    <p class="subtitle">Register to access the HotSpot network</p>

    <form action="" method="post">
      <!-- Preserve ChilliSpot GET params so $login_url is available after submit -->
      <input type="hidden" name="_uamip"   value="<?= htmlspecialchars($uamip) ?>">
      <input type="hidden" name="_uamport" value="<?= htmlspecialchars($uamport) ?>">
      <input type="hidden" name="_userurl" value="<?= $userurl ?>">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username" autocomplete="username">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" autocomplete="new-password">
      </div>
      <input type="submit" value="Register →" name="command">
    </form>

    <?php if ($error !== ''): ?>
      <p class="msg-error">⚠ <?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if ($log !== ''): ?>
      <p class="msg-success">✅ <?php echo htmlspecialchars($log); ?></p>
      <?php if ($login_url !== ''): ?>
        <a href="<?= htmlspecialchars($login_url) ?>" class="btn-back">← Back to Login</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
