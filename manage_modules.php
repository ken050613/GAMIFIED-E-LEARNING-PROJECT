<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle module deletion
if (isset($_GET['delete']) && ($user_role === 'admin' || $user_role === 'lecturer')) {
    $module_id = intval($_GET['delete']);
    
    // For lecturers, verify they are the creator
    if ($user_role === 'lecturer') {
        $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $module_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $error = "You don't have permission to delete this module.";
        } else {
            // Delete module and related data
            $conn->begin_transaction();
            try {
                // Delete module participants
                $stmt = $conn->prepare("DELETE FROM module_participants WHERE module_id = ?");
                $stmt->bind_param("i", $module_id);
                $stmt->execute();
                
                // Delete module
                $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
                $stmt->bind_param("i", $module_id);
                $stmt->execute();
                
                $conn->commit();
                $success = "Module deleted successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error deleting module.";
            }
        }
    } else {
        // Admin can delete any module
        $conn->begin_transaction();
        try {
            // Delete module participants
            $stmt = $conn->prepare("DELETE FROM module_participants WHERE module_id = ?");
            $stmt->bind_param("i", $module_id);
            $stmt->execute();
            
            // Delete module
            $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->bind_param("i", $module_id);
            $stmt->execute();
            
            $conn->commit();
            $success = "Module deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting module.";
        }
    }
}

// Handle module editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_module'])) {
    $module_id = intval($_POST['module_id']);
    $title = $_POST['title'];
    $description = $_POST['description'];
    
    // For lecturers, verify they are the creator
    if ($user_role === 'lecturer') {
        $stmt = $conn->prepare("SELECT id FROM modules WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $module_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $error = "You don't have permission to edit this module.";
        } else {
            $stmt = $conn->prepare("UPDATE modules SET title = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $title, $description, $module_id);
            if ($stmt->execute()) {
                $success = "Module updated successfully.";
            } else {
                $error = "Error updating module.";
            }
        }
    } else {
        // Admin can edit any module
        $stmt = $conn->prepare("UPDATE modules SET title = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $title, $description, $module_id);
        if ($stmt->execute()) {
            $success = "Module updated successfully.";
        } else {
            $error = "Error updating module.";
        }
    }
}

// Handle module creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_module'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $join_code = strtoupper(substr(md5(uniqid()), 0, 6));
    
    $stmt = $conn->prepare("INSERT INTO modules (title, description, join_code, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $description, $join_code, $user_id);
    
    if ($stmt->execute()) {
        $module_id = $conn->insert_id;
        // Add creator as lecturer participant
        $stmt = $conn->prepare("INSERT INTO module_participants (module_id, user_id, role) VALUES (?, ?, 'lecturer')");
        $stmt->bind_param("ii", $module_id, $user_id);
        $stmt->execute();
        $success = "Module created successfully!";
        $join_code = $join_code; // Store join code for display
    } else {
        $error = "Error creating module.";
    }
}

// Handle joining module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_module'])) {
    $join_code = $_POST['join_code'];
    
    $stmt = $conn->prepare("SELECT id FROM modules WHERE join_code = ?");
    $stmt->bind_param("s", $join_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $module = $result->fetch_assoc();
        $module_id = $module['id'];
        
        // Check if already a participant
        $stmt = $conn->prepare("SELECT id FROM module_participants WHERE module_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $module_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            // Add as participant
            $stmt = $conn->prepare("INSERT INTO module_participants (module_id, user_id, role) VALUES (?, ?, ?)");
            $role = $user_role === 'lecturer' ? 'lecturer' : 'student';
            $stmt->bind_param("iis", $module_id, $user_id, $role);
            if ($stmt->execute()) {
                $success = "Successfully joined the module!";
            } else {
                $error = "Error joining module.";
            }
        } else {
            $error = "You are already a participant in this module.";
        }
    } else {
        $error = "Invalid join code.";
    }
}

