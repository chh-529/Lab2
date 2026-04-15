<?php

# File: hotspotlogin.php
# working with chillispot_0.97
# last change 2004-10-01
# this is forked from original chillispot.org's hotspotlogin.cgi by Kanne
# uamsecret enabled by Cedric

include("config.php");


# Shared secret used to encrypt challenge with. Prevents dictionary attacks.
# You should change this to your own shared secret.
$uamsecret = "tauam";

# Uncomment the following line if you want to use ordinary user-password
# for radius authentication. Must be used together with $uamsecret.
$userpassword=1;

$loginpath = "hotspotlogin.php";

# possible Cases:	
# attempt to login                          login=login
# 1: Login successful                       res=success
# 2: Login failed                           res=failed
# 3: Logged out                             res=logoff
# 4: Tried to login while already logged in res=already
# 5: Not logged in yet                      res=notyet
#11: Popup                                  res=popup1
#12: Popup                                  res=popup2
#13: Popup                                  res=popup3
# 0: It was not a form request              res=""

#Read query parameters which we care about
# $_GET['res'];
# $_GET['challenge'];
# $_GET['uamip'];
# $_GET['uamport'];
# $_GET['reply'];
# $_GET['userurl'];
# $_GET['timeleft'];
# $_GET['redirurl'];

#Read form parameters which we care about
# $_GET['username'];
# $_GET['password'];
# $_GET['chal'];
# $_GET['login'];
# $_GET['logout'];
# $_GET['prelogin'];
# $_GET['res'];
# $_GET['uamip'];
# $_GET['uamport'];
# $_GET['userurl'];
# $_GET['timeleft'];
# $_GET['redirurl'];

$titel = '';
$headline = '';
$bodytext = '';
$body_onload = '';
// Pass ChilliSpot params to register.php so it can build a "Back to Login" link
$_reg_qs = http_build_query([
    'challenge' => $_GET['challenge'] ?? '',
    'uamip'     => $_GET['uamip']     ?? '',
    'uamport'   => $_GET['uamport']   ?? '',
    'userurl'   => $_GET['userurl']   ?? '',
]);
$footer_text = '<center>
                  <a href="register.php?' . $_reg_qs . '">[register]</a>
                  <a href="http://www.example.de/rules.php">[terms and conditions]</a>
                </center>';

# attempt to login
if (($_GET['login'] ?? '') == 'login') {

  $hexchal = pack ("H32", $_GET['chal']);

  if (isset ($uamsecret)) {
    $newchal = pack ("H*", md5($hexchal . $uamsecret));
  } else {
    $newchal = $hexchal;
  }

  $response = md5("\0" . $_GET['Password'] . $newchal);

  $newpwd = pack("a32", $_GET['Password']);
  $pappassword = implode ("", unpack("H32", ($newpwd ^ $newchal)));
    
  $titel = 'Logging in to HotSpot'; 
  $headline = 'Logging in to HotSpot';
  $bodytext = ''; 

  print_header();

  if ((isset ($uamsecret)) && isset($userpassword)) {
    print '<meta http-equiv="refresh" content="0;url=http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logon?username=' . $_GET['UserName'] . '&password=' . $pappassword . '">';
  } else {
    print '<meta http-equiv="refresh" content="0;url=http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logon?username=' . $_GET['UserName'] . '&response=' . $response . '&userurl=' . $_GET['userurl'] . '">';
  }

   print_body();
   print_footer();

}

