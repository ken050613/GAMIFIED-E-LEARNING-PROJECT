<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'student') {
  header("Location: dashboard.php");
  exit();
}

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Check if student has already taken this quiz
$stmt = $conn->prepare("SELECT id FROM quiz_results WHERE user_id = ? AND quiz_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $quiz_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
  header("Location: dashboard.php");
  exit();
}

// Fetch student's currency balance
$stmt = $conn->prepare("SELECT balance FROM user_currency WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$currency_result = $stmt->get_result();
$currency_balance = $currency_result->fetch_assoc()['balance'] ?? 0;

// Fetch quiz with reward information
$quizStmt = $conn->prepare("SELECT title, description, reward_per_question FROM quizzes WHERE id = ?");
$quizStmt->bind_param("i", $quiz_id);
$quizStmt->execute();
$quizResult = $quizStmt->get_result();
$quiz = $quizResult->fetch_assoc();

// Fetch notes
$notesStmt = $conn->prepare("SELECT * FROM quiz_notes WHERE quiz_id = ? ORDER BY id");
$notesStmt->bind_param("i", $quiz_id);
$notesStmt->execute();
$notes = $notesStmt->get_result();

// Fetch questions
$qStmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ?");
$qStmt->bind_param("i", $quiz_id);
$qStmt->execute();
$questions = $qStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Take Quiz</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2><?= htmlspecialchars($quiz['title']) ?></h2>
      <div class="d-flex align-items-center">
        <div class="me-3">
          <span class="badge bg-warning text-dark">
            <i class="fas fa-coins"></i> <?= $currency_balance ?> Coins
          </span>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
      </div>
    </div>

    <div class="alert alert-info mb-4">
      <h5 class="alert-heading">Quiz Information</h5>
      <p class="mb-0"><?= htmlspecialchars($quiz['description']) ?></p>
      <hr>
      <p class="mb-0">
        <strong>Reward:</strong> <?= $quiz['reward_per_question'] ?> coins per correct answer
      </p>
    </div>

    <?php if ($notes->num_rows > 0): ?>
      <div class="card mb-4">
        <div class="card-header">
          <h4>Learning Notes</h4>
        </div>
        <div class="card-body">
          <?php while ($note = $notes->fetch_assoc()): ?>
            <div class="mb-4">
              <div class="note-content">
                <?= nl2br(htmlspecialchars($note['content'])) ?>
              </div>
              <?php if ($note['media_type'] === 'image' && $note['media_url']): ?>
                <div class="mt-2">
                  <img src="<?= htmlspecialchars($note['media_url']) ?>" class="img-fluid" alt="Note Image">
                </div>
              <?php elseif ($note['media_type'] === 'video' && $note['media_url']): ?>
                <div class="mt-2 ratio ratio-16x9">
                  <iframe src="<?= htmlspecialchars($note['media_url']) ?>" 
                          allowfullscreen></iframe>
                </div>
              <?php endif; ?>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
    <?php endif; ?>

    <form action="submit_quiz.php" method="POST">
      <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
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
  </div>
</body>
</html>
