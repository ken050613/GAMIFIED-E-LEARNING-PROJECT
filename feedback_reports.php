<?php
session_start();
require 'db_config.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: dashboard.php");
  exit();
}

// Get feedback trends over time
$trends = $conn->query("
  SELECT 
    DATE(submitted_at) as date,
    COUNT(*) as count,
    COUNT(CASE WHEN u.role = 'student' THEN 1 END) as student_count,
    COUNT(CASE WHEN u.role = 'lecturer' THEN 1 END) as lecturer_count
  FROM feedback f
  JOIN users u ON f.user_id = u.id
  WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY DATE(submitted_at)
  ORDER BY date DESC
");

// Get most active feedback providers
$active_users = $conn->query("
  SELECT 
    u.name,
    u.role,
    COUNT(*) as feedback_count
  FROM feedback f
  JOIN users u ON f.user_id = u.id
  GROUP BY f.user_id
  ORDER BY feedback_count DESC
  LIMIT 10
");

// Get feedback by time of day
$time_distribution = $conn->query("
  SELECT 
    HOUR(submitted_at) as hour,
    COUNT(*) as count
  FROM feedback
  GROUP BY HOUR(submitted_at)
  ORDER BY hour
");

// Calculate average feedback per day
$avg_feedback = $conn->query("
  SELECT 
    COUNT(*) / COUNT(DISTINCT DATE(submitted_at)) as avg_per_day
  FROM feedback
  WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch_assoc()['avg_per_day'];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Feedback Reports & Analytics</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Feedback Reports & Analytics</h2>
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <!-- Overview Cards -->
    <div class="row mb-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Feedback Trends (Last 30 Days)</h5>
            <canvas id="trendsChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Feedback by Time of Day</h5>
            <canvas id="timeChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Most Active Users -->
    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Most Active Feedback Providers</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Total Feedback</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($user = $active_users->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($user['name']) ?></td>
                      <td><?= ucfirst($user['role']) ?></td>
                      <td><?= $user['feedback_count'] ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics -->
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Feedback Statistics</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="alert alert-info">
                  <h6>Average Feedback per Day</h6>
                  <h3><?= round($avg_feedback, 1) ?></h3>
                </div>
              </div>
              <div class="col-md-4">
                <div class="alert alert-success">
                  <h6>Total Feedback (Last 30 Days)</h6>
                  <h3><?= $trends->num_rows ?></h3>
                </div>
              </div>
              <div class="col-md-4">
                <div class="alert alert-warning">
                  <h6>Unique Feedback Providers</h6>
                  <h3><?= $active_users->num_rows ?></h3>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Prepare data for trends chart
    const trendsData = {
      labels: [],
      studentData: [],
      lecturerData: []
    };

    <?php 
    $trends->data_seek(0);
    while ($row = $trends->fetch_assoc()): 
    ?>
      trendsData.labels.unshift('<?= date('M d', strtotime($row['date'])) ?>');
      trendsData.studentData.unshift(<?= $row['student_count'] ?>);
      trendsData.lecturerData.unshift(<?= $row['lecturer_count'] ?>);
    <?php endwhile; ?>

    // Create trends chart
    new Chart(document.getElementById('trendsChart'), {
      type: 'line',
      data: {
        labels: trendsData.labels,
        datasets: [
          {
            label: 'Student Feedback',
            data: trendsData.studentData,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
          },
          {
            label: 'Lecturer Feedback',
            data: trendsData.lecturerData,
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1
          }
        ]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });

    // Prepare data for time distribution chart
    const timeData = {
      labels: [],
      data: []
    };

    <?php 
    $time_distribution->data_seek(0);
    while ($row = $time_distribution->fetch_assoc()): 
    ?>
      timeData.labels.push('<?= sprintf('%02d:00', $row['hour']) ?>');
      timeData.data.push(<?= $row['count'] ?>);
    <?php endwhile; ?>

    // Create time distribution chart
    new Chart(document.getElementById('timeChart'), {
      type: 'bar',
      data: {
        labels: timeData.labels,
        datasets: [{
          label: 'Feedback Count',
          data: timeData.data,
          backgroundColor: 'rgba(54, 162, 235, 0.5)',
          borderColor: 'rgb(54, 162, 235)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });
  </script>
</body>
</html> 