# 1: Login successful
if ($_GET['res'] == 'success') {

  $result = 1;
  $titel = 'Logged in to HotSpot';
  $headline = 'Logged in to HotSpot';
  # $bodytext = 'Welcome';
  
  $bodytext = '<a href="http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logoff?username='. $_GET['uid'] .'">Logout</a>';
  $body_onload = 'onLoad="javascript:popUp(' . $loginpath . '?res=popup&uamip=' . $_GET['uamip'] . '&uamport=' . $_GET['uamport'] . '&timeleft='  . $_GET['timeleft'] . ')"';

  print_header();
  print_body();
   
  if ($reply) { 
    print '<center>' . $reply . '</BR></BR></center>';
  }


  sleep(1); # Wait for acct to update
  # Get data from acct
  $sql = "SELECT acctstarttime, acctinputoctets, acctoutputoctets FROM radacct WHERE username = '" . $_GET['uid'] . "'";
  $result = mysqli_query($db,$sql);
  if (!$result) print 'unable';
  $allRows = $result->num_rows;
  $flow = 0;
  $login_at = 0;
  while ($row = mysqli_fetch_array($result)) {
    $flow = $row['acctinputoctets'] + $row['acctoutputoctets'];
    $login_at = $row['acctstarttime'];
    # print '<h6>' . $login_at . '</h6>';
  }
  # print '<h6>' . $login_at . '</h6>';

  
  # Show time left
  $sql = "SELECT value FROM radacct WHERE attribute = 'ChilliSpot-Max-Total-Octets'";
  $result = mysqli_query($db,$sql);
  $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
  $flowLimit = $row['value'];
  // print '<h3> Login at: <span id="loginat">' . $login_at .'</span> </h3>';
  // print '<h3> Access time: <span id="logintime"> 0 </span> / ' . $_GET['timeleft'] . ' seconds </h3>';
  // Show success page with live timer and traffic stats
  $safe_uamip    = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_GET['uamip']);
  $safe_uamport  = intval($_GET['uamport']);
  $safe_timeleft = intval($_GET['timeleft']);
  $uid_js        = json_encode($_GET['uid']);
  $logouturl_js  = json_encode('http://' . $safe_uamip . ':' . $safe_uamport . '/logoff');
  $flow_js       = intval($flow);
  $flowLimit_js  = intval($flowLimit);

  // Get display-only logout link for the card
  $logouturl_display = 'http://' . htmlspecialchars($safe_uamip) . ':' . $safe_uamport . '/logoff';

  print '
    <div class="hs-logo">✅</div>
    <div class="hs-title">You are online!</div>
    <div class="hs-sub">Welcome, ' . htmlspecialchars($_GET['uid'] ?? 'User') . '</div>

    <div class="stat-row">
      <div>
        <div class="stat-label">Access Time</div>
        <div class="stat-val"><span id="hs-time">0s</span></div>
      </div>
      <div style="text-align:right">
        <div class="stat-label">Limit</div>
        <div class="stat-val">' . ($safe_timeleft > 0 ? $safe_timeleft . 's' : '∞') . '</div>
      </div>
    </div>

    <div class="stat-row">
      <div>
        <div class="stat-label">Traffic Used</div>
        <div class="stat-val"><span id="hs-traffic">—</span></div>
        <div class="prog-wrap"><div class="prog-bar" id="hs-bar" style="width:0%"></div></div>
      </div>
      <div style="text-align:right">
        <div class="stat-label">Limit</div>
        <div class="stat-val" id="hs-limit">' . ($flowLimit_js > 0 ? round($flowLimit_js/1048576,1).' MB' : '∞') . '</div>
      </div>
    </div>

    <a href="' . $logouturl_display . '" class="btn-action btn-logout">Sign Out</a>

  <script>
  var username    = ' . $uid_js . ';
  var timecount   = 0;
  var timemax     = ' . $safe_timeleft . '; // 0 = no limit
  var logouturl   = ' . $logouturl_js . ';
  var trafficLim  = ' . $flowLimit_js . '; // bytes, 0 = no limit

  function fmtBytes(b) {
    if (b >= 1073741824) return (b/1073741824).toFixed(2) + " GB";
    if (b >= 1048576)    return (b/1048576).toFixed(2)    + " MB";
    if (b >= 1024)       return (b/1024).toFixed(1)       + " KB";
    return b + " B";
  }
  function fmtTime(s) {
    var h = Math.floor(s/3600),
        m = Math.floor((s%3600)/60),
        ss = s%60;
    return (h ? h+"h " : "") + (m ? m+"m " : "") + ss + "s";
  }

  function checkTraffic() {
    fetch("check_status.php?username=" + encodeURIComponent(username))
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (typeof d.traffic !== "undefined") {
          var t = parseInt(d.traffic,10);
          var lim = parseInt(d.traffic_limit,10) || trafficLim;
          document.getElementById("hs-traffic").textContent = fmtBytes(t);
          if (lim > 0) {
            var pct = Math.min(100, Math.round(t/lim*100));
            var bar = document.getElementById("hs-bar");
            bar.style.width = pct + "%";
            bar.className   = "prog-bar" + (pct>=90?" danger":(pct>=70?" warn":""));
            if (t >= lim) {
              clearInterval(timer);
              window.location.href = logouturl;
            }
          }
        }
      })
      .catch(function(){});
  }

  // Fetch traffic immediately on load
  checkTraffic();

  var timer = setInterval(function() {
    timecount++;
    document.getElementById("hs-time").textContent = fmtTime(timecount);

    // Time-based kick
    if (timemax > 0 && timecount >= timemax) {
      clearInterval(timer);
      document.getElementById("hs-time").textContent = "Time\'s up — signing out...";
      window.location.href = logouturl;
      return;
    }

    // Poll traffic every 10 seconds
    if (timecount % 10 === 0) checkTraffic();

  }, 1000);
  </script>';

  print_footer();
}

