<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}

ensure_management_schema($pdo);

$message = '';
$error = '';
$viewId = (int) ($_GET['view'] ?? 0);
$company = null;

if (isset($_GET['approve'])) {
    $approveId = (int) $_GET['approve'];
    $stmt = $pdo->prepare("UPDATE company SET company_approval_status = 'Approved' WHERE company_id = ?");
    $stmt->execute([$approveId]);
    $message = 'Company approved.';
}

if ($viewId) {
    $stmt = $pdo->prepare("SELECT * FROM company WHERE company_id = ?");
    $stmt->execute([$viewId]);
    $company = $stmt->fetch();
}

$companies = $pdo->query("
    SELECT c.*,
           SUM(CASE WHEN s.student_course LIKE '%Information Systems%' THEN 1 ELSE 0 END) AS ais_total,
           SUM(CASE WHEN s.student_course NOT LIKE '%Information Systems%' AND s.student_id IS NOT NULL THEN 1 ELSE 0 END) AS accounting_total,
           COUNT(i.internship_id) AS placed_total
    FROM company c
    LEFT JOIN internship i ON i.company_id = c.company_id AND i.internship_status IN ('Accepted','Active','Completed')
    LEFT JOIN student s ON s.student_id = i.student_id
    GROUP BY c.company_id
    ORDER BY c.company_name
")->fetchAll();

$companyGroups = ['pending' => [], 'approved' => []];
foreach ($companies as $row) {
    $group = ($row['company_approval_status'] ?? 'Pending') === 'Approved' ? 'approved' : 'pending';
    $companyGroups[$group][] = $row;
}

function render_company_table(array $companies)
{
    if (!$companies) {
        echo '<div class="alert alert-info mb-0">No companies in this list.</div>';
        return;
    }
    ?>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th>Company</th><th>Industry</th><th>Address</th><th>Allowance</th><th>Capacity</th><th>Placed Students</th><th>Action</th></tr></thead>
        <tbody><?php foreach ($companies as $row): ?><tr>
            <td><strong><?php echo htmlspecialchars($row['company_name']); ?></strong><br><small><?php echo htmlspecialchars($row['company_contact_person'] ?: '-'); ?> | <?php echo htmlspecialchars($row['company_email']); ?></small></td>
            <td><?php echo htmlspecialchars($row['company_type'] ?: '-'); ?></td>
            <td><?php echo htmlspecialchars($row['company_address_line'] ?: $row['company_address'] ?: '-'); ?><br><small><?php echo htmlspecialchars(trim(($row['company_postcode'] ?: '') . ' ' . ($row['company_state'] ?: ''))); ?></small></td>
            <td><?php echo htmlspecialchars($row['company_allowance_range'] ?: '-'); ?></td>
            <td><?php echo htmlspecialchars($row['company_capacity_programme'] ?: '-'); ?><br><small>B.Acct (IS) <?php echo (int) $row['company_capacity_ais']; ?> | B.Acct <?php echo (int) $row['company_capacity_accounting']; ?></small></td>
            <td>B.Acct (IS): <?php echo (int) $row['ais_total']; ?><br>B.Acct: <?php echo (int) $row['accounting_total']; ?><br><strong>Total: <?php echo (int) $row['placed_total']; ?></strong></td>
            <td><a class="btn btn-sm btn-outline-primary" href="companies.php?view=<?php echo (int) $row['company_id']; ?>">View</a><?php if (($row['company_approval_status'] ?? 'Pending') !== 'Approved'): ?> <a class="btn btn-sm btn-success" href="companies.php?approve=<?php echo (int) $row['company_id']; ?>" onclick="return confirm('Approve this company?');">Approve</a><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Companies - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}.company-tabs .nav-link{color:#611525;font-weight:700}.company-tabs .nav-link.active{background:#611525;color:#fff;border-color:#611525}.tab-count{border-radius:99px;padding:.1rem .45rem;background:#f0dfe4}.nav-link.active .tab-count{background:rgba(255,255,255,.2)}</style>
<link rel="stylesheet" href="../assets/css/theme.css"></head>
<body><div class="container-fluid"><div class="row">
<?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
<div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<h2>Company Management</h2>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$company): ?>
<div class="alert alert-info">Companies register themselves from the login page. Coordinator can review and approve registered company profiles below.</div>
<?php endif; ?>

<?php if ($company): ?>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Company Information</h5></div><div class="card-body">
<div class="row g-3">
<div class="col-md-4"><strong>Company Name</strong><div><?php echo htmlspecialchars($company['company_name'] ?: '-'); ?></div></div>
<div class="col-md-4"><strong>Email Address</strong><div><?php echo htmlspecialchars($company['company_email'] ?: '-'); ?></div></div>
<div class="col-md-4"><strong>Contact Number</strong><div><?php echo htmlspecialchars($company['company_phone'] ?: '-'); ?></div></div>
<div class="col-md-4"><strong>Contact Person</strong><div><?php echo htmlspecialchars($company['company_contact_person'] ?: '-'); ?></div></div>
<div class="col-md-4"><strong>Industry Type</strong><div><?php echo htmlspecialchars($company['company_type'] ?: '-'); ?></div></div>
<div class="col-md-4"><strong>Allowance Range</strong><div><?php echo htmlspecialchars($company['company_allowance_range'] ?: '-'); ?></div></div>
<div class="col-md-8"><strong>Address</strong><div><?php echo htmlspecialchars(trim(($company['company_address'] ?: '') . ' ' . ($company['company_address_line'] ?: '') . ' ' . ($company['company_postcode'] ?: '') . ' ' . ($company['company_state'] ?: '')) ?: '-'); ?></div></div>
<div class="col-md-4"><strong>Capacity</strong><div><?php echo htmlspecialchars($company['company_capacity_programme'] ?: '-'); ?>, B.Acct (IS): <?php echo (int) $company['company_capacity_ais']; ?>, B.Acct: <?php echo (int) $company['company_capacity_accounting']; ?></div></div>
<div class="col-12"><strong>Description</strong><div><?php echo nl2br(htmlspecialchars($company['company_description'] ?: '-')); ?></div></div>
<div class="col-12"><a class="btn btn-secondary" href="companies.php">Back to Companies</a></div>
</div>
</div></div>
<?php endif; ?>

<div class="card"><div class="card-header"><h5 class="mb-0">Company Profiles & Capacity</h5></div><div class="card-body">
<ul class="nav nav-tabs company-tabs mb-3" role="tablist">
<li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pending-companies" type="button">Pending Companies <span class="tab-count"><?php echo count($companyGroups['pending']); ?></span></button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#approved-companies" type="button">Approved Companies <span class="tab-count"><?php echo count($companyGroups['approved']); ?></span></button></li>
</ul>
<div class="tab-content"><div class="tab-pane fade show active" id="pending-companies"><?php render_company_table($companyGroups['pending']); ?></div><div class="tab-pane fade" id="approved-companies"><?php render_company_table($companyGroups['approved']); ?></div></div>
</div></div>
</div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
