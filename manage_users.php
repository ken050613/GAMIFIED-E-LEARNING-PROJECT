<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit();
}

// Handle user deletion
if (isset($_GET['delete'])) {
  $uid = intval($_GET['delete']);
  // Prevent admin from deleting themselves
  if ($uid !== $_SESSION['user_id']) {
    $conn->query("DELETE FROM users WHERE id = $uid");
    $success = "User deleted.";
  } else {
    $error = "You cannot delete your own account.";
  }
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
  $uid = intval($_POST['user_id']);
  $role = $_POST['role'];
  if (in_array($role, ['admin', 'lecturer', 'student'])) {
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $role, $uid);
    $stmt->execute();
    $success = "User role updated.";
  }
}

// Fetch all users
$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Manage Users</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <?php if (isset($success)): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>TP Number</th>
          <th>Role</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($user = $users->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['tp_number']) ?></td>
            <td>
              <form method="POST" class="d-flex align-items-center gap-2 mb-0">
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                <select name="role" class="form-select form-select-sm" style="width:auto;display:inline-block;">
                  <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
                  <option value="lecturer" <?= $user['role']==='lecturer'?'selected':'' ?>>Lecturer</option>
                  <option value="student" <?= $user['role']==='student'?'selected':'' ?>>Student</option>
                </select>
                <button type="submit" name="update_role" class="btn btn-sm btn-primary">Update</button>
              </form>
            </td>
            <td>
              <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
              <?php else: ?>
                <span class="text-muted">(You)</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</body>
</html> 