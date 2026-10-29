<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit();
}

// Leaderboard: total correct answers per student
$sql = "SELECT u.name, SUM(r.score) as total_correct, COUNT(r.id) as attempts
        FROM quiz_results r
        JOIN users u ON r.user_id = u.id
        WHERE u.role = 'student'
        GROUP BY r.user_id
        ORDER BY total_correct DESC, attempts DESC, u.name ASC
        LIMIT 50";
$results = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Scoreboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
  <div class="container mt-4">
    <h2>Quiz Leaderboard</h2>
    <table class="table table-bordered mt-3">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Student</th>
          <th>Total Correct Answers</th>
          <th>Quiz Attempts</th>
        </tr>
      </thead>
      <tbody>
        <?php $rank = 1; ?>
        <?php while ($row = $results->fetch_assoc()): ?>
          <tr>
            <td><?= $rank++ ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= $row['total_correct'] ?></td>
            <td><?= $row['attempts'] ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>
</body>
</html>
