<?php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'radius');
   define('DB_PASSWORD', 'lab2lab2');
   define('DB_DATABASE', 'radius');
   $db = mysqli_connect(DB_SERVER,DB_USERNAME,DB_PASSWORD,DB_DATABASE);
   # if ($db) echo "OK";
   # else echo "Not OK";
?>
