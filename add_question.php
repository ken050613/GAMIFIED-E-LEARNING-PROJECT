<?php
session_start();
require 'db_config.php';

if (!in_array($_SESSION['user_role'], ['lecturer', 'admin'])) {
  header("Location: dashboard.php");
  exit();
}

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Verify user has permission to add questions to this quiz
$stmt = $conn->prepare("SELECT created_by FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

if (!$quiz || ($_SESSION['user_role'] !== 'admin' && $quiz['created_by'] !== $_SESSION['user_id'])) {
  header("Location: dashboard.php");
  exit();
}

// Handle multiple question submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['questions']) && is_array($_POST['questions'])) {
  $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
  foreach ($_POST['questions'] as $q) {
    if (!empty($q['question']) && !empty($q['option_a']) && !empty($q['option_b']) && !empty($q['option_c']) && !empty($q['option_d']) && !empty($q['correct_option'])) {
      $question = $q['question'];
      $optA = $q['option_a'];
      $optB = $q['option_b'];
      $optC = $q['option_c'];
      $optD = $q['option_d'];
      $correct = strtoupper($q['correct_option']);
      $stmt->bind_param("issssss", $quiz_id, $question, $optA, $optB, $optC, $optD, $correct);
      $stmt->execute();
    }
  }
  $message = "Questions added!";
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Add Questions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Add Questions</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if (isset($message)): ?>
      <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" id="questionsForm">
      <div id="questionsContainer">
        <div class="question-block card mb-4">
          <div class="card-body">
            <h5 class="card-title">Question 1</h5>
            <div class="mb-3">
              <label>Question</label>
              <textarea name="questions[0][question]" class="form-control" required></textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label>Option A</label>
                <input type="text" name="questions[0][option_a]" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label>Option B</label>
                <input type="text" name="questions[0][option_b]" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label>Option C</label>
                <input type="text" name="questions[0][option_c]" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label>Option D</label>
                <input type="text" name="questions[0][option_d]" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label>Correct Option (A/B/C/D)</label>
                <input type="text" name="questions[0][correct_option]" class="form-control" maxlength="1" required>
              </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm remove-question" style="display:none;">Remove</button>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2 mb-3">
        <button type="button" class="btn btn-secondary" onclick="addQuestionBlock()">Add Another Question</button>
        <button type="submit" class="btn btn-primary">Submit All Questions</button>
      </div>
    </form>
  </div>

  <script>
    let questionCount = 1;
    function addQuestionBlock() {
      const container = document.getElementById('questionsContainer');
      const block = document.createElement('div');
      block.className = 'question-block card mb-4';
      block.innerHTML = `
        <div class="card-body">
          <h5 class="card-title">Question ${questionCount + 1}</h5>
          <div class="mb-3">
            <label>Question</label>
            <textarea name="questions[${questionCount}][question]" class="form-control" required></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Option A</label>
              <input type="text" name="questions[${questionCount}][option_a]" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Option B</label>
              <input type="text" name="questions[${questionCount}][option_b]" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Option C</label>
              <input type="text" name="questions[${questionCount}][option_c]" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Option D</label>
              <input type="text" name="questions[${questionCount}][option_d]" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Correct Option (A/B/C/D)</label>
              <input type="text" name="questions[${questionCount}][correct_option]" class="form-control" maxlength="1" required>
            </div>
          </div>
          <button type="button" class="btn btn-danger btn-sm remove-question">Remove</button>
        </div>
      `;
      container.appendChild(block);
      questionCount++;
      updateRemoveButtons();
    }
    function updateRemoveButtons() {
      const blocks = document.querySelectorAll('.question-block');
      blocks.forEach((block, idx) => {
        const btn = block.querySelector('.remove-question');
        btn.style.display = (blocks.length > 1) ? 'inline-block' : 'none';
        btn.onclick = function() {
          block.remove();
          updateRemoveButtons();
        };
      });
    }
    updateRemoveButtons();
  </script>
</body>
</html>
