<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';
require_once __DIR__ . '/../includes/evaluation_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header('Location: ../login.php');
    exit();
}

$company_id = $_SESSION['company_id'];
ensure_management_schema($pdo);
ensure_company_evaluation_round_schema($pdo);
require_company_approval($pdo, $company_id);

try {
    $stmt = $pdo->prepare("
        SELECT i.*, s.student_name, s.student_course, l.lecturer_name, j.job_title,
               ce.ce_first_id, ce.ce_final_id, ce.ce_first_score, ce.ce_final_score
        FROM internship i
        JOIN student s ON i.student_id = s.student_id
        JOIN lecturer l ON i.lecturer_id = l.lecturer_id
        LEFT JOIN jobposting j ON i.job_id = j.job_id
        LEFT JOIN " . company_evaluation_summary_sql('ce') . " ON ce.internship_id = i.internship_id
        WHERE i.company_id = ? AND i.internship_status IN ('Active', 'Completed')
        ORDER BY UPPER(s.student_name) ASC
    ");
    $stmt->execute([$company_id]);
    $interns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching interns: ' . $e->getMessage());
    $interns = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Interns - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar { min-height: 100vh; background: #2c3e50; }
        .sidebar a { color: white; text-decoration: none; padding: 12px 20px; display: block; transition: .3s; }
        .sidebar a:hover { background: #34495e; }
        .sidebar a.active { background: #1abc9c; }
        .content { padding: 20px; }
        .status-badge { display: inline-block; padding: 6px 10px; border-radius: 5px; font-size: 12px; font-weight: 700; }
        .status-active { background: #d4edda; color: #155724; }
        .status-completed { background: #cce5ff; color: #004085; }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0 sidebar">
            <div class="sb-brand">
                <img class="sb-logo" src="../assets/img/logo-light.png" alt="TISSA &middot; Universiti Utara Malaysia">
                <span class="sb-wordmark">Intern<span>Hub</span></span>
            </div>
            <hr class="bg-white">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
            <a href="profile.php"><i class="fas fa-building me-2"></i> Company Profile</a>
            <a href="jobs.php"><i class="fas fa-briefcase me-2"></i> Manage Jobs</a>
            <a href="applications.php"><i class="fas fa-file-alt me-2"></i> Applications</a>
            <a href="interns.php" class="active"><i class="fas fa-users me-2"></i> My Interns</a>
            <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>
        <div class="col-md-10 content">
            <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Company'); ?></div>
            <h2><i class="fas fa-users me-2"></i> My Interns</h2>
            <p class="text-muted">Manage and evaluate your interns</p>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-users me-2"></i> Intern List</h5></div>
                <div class="card-body">
                    <?php if ($interns): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Course</th>
                                        <th>Job Title</th>
                                        <th>Supervisor</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>First 3 Month Evaluation</th>
                                        <th>Final Evaluation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($interns as $intern): ?>
                                        <?php $completed = $intern['internship_status'] === 'Completed'; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($intern['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars(programme_short_label($intern['student_course'])); ?></td>
                                            <td><?php echo htmlspecialchars($intern['job_title'] ?: 'Not specified'); ?></td>
                                            <td><?php echo htmlspecialchars($intern['lecturer_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($intern['internship_start_date'])); ?></td>
                                            <td><?php echo date('d M Y', strtotime($intern['internship_end_date'])); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $completed ? 'status-completed' : 'status-active'; ?>">
                                                    <i class="fas <?php echo $completed ? 'fa-check' : 'fa-circle'; ?> me-1"></i><?php echo htmlspecialchars($intern['internship_status']); ?>
                                                </span>
                                            </td>
                                            <td><a href="evaluate.php?internship_id=<?php echo (int) $intern['internship_id']; ?>&round=1" class="btn btn-sm <?php echo $intern['ce_first_id'] ? 'btn-outline-secondary' : 'btn-primary'; ?>"><i class="fas <?php echo $intern['ce_first_id'] ? 'fa-check' : 'fa-star'; ?> me-1"></i><?php echo $intern['ce_first_id'] ? 'Submitted' : 'Evaluate'; ?></a><?php if ($intern['ce_first_score'] !== null): ?><br><small><?php echo (int) $intern['ce_first_score']; ?>/84</small><?php endif; ?></td>
                                            <td><a href="evaluate.php?internship_id=<?php echo (int) $intern['internship_id']; ?>&round=2" class="btn btn-sm <?php echo $intern['ce_final_id'] ? 'btn-outline-secondary' : 'btn-primary'; ?>"><i class="fas <?php echo $intern['ce_final_id'] ? 'fa-check' : 'fa-star'; ?> me-1"></i><?php echo $intern['ce_final_id'] ? 'Submitted' : 'Evaluate'; ?></a><?php if ($intern['ce_final_score'] !== null): ?><br><small><?php echo (int) $intern['ce_final_score']; ?>/84</small><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0"><i class="fas fa-info-circle"></i> No interns found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