// Handle removing participant
if (isset($_GET['remove_participant'])) {
    $participant_id = intval($_GET['remove_participant']);
    $module_id = intval($_GET['module_id']);
    
    // Check if current user is the module creator or an admin
    $stmt = $conn->prepare("SELECT created_by FROM modules WHERE id = ?");
    $stmt->bind_param("i", $module_id);
    $stmt->execute();
    $module = $stmt->get_result()->fetch_assoc();
    
    if ($module && ($module['created_by'] == $user_id || $user_role === 'admin')) {
        $stmt = $conn->prepare("DELETE FROM module_participants WHERE id = ?");
        $stmt->bind_param("i", $participant_id);
        if ($stmt->execute()) {
            $success = "Participant removed successfully.";
        } else {
            $error = "Error removing participant.";
        }
    } else {
        $error = "You don't have permission to remove participants.";
    }
}

// Fetch modules based on user role
if ($user_role === 'admin') {
    // Admin sees all modules
    $stmt = $conn->prepare("
        SELECT m.*, u.name as creator_name,
               (SELECT COUNT(*) FROM module_participants WHERE module_id = m.id AND role = 'student') as student_count,
               (SELECT COUNT(*) FROM module_participants WHERE module_id = m.id AND role = 'lecturer') as lecturer_count
        FROM modules m 
        JOIN users u ON m.created_by = u.id 
        ORDER BY m.created_at DESC
    ");
} else {
    // Lecturer sees their created modules
    $stmt = $conn->prepare("
        SELECT m.*, u.name as creator_name,
               (SELECT COUNT(*) FROM module_participants WHERE module_id = m.id AND role = 'student') as student_count,
               (SELECT COUNT(*) FROM module_participants WHERE module_id = m.id AND role = 'lecturer') as lecturer_count
        FROM modules m 
        JOIN users u ON m.created_by = u.id 
        WHERE m.created_by = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$modules = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Modules</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Modules</h2>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?= $success ?>
                <?php if (isset($join_code)): ?>
                    <div class="mt-3">
                        <h5>Module Join Code:</h5>
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-3 rounded border">
                                <span class="fs-4 fw-bold"><?= htmlspecialchars($join_code) ?></span>
                            </div>
                            <button class="btn btn-primary ms-3" onclick="copyJoinCode('<?= htmlspecialchars($join_code) ?>')">
                                Copy Code
                            </button>
                        </div>
                        <small class="text-muted mt-2 d-block">Share this code with students or lecturers to let them join this module.</small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($user_role === 'lecturer'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Create New Module</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="create_module" value="1">
                        <div class="mb-3">
                            <label>Module Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Module</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h4><?= $user_role === 'admin' ? 'All Modules' : 'Your Modules' ?></h4>
            </div>
            <div class="card-body">
                <?php if ($modules->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($module = $modules->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($module['title']) ?></h5>
                                        <p class="mb-1"><?= htmlspecialchars($module['description']) ?></p>
                                        <small class="text-muted">
                                            Created by: <?= htmlspecialchars($module['creator_name']) ?><br>
                                            Join Code: <?= htmlspecialchars($module['join_code']) ?><br>
                                            <?= $module['student_count'] ?> students, <?= $module['lecturer_count'] ?> lecturers
                                        </small>
                                    </div>
                                    <div class="btn-group">
                                        <a href="manage_module.php?id=<?= $module['id'] ?>" class="btn btn-primary btn-sm">Manage</a>
                                        <?php if ($user_role === 'admin' || ($user_role === 'lecturer' && $module['created_by'] == $user_id)): ?>
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?= $module['id'] ?>">
                                                Edit
                                            </button>
                                            <a href="?delete=<?= $module['id'] ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this module? This will also remove all participants and quizzes.')">
                                                Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?= $module['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Module</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="edit_module" value="1">
                                                <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                                                <div class="mb-3">
                                                    <label>Module Title</label>
                                                    <input type="text" name="title" class="form-control" 
                                                           value="<?= htmlspecialchars($module['title']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Description</label>
                                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($module['description']) ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No modules found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyJoinCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert('Join code copied to clipboard!');
            });
        }
    </script>
</body>
</html> 