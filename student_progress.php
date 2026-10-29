<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'lecturer') {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Fetch modules created by this lecturer
$stmt = $conn->prepare("
  SELECT m.*, 
         (SELECT COUNT(*) FROM module_participants WHERE module_id = m.id AND role = 'student') as student_count
  FROM modules m
  WHERE m.created_by = ?
  ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$modules = $stmt->get_result();

// If a specific module is selected
$selected_module = null;
$module_students = null;
if (isset($_GET['module_id'])) {
  $module_id = intval($_GET['module_id']);
  
  // Verify module belongs to this lecturer
  $stmt = $conn->prepare("SELECT * FROM modules WHERE id = ? AND created_by = ?");
  $stmt->bind_param("ii", $module_id, $user_id);
  $stmt->execute();
  $selected_module = $stmt->get_result()->fetch_assoc();
  
  if ($selected_module) {
    // Fetch students and their progress in this module
    $stmt = $conn->prepare("
      SELECT u.id, u.name,
             COUNT(DISTINCT q.id) as total_quizzes,
             COUNT(DISTINCT qr.id) as quizzes_attempted,
             SUM(qr.score) as total_score,
             SUM(qr.total) as total_questions,
             MAX(qr.taken_at) as last_attempt
      FROM users u
      JOIN module_participants mp ON u.id = mp.user_id
      LEFT JOIN quizzes q ON q.module_id = mp.module_id
      LEFT JOIN quiz_results qr ON qr.quiz_id = q.id AND qr.user_id = u.id
      WHERE mp.module_id = ? AND mp.role = 'student'
      GROUP BY u.id
      ORDER BY u.name
    ");
    $stmt->bind_param("i", $module_id);
    $stmt->execute();
    $module_students = $stmt->get_result();
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Student Progress</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Student Progress Reports</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h4>Your Modules</h4>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Module Title</th>
                    <th>Total Students</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($module = $modules->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($module['title']) ?></td>
                      <td><?= $module['student_count'] ?></td>
                      <td>
                        <a href="?module_id=<?= $module['id'] ?>" class="btn btn-sm btn-primary">View Progress</a>
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

    <?php if ($selected_module && $module_students): ?>
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-header">
              <h4>Student Progress: <?= htmlspecialchars($selected_module['title']) ?></h4>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Quizzes Attempted</th>
                      <th>Overall Score</th>
                      <th>Last Attempt</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($student = $module_students->fetch_assoc()): ?>
                      <tr>
                        <td><?= htmlspecialchars($student['name']) ?></td>
                        <td>
                          <?= $student['quizzes_attempted'] ?>/<?= $student['total_quizzes'] ?>
                          (<?= round(($student['quizzes_attempted']/$student['total_quizzes']) * 100, 1) ?>%)
                        </td>
                        <td>
                          <?php if ($student['total_questions'] > 0): ?>
                            <?= $student['total_score'] ?>/<?= $student['total_questions'] ?>
                            (<?= round(($student['total_score']/$student['total_questions']) * 100, 1) ?>%)
                          <?php else: ?>
                            No attempts
                          <?php endif; ?>
                        </td>
                        <td><?= $student['last_attempt'] ? date('Y-m-d H:i', strtotime($student['last_attempt'])) : 'Never' ?></td>
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