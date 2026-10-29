<?php
session_start();
require 'db_config.php';
require 'currency_utils.php';

$user_id = $_SESSION['user_id'];
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate user is part of this challenge
$stmt = $conn->prepare("SELECT * FROM challenges WHERE id = ? AND (challenger_id = ? OR opponent_id = ?)");
$stmt->bind_param("iii", $challenge_id, $user_id, $user_id);
$stmt->execute();
$challenge = $stmt->get_result()->fetch_assoc();

if (!$challenge) {
  die("Challenge not found or access denied.");
}

$quiz_id = $challenge['quiz_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $answers = $_POST['answers'];
  $score = 0;
  $total = count($answers);

  foreach ($answers as $question_id => $user_answer) {
    $q = $conn->query("SELECT correct_option FROM questions WHERE id = $question_id")->fetch_assoc();
    if (strtoupper($user_answer) === $q['correct_option']) {
      $score++;
    }
  }

  // Store attempt
  $conn->query("INSERT INTO challenge_attempts (challenge_id, user_id, score, total)
                VALUES ($challenge_id, $user_id, $score, $total)");

  // Redirect to result page
  header("Location: challenge_result.php?id=$challenge_id");
  exit();
}

// Load quiz questions
$questions = $conn->query("SELECT * FROM questions WHERE quiz_id = $quiz_id");
?>

<!DOCTYPE html>
<html>
<head>
  <title>1v1 Challenge</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
  <div class="container mt-4">
    <h2>Take the Challenge Quiz</h2>
    <form method="POST">
      <?php $qnum = 1; ?>
      <?php while ($q = $questions->fetch_assoc()): ?>
        <div class="mb-3">
          <strong>Q<?= $qnum++ ?>: <?= htmlspecialchars($q['question_text']) ?></strong>
          <?php foreach (['a','b','c','d'] as $opt): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="<?= strtoupper($opt) ?>" required>
              <label class="form-check-label"><?= htmlspecialchars($q['option_' . $opt]) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endwhile; ?>
      <button class="btn btn-success" type="submit">Submit</button>
    </form>
  </div>
</body>
</html>
