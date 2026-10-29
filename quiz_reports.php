<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'lecturer') {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Fetch quizzes created by this lecturer
$stmt = $conn->prepare("
  SELECT q.*, 
         COUNT(DISTINCT qr.user_id) as total_students,
         COUNT(qr.id) as total_attempts,
         AVG(qr.score/qr.total) as avg_score
  FROM quizzes q
  LEFT JOIN quiz_results qr ON q.id = qr.quiz_id
  WHERE q.created_by = ?
  GROUP BY q.id
  ORDER BY q.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$quizzes = $stmt->get_result();

// If a specific quiz is selected
$selected_quiz = null;
$quiz_results = null;
if (isset($_GET['quiz_id'])) {
  $quiz_id = intval($_GET['quiz_id']);
  
  // Verify quiz belongs to this lecturer
  $stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND created_by = ?");
  $stmt->bind_param("ii", $quiz_id, $user_id);
  $stmt->execute();
  $selected_quiz = $stmt->get_result()->fetch_assoc();
  
  if ($selected_quiz) {
    // Fetch detailed results for this quiz
    $stmt = $conn->prepare("
      SELECT qr.*, u.name as student_name
      FROM quiz_results qr
      JOIN users u ON qr.user_id = u.id
      WHERE qr.quiz_id = ?
      ORDER BY qr.taken_at DESC
    ");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz_results = $stmt->get_result();
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Quiz Reports</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Quiz Performance Reports</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h4>Your Quizzes Overview</h4>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Quiz Title</th>
                    <th>Total Students</th>
                    <th>Total Attempts</th>
                    <th>Average Score</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($quiz['title']) ?></td>
                      <td><?= $quiz['total_students'] ?></td>
                      <td><?= $quiz['total_attempts'] ?></td>
                      <td><?= round($quiz['avg_score'] * 100, 1) ?>%</td>
                      <td>
                        <a href="?quiz_id=<?= $quiz['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($selected_quiz && $quiz_results): ?>
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-header">
              <h4>Detailed Results: <?= htmlspecialchars($selected_quiz['title']) ?></h4>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Score</th>
                      <th>Percentage</th>
                      <th>Date Taken</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($result = $quiz_results->fetch_assoc()): ?>
                      <tr>
                        <td><?= htmlspecialchars($result['student_name']) ?></td>
                        <td><?= $result['score'] ?>/<?= $result['total'] ?></td>
                        <td><?= round(($result['score']/$result['total']) * 100, 1) ?>%</td>
                        <td><?= $result['taken_at'] ?></td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html> 