# 2: Login failed
if ($_GET['res'] == 'failed') {

  $result = 2;
  $titel = 'HotSpot Login Failed';
  $reply_msg = htmlspecialchars($_GET['reply'] ?? 'Incorrect username or password. Please try again.');
   
  print_header();
  print_body();
  // Show error hint above the login form
  print '<p class="msg-err">⚠ ' . $reply_msg . '</p>';
  print_login_form();
  print_footer();

}

# 3: Logged out
if ($_GET['res'] == 'logoff') {

  $result = 3;
  $titel = 'Logged out from HotSpot';
  $headline = 'Logged out from HotSpot';
  $bodytext = '<a href="http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/prelogin">Login</a>';
   
  print_header();
  print_body();


  # Show traffic quota
  # $sql = "SELECT id FROM radgroupreply WHERE attribute = 'ChilliSpot-Max-Total-Octets'";
  $sql = "SELECT value FROM radgroupreply WHERE attribute = 'ChilliSpot-Max-Total-Octets'";
  $result = mysqli_query($db,$sql);
  $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
  $flowLimit = $row['value'];

  $sql = "SELECT acctsessiontime, acctinputoctets, acctoutputoctets FROM radacct WHERE username =" . $_GET['UserName'];
  $result = mysqli_query($db,$sql);
  $allRows = $result->num_rows;
  $flow = 0;
  $sess = 0;
  while ($row = mysqli_fetch_array($result)) {
    $flow = $row['acctinputoctets'] + $row['acctoutputoctets'];
    $sess = $row['acctsessiontime'];
  }
  # print '<h3> Access Time: ' . $flow . ' / ' . $flowLimit . ' seconds </h3>';
  # print '<h3> Traffic : <span id="logintraffic">' . $flow . '  </span> / ' .  $flowLimit .  ' bytes </h3>';


  print_footer();

}

# 4: Tried to login while already logged in
if ($_GET['res'] == 'already') {

  $result = 4;
  $titel = 'Already logged in to HotSpot';
  $headline = 'Already logged in to HotSpot';
  $bodytext = '<a href="http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logoff">Logout</a>';
   
  print_header();
  print_body();
  print_footer();

}

# 5: Not logged in yet
if ($_GET['res'] == 'notyet') {

  $result = 5;
  $titel = 'Please Login';

  print_header();
  print_body();
  print_login_form();
  print_footer();

}

#11: Popup1
if ($_GET['res'] == 'popup1') {

  $result = 11;
  $titel = 'Logging into HotSpot';
  $headline = 'Logged in to HotSpot';
  $bodytext = 'Please wait...';
   
  print_header();
  print_body();
  print_footer();
}

#12: Popup2
if ($_GET['res'] == 'popup2') {

  $result = 12;
  $titel = 'Logged in to HotSpot';
  $headline = 'Logged in to HotSpot';
  $bodytext = '<a href="http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logoff">Logout</a>';
   
  print_header();
  print_body();
  print_footer();
  
}

#13: Popup3
if ($_GET['res'] == 'popup3') {

  $result = 13;
  $titel = 'Logged out from HotSpot';
  $headline = 'Logged out from HotSpot';
  $bodytext = '<a href="http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/prelogin">Login</a>';
   
  print_header();
  print_body();
  print_footer();

}

