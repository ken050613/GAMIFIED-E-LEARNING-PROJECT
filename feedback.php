<?php
session_start();
require 'db_config.php';

if (!in_array($_SESSION['user_role'], ['student', 'lecturer'])) {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $msg = $_POST['message'];
  $stmt = $conn->prepare("INSERT INTO feedback (user_id, message) VALUES (?, ?)");
  $stmt->bind_param("is", $user_id, $msg);
  $stmt->execute();
  $success = "Feedback submitted!";
}

// Fetch user's feedback history
$history = $conn->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY submitted_at DESC");
$history->bind_param("i", $user_id);
$history->execute();
$results = $history->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Feedback</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Submit Feedback</h2>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>

  <?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>

  <form method="POST" class="mb-4">
    <textarea name="message" class="form-control mb-2" rows="4" placeholder="Your feedback..." required></textarea>
    <button class="btn btn-primary">Send Feedback</button>
  </form>

  <h4>Your Feedback History</h4>
  <ul class="list-group">
    <?php while ($fb = $results->fetch_assoc()): ?>
      <li class="list-group-item">
        <?= htmlspecialchars($fb['message']) ?>
        <br><small class="text-muted"><?= $fb['submitted_at'] ?></small>
      </li>
    <?php endwhile; ?>
  </ul>
</div>
</body>
</html>
