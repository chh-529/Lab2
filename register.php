<?php
//    include("config.php");
   session_start();

   // 預設值（避免未定義變數警告）
   $error = '';
   $log   = '';

   function get_milliseconds()
   {
      $chunks = explode(' ', microtime());
      return sprintf('%d%d', $chunks[1], $chunks[0] * 1000);
   }

   if($_SERVER["REQUEST_METHOD"] == "POST") {
      // username and password sent from form
      $myusername = mysqli_real_escape_string($db,$_POST['username']);
      $mypassword = mysqli_real_escape_string($db,$_POST['password']);

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
?>


<html>
   
   <head>
      <title>Login Page</title>
      
      <style type = "text/css">
         body {
            font-family:Arial, Helvetica, sans-serif;
            font-size:14px;
         }
         label {
            font-weight:bold;
            width:100px;
            font-size:14px;
         }
         .box {
            border:#666666 solid 1px;
         }
      </style>
      
   </head>
   
   <body bgcolor = "#FFFFFF">
	
      <div align = "center">
         <div style = "width:300px; border: solid 1px #333333; " align = "left">
            <div style = "background-color:#333333; color:#FFFFFF; padding:3px;"><b>Login</b></div>
				
            <div style = "margin:30px">
               
               <form action = "" method = "post">
                  <label>UserName  :</label><input type = "text" name = "username" class = "box"/><br /><br />
                  <label>Password  :</label><input type = "password" name = "password" class = "box" /><br/><br />
		  <input type = "submit" value = "Register" name = "command"/>
       
               </form>
               

               <div style = "font-size:11px; color:#cc0000; margin-top:10px"><?php echo $error; ?></div>
               <div style = "font-size:11px; color:#00cc00; margin-top:10px"><?php echo $log; ?></div>
            </div>
				
         </div>
			
      </div>

   </body>
</html>
