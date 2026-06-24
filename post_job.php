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

// Get all jobs
$stmt = $pdo->prepare("
    SELECT j.job_id, j.job_title, j.job_location, j.job_allowance_range, j.job_status,
        j.job_poster_uploaded_at, (j.job_poster_data IS NOT NULL) AS has_poster,
        (SELECT COUNT(*) FROM application WHERE job_id = j.job_id) as total_applications
    FROM jobposting j
    WHERE j.company_id = ?
    ORDER BY j.job_id DESC
");
$stmt->execute([$company_id]);
$jobs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - InternHub</title>
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
            <a href="jobs.php" class="active">Manage Jobs</a>
            <a href="applications.php">Applications</a>
            <a href="interns.php">My Interns</a>
            <a href="../logout.php" class="text-danger">Logout</a>
        </div>
        
        <div class="col-md-10 content">
            <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Company'); ?></div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Job Listings</h2>
                <a href="post_job.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Post New Job
                </a>
            </div>
            
            <?php if(count($jobs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Job Title</th>
                                <th>Poster</th>
                                <th>Location</th>
                                <th>Allowance</th>
                                <th>Status</th>
                                <th>Applications</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($jobs as $job): ?>
                            <tr>
                                <td><?php echo $job['job_id']; ?></td>
                                <td><?php echo htmlspecialchars($job['job_title']); ?></td>
                                <td>
                                    <?php if (!empty($job['has_poster'])): ?>
                                        <img src="../job_poster.php?id=<?php echo (int) $job['job_id']; ?>" alt="Internship poster" style="width:64px;aspect-ratio:4/5;object-fit:cover" class="img-thumbnail" draggable="false" oncontextmenu="return false;">
                                    <?php else: ?>
                                        <span class="text-muted">No poster</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($job['job_location'] ?: 'Not specified'); ?></td>
                                <td><?php echo htmlspecialchars($job['job_allowance_range'] ?: 'Not specified'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $job['job_status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                        <?php echo $job['job_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="applications.php?job_id=<?php echo $job['job_id']; ?>">
                                        <?php echo $job['total_applications']; ?> applications
                                    </a>
                                </td>
                                <td>
                                    <a href="edit_job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No jobs posted yet. <a href="post_job.php">Post your first job</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
