<?php
require 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = $_POST['name'];
  $tp_number = $_POST['tp_number'];
  $email = $_POST['email'];
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $role = $_POST['role']; // Only allow student from frontend for now

  // Validate passwords match
  if ($password !== $confirm_password) {
    $error = "Passwords do not match.";
  } else {
    $password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, tp_number, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $tp_number, $email, $password, $role);
    if ($stmt->execute()) {
      header("Location: index.php?registered=1");
      exit();
    } else {
      $error = "Email already registered.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - Gamified E-Learning</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="text-center mb-0">Register</h2>
      <a href="index.php" class="btn btn-secondary">Back to Login</a>
    </div>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="mx-auto" style="max-width: 400px;">
  <div class="mb-3">
    <label>Name</label>
    <input type="text" name="name" class="form-control" required>
  </div>
  <div class="mb-3">
    <label>TP Number</label>
    <input type="text" name="tp_number" class="form-control">
  </div>
  <div class="mb-3">
    <label>Email</label>
    <input type="email" name="email" class="form-control" required>
  </div>
  <div class="mb-3">
    <label>Password</label>
    <input type="password" name="password" class="form-control" required>
  </div>
  <div class="mb-3">
    <label>Confirm Password</label>
    <input type="password" name="confirm_password" class="form-control" required>
  </div>
  <div class="mb-3">
    <label for="role">Register As</label>
    <select name="role" id="role" class="form-select" required>
      <option value="student">Student</option>
      <option value="lecturer">Lecturer</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary w-100">Register</button>
</form>
  </div>
</body>
</html>
