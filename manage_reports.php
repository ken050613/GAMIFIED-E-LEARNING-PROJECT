<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit();
}

// Count users
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$lecturers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer'")->fetch_assoc()['count'];

// Quiz stats
$totalQuizzes = $conn->query("SELECT COUNT(*) as count FROM quizzes")->fetch_assoc()['count'];
$totalAttempts = $conn->query("SELECT COUNT(*) as count FROM quiz_results")->fetch_assoc()['count'];
$avgScoreRow = $conn->query("SELECT AVG(score/total) as avg FROM quiz_results")->fetch_assoc();
$avgScore = round($avgScoreRow['avg'] * 100, 2);

// Most active students (top 5 by attempts)
$topStudents = $conn->query("
  SELECT u.name, COUNT(*) as attempts
  FROM quiz_results r
  JOIN users u ON r.user_id = u.id
  GROUP BY r.user_id
  ORDER BY attempts DESC
  LIMIT 5
");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Reports</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Platform Reports</h2>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>

  <div class="row mb-4">
    <div class="col-md-4">
      <div class="alert alert-info">Total Users: <strong><?= $totalUsers ?></strong></div>
      <div class="alert alert-secondary">Students: <?= $students ?></div>
      <div class="alert alert-secondary">Lecturers: <?= $lecturers ?></div>
    </div>
    <div class="col-md-4">
      <div class="alert alert-info">Total Quizzes: <strong><?= $totalQuizzes ?></strong></div>
      <div class="alert alert-secondary">Quiz Attempts: <?= $totalAttempts ?></div>
      <div class="alert alert-success">Avg. Score: <?= $avgScore ?>%</div>
    </div>
  </div>

  <h4>Top 5 Active Students</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th>Student</th>
        <th>Attempts</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $topStudents->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= $row['attempts'] ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
