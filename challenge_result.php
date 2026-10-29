<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit();
}

$user_id = $_SESSION['user_id'];
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate user is part of this challenge
$stmt = $conn->prepare("SELECT * FROM challenges WHERE id = ? AND (challenger_id = ? OR opponent_id = ?)");
$stmt->bind_param("iii", $challenge_id, $user_id, $user_id);
$stmt->execute();
$challenge = $stmt->get_result()->fetch_assoc();

if (!$challenge) {
  die('Challenge not found or access denied.');
}

// Get attempts
$results = $conn->query("SELECT * FROM challenge_attempts WHERE challenge_id = $challenge_id");

?><!DOCTYPE html>
<html>
<head>
  <title>1v1 Challenge Result</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container mt-5">
  <div class="card mx-auto" style="max-width:400px;">
    <div class="card-body text-center">
      <?php if ($results->num_rows == 2):
        $attempts = [];
        while ($r = $results->fetch_assoc()) $attempts[] = $r;
        // Determine winner
        if ($attempts[0]['score'] > $attempts[1]['score']) {
          $winner = $attempts[0]['user_id'];
          $loser = $attempts[1]['user_id'];
        } elseif ($attempts[0]['score'] < $attempts[1]['score']) {
          $winner = $attempts[1]['user_id'];
          $loser = $attempts[0]['user_id'];
        } else {
          $winner = $loser = null; // tie
        }
        // Update challenge status if not already completed
        if ($challenge['status'] !== 'completed') {
          if ($winner && $loser) {
            $conn->query("UPDATE challenges SET winner_id = $winner, loser_id = $loser, status = 'completed' WHERE id = $challenge_id");
            // Reward winner, deduct from loser
            require_once 'currency_utils.php';
            updateCurrency($conn, $winner, 10, 'gain', '1v1 Challenge Win');
            updateCurrency($conn, $loser, -5, 'spend', '1v1 Challenge Loss');
            // Notifications
            $stmt = $conn->prepare("SELECT name FROM users WHERE id IN (?, ?)");
            $stmt->bind_param("ii", $winner, $loser);
            $stmt->execute();
            $names = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $winner_name = $names[0]['name'];
            $loser_name = $names[1]['name'];
            $winner_msg = "You won the challenge against " . $loser_name . "! (+10 coins)";
            $loser_msg = "You lost the challenge against " . $winner_name . " (-5 coins)";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'result', ?)");
            $stmt->bind_param("is", $winner, $winner_msg);
            $stmt->execute();
            $stmt->bind_param("is", $loser, $loser_msg);
            $stmt->execute();
          } else {
            $conn->query("UPDATE challenges SET status = 'completed' WHERE id = $challenge_id");
          }
        }
        $user_attempt = $attempts[0]['user_id'] == $user_id ? $attempts[0] : $attempts[1];
        $opponent_attempt = $attempts[0]['user_id'] == $user_id ? $attempts[1] : $attempts[0];
        $user_score = $user_attempt['score'];
        $user_total = $user_attempt['total'];
        $opponent_score = $opponent_attempt['score'];
        $opponent_total = $opponent_attempt['total'];
        $result = ($winner == $user_id) ? 'win' : (($loser == $user_id) ? 'lose' : 'tie');
        $coin_change = ($result == 'win') ? '+10' : (($result == 'lose') ? '-5' : '0');
        $result_text = $result == 'win' ? 'You Won!' : ($result == 'lose' ? 'You Lost' : 'It\'s a Tie!');
        $result_class = $result == 'win' ? 'success' : ($result == 'lose' ? 'danger' : 'warning');
      ?>
        <h2 class="text-<?= $result_class ?> mb-3"><?= $result_text ?></h2>
        <p class="mb-1"><strong>Your Score:</strong> <?= $user_score ?>/<?= $user_total ?></p>
        <p class="mb-3"><strong>Opponent's Score:</strong> <?= $opponent_score ?>/<?= $opponent_total ?></p>
        <p class="mb-3"><span class="badge bg-<?= $result_class ?>" style="font-size:1.1em;">Coins: <?= $coin_change ?></span></p>
        <a href="dashboard.php" class="btn btn-primary mt-2">Back to Dashboard</a>
      <?php else: ?>
        <h2 class="text-warning mb-3">Waiting for Opponent</h2>
        <p class="mb-3">You have submitted your answers. Please wait for your opponent to complete the challenge.</p>
        <a href="dashboard.php" class="btn btn-primary mt-2">Back to Dashboard</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html> 