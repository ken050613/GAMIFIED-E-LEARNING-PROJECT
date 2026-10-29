<?php
session_start();
if (isset($_SESSION['user_id'])) {
  header("Location: dashboard.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DQuizz - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container d-flex flex-column justify-content-center align-items-center min-vh-100">
    <div class="pixel-card p-4 w-100" style="max-width: 400px;">
      <div class="text-center mb-4">
        <img src="images/dquizz_logo.jpg" alt="DQuizz Logo" class="pixel-logo">
        <h2 class="pixel-heading mb-0">DQuizz</h2>
        <small class="text-muted" style="font-family: 'Press Start 2P', Arial, sans-serif;">Gamified Learning Platform</small>
      </div>
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger text-center">Invalid credentials</div>
      <?php endif; ?>
      <form action="login_process.php" method="POST" class="mx-auto" style="max-width: 400px;">
        <div class="mb-3">
          <label for="email" class="form-label pixel-label">Email address</label>
          <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
        </div>
        <div class="mb-3">
          <label for="password" class="form-label pixel-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
        </div>
        <button type="submit" class="btn pixel-btn w-100">LOGIN</button>
      </form>
      <div class="text-center mt-3">
        <a href="register.php" class="pixel-link">Don't have an account? Register for free!</a>
      </div>
    </div>
  </div>
</body>
</html>
