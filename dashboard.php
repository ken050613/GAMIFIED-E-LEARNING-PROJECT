<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

$name = $_SESSION['user_name'];
$role = $_SESSION['user_role'];

require 'db_config.php';

// Move POST handling for notifications here
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
    $stmt->execute();
    header("Location: dashboard.php");
    exit();
  }
  if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    header("Location: dashboard.php");
    exit();
  }
}

// Fetch available quizzes for students
if ($role === 'student') {
  // Fetch student's currency balance
  $stmt = $conn->prepare("SELECT balance FROM user_currency WHERE user_id = ?");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $currency_result = $stmt->get_result();
  $currency_balance = $currency_result->fetch_assoc()['balance'] ?? 0;

  // Fetch unread notifications count
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $notifications_count = $stmt->get_result()->fetch_assoc()['count'];

  // Fetch recent notifications
  $stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
  ");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $notifications = $stmt->get_result();

  // Fetch student's selected avatar
  $stmt = $conn->prepare("
    SELECT a.image_url 
    FROM user_avatars ua 
    JOIN avatars a ON ua.avatar_id = a.id 
    WHERE ua.user_id = ? AND ua.is_selected = 1
  ");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $avatar_result = $stmt->get_result();
  $selected_avatar = $avatar_result->fetch_assoc();
  $profile_pic = $selected_avatar ? $selected_avatar['image_url'] : 'avatars/default.png';

  // Handle avatar selection
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_avatar'])) {
    $avatar_id = intval($_POST['avatar_id']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
      // Unselect all avatars
      $stmt = $conn->prepare("UPDATE user_avatars SET is_selected = 0 WHERE user_id = ?");
      $stmt->bind_param("i", $_SESSION['user_id']);
      $stmt->execute();
      
      // Select new avatar
      $stmt = $conn->prepare("UPDATE user_avatars SET is_selected = 1 WHERE user_id = ? AND avatar_id = ?");
      $stmt->bind_param("ii", $_SESSION['user_id'], $avatar_id);
      $stmt->execute();
      
      $conn->commit();
      $success = "Profile picture updated successfully!";
      
      // Refresh the page to show new avatar
      header("Location: dashboard.php");
      exit();
    } catch (Exception $e) {
      $conn->rollback();
      $error = "Failed to update profile picture. Please try again.";
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
      $stmt->bind_param("ii", $module_id, $_SESSION['user_id']);
      $stmt->execute();
      if ($stmt->get_result()->num_rows === 0) {
        // Add as participant
        $stmt = $conn->prepare("INSERT INTO module_participants (module_id, user_id, role) VALUES (?, ?, 'student')");
        $stmt->bind_param("ii", $module_id, $_SESSION['user_id']);
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

  // Fetch student's enrolled modules with participant counts
  $stmt = $conn->prepare("
    SELECT m.*, u.name as creator_name,
           (SELECT COUNT(*) FROM module_participants WHERE module_id = m.id AND role = 'student') as student_count,
           (SELECT COUNT(*) FROM module_participants WHERE module_id = m.id AND role = 'lecturer') as lecturer_count
    FROM modules m 
    JOIN module_participants mp ON m.id = mp.module_id 
    JOIN users u ON m.created_by = u.id 
    WHERE mp.user_id = ? AND mp.role = 'student'
    ORDER BY m.created_at DESC
  ");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $enrolled_modules = $stmt->get_result();

  // If viewing a specific module
  if (isset($_GET['view_module'])) {
    $module_id = intval($_GET['view_module']);
    
    // Verify student is enrolled in this module
    $stmt = $conn->prepare("
      SELECT m.*, u.name as creator_name 
      FROM modules m 
      JOIN module_participants mp ON m.id = mp.module_id 
      JOIN users u ON m.created_by = u.id 
      WHERE m.id = ? AND mp.user_id = ? AND mp.role = 'student'
    ");
    $stmt->bind_param("ii", $module_id, $_SESSION['user_id']);
    $stmt->execute();
    $current_module = $stmt->get_result()->fetch_assoc();

    if ($current_module) {
      // Fetch module quizzes with reward information
      $stmt = $conn->prepare("
        SELECT q.*, 
               (SELECT COUNT(*) FROM quiz_results WHERE quiz_id = q.id AND user_id = ?) as attempts,
               (SELECT score FROM quiz_results WHERE quiz_id = q.id AND user_id = ? ORDER BY taken_at DESC LIMIT 1) as last_score,
               (SELECT total FROM quiz_results WHERE quiz_id = q.id AND user_id = ? ORDER BY taken_at DESC LIMIT 1) as last_total
        FROM quizzes q 
        WHERE q.module_id = ?
        ORDER BY q.created_at DESC
      ");
      $stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $module_id);
      $stmt->execute();
      $module_quizzes = $stmt->get_result();

      // Fetch module participants
      $stmt = $conn->prepare("
        SELECT mp.*, u.name, u.email 
        FROM module_participants mp 
        JOIN users u ON mp.user_id = u.id 
        WHERE mp.module_id = ?
        ORDER BY mp.role DESC, u.name
      ");
      $stmt->bind_param("i", $module_id);
      $stmt->execute();
      $module_participants = $stmt->get_result();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - DQuizz</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
  <style>
    .profile-pic {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #007bff;
      cursor: pointer;
      transition: transform 0.2s;
    }
    .profile-pic:hover {
      transform: scale(1.05);
    }
    .avatar-selection {
      max-height: 300px;
      overflow-y: auto;
    }
    .brand-name {
      color: #007bff;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center">
        <img src="images/dquizz_logo.jpg" alt="DQuizz Logo" style="height:70px; width:auto; margin-right:18px;">
        <?php if ($role === 'student'): ?>
          <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-pic me-3" 
               data-bs-toggle="modal" data-bs-target="#avatarModal">
        <?php endif; ?>
        <h2>Welcome to <span class="brand-name">DQuizz</span>, <?= htmlspecialchars($name) ?> (<?= ucfirst($role) ?>)</h2>
      </div>
      <div class="d-flex align-items-center">
        <?php if ($role === 'student'): ?>
          <div class="me-3">
            <span class="badge bg-warning text-dark">
              <i class="fas fa-coins"></i> <?= $currency_balance ?> Coins
            </span>
          </div>
          <div class="me-3 position-relative">
            <a href="#notifications" class="btn btn-outline-primary" data-bs-toggle="collapse">
              <i class="fas fa-bell"></i>
              <?php if ($notifications_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  <?= $notifications_count ?>
                </span>
              <?php endif; ?>
            </a>
          </div>
          <a href="store.php" class="btn btn-outline-primary me-2">
            <i class="fas fa-shopping-cart"></i>
          </a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
      </div>
    </div>

    <!-- Role-Based Content -->
    <?php if ($role === 'admin'): ?>
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header">
              <h4>User Management</h4>
            </div>
            <div class="card-body">
      <div class="list-group">
        <a href="manage_users.php" class="list-group-item">Manage Users</a>
        <a href="manage_quiz.php" class="list-group-item">Manage Quizzes</a>
        <a href="manage_reports.php" class="list-group-item">View Reports</a>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header">
              <h4>Module Management</h4>
            </div>
            <div class="card-body">
              <a href="manage_modules.php" class="btn btn-primary">Manage All Modules</a>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-12">
          <div class="card mb-4">
            <div class="card-header">
              <h4>Feedback Management</h4>
            </div>
            <div class="card-body">
              <div class="list-group">
                <a href="view_all_feedback.php" class="list-group-item">View All Feedback</a>
                <a href="feedback_reports.php" class="list-group-item">Feedback Reports & Analytics</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    
    <?php elseif ($role === 'lecturer'): ?>
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header">
              <h4>Quiz Management</h4>
            </div>
            <div class="card-body">
      <div class="list-group">
        <a href="create_quiz.php" class="list-group-item">Create New Quiz</a>
        <a href="manage_quiz.php" class="list-group-item">Manage Existing Quizzes</a>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header">
              <h4>Module Management</h4>
            </div>
            <div class="card-body">
              <a href="manage_modules.php" class="btn btn-primary">Manage Modules</a>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header">
              <h4>Reports</h4>
            </div>
            <div class="card-body">
              <div class="list-group">
                <a href="quiz_reports.php" class="list-group-item">Quiz Performance Reports</a>
                <a href="student_progress.php" class="list-group-item">Student Progress Reports</a>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header">
              <h4>Other Options</h4>
            </div>
            <div class="card-body">
              <div class="list-group">
                <a href="feedback.php" class="list-group-item">Feedback</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    
    <?php elseif ($role === 'student'): ?>
      <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
      <?php endif; ?>
      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <?php if (isset($_GET['view_module']) && isset($current_module)): ?>
        <!-- Module Details View -->
        <div class="mb-4">
          <a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>
          <div class="card">
            <div class="card-header">
              <h3><?= htmlspecialchars($current_module['title']) ?></h3>
              <p class="text-muted mb-0">Created by: <?= htmlspecialchars($current_module['creator_name']) ?></p>
            </div>
            <div class="card-body">
              <p><?= htmlspecialchars($current_module['description']) ?></p>
              
              <div class="row">
                <div class="col-md-8">
                  <h4>Module Quizzes</h4>
                  <?php if ($module_quizzes->num_rows > 0): ?>
                    <div class="list-group">
                      <?php while ($quiz = $module_quizzes->fetch_assoc()): ?>
                        <div class="list-group-item">
                          <div class="d-flex justify-content-between align-items-start">
                            <div>
                              <h5 class="mb-1"><?= htmlspecialchars($quiz['title']) ?></h5>
                              <p class="mb-1"><?= htmlspecialchars($quiz['description']) ?></p>
                              <p class="mb-1 text-muted">
                                Reward: <?= $quiz['reward_per_question'] ?> coins per correct answer
                                <?php if ($quiz['attempts'] > 0): ?>
                                  <br>Last attempt: <?= $quiz['last_score'] ?>/<?= $quiz['last_total'] ?>
                                  <br><span class="text-danger">You have already taken this quiz</span>
                                <?php endif; ?>
                              </p>
                            </div>
                            <div>
                              <?php if ($quiz['attempts'] > 0): ?>
                                <button class="btn btn-secondary btn-sm" disabled>Already Taken</button>
                              <?php else: ?>
                                <a href="quiz.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-primary btn-sm">Take Quiz</a>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      <?php endwhile; ?>
                    </div>
                  <?php else: ?>
                    <p class="text-muted">No quizzes available in this module yet.</p>
                  <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                  <h4>Module Participants</h4>
                  <div class="list-group">
                    <?php while ($participant = $module_participants->fetch_assoc()): ?>
                      <div class="list-group-item">
                        <h6 class="mb-1"><?= htmlspecialchars($participant['name']) ?></h6>
                        <p class="mb-0 text-muted">
                          <?= ucfirst($participant['role']) ?>
                        </p>
                      </div>
                    <?php endwhile; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Main Student Dashboard -->
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h4>Join Module</h4>
              </div>
              <div class="card-body">
                <form method="POST">
                  <input type="hidden" name="join_module" value="1">
                  <div class="mb-3">
                    <label>Enter Module Join Code</label>
                    <input type="text" name="join_code" class="form-control" required>
                  </div>
                  <button type="submit" class="btn btn-primary">Join Module</button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <h4>Your Modules</h4>
              </div>
              <div class="card-body">
                <?php if ($enrolled_modules->num_rows > 0): ?>
                  <div class="list-group">
                    <?php while ($module = $enrolled_modules->fetch_assoc()): ?>
                      <a href="?view_module=<?= $module['id'] ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                          <h5 class="mb-1"><?= htmlspecialchars($module['title']) ?></h5>
                          <small class="text-muted">
                            <?= $module['student_count'] ?> students, <?= $module['lecturer_count'] ?> lecturers
                          </small>
                        </div>
                        <p class="mb-1"><?= htmlspecialchars($module['description']) ?></p>
                        <small class="text-muted">Created by: <?= htmlspecialchars($module['creator_name']) ?></small>
            </a>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
                  <p class="text-muted">You haven't joined any modules yet.</p>
      <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      <h3 class="mb-3">Other Options</h3>
      <div class="list-group">
        <a href="daily_quiz.php" class="list-group-item">Daily Quiz</a>
        <a href="challenge.php" class="list-group-item">1v1 Challenge</a>
        <a href="scoreboard.php" class="list-group-item">View Scoreboard</a>
        <a href="currency.php" class="list-group-item">View Currency</a>
        <a href="feedback.php" class="list-group-item">Feedback</a>
      </div>

      <!-- 1v1 Challenges Section -->
      <div class="card mt-4">
        <div class="card-header">
          <h4>1v1 Challenges</h4>
        </div>
        <div class="card-body">
          <?php
          // Fetch challenges where the student is the opponent or challenger
          $stmt = $conn->prepare("
            SELECT c.*, q.title AS quiz_title, u1.name AS challenger_name, u2.name AS opponent_name
            FROM challenges c
            JOIN quizzes q ON c.quiz_id = q.id
            JOIN users u1 ON c.challenger_id = u1.id
            JOIN users u2 ON c.opponent_id = u2.id
            WHERE c.challenger_id = ? OR c.opponent_id = ?
            ORDER BY c.created_at DESC
          ");
          $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
          $stmt->execute();
          $challenges = $stmt->get_result();
          if ($challenges->num_rows > 0):
          ?>
            <div class="list-group">
              <?php while ($ch = $challenges->fetch_assoc()): ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <strong><?= htmlspecialchars($ch['quiz_title']) ?></strong><br>
                      <small>
                        Challenger: <?= htmlspecialchars($ch['challenger_name']) ?> | 
                        Opponent: <?= htmlspecialchars($ch['opponent_name']) ?>
                      </small><br>
                      <small>Status: <?= ucfirst($ch['status']) ?></small>
                    </div>
                    <div>
                      <?php if ($ch['status'] === 'pending'):
                        // Check if the logged-in user has already attempted
                        $attempted = $conn->query("SELECT 1 FROM challenge_attempts WHERE challenge_id = " . $ch['id'] . " AND user_id = " . $_SESSION['user_id'])->num_rows > 0;
                        if (!$attempted): ?>
                          <a href="take_challenge.php?id=<?= $ch['id'] ?>" class="btn btn-success btn-sm">Take Challenge</a>
                        <?php else: ?>
                          <span class="badge bg-secondary">Waiting for opponent</span>
                        <?php endif; ?>
                      <?php elseif ($ch['status'] === 'completed'): ?>
                        <?php if ($ch['winner_id'] == $_SESSION['user_id']): ?>
                          <span class="badge bg-success">You Won</span>
                        <?php elseif ($ch['loser_id'] == $_SESSION['user_id']): ?>
                          <span class="badge bg-danger">You Lost</span>
                        <?php else: ?>
                          <span class="badge bg-warning text-dark">Tie</span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0">No 1v1 challenges yet.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Add notifications section for students -->
      <?php if ($role === 'student'): ?>
        <div class="collapse mb-4" id="notifications">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h4 class="mb-0">Notifications</h4>
              <form method="POST" class="d-inline">
                <input type="hidden" name="mark_all_read" value="1">
                <button type="submit" class="btn btn-sm btn-outline-primary">Mark All as Read</button>
              </form>
            </div>
            <div class="card-body">
              <?php if ($notifications->num_rows > 0): ?>
                <div class="list-group">
                  <?php while ($notification = $notifications->fetch_assoc()): ?>
                    <div class="list-group-item <?= $notification['is_read'] ? '' : 'list-group-item-primary' ?>">
                      <div class="d-flex w-100 justify-content-between">
                        <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($notification['created_at'])) ?></small>
                      </div>
                      <?php if (!$notification['is_read']): ?>
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="mark_read" value="<?= $notification['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-link">Mark as read</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  <?php endwhile; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mb-0">No notifications</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>

    <!-- Avatar Selection Modal -->
    <div class="modal fade" id="avatarModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Select Profile Picture</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body avatar-selection">
            <?php
            // Fetch user's owned avatars
            $stmt = $conn->prepare("
              SELECT a.*, ua.is_selected 
              FROM avatars a 
              JOIN user_avatars ua ON a.id = ua.avatar_id 
              WHERE ua.user_id = ?
              ORDER BY ua.is_selected DESC, a.price ASC
            ");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $owned_avatars = $stmt->get_result();
            
            if ($owned_avatars->num_rows > 0):
              while ($avatar = $owned_avatars->fetch_assoc()):
            ?>
              <div class="card mb-2">
                <div class="card-body d-flex align-items-center">
                  <img src="<?= htmlspecialchars($avatar['image_url']) ?>" alt="<?= htmlspecialchars($avatar['name']) ?>" 
                       class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                  <div class="flex-grow-1">
                    <h6 class="mb-0"><?= htmlspecialchars($avatar['name']) ?></h6>
                    <small class="text-muted"><?= htmlspecialchars($avatar['description']) ?></small>
                  </div>
                  <?php if ($avatar['is_selected']): ?>
                    <span class="badge bg-success">Selected</span>
                  <?php else: ?>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="avatar_id" value="<?= $avatar['id'] ?>">
                      <button type="submit" name="select_avatar" class="btn btn-primary btn-sm">Select</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php 
              endwhile;
            else:
            ?>
              <p class="text-center text-muted">You haven't purchased any avatars yet.</p>
              <div class="text-center">
                <a href="store.php" class="btn btn-primary">Visit Avatar Store</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
