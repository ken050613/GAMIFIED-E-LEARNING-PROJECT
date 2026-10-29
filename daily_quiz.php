<?php
session_start();
require 'db_config.php';
require 'currency_utils.php';

if ($_SESSION['user_role'] !== 'student') {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Check if user has already taken the daily quiz today
$today = date('Y-m-d');
$checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM quiz_results 
                            WHERE user_id = ? AND DATE(taken_at) = ?");
$checkStmt->bind_param("is", $user_id, $today);
$checkStmt->execute();
$result = $checkStmt->get_result();
$takenToday = $result->fetch_assoc()['count'] > 0;

// Get reward per correct answer for daily quiz (quiz_id = 1)
$reward = 0;
$rewardStmt = $conn->prepare("SELECT reward_per_question FROM quizzes WHERE id = 1");
$rewardStmt->execute();
$rewardResult = $rewardStmt->get_result();
if ($row = $rewardResult->fetch_assoc()) {
  $reward = intval($row['reward_per_question']);
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $answers = $_POST['answers'];
  $score = 0;
  $total = count($answers);

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

  // Save result
  $stmt = $conn->prepare("INSERT INTO quiz_results (user_id, quiz_id, score, total) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("iiii", $user_id, $_POST['quiz_id'], $score, $total);
  $stmt->execute();

  // Reward currency based on correct answers
  $total_reward = $score * $reward;
  if ($total_reward > 0) {
    updateCurrency($conn, $user_id, $total_reward, 'gain', 'Daily Quiz Correct Answers');
  }

  $success = "Quiz completed! You earned $total_reward coins.";
  $takenToday = true;
}

// Get daily quiz questions
$questions = $conn->query("
    SELECT q.* 
    FROM questions q 
    JOIN quizzes qz ON q.quiz_id = qz.id 
    WHERE qz.module_id IS NULL 
    ORDER BY RAND() 
    LIMIT 5
"); // Randomly select 5 questions from non-module quizzes
?>

<!DOCTYPE html>
<html>
<head>
  <title>Daily Quiz</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <h2>Daily Quiz</h2>

    <?php if (isset($success)): ?>
      <div class="alert alert-success"><?= $success ?></div>
      <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    <?php elseif ($takenToday): ?>
      <div class="alert alert-info">
        You've already taken today's quiz. Come back tomorrow for a new challenge!
      </div>
      <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    <?php else: ?>
      <div class="alert alert-info mb-4">
        Complete today's quiz to earn coins for each correct answer!<br>
        <strong>Reward per correct answer: <?= $reward ?> coins</strong>
      </div>

      <form method="POST">
        <input type="hidden" name="quiz_id" value="1">
        <?php $qnum = 1; ?>
        <?php while ($q = $questions->fetch_assoc()): ?>
          <div class="mb-4">
            <h5>Q<?= $qnum++ ?>: <?= htmlspecialchars($q['question_text']) ?></h5>
            <?php foreach (['a','b','c','d'] as $opt): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="<?= strtoupper($opt) ?>" required>
                <label class="form-check-label">
                  <?= htmlspecialchars($q['option_' . $opt]) ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endwhile; ?>
        <button type="submit" class="btn btn-success">Submit Answers</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html> 