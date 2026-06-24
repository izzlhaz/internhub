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
$application_id = $_GET['id'] ?? 0;

// Get application details
$stmt = $pdo->prepare("
    SELECT a.*, j.job_title, j.job_description, j.job_location, j.job_requirement,
           s.student_name, s.student_email, s.student_phone, s.student_course, s.student_intake,
           s.student_id, s.student_photo_data IS NOT NULL AS has_photo,
           r.resume_id, r.resume_file_name, r.resume_uploaded_at, r.resume_file_data IS NOT NULL AS has_resume
    FROM application a
    JOIN jobposting j ON a.job_id = j.job_id
    JOIN student s ON a.student_id = s.student_id
    JOIN resume r ON a.resume_id = r.resume_id
    WHERE a.application_id = ? AND j.company_id = ?
");
$stmt->execute([$application_id, $company_id]);
$app = $stmt->fetch();

if (!$app) {
    header("Location: applications.php");
    exit();
}

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE application SET application_status = ? WHERE application_id = ?");
    $stmt->execute([$new_status, $application_id]);
    header("Location: view_application.php?id=" . $application_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        .va-hero { display: flex; gap: 22px; flex-wrap: wrap; align-items: center; }
        .va-photo { width: 132px; aspect-ratio: 7/10; border-radius: 12px; overflow: hidden; border: 1px solid var(--line); flex: 0 0 auto; box-shadow: var(--shadow-sm); }
        .va-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .va-photo-ph { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 40px; color: var(--maroon-300); background: linear-gradient(160deg, var(--maroon-100), var(--surface-muted)); }
        .va-name { font-family: var(--font-display); font-weight: 700; font-size: 1.65rem; line-height: 1.1; margin: 2px 0 4px; color: var(--ink-950); }
        .va-prog { font-family: var(--font-mono); font-size: .76rem; letter-spacing: .08em; text-transform: uppercase; color: var(--maroon-600); margin-bottom: 12px; }
        .va-contacts { display: flex; flex-direction: column; gap: 7px; font-size: 14px; color: var(--ink-700); }
        .va-contacts a { color: inherit; }
        .va-contacts i { color: var(--maroon-500); width: 18px; text-align: center; margin-right: 8px; }
        .va-jobgrid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
        .va-jobgrid .lbl { display: block; font-family: var(--font-mono); font-size: .6rem; letter-spacing: .1em; text-transform: uppercase; color: var(--ink-500); margin-bottom: 3px; }
        .va-jobgrid .val { font-weight: 600; color: var(--ink-950); }
        .va-resume { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .va-resume-ic { width: 44px; height: 44px; border-radius: 10px; background: var(--maroon-50); color: var(--maroon-600); display: inline-flex; align-items: center; justify-content: center; font-size: 18px; flex: 0 0 auto; }
    </style>
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
            <a href="applications.php">Applications</a>
            <a href="interns.php">My Interns</a>
            <a href="../logout.php" class="text-danger">Logout</a>
        </div>
        
        <div class="col-md-10 content">
            <?php
                $current_status = normalise_application_status($app['application_status']);
                $statusColors = ['Pending' => 'secondary', 'Shortlisted' => 'warning', 'Interview' => 'info', 'Accepted' => 'success', 'Rejected' => 'danger'];
                $statusColor = $statusColors[$current_status] ?? 'secondary';
            ?>
            <div class="ih-pagehead">
                <div>
                    <div class="ih-kicker">Company &middot; Application Review</div>
                    <h1 class="ih-title"><?php echo htmlspecialchars(ucwords(strtolower($app['student_name']))); ?></h1>
                </div>
                <div class="ih-pagehead-right">
                    <span class="badge bg-<?php echo $statusColor; ?>"><?php echo htmlspecialchars($current_status); ?></span>
                    <a href="applications.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <!-- Candidate -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="va-hero">
                                <div class="va-photo">
                                    <?php if ($app['has_photo']): ?>
                                        <img src="../student_photo.php?id=<?php echo (int) $app['student_id']; ?>" alt="Formal student picture">
                                    <?php else: ?>
                                        <div class="va-photo-ph"><i class="fas fa-user"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1" style="min-width:220px;">
                                    <div class="ih-kicker">Candidate</div>
                                    <div class="va-name"><?php echo htmlspecialchars(ucwords(strtolower($app['student_name']))); ?></div>
                                    <div class="va-prog"><?php echo htmlspecialchars(programme_short_label($app['student_course'])); ?> &middot; <?php echo htmlspecialchars(strtoupper($app['student_intake'])); ?></div>
                                    <div class="va-contacts">
                                        <div><i class="fas fa-envelope"></i><a href="mailto:<?php echo htmlspecialchars($app['student_email']); ?>"><?php echo htmlspecialchars($app['student_email']); ?></a></div>
                                        <div><i class="fas fa-phone"></i><?php echo htmlspecialchars($app['student_phone'] ?: 'Not provided'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resume -->
                    <div class="card mb-3">
                        <div class="card-header"><h5 class="mb-0">Resume</h5></div>
                        <div class="card-body">
                            <?php if ($app['has_resume']): ?>
                                <div class="va-resume">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="va-resume-ic"><i class="fas fa-file-pdf"></i></span>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($app['resume_file_name']); ?></div>
                                            <?php if (!empty($app['resume_uploaded_at'])): ?><small class="text-muted">Uploaded <?php echo date('d M Y', strtotime($app['resume_uploaded_at'])); ?></small><?php endif; ?>
                                        </div>
                                    </div>
                                    <a class="btn btn-primary" href="../download_resume.php?id=<?php echo (int) $app['resume_id']; ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square me-2"></i>Open PDF</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0"><i class="fas fa-triangle-exclamation me-2"></i>No PDF resume is available for this application.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Position applied -->
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Position Applied</h5></div>
                        <div class="card-body">
                            <div class="va-jobgrid">
                                <div><span class="lbl">Position</span><span class="val"><?php echo htmlspecialchars($app['job_title']); ?></span></div>
                                <div><span class="lbl">Location</span><span class="val"><?php echo htmlspecialchars($app['job_location'] ?: 'Not specified'); ?></span></div>
                                <div><span class="lbl">Applied</span><span class="val"><?php echo date('d M Y, h:i A', strtotime($app['application_applied_date'])); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Update status -->
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Update Status</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <label class="form-label">Application status</label>
                                <select name="status" class="form-select mb-3">
                                    <?php foreach (application_statuses() as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $current_status == $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary w-100"><i class="fas fa-check me-2"></i>Update Status</button>
                            </form>
                            <?php if ($current_status == 'Accepted'): ?>
                                <div class="alert alert-success mt-3 mb-0"><i class="fas fa-circle-check me-2"></i>Accepted. The coordinator can now assign the lecturer supervisor and internship dates.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
