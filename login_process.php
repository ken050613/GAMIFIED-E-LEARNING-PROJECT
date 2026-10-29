<?php
session_start();
require 'db_config.php';

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $user = $result->fetch_assoc();
  
  // Special case for admin users with plain-text passwords
  if ($user['role'] === 'admin' && $password === $user['password']) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    header("Location: dashboard.php");
    exit();
  }
  // Normal case for other users with hashed passwords
  else if (password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    header("Location: dashboard.php");
    exit();
  }
}
header("Location: index.php?error=1");
exit();
?>
