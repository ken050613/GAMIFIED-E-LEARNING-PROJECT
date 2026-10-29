<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'student') {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Get balance
$balStmt = $conn->prepare("SELECT balance FROM user_currency WHERE user_id = ?");
$balStmt->bind_param("i", $user_id);
$balStmt->execute();
$balResult = $balStmt->get_result();
$balance = $balResult->fetch_assoc()['balance'] ?? 0;

// Get transaction history
$tx = $conn->prepare("SELECT * FROM currency_transactions WHERE user_id = ? ORDER BY created_at DESC");
$tx->bind_param("i", $user_id);
$tx->execute();
$history = $tx->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Currency Balance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Currency Wallet</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <div class="alert alert-info">Current Balance: <strong><?= $balance ?></strong> coins</div>

    <h4>Transaction History</h4>
    <table class="table table-bordered mt-3">
      <thead>
        <tr>
          <th>Type</th>
          <th>Amount</th>
          <th>Description</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $history->fetch_assoc()): ?>
          <tr>
            <td><?= ucfirst($row['type']) ?></td>
            <td><?= $row['amount'] ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= $row['created_at'] ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
