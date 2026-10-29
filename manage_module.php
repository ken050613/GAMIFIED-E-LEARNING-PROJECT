<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$module_id = intval($_GET['id']);

// Verify user is either the module creator or an admin
$stmt = $conn->prepare("SELECT * FROM modules WHERE id = ? AND (created_by = ? OR ? = 'admin')");
$stmt->bind_param("iis", $module_id, $user_id, $_SESSION['user_role']);
$stmt->execute();
$module = $stmt->get_result()->fetch_assoc();

if (!$module) {
    header("Location: manage_modules.php");
    exit();
}

// Handle adding new quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quiz'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $reward = isset($_POST['reward_per_question']) ? intval($_POST['reward_per_question']) : 0;
    
    $stmt = $conn->prepare("INSERT INTO quizzes (title, description, created_by, module_id, reward_per_question) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiii", $title, $description, $user_id, $module_id, $reward);
    
    if ($stmt->execute()) {
        $success = "Quiz added successfully!";
    } else {
        $error = "Error adding quiz.";
    }
}

// Handle adding new participant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_participant'])) {
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    // Find user by email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        // Check if already a participant
        $stmt = $conn->prepare("SELECT id FROM module_participants WHERE module_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $module_id, $user['id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO module_participants (module_id, user_id, role) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $module_id, $user['id'], $role);
            if ($stmt->execute()) {
                $success = "Participant added successfully!";
            } else {
                $error = "Error adding participant.";
            }
        } else {
            $error = "User is already a participant in this module.";
        }
    } else {
        $error = "User not found.";
    }
}

// Fetch module quizzes
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE module_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $module_id);
$stmt->execute();
$quizzes = $stmt->get_result();

// Fetch module participants
$stmt = $conn->prepare("
    SELECT mp.*, u.name, u.email 
    FROM module_participants mp 
    JOIN users u ON mp.user_id = u.id 
    WHERE mp.module_id = ?
    ORDER BY mp.role, u.name
");
$stmt->bind_param("i", $module_id);
$stmt->execute();
$participants = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Module - <?= htmlspecialchars($module['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Module: <?= htmlspecialchars($module['title']) ?></h2>
            <a href="manage_modules.php" class="btn btn-secondary">Back to Modules</a>
        </div>

        <div class="mb-3">
            <span class="badge bg-info text-dark fs-5">Join Code: <strong><?= htmlspecialchars($module['join_code']) ?></strong></span>
            <small class="text-muted ms-2">Share this code with students or lecturers to let them join this module.</small>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Quiz</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="add_quiz" value="1">
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
                            <button type="submit" class="btn btn-primary">Add Quiz</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Add Participant</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="add_participant" value="1">
                            <div class="mb-3">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Role</label>
                                <select name="role" class="form-control" required>
                                    <option value="student">Student</option>
                                    <option value="lecturer">Lecturer</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">Add Participant</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Module Quizzes</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($quizzes->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <h5 class="mb-1"><?= htmlspecialchars($quiz['title']) ?></h5>
                                        <p class="mb-1"><?= htmlspecialchars($quiz['description']) ?></p>
                                        <div class="btn-group">
                                            <a href="manage_quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-sm btn-primary">Manage Quiz</a>
                                            <a href="add_question.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-sm btn-success">Add Questions</a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No quizzes added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Module Participants</h4>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($participant = $participants->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($participant['name']) ?></td>
                                        <td><?= htmlspecialchars($participant['email']) ?></td>
                                        <td><?= ucfirst($participant['role']) ?></td>
                                        <td><?= date('M d, Y', strtotime($participant['joined_at'])) ?></td>
                                        <td>
                                            <?php if ($participant['user_id'] != $user_id): ?>
                                                <a href="manage_modules.php?remove_participant=<?= $participant['id'] ?>&module_id=<?= $module_id ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure you want to remove this participant?')">
                                                    Remove
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 