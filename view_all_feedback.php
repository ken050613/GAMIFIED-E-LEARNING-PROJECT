<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit();
}

// Handle feedback deletion
if (isset($_GET['delete'])) {
  $fid = intval($_GET['delete']);
  $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
  $stmt->bind_param("i", $fid);
  $stmt->execute();
  $success = "Feedback deleted successfully.";
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';

// Build the query based on filters
$query = "
  SELECT f.*, u.name, u.role, u.email
  FROM feedback f
  JOIN users u ON f.user_id = u.id
  WHERE 1=1
";

if ($role_filter !== 'all') {
  $query .= " AND u.role = ?";
}

if ($date_filter === 'today') {
  $query .= " AND DATE(f.submitted_at) = CURDATE()";
} elseif ($date_filter === 'week') {
  $query .= " AND f.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
  $query .= " AND f.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$query .= " ORDER BY f.submitted_at DESC";

$stmt = $conn->prepare($query);

if ($role_filter !== 'all') {
  $stmt->bind_param("s", $role_filter);
}

$stmt->execute();
$feedbacks = $stmt->get_result();

// Get feedback statistics
$stats = $conn->query("
  SELECT 
    COUNT(*) as total_feedback,
    COUNT(CASE WHEN u.role = 'student' THEN 1 END) as student_feedback,
    COUNT(CASE WHEN u.role = 'lecturer' THEN 1 END) as lecturer_feedback,
    COUNT(CASE WHEN DATE(f.submitted_at) = CURDATE() THEN 1 END) as today_feedback
  FROM feedback f
  JOIN users u ON f.user_id = u.id
")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
  <title>View All Feedback</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Feedback Management</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white">
          <div class="card-body">
            <h5 class="card-title">Total Feedback</h5>
            <h2><?= $stats['total_feedback'] ?></h2>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success text-white">
          <div class="card-body">
            <h5 class="card-title">Student Feedback</h5>
            <h2><?= $stats['student_feedback'] ?></h2>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white">
          <div class="card-body">
            <h5 class="card-title">Lecturer Feedback</h5>
            <h2><?= $stats['lecturer_feedback'] ?></h2>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning text-dark">
          <div class="card-body">
            <h5 class="card-title">Today's Feedback</h5>
            <h2><?= $stats['today_feedback'] ?></h2>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body">
        <form method="GET" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Filter by Role</label>
            <select name="role" class="form-select">
              <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
              <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students</option>
              <option value="lecturer" <?= $role_filter === 'lecturer' ? 'selected' : '' ?>>Lecturers</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Filter by Date</label>
            <select name="date" class="form-select">
              <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
              <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
              <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
              <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">&nbsp;</label>
            <button type="submit" class="btn btn-primary d-block">Apply Filters</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Feedback List -->
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Message</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($feedback = $feedbacks->fetch_assoc()): ?>
                <tr>
                  <td>
                    <?= htmlspecialchars($feedback['name']) ?>
                    <br>
                    <small class="text-muted"><?= htmlspecialchars($feedback['email']) ?></small>
                  </td>
                  <td><?= ucfirst($feedback['role']) ?></td>
                  <td><?= nl2br(htmlspecialchars($feedback['message'])) ?></td>
                  <td><?= date('M d, Y H:i', strtotime($feedback['submitted_at'])) ?></td>
                  <td>
                    <a href="?delete=<?= $feedback['id'] ?>" 
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this feedback?')">
                      Delete
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 