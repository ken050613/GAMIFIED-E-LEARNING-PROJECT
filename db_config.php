<?php
$host = "localhost";
$dbname = "elearning_db";
$user = "root";
$password = "";

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>
