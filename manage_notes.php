<?php
session_start();
require 'db_config.php';

// Check if user is lecturer or admin
if (!in_array($_SESSION['user_role'], ['lecturer', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Handle note deletion
if (isset($_GET['delete_note'])) {
    $note_id = intval($_GET['delete_note']);
    $stmt = $conn->prepare("DELETE FROM quiz_notes WHERE id = ? AND quiz_id = ?");
    $stmt->bind_param("ii", $note_id, $quiz_id);
    $stmt->execute();
    header("Location: manage_notes.php?quiz_id=$quiz_id");
    exit();
}

// Handle note updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_note'])) {
    $note_id = intval($_POST['note_id']);
    $content = $_POST['content'];
    $media_type = $_POST['media_type'];
    $media_url = $_POST['media_url'];

    $stmt = $conn->prepare("UPDATE quiz_notes SET content = ?, media_type = ?, media_url = ? WHERE id = ? AND quiz_id = ?");
    $stmt->bind_param("sssii", $content, $media_type, $media_url, $note_id, $quiz_id);
    $stmt->execute();
    header("Location: manage_notes.php?quiz_id=$quiz_id");
    exit();
}

// Handle adding new note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $content = $_POST['content'];
    $media_type = $_POST['media_type'];
    $media_url = $_POST['media_url'];

    $stmt = $conn->prepare("INSERT INTO quiz_notes (quiz_id, content, media_type, media_url) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $quiz_id, $content, $media_type, $media_url);
    $stmt->execute();
    header("Location: manage_notes.php?quiz_id=$quiz_id");
    exit();
}

// Fetch quiz details
$stmt = $conn->prepare("SELECT title FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

// Fetch notes
$stmt = $conn->prepare("SELECT * FROM quiz_notes WHERE quiz_id = ? ORDER BY id");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$notes = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Notes - <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Notes - <?= htmlspecialchars($quiz['title']) ?></h2>
            <div>
                <a href="manage_quiz.php?id=<?= $quiz_id ?>" class="btn btn-secondary">Back to Quiz</a>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>

        <!-- Add New Note Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Add New Note</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_note" value="1">
                    <div class="mb-3">
                        <label>Note Content</label>
                        <textarea name="content" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Media Type</label>
                        <select name="media_type" class="form-select" onchange="toggleMediaUrl(this)">
                            <option value="text">Text Only</option>
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    <div class="mb-3 media-url-field" style="display: none;">
                        <label>Media URL</label>
                        <input type="url" name="media_url" class="form-control" placeholder="Enter image or video URL">
                        <small class="text-muted">For images: Use direct image URLs. For videos: Use YouTube embed URLs.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Note</button>
                </form>
            </div>
        </div>

        <!-- Existing Notes -->
        <div class="card">
            <div class="card-header">
                <h4>Existing Notes</h4>
            </div>
            <div class="card-body">
                <?php if ($notes->num_rows > 0): ?>
                    <?php while ($note = $notes->fetch_assoc()): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="update_note" value="1">
                                    <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label>Note Content</label>
                                        <textarea name="content" class="form-control" rows="3" required><?= htmlspecialchars($note['content']) ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label>Media Type</label>
                                        <select name="media_type" class="form-select" onchange="toggleMediaUrl(this)">
                                            <option value="text" <?= $note['media_type'] === 'text' ? 'selected' : '' ?>>Text Only</option>
                                            <option value="image" <?= $note['media_type'] === 'image' ? 'selected' : '' ?>>Image</option>
                                            <option value="video" <?= $note['media_type'] === 'video' ? 'selected' : '' ?>>Video</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3 media-url-field" style="display: <?= $note['media_type'] !== 'text' ? 'block' : 'none' ?>;">
                                        <label>Media URL</label>
                                        <input type="url" name="media_url" class="form-control" value="<?= htmlspecialchars($note['media_url']) ?>" placeholder="Enter image or video URL">
                                        <small class="text-muted">For images: Use direct image URLs. For videos: Use YouTube embed URLs.</small>
                                    </div>

                                    <?php if ($note['media_type'] === 'image' && $note['media_url']): ?>
                                        <div class="mb-3">
                                            <img src="<?= htmlspecialchars($note['media_url']) ?>" class="img-fluid" alt="Note Image">
                                        </div>
                                    <?php elseif ($note['media_type'] === 'video' && $note['media_url']): ?>
                                        <div class="mb-3 ratio ratio-16x9">
                                            <iframe src="<?= htmlspecialchars($note['media_url']) ?>" allowfullscreen></iframe>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Update Note</button>
                                        <a href="?quiz_id=<?= $quiz_id ?>&delete_note=<?= $note['id'] ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this note?')">
                                            Delete Note
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">No notes added yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleMediaUrl(select) {
            const mediaUrlField = select.parentElement.nextElementSibling;
            if (select.value === 'text') {
                mediaUrlField.style.display = 'none';
            } else {
                mediaUrlField.style.display = 'block';
            }
        }
    </script>
</body>
</html> 