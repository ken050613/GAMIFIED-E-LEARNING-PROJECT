<?php
session_start();
require 'db_config.php';
require_once 'currency_utils.php';

if ($_SESSION['user_role'] !== 'student') {
  header("Location: dashboard.php");
  exit();
}

$answers = $_POST['answers'];
$quiz_id = intval($_POST['quiz_id']);
$score = 0;
$total = count($answers);

// Get reward per correct answer
$reward = 0;
$rewardStmt = $conn->prepare("SELECT reward_per_question FROM quizzes WHERE id = ?");
$rewardStmt->bind_param("i", $quiz_id);
$rewardStmt->execute();
$rewardResult = $rewardStmt->get_result();
if ($row = $rewardResult->fetch_assoc()) {
  $reward = intval($row['reward_per_question']);
}

// Evaluate answers
foreach ($answers as $question_id => $user_answer) {
  $stmt = $conn->prepare("SELECT correct_option FROM questions WHERE id = ?");
  $stmt->bind_param("i", $question_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $correct = $result->fetch_assoc()['correct_option'];

  if (strtoupper($user_answer) === $correct) {
    $score++;
  }
}

// Save result to DB
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("INSERT INTO quiz_results (user_id, quiz_id, score, total) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiii", $user_id, $quiz_id, $score, $total);
$stmt->execute();

// Award coins for correct answers
$total_reward = $score * $reward;
if ($total_reward > 0) {
  updateCurrency($conn, $user_id, $total_reward, 'gain', 'Quiz Correct Answers');
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Quiz Results</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
  <div class="container mt-5">
    <h2>Your Score: <?= $score ?> / <?= $total ?></h2>
    <div class="alert alert-info">You earned <strong><?= $total_reward ?></strong> coins for correct answers.</div>
    <a href="dashboard.php" class="btn btn-primary mt-3">Return to Dashboard</a>
  </div>
</body>
</html>
