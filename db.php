<?php
// db.php

$servername = "localhost";
$username = "uogzjyyjih8ek";
$password = "ahsan1234";
$dbname = "dby4fy28ykjlkf";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
