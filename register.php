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
      if (!isset($db)) {
         // Preview mode: DB not connected, skip registration logic
         $log = '[Preview] Registration skipped — no database connection.';
      } else {
         // username and password sent from form
         $myusername = mysqli_real_escape_string($db, $_POST['username']);
         $mypassword = mysqli_real_escape_string($db, $_POST['password']);

         # if($_POST["command"] == "Back to Login Page"){
         #    $previous = "javascript:history.go(-2)";
         #    header("Location:" . "https://192.168.182.1:3990");
         # }
         if($_POST["command"] == "Register"){
            $sql = "insert into radcheck (username, attribute, op, value) values ('$myusername', 'Cleartext-Password',':=', '$mypassword')";
            mysqli_query($db, $sql);
            $sql = "insert into radusergroup (username, groupname) values ('$myusername', 'user')";
            mysqli_query($db, $sql);
            $log = "Register Successfully!!!";
         }
      }
   }
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
  </style>
</head>
<body>
  <div class="glass">
    <div class="logo">🛰</div>
    <h2>Create Account</h2>
    <p class="subtitle">Register to access the HotSpot network</p>

    <form action="" method="post">
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
    <?php endif; ?>
  </div>
</body>
</html>
