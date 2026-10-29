<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'student') {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Get user's current balance
$balStmt = $conn->prepare("SELECT balance FROM user_currency WHERE user_id = ?");
$balStmt->bind_param("i", $user_id);
$balStmt->execute();
$balance = $balStmt->get_result()->fetch_assoc()['balance'] ?? 0;

// Handle avatar purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_avatar'])) {
  $avatar_id = intval($_POST['avatar_id']);
  $avatar_price = intval($_POST['avatar_price']);
  
  // Check if user has enough coins
  if ($balance >= $avatar_price) {
    // Check if user already owns this avatar
    $checkStmt = $conn->prepare("SELECT id FROM user_avatars WHERE user_id = ? AND avatar_id = ?");
    $checkStmt->bind_param("ii", $user_id, $avatar_id);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows === 0) {
      // Begin transaction
      $conn->begin_transaction();
      
      try {
        // Deduct coins
        $new_balance = $balance - $avatar_price;
        $updateBal = $conn->prepare("UPDATE user_currency SET balance = ? WHERE user_id = ?");
        $updateBal->bind_param("ii", $new_balance, $user_id);
        $updateBal->execute();
        
        // Add avatar to user's collection
        $addAvatar = $conn->prepare("INSERT INTO user_avatars (user_id, avatar_id) VALUES (?, ?)");
        $addAvatar->bind_param("ii", $user_id, $avatar_id);
        $addAvatar->execute();
        
        // Add transaction record
        $txDesc = "Purchased avatar #" . $avatar_id;
        $addTx = $conn->prepare("INSERT INTO currency_transactions (user_id, type, amount, description) VALUES (?, 'purchase', ?, ?)");
        $addTx->bind_param("iis", $user_id, $avatar_price, $txDesc);
        $addTx->execute();
        
        $conn->commit();
        $success = "Avatar purchased successfully!";
        $balance = $new_balance;
      } catch (Exception $e) {
        $conn->rollback();
        $error = "Transaction failed. Please try again.";
      }
    } else {
      $error = "You already own this avatar!";
    }
  } else {
    $error = "Not enough coins to purchase this avatar!";
  }
}

// Get user's owned avatars
$ownedStmt = $conn->prepare("SELECT avatar_id FROM user_avatars WHERE user_id = ?");
$ownedStmt->bind_param("i", $user_id);
$ownedStmt->execute();
$owned_avatars = $ownedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$owned_avatar_ids = array_column($owned_avatars, 'avatar_id');

// Get all available avatars
$avatars = $conn->query("SELECT * FROM avatars ORDER BY price ASC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Avatar Store</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Avatar Store</h2>
      <div class="d-flex align-items-center">
        <div class="me-3">
          <span class="badge bg-warning text-dark">
            <i class="fas fa-coins"></i> <?= $balance ?> Coins
          </span>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
      </div>
    </div>

    <?php if (isset($success)): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row">
      <?php while ($avatar = $avatars->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <img src="<?= htmlspecialchars($avatar['image_url']) ?>" class="card-img-top" alt="Avatar">
            <div class="card-body">
              <h5 class="card-title"><?= htmlspecialchars($avatar['name']) ?></h5>
              <p class="card-text"><?= htmlspecialchars($avatar['description']) ?></p>
              <div class="d-flex justify-content-between align-items-center">
                <span class="badge bg-warning text-dark">
                  <i class="fas fa-coins"></i> <?= $avatar['price'] ?> Coins
                </span>
                <?php if (in_array($avatar['id'], $owned_avatar_ids)): ?>
                  <button class="btn btn-success" disabled>Owned</button>
                <?php else: ?>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="avatar_id" value="<?= $avatar['id'] ?>">
                    <input type="hidden" name="avatar_price" value="<?= $avatar['price'] ?>">
                    <button type="submit" name="buy_avatar" class="btn btn-primary" 
                            <?= $balance < $avatar['price'] ? 'disabled' : '' ?>>
                      Purchase
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</body>
</html> 