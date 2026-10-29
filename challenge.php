<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'student') {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Fetch students to challenge
$students = $conn->query("SELECT id, name FROM users WHERE role = 'student' AND id != $user_id");

// Fetch available quizzes
$quizzes = $conn->query("SELECT id, title FROM quizzes");

// Handle new challenge creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $opponent = $_POST['opponent'];
  $quiz = $_POST['quiz'];

  $stmt = $conn->prepare("INSERT INTO challenges (challenger_id, opponent_id, quiz_id) VALUES (?, ?, ?)");
  $stmt->bind_param("iii", $user_id, $opponent, $quiz);
  $stmt->execute();
  
  // Get challenger's name for the notification
  $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $challenger = $stmt->get_result()->fetch_assoc();
  
  // Create notification for opponent
  $message = $challenger['name'] . " has challenged you to a quiz!";
  $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'challenge', ?)");
  $stmt->bind_param("is", $opponent, $message);
  $stmt->execute();

  $success = "Challenge sent!";
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>1v1 Challenge</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>1v1 Challenge</h2>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>

  <?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>

  <form method="POST" class="mb-4">
    <div class="mb-3">
      <label>Select Opponent</label>
      <select name="opponent" class="form-select" required>
        <?php while ($row = $students->fetch_assoc()): ?>
          <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>Select Quiz</label>
      <select name="quiz" class="form-select" required>
        <?php while ($q = $quizzes->fetch_assoc()): ?>
          <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['title']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Send Challenge</button>
  </form>
</div>
</body>
</html>
