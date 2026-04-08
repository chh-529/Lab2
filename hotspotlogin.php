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
$footer_text = '<center>
                  <a href="register.php">[register]</a> 
                  <a href="http://www.example.de/rules.php">[terms and conditions]</a>  
                </center>';

# attempt to login
if ($_GET['login'] == login) {

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
if ($_GET['res'] == success) {

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
  $safe_uamip   = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_GET['uamip']);
  $safe_uamport  = intval($_GET['uamport']);
  $safe_timeleft = intval($_GET['timeleft']);
  $uid_js        = json_encode($_GET['uid']);
  $logouturl_js  = json_encode('http://' . $safe_uamip . ':' . $safe_uamport . '/logoff');
  $login_at_js   = htmlspecialchars($login_at, ENT_QUOTES);

  print '<center><h3> Access time: <span id="logintime2"> accessing... </span> / ' . $safe_timeleft . ' seconds </h3></center>';
  print '<script>
var username   = ' . $uid_js . ';
var timecount  = 0;
var time_at    = new Date("' . $login_at_js . ' +0800");
var timemax    = ' . $safe_timeleft . ';  // remaining seconds from ChilliSpot
var logouturl  = ' . $logouturl_js . ';

// Poll server for current traffic; kick user if limit exceeded
function checkTraffic() {
  fetch("check_status.php?username=" + encodeURIComponent(username))
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (typeof data.traffic !== "undefined") {
        document.getElementById("logintraffic").innerHTML = data.traffic;
        if (data.traffic_limit > 0 && data.traffic >= data.traffic_limit) {
          clearInterval(timer);
          document.getElementById("logintraffic").innerHTML =
            data.traffic + " (已達上限，即將登出...）";
          window.location.href = logouturl;
        }
      }
    })
    .catch(function() {});
}

var timer = setInterval(function() {
  timecount += 1;

  // Time-based kick: redirect to logout when session time is up
  if (timemax > 0 && timecount >= timemax) {
    clearInterval(timer);
    document.getElementById("logintime2").innerHTML = "時間到，即將登出...";
    window.location.href = logouturl;
    return;
  }

  var tim = Math.floor((new Date() - time_at) / 1000);
  document.getElementById("logintime2").innerHTML = tim;

  // Traffic-based kick: poll every 10 seconds
  if (timecount % 10 === 0) {
    checkTraffic();
  }
}, 1000);
</script>';

  # Show traffic quota — group default, then override with per-user limit if set
  $sql = "SELECT value FROM radgroupreply WHERE attribute = 'ChilliSpot-Max-Total-Octets'";
  $result = mysqli_query($db, $sql);
  $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $flowLimit = $row['value'] ?? 0;

  # Per-user traffic limit in radreply takes precedence over group limit
  $uid_escaped = mysqli_real_escape_string($db, $_GET['uid']);
  $sql = "SELECT value FROM radreply
          WHERE username='$uid_escaped'
            AND attribute='ChilliSpot-Max-Total-Octets'
          LIMIT 1";
  $res_personal = mysqli_query($db, $sql);
  if ($res_personal && $row_personal = mysqli_fetch_assoc($res_personal)) {
    $flowLimit = $row_personal['value'];
  }

  print '<center><h3> Traffic : <span id="logintraffic">' . $flow . '</span> / ' . intval($flowLimit) . ' bytes </h3></center>';

  


  print_footer();
}

# 2: Login failed
if ($_GET['res'] == failed) {

  $result = 2;
  $titel = 'HotSpot Login Failed';
  $headline = 'HotSpot Login Failed';
  $bodytext = 'Sorry, try again<br>';
   
  print_header();
  print_body();

  if ($_GET['reply']) {
    print '<center>' . $_GET['reply'] . '</center>';
  }
   
  print_login_form();
  print_footer();

}

# 3: Logged out
if ($_GET['res'] == logoff) {

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
if ($_GET['res'] == already) {

  $result = 4;
  $titel = 'Already logged in to HotSpot';
  $headline = 'Already logged in to HotSpot';
  $bodytext = '<a href="http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logoff">Logout</a>';
   
  print_header();
  print_body();
  print_footer();

}

# 5: Not logged in yet
if ($_GET['res'] == notyet) {

  $result = 5;
  $titel = 'Please login';
  $headline = 'Please login to HotSpot';
  $bodytext = 'Please login.<br>';
   
  print_header();
  print_body();
  print_login_form();
  print_footer();

}

#11: Popup1
if ($_GET['res'] == popup1) {

  $result = 11;
  $titel = 'Logging into HotSpot';
  $headline = 'Logged in to HotSpot';
  $bodytext = 'Please wait...';
   
  print_header();
  print_body();
  print_footer();
}

#12: Popup2
if ($_GET['res'] == popup2) {

  $result = 12;
  $titel = 'Logged in to HotSpot';
  $headline = 'Logged in to HotSpot';
  $bodytext = '<a href="http://' . $_GET['uamip'] . ':' . $_GET['uamport'] . '/logoff">Logout</a>';
   
  print_header();
  print_body();
  print_footer();
  
}

#13: Popup3
if ($_GET['res'] == popup3) {

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
}

function print_body(){
  global $headline, $bodytext, $body_onload, $result, $loginpath;
  
  $uamip = $_GET['uamip'];
  $uamport = $_GET['uamport'];
  $userurl = $_GET['userurl'];
  $redirurl = $_GET['redirurl'];
  $userurldecode = $_GET['userurl'];
  $redirurldecode = $_GET['redirurl'];
  $timeleft = $_GET['timeleft'];
  
  print "
  </head>
    <body onLoad=\"javascript:doOnLoad($result, '$loginpath?res=popup2&uamip=$uamip&uamport=$uamport&userurl=$userurl&redirurl=$redirurl&timeleft=$timeleft','$userurldecode', '$redirurldecode', '$timeleft')\" onBlur = \"javascript:doOnBlur($result)\" bgColor = '#c0d8f4'>
      <h1 style=\"text-align: center;\">$headline</h1>
      <center>$bodytext</center><br>";

# begin debugging
#  print '<center>THE INPUT (for debugging):<br>';
#
#    foreach ($_GET as $key => $value) {
#      print $key . '=' . $value . '<br>';
#    }
#
#  print '<br></center>';
# end debugging

}

function print_login_form(){
  global $loginpath;
  print '<FORM name="form1" METHOD="get" action="' . $loginpath . '?">
          <INPUT TYPE="HIDDEN" NAME="chal" VALUE="' . $_GET['challenge'] . '">
          <INPUT TYPE="HIDDEN" NAME="uamip" VALUE="' . $_GET['uamip'] . '">
          <INPUT TYPE="HIDDEN" NAME="uamport" VALUE="' . $_GET['uamport'] . '">
          <INPUT TYPE="HIDDEN" NAME="userurl" VALUE="' . $_GET['userurl'] . '">
          <center>
          <table border="0" cellpadding="5" cellspacing="0" style="width: 217px;">
          <tbody>
            <tr>
              <td align="right">Username:</td>
              <td><input type="text" name="UserName" size="20" maxlength="255"></td>
            </tr>
            <tr>
              <td align="right">Password:</td>
              <td><input type="password" name="Password" size="20" maxlength="255"></td>
            </tr>
            <tr>
              <td align="center" colspan="2" height="23"><input type="submit" name="login" value="login"></td>
          </tr>
        </tbody>
        </table>
        </center>
      </form>';
}

function print_footer(){
  global $footer_text;
  print $footer_text . '</body></html>';
  exit(0);
}

exit(0);

?>


