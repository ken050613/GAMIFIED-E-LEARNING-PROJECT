<?php
session_start();
require 'db_config.php';

if (!in_array($_SESSION['user_role'], ['lecturer', 'admin'])) {
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['user_role'] === 'admin';

// Handle quiz deletion
if (isset($_GET['delete_quiz'])) {
  $quiz_id = intval($_GET['delete_quiz']);
  // Only allow lecturer to delete their own quiz, admin can delete any
  $quiz = $conn->query("SELECT * FROM quizzes WHERE id = $quiz_id")->fetch_assoc();
  if ($quiz && ($is_admin || $quiz['created_by'] == $user_id)) {
    $conn->query("DELETE FROM quizzes WHERE id = $quiz_id");
    $success = "Quiz deleted successfully!";
  } else {
    $error = "You do not have permission to delete this quiz.";
  }
}

// Handle quiz update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quiz'])) {
  $quiz_id = intval($_POST['quiz_id']);
  $title = $_POST['title'];
  $desc = $_POST['description'];
  $quiz = $conn->query("SELECT * FROM quizzes WHERE id = $quiz_id")->fetch_assoc();
  if ($quiz && ($is_admin || $quiz['created_by'] == $user_id)) {
    $stmt = $conn->prepare("UPDATE quizzes SET title = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $title, $desc, $quiz_id);
    $stmt->execute();
    $success = "Quiz updated successfully!";
  } else {
    $error = "You do not have permission to edit this quiz.";
  }
}

// Handle question deletion
if (isset($_GET['delete_question'])) {
  $question_id = intval($_GET['delete_question']);
  $conn->query("DELETE FROM questions WHERE id = $question_id");
  $success = "Question deleted successfully!";
}

// Handle question update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
  $question_id = intval($_POST['question_id']);
  $question = $_POST['question'];
  $optA = $_POST['option_a'];
  $optB = $_POST['option_b'];
  $optC = $_POST['option_c'];
  $optD = $_POST['option_d'];
  $correct = strtoupper($_POST['correct_option']);

  $stmt = $conn->prepare("UPDATE questions SET 
    question_text = ?, 
    option_a = ?, 
    option_b = ?, 
    option_c = ?, 
    option_d = ?, 
    correct_option = ? 
    WHERE id = ?");
  $stmt->bind_param("ssssssi", $question, $optA, $optB, $optC, $optD, $correct, $question_id);
  $stmt->execute();
  
  $success = "Question updated successfully!";
}

// Fetch quizzes
if ($is_admin) {
  $quizzes = $conn->query("SELECT * FROM quizzes ORDER BY id DESC");
} else {
  $quizzes = $conn->query("SELECT * FROM quizzes WHERE created_by = $user_id ORDER BY id DESC");
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Quizzes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <h2>Manage Quizzes</h2>
    <?php if (isset($success)): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="mb-4">
      <a href="create_quiz.php" class="btn btn-primary">Create New Quiz</a>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php while ($quiz = $quizzes->fetch_assoc()): ?>
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h4 class="mb-0 d-inline-block"><?= htmlspecialchars($quiz['title']) ?></h4>
            <p class="text-muted mb-0">Created by User #<?= $quiz['created_by'] ?></p>
          </div>
          <div>
            <button class="btn pixel-btn btn-sm me-2" onclick='editQuizModal(<?= json_encode(["id"=>$quiz["id"],"title"=>$quiz["title"],"description"=>$quiz["description"]]) ?>)'>Edit Quiz</button>
            <a href="?delete_quiz=<?= $quiz['id'] ?>" class="btn pixel-btn btn-sm" onclick="return confirm('Are you sure you want to delete this quiz?')">Delete Quiz</a>
          </div>
        </div>
        <div class="card-body">
          <p class="text-muted"><?= htmlspecialchars($quiz['description']) ?></p>
          <?php
          $questions = $conn->query("SELECT * FROM questions WHERE quiz_id = {$quiz['id']} ORDER BY id");
          if ($questions->num_rows > 0):
          ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Question</th>
                  <th>Options</th>
                  <th>Correct Answer</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($q = $questions->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                    <td>
                      A: <?= htmlspecialchars($q['option_a']) ?><br>
                      B: <?= htmlspecialchars($q['option_b']) ?><br>
                      C: <?= htmlspecialchars($q['option_c']) ?><br>
                      D: <?= htmlspecialchars($q['option_d']) ?>
                    </td>
                    <td><?= $q['correct_option'] ?></td>
                    <td>
                      <button class="btn btn-sm btn-primary" 
                              onclick="editQuestion(<?= htmlspecialchars(json_encode($q)) ?>)">
                        Edit
                      </button>
                      <a href="?delete_question=<?= $q['id'] ?>" 
                         class="btn btn-sm btn-danger"
                         onclick="return confirm('Are you sure you want to delete this question?')">
                        Delete
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-muted">No questions added yet.</p>
          <?php endif; ?>
          
          <div class="btn-group">
            <a href="manage_notes.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-info">Manage Notes</a>
            <a href="add_question.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-success">Add Questions</a>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <!-- Edit Quiz Modal -->
  <div class="modal fade" id="editQuizModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Quiz</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="quiz_id" id="edit_quiz_id">
            <input type="hidden" name="update_quiz" value="1">
            <div class="mb-3">
              <label>Quiz Title</label>
              <input type="text" name="title" id="edit_quiz_title" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Description</label>
              <textarea name="description" id="edit_quiz_desc" class="form-control"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Question Modal -->
  <div class="modal fade" id="editQuestionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="question_id" id="edit_question_id">
            <input type="hidden" name="update_question" value="1">
            
            <div class="mb-3">
              <label>Question</label>
              <textarea name="question" id="edit_question" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
              <label>Option A</label>
              <input type="text" name="option_a" id="edit_option_a" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Option B</label>
              <input type="text" name="option_b" id="edit_option_b" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Option C</label>
              <input type="text" name="option_c" id="edit_option_c" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Option D</label>
              <input type="text" name="option_d" id="edit_option_d" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Correct Option (A/B/C/D)</label>
              <input type="text" name="correct_option" id="edit_correct_option" class="form-control" maxlength="1" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function editQuizModal(quiz) {
      document.getElementById('edit_quiz_id').value = quiz.id;
      document.getElementById('edit_quiz_title').value = quiz.title;
      document.getElementById('edit_quiz_desc').value = quiz.description;
      new bootstrap.Modal(document.getElementById('editQuizModal')).show();
    }
    function editQuestion(question) {
      document.getElementById('edit_question_id').value = question.id;
      document.getElementById('edit_question').value = question.question_text;
      document.getElementById('edit_option_a').value = question.option_a;
      document.getElementById('edit_option_b').value = question.option_b;
      document.getElementById('edit_option_c').value = question.option_c;
      document.getElementById('edit_option_d').value = question.option_d;
      document.getElementById('edit_correct_option').value = question.correct_option;
      new bootstrap.Modal(document.getElementById('editQuestionModal')).show();
    }
  </script>
</body>
</html> 