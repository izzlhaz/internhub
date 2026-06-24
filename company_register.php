<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/management_helpers.php';
include __DIR__ . '/../includes/header.php';

ensure_management_schema($pdo);

// Get search/filter
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';

// Build query
$sql = "
    SELECT j.job_id, j.company_id, j.job_title, j.job_description, j.job_location,
           j.job_status, j.job_requirement, j.job_allowance_range, j.job_posted_date,
           j.job_poster_uploaded_at, (j.job_poster_data IS NOT NULL) AS has_poster,
           c.company_name, c.company_address
    FROM jobposting j
    JOIN company c ON j.company_id = c.company_id
    WHERE j.job_status = 'Active'
";
$params = [];

if ($search) {
    $sql .= " AND (j.job_title LIKE ? OR j.job_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($location) {
    $sql .= " AND j.job_location LIKE ?";
    $params[] = "%$location%";
}

$sql .= " ORDER BY j.job_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Get already applied jobs
$stmt2 = $pdo->prepare("SELECT job_id FROM application WHERE student_id = ?");
$stmt2->execute([$student_id]);
$applied_jobs = $stmt2->fetchAll(PDO::FETCH_COLUMN);

$stmt3 = $pdo->prepare("SELECT COUNT(*) AS total FROM internship WHERE student_id = ? AND internship_status IN ('Accepted','Active')");
$stmt3->execute([$student_id]);
$has_active_internship = (int) $stmt3->fetch()['total'] > 0;
?>

<h2>Available Internships</h2>

<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<?php if ($has_active_internship): ?>
    <div class="alert alert-info">
        You are already enrolled in an internship. Job applications are closed while your internship is active.
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by title or description" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-5">
                <input type="text" name="location" class="form-control" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if(count($jobs) > 0): ?>
<div class="joblist">
    <?php foreach($jobs as $job):
        $postedDate = $job['job_posted_date'] ?? null;
        $daysOpen = $postedDate ? max(0, (int) floor((time() - strtotime($postedDate)) / 86400)) : 0;
        $postedTxt = $daysOpen === 0 ? 'Posted today' : 'Open for ' . $daysOpen . ' day' . ($daysOpen === 1 ? '' : 's');
        $applied = in_array($job['job_id'], $applied_jobs);
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($job['job_description'])));
        $descShort = strlen($desc) > 190 ? rtrim(substr($desc, 0, 190)) . '…' : $desc;
    ?>
        <article class="jobcard">
            <div class="jobcard-poster">
                <?php if (!empty($job['has_poster'])): ?>
                    <img src="../job_poster.php?id=<?php echo (int) $job['job_id']; ?>&v=<?php echo urlencode($job['job_poster_uploaded_at'] ?? ''); ?>" alt="<?php echo htmlspecialchars($job['job_title']); ?> poster" draggable="false" oncontextmenu="return false;">
                <?php else: ?>
                    <i class="fas fa-briefcase ph"></i><span class="ph-cap">No poster</span>
                <?php endif; ?>
            </div>
            <div class="jobcard-body">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <h3 class="jobcard-title"><?php echo htmlspecialchars($job['job_title']); ?></h3>
                    <?php if ($has_active_internship): ?>
                        <span class="badge bg-secondary">Applications Closed</span>
                    <?php elseif ($applied): ?>
                        <span class="badge bg-success">Already Applied</span>
                    <?php endif; ?>
                </div>
                <div class="jobcard-meta">
                    <span><i class="fas fa-building"></i><?php echo htmlspecialchars($job['company_name']); ?></span>
                    <span><i class="fas fa-location-dot"></i><?php echo htmlspecialchars($job['job_location'] ?: 'Not specified'); ?></span>
                    <span><i class="fas fa-calendar"></i><?php echo $postedTxt; ?></span>
                    <?php if (!empty($job['job_allowance_range'])): ?><span class="jobcard-tag"><?php echo htmlspecialchars($job['job_allowance_range']); ?></span><?php endif; ?>
                </div>
                <p class="jobcard-desc"><?php echo htmlspecialchars($descShort); ?></p>
                <div class="jobcard-foot">
                    <?php if ($has_active_internship): ?>
                        <button class="btn btn-secondary btn-sm" disabled>Applications Closed</button>
                    <?php elseif ($applied): ?>
                        <button class="btn btn-outline-secondary btn-sm" disabled>Already Applied</button>
                    <?php else: ?>
                        <a href="apply_job.php?job_id=<?php echo (int) $job['job_id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>Apply Now</a>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#details<?php echo (int) $job['job_id']; ?>"><i class="fas fa-circle-info me-1"></i>View Details</button>
                </div>
                <div class="collapse mt-3" id="details<?php echo (int) $job['job_id']; ?>">
                    <div class="card card-body">
                        <h6>Full Description</h6>
                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($job['job_description'])); ?></p>
                        <?php if ($job['job_requirement']): ?>
                            <h6>Requirements</h6>
                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($job['job_requirement'])); ?></p>
                        <?php endif; ?>
                        <h6>Company Info</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($job['company_address'] ?: 'Address not provided')); ?></p>
                    </div>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="alert alert-info"><i class="fas fa-circle-info me-2"></i>No job listings found.</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