# 0: It was not a form request
# Send out an error message
if ($_GET['res'] == "") {

  $result = 0;
  $titel = 'What do you want here?';
  $headline = 'HotSpot Login Failed';
  $bodytext = 'Login must be performed through ChilliSpot daemon!';

  print_header();
  print_body();
  print_footer();

}

# functions

function print_header(){
  global $titel, $loginpath;

  $uamip = $_GET['uamip'];
  $uamport = $_GET['uamport'];

  print "
  <html>
    <head>
      <title>$titel</title>
        <meta http-equiv=\"Cache-control\" content=\"no-cache\">
        <meta http-equiv=\"Pragma\" content=\"no-cache\">
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=ISO-8859-1\">
        <SCRIPT LANGUAGE=\"JavaScript\">
    var blur = 0;
    var starttime = new Date();
    var startclock = starttime.getTime();
    var mytimeleft = 0;

    function doTime() {
      window.setTimeout( \"doTime()\", 1000 );
      t = new Date();
      time = Math.round((t.getTime() - starttime.getTime())/1000);
      if (mytimeleft) {
        time = mytimeleft - time;
        if (time <= 0) {
          window.location = \"$loginpath?res=popup3&uamip=$uamip&uamport=$uamport\";
        }
      }
      if (time < 0) time = 0;
      hours = (time - (time % 3600)) / 3600;
      time = time - (hours * 3600);
      mins = (time - (time % 60)) / 60;
      secs = time - (mins * 60);
      if (hours < 10) hours = \"0\" + hours;
      if (mins < 10) mins = \"0\" + mins;
      if (secs < 10) secs = \"0\" + secs;
      title = \"Online time: \" + hours + \":\" + mins + \":\" + secs;
      if (mytimeleft) {
        title = \"Remaining time: \" + hours + \":\" + mins + \":\" + secs;
      }
      if(document.all || document.getElementById){
         document.title = title;
      }
      else {   
        self.status = title;
      }
    }

    function popUp(URL) {
      if (self.name != \"chillispot_popup\") {
         chillispot_popup = window.open(URL, 'chillispot_popup', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=1,width=500,height=375');
      }
    }

    function doOnLoad(result, URL, userurl, redirurl, timeleft) {
      return;
      if (timeleft) {
        mytimeleft = timeleft;
      }
      if ((result == 1) && (self.name == \"chillispot_popup\")) {
        doTime();
      }
      if ((result == 1) && (self.name != \"chillispot_popup\")) {
        chillispot_popup = window.open(URL, 'chillispot_popup', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=1,width=500,height=375');
      }
      if ((result == 2) || result == 5) {
        document.form1.UserName.focus()
      }
      if ((result == 2) && (self.name != \"chillispot_popup\")) {
        chillispot_popup = window.open('', 'chillispot_popup', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=1,width=400,height=200');
        chillispot_popup.close();
      }
      if ((result == 12) && (self.name == \"chillispot_popup\")) {
        doTime();
        if (redirurl) {
          opener.location = redirurl;
        }
        else if (opener.home) {
          opener.home();
        }
        else {
          opener.location = \"about:home\";
        }
        self.focus();
        blur = 0;
      }
      if ((result == 13) && (self.name == \"chillispot_popup\")) {
        self.focus();
        blur = 1;
      }
    }

    function doOnBlur(result) {
      if ((result == 12) && (self.name == \"chillispot_popup\")) {
        if (blur == 0) {
          blur = 1;
          self.focus();
        }
      }
    }
  </script>";

  // ── Dark glassmorphism CSS (consistent with register.php & admin.php) ──
  print '
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    }
    /* ── Main card ── */
    .hs-card {
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 20px;
      padding: 40px 36px 36px;
      width: 380px;
      backdrop-filter: blur(16px);
      box-shadow: 0 8px 40px rgba(0,0,0,.45);
      color: #e2e8f0;
    }
    /* ── Logo / title / subtitle ── */
    .hs-logo  { text-align: center; font-size: 42px; margin-bottom: 6px; }
    .hs-title {
      text-align: center; font-size: 22px; font-weight: 700;
      color: #fff; margin-bottom: 4px;
    }
    .hs-sub   { text-align: center; color: rgba(255,255,255,.4); font-size: 13px; margin-bottom: 28px; }
    /* ── Login form ── */
    .form-group { margin-bottom: 18px; }
    .form-group label {
      display: block; font-size: 11px; font-weight: 700; letter-spacing: .6px;
      text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 7px;
    }
    .form-group input[type=text],
    .form-group input[type=password] {
      width: 100%; padding: 11px 14px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 10px; color: #e2e8f0; font-size: 15px;
      outline: none; transition: border-color .2s;
    }
    .form-group input:focus { border-color: #74b9ff; }
    input[type=submit] {
      width: 100%; padding: 12px;
      background: linear-gradient(135deg, #e94560, #c0392b);
      color: #fff; border: none; border-radius: 10px;
      font-size: 15px; font-weight: 700; cursor: pointer;
      letter-spacing: .4px; transition: opacity .2s; margin-top: 4px;
    }
    input[type=submit]:hover { opacity: .88; }
    /* ── Error message ── */
    .msg-err {
      color: #ff7090; font-size: 13px; text-align: center;
      margin-bottom: 16px;
      background: rgba(233,69,96,.12);
      border: 1px solid rgba(233,69,96,.3);
      border-radius: 8px; padding: 9px 14px;
    }
    /* ── Success page stat cards ── */
    .stat-row {
      display: flex; justify-content: space-between; align-items: flex-start;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px; padding: 14px 16px; margin-bottom: 14px;
    }
    .stat-label { font-size: 11px; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .stat-val   { font-size: 22px; font-weight: 700; color: #fff; }
    /* ── Traffic progress bar ── */
    .prog-wrap { background: rgba(255,255,255,.1); border-radius: 6px; height: 6px; margin-top: 8px; overflow: hidden; }
    .prog-bar  { height: 100%; border-radius: 6px; background: #00b894; transition: width .6s, background .4s; }
    .prog-bar.warn   { background: #fdcb6e; }
    .prog-bar.danger { background: #e17055; }
    /* ── Buttons ── */
    .btn-action {
      display: block; width: 100%; padding: 13px;
      border: none; border-radius: 12px; cursor: pointer;
      font-size: 15px; font-weight: 700; letter-spacing: .3px;
      text-align: center; text-decoration: none;
      transition: opacity .2s; margin-top: 20px;
    }
    .btn-logout {
      background: linear-gradient(135deg, #e94560, #c0392b); color: #fff;
    }
    .btn-action:hover { opacity: .88; }
    /* ── Footer links ── */
    .hs-links { text-align: center; margin-top: 22px; font-size: 13px; }
    .hs-links a { color: rgba(255,255,255,.45); text-decoration: none; margin: 0 8px; }
    .hs-links a:hover { color: #74b9ff; }
  </style>';
}

function print_body(){
  // Dark theme: body + card wrapper opened here; closed by print_footer
  print '</head><body><div class="hs-card">';
}

function print_login_form(){
  global $loginpath;
  $challenge = htmlspecialchars($_GET['challenge'] ?? '', ENT_QUOTES);
  $uamip     = htmlspecialchars($_GET['uamip']     ?? '', ENT_QUOTES);
  $uamport   = htmlspecialchars($_GET['uamport']   ?? '', ENT_QUOTES);
  $userurl   = htmlspecialchars($_GET['userurl']   ?? '', ENT_QUOTES);

  print '
    <div class="hs-logo">🛰</div>
    <div class="hs-title">HotSpot Login</div>
    <div class="hs-sub">Sign in to access the network</div>
    <form name="form1" method="get" action="' . $loginpath . '?">
      <input type="hidden" name="chal"     value="' . $challenge . '">
      <input type="hidden" name="uamip"    value="' . $uamip . '">
      <input type="hidden" name="uamport"  value="' . $uamport . '">
      <input type="hidden" name="userurl"  value="' . $userurl . '">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="UserName" maxlength="255" placeholder="Enter username" autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="Password" maxlength="255" placeholder="Enter password">
      </div>
      <input type="submit" name="login" value="Sign In →">
    </form>';
}

function print_footer(){
  global $footer_text;
  // Close the card div opened by print_body, then footer links
  print '<div class="hs-links">' . $footer_text . '</div>';
  print '</div></body></html>';
  exit(0);
}

exit(0);

?>


