<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if logged in as company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
ensure_management_schema($pdo);
require_company_approval($pdo, $company_id);
$status_filter = $_GET['status'] ?? '';
$job_filter = $_GET['job_id'] ?? '';

// Build query
$sql = "
    SELECT a.*, j.job_title, j.job_location, s.student_name, s.student_email, s.student_phone, s.student_course, r.resume_id
    FROM application a
    JOIN jobposting j ON a.job_id = j.job_id
    JOIN student s ON a.student_id = s.student_id
    JOIN resume r ON a.resume_id = r.resume_id
    WHERE j.company_id = ?
";
$params = [$company_id];

if ($status_filter) {
    $sql .= " AND a.application_status = ?";
    $params[] = $status_filter;
}

if ($job_filter) {
    $sql .= " AND a.job_id = ?";
    $params[] = $job_filter;
}

$sql .= " ORDER BY
    CASE
        WHEN a.application_status = 'Pending' THEN 0
        WHEN a.application_status IN ('Accepted', 'Accept') THEN 1
        ELSE 2
    END,
    UPPER(s.student_name) ASC,
    a.application_applied_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get jobs for filter
$stmt2 = $pdo->prepare("SELECT job_id, job_title FROM jobposting WHERE company_id = ?");
$stmt2->execute([$company_id]);
$jobs = $stmt2->fetchAll();

$status_colors = [
    'Pending' => 'warning',
    'Shortlisted' => 'info',
    'Interview' => 'primary',
    'Accepted' => 'success',
    'Rejected' => 'danger'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: 0.3s;
        }
        .sidebar a:hover {
            background: #34495e;
        }
        .sidebar a.active {
            background: #3498db;
        }
        .content {
            padding: 20px;
        }
    </style>
    <link rel="stylesheet" href="">
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
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">Company Profile</a>
            <a href="jobs.php">Manage Jobs</a>
            <a href="applications.php" class="active">Applications</a>
            <a href="interns.php">My Interns</a>
            <a href="../logout.php" class="text-danger">Logout</a>
        </div>
        
        <div class="col-md-10 content">
            <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Company'); ?></div>
            <h2>Student Applications</h2>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-4">
                            <label>Filter by Job</label>
                            <select name="job_id" class="form-control">
                                <option value="">All Jobs</option>
                                <?php foreach($jobs as $job): ?>
                                    <option value="<?php echo $job['job_id']; ?>" <?php echo $job_filter == $job['job_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job['job_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Filter by Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <?php foreach (application_statuses() as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status_filter == $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary form-control">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if(count($applications) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Position</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($applications as $app): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['student_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($app['student_email']); ?></small>
                                 </td>
                                 <td><?php echo htmlspecialchars(programme_short_label($app['student_course'])); ?></td>
                                 <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                 <td><?php echo date('d M Y', strtotime($app['application_applied_date'])); ?></td>
                                 <td>
                                    <?php $display_status = normalise_application_status($app['application_status']); ?>
                                    <span class="badge bg-<?php echo $status_colors[$display_status] ?? 'secondary'; ?>">
                                        <?php echo htmlspecialchars($display_status); ?>
                                    </span>
                                 </td>
                                 <td>
                                    <a href="view_application.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No applications found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
