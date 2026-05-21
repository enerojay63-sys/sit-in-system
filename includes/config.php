<?php
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','ccs_sitin');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error)
  die('<p style="color:red;font-family:sans-serif;padding:20px">DB Error: '.$conn->connect_error.'</p>');
$conn->set_charset('utf8mb4');