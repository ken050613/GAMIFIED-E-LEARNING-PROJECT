<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit();
}

if (isset($_GET['delete'])) {
  $fid = intval($_GET['delete']);
  $conn->query("DELETE FROM feedback WHERE id = $fid");
}

// Fetch all feedback
$feedbacks = $conn->query("SELECT f.id, f.message, f.submitted_at, u.name FROM feedback f JOIN users u ON f.user_id = u.id ORDER BY f.submitted_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Feedback</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
<div class="container mt-4">
  <h2>User Feedback</h2>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th>User</th>
        <th>Message</th>
        <th>Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $feedbacks->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['message']) ?></td>
          <td><?= $row['submitted_at'] ?></td>
          <td><a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger">Delete</a></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>
</body>
</html>
