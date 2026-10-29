<?php
session_start();
require 'db_config.php';
if (!in_array($_SESSION['user_role'], ['lecturer', 'admin'])) {
  header("Location: dashboard.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $title = $_POST['title'];
  $desc = $_POST['description'];
  $lecturer_id = $_SESSION['user_id'];
  $reward = isset($_POST['reward_per_question']) ? intval($_POST['reward_per_question']) : 0;

  $stmt = $conn->prepare("INSERT INTO quizzes (title, description, created_by, reward_per_question) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssii", $title, $desc, $lecturer_id, $reward);
  $stmt->execute();
  $quiz_id = $stmt->insert_id;

  // Handle notes
  if (isset($_POST['notes']) && is_array($_POST['notes'])) {
    $stmt = $conn->prepare("INSERT INTO quiz_notes (quiz_id, content, media_type, media_url) VALUES (?, ?, ?, ?)");
    
    foreach ($_POST['notes'] as $note) {
      if (!empty($note['content'])) {
        $media_type = $note['media_type'];
        $media_url = $note['media_url'] ?? null;
        
        $stmt->bind_param("isss", $quiz_id, $note['content'], $media_type, $media_url);
        $stmt->execute();
      }
    }
  }

  header("Location: add_question.php?quiz_id=$quiz_id");
  exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Create Quiz</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Create a New Quiz</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <form method="POST" id="quizForm">
      <div class="mb-3">
        <label>Quiz Title</label>
        <input type="text" name="title" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control"></textarea>
      </div>
      <div class="mb-3">
        <label>Reward Coins per Correct Answer</label>
        <input type="number" name="reward_per_question" class="form-control" min="0" value="0" required>
        <small class="text-muted">Students will earn this many coins for each correct answer.</small>
      </div>

      <h4 class="mt-4">Add Learning Notes</h4>
      <div id="notesContainer">
        <div class="note-entry card mb-3">
          <div class="card-body">
            <div class="mb-3">
              <label>Note Content</label>
              <textarea name="notes[0][content]" class="form-control" rows="3"></textarea>
            </div>
            <div class="mb-3">
              <label>Media Type</label>
              <select name="notes[0][media_type]" class="form-select" onchange="toggleMediaUrl(this)">
                <option value="text">Text Only</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
              </select>
            </div>
            <div class="mb-3 media-url-field" style="display: none;">
              <label>Media URL</label>
              <input type="url" name="notes[0][media_url]" class="form-control" placeholder="Enter image or video URL">
              <small class="text-muted">For images: Use direct image URLs. For videos: Use YouTube embed URLs.</small>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-3">
        <button type="button" class="btn btn-secondary" onclick="addNote()">Add Another Note</button>
        <button type="submit" class="btn btn-primary">Create & Add Questions</button>
      </div>
    </form>
  </div>

  <script>
    let noteCount = 1;

    function addNote() {
      const container = document.getElementById('notesContainer');
      const newNote = document.createElement('div');
      newNote.className = 'note-entry card mb-3';
      newNote.innerHTML = `
        <div class="card-body">
          <div class="mb-3">
            <label>Note Content</label>
            <textarea name="notes[${noteCount}][content]" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label>Media Type</label>
            <select name="notes[${noteCount}][media_type]" class="form-select" onchange="toggleMediaUrl(this)">
              <option value="text">Text Only</option>
              <option value="image">Image</option>
              <option value="video">Video</option>
            </select>
          </div>
          <div class="mb-3 media-url-field" style="display: none;">
            <label>Media URL</label>
            <input type="url" name="notes[${noteCount}][media_url]" class="form-control" placeholder="Enter image or video URL">
            <small class="text-muted">For images: Use direct image URLs. For videos: Use YouTube embed URLs.</small>
          </div>
          <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">Remove Note</button>
        </div>
      `;
      container.appendChild(newNote);
      noteCount++;
    }

    function toggleMediaUrl(select) {
      const mediaUrlField = select.parentElement.nextElementSibling;
      if (select.value === 'text') {
        mediaUrlField.style.display = 'none';
      } else {
        mediaUrlField.style.display = 'block';
      }
    }

    updateRemoveButtons();
  </script>
</body>
</html>
    