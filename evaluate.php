<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';
require_once __DIR__ . '/../includes/photo_uploader.php';

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
$job_id = $_GET['id'] ?? 0;

// Get job details
$stmt = $pdo->prepare("SELECT * FROM jobposting WHERE job_id = ? AND company_id = ?");
$stmt->execute([$job_id, $company_id]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: jobs.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_title = $_POST['job_title'];
    $job_description = $_POST['job_description'];
    $job_location = $_POST['job_location'];
    $job_allowance_range = $_POST['job_allowance_range'];
    $job_requirement = $_POST['job_requirement'];
    $job_status = $_POST['job_status'];
    
    try {
        $poster = decode_cropped_image($_POST['job_poster_cropped'] ?? null);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE jobposting SET 
            job_title = ?, job_description = ?, job_location = ?,
            job_allowance_range = ?, job_requirement = ?, job_status = ?
            WHERE job_id = ? AND company_id = ?
        ");
        $stmt->execute([$job_title, $job_description, $job_location, $job_allowance_range, $job_requirement, $job_status, $job_id, $company_id]);

        if ($poster) {
            $stmt = $pdo->prepare('UPDATE jobposting SET job_poster_name = ?, job_poster_type = ?, job_poster_data = ?, job_poster_uploaded_at = NOW() WHERE job_id = ? AND company_id = ?');
            $stmt->bindValue(1, $poster['name']);
            $stmt->bindValue(2, $poster['type']);
            $stmt->bindValue(3, $poster['data'], PDO::PARAM_LOB);
            $stmt->bindValue(4, (int) $job_id, PDO::PARAM_INT);
            $stmt->bindValue(5, (int) $company_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        $pdo->commit();
        $success = "Job updated successfully!";
        $stmt = $pdo->prepare("SELECT * FROM jobposting WHERE job_id = ? AND company_id = ?");
        $stmt->execute([$job_id, $company_id]);
        $job = $stmt->fetch();
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Update failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - InternHub</title>
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
        .content {
            padding: 20px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
    <?php photo_uploader_assets(); ?>
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
            <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Company'); ?></div>
            <div class="card">
                <div class="card-header">
                    <h4>Edit Job: <?php echo htmlspecialchars($job['job_title']); ?></h4>
                </div>
                <div class="card-body">
                    <?php if($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label>Job Title</label>
                            <input type="text" name="job_title" class="form-control" value="<?php echo htmlspecialchars($job['job_title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Location</label>
                            <select name="job_location" class="form-select" required>
                                <option value="">Select state</option>
                                <?php echo select_options(malaysia_states(), $job['job_location'] ?? ''); ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Allowance</label>
                            <select name="job_allowance_range" class="form-select" required>
                                <option value="">Select allowance range</option>
                                <?php echo select_options(allowance_ranges(), $job['job_allowance_range'] ?? ''); ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label>Job Description</label>
                            <textarea name="job_description" class="form-control" rows="6" required><?php echo htmlspecialchars($job['job_description']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label>Requirements</label>
                            <textarea name="job_requirement" class="form-control" rows="4"><?php echo htmlspecialchars($job['job_requirement']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Internship Promotion Poster</label>
                            <?php photo_uploader_field([
                                'name' => 'job_poster_cropped',
                                'aspect' => '4 / 5',
                                'outW' => 1000,
                                'outH' => 1250,
                                'frameW' => 220,
                                'ratioLabel' => '4:5',
                                'current' => !empty($job['job_poster_data']) ? '../job_poster.php?id=' . (int) $job_id . '&v=' . urlencode($job['job_poster_uploaded_at'] ?? '') : '',
                                'help' => 'Portrait poster. Drag to position and zoom. Choosing a new photo replaces the current one.',
                            ]); ?>
                        </div>
                        
                        <div class="mb-3">
                            <label>Status</label>
                            <select name="job_status" class="form-control">
                                <option value="Active" <?php echo $job['job_status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Expiring" <?php echo $job['job_status'] == 'Expiring' ? 'selected' : ''; ?>>Expiring</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Job</button>
                        <a href="jobs.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
