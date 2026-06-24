<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evaluation_helpers.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}

$stmt = $pdo->query("SELECT internship_id FROM internship");
foreach ($stmt->fetchAll() as $row) {
    sync_report($pdo, $row['internship_id']);
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$gradeFilter = $_GET['grade'] ?? '';
$evalFilter = $_GET['evaluation'] ?? '';
$batchOptions = student_batch_options($pdo);
$batchFilter = strtoupper(trim($_GET['batch'] ?? ($batchOptions[0] ?? '')));
$companySummary = company_evaluation_summary_sql('ce');
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(s.student_name LIKE ? OR s.student_matric_no LIKE ? OR c.company_name LIKE ? OR l.lecturer_name LIKE ?)";
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($statusFilter !== '') {
    $where[] = "i.internship_status = ?";
    $params[] = $statusFilter;
}
if ($gradeFilter !== '') {
    $where[] = "r.report_grade = ?";
    $params[] = $gradeFilter;
}
if ($evalFilter === 'complete') {
    $where[] = "ce.ce_count = 2 AND le.le_id IS NOT NULL";
} elseif ($evalFilter === 'pending') {
    $where[] = "(COALESCE(ce.ce_count, 0) < 2 OR le.le_id IS NULL)";
}
if ($batchFilter !== '') {
    $where[] = 's.student_intake = ?';
    $params[] = $batchFilter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("
    SELECT i.internship_id, i.internship_status, s.student_name, s.student_course, s.student_intake,
           c.company_name, l.lecturer_name,
           ce.ce_first_id, ce.ce_final_id, ce.ce_count, ce.ce_first_score, ce.ce_final_score, ce.ce_total_score,
           le.le_id, le.le_total_score, r.report_total_score, r.report_grade
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    JOIN lecturer l ON l.lecturer_id = i.lecturer_id
    LEFT JOIN {$companySummary} ON ce.internship_id = i.internship_id
    LEFT JOIN lecturerevaluation le ON le.internship_id = i.internship_id
    LEFT JOIN report r ON r.internship_id = i.internship_id
    $whereSql
    ORDER BY s.student_intake DESC, COALESCE(r.report_id, 0) DESC, s.student_name ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Summary - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        /* Column widths follow the content, not the header text */
        .eval-table { margin-bottom: 0; }
        .eval-table thead th { white-space: normal !important; vertical-align: middle; line-height: 1.25; }
        .eval-table td { vertical-align: middle; }
        /* keep the long identity columns on one line so rows stay compact */
        .eval-table td:nth-child(1),
        .eval-table td:nth-child(2),
        .eval-table td:nth-child(3),
        .eval-table td:nth-child(4) { white-space: nowrap; }
        .eval-table td small { color: var(--ink-500); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
        <div class="col-md-10 content">
            <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Evaluation Summary</h2>
                <a class="btn btn-primary" href="export_reports.php<?php echo $batchFilter !== '' ? '?session=' . urlencode($batchFilter) : ''; ?>">Generate Student Transcript/Report</a>
            </div>
            <form class="card card-body mb-3" method="GET">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3"><label class="form-label">Search</label><input class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Student, matric, company or lecturer"></div>
                    <div class="col-lg-2"><label class="form-label">Batch</label><select class="form-select" name="batch"><option value="">All Batches</option><?php foreach ($batchOptions as $batch): ?><option value="<?php echo htmlspecialchars($batch); ?>" <?php echo $batchFilter === strtoupper($batch) ? 'selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($batch)); ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-2"><label class="form-label">Internship Status</label><select class="form-select" name="status"><option value="">All</option><?php foreach (['Active','Completed','Accepted'] as $status): ?><option value="<?php echo $status; ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo $status; ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-2"><label class="form-label">Evaluation</label><select class="form-select" name="evaluation"><option value="">All</option><option value="complete" <?php echo $evalFilter === 'complete' ? 'selected' : ''; ?>>Complete</option><option value="pending" <?php echo $evalFilter === 'pending' ? 'selected' : ''; ?>>Pending</option></select></div>
                    <div class="col-lg-2"><label class="form-label">Grade</label><select class="form-select" name="grade"><option value="">All</option><?php foreach (['A+','A','A-','B+','B','B-','C+','C','C-','D+','D','F'] as $grade): ?><option value="<?php echo $grade; ?>" <?php echo $gradeFilter === $grade ? 'selected' : ''; ?>><?php echo $grade; ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-1"><button class="btn btn-primary" type="submit">Filter</button></div>
                </div>
            </form>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover eval-table">
                            <thead class="table-dark">
                                <tr><th>Student</th><th>Company</th><th>Lecturer</th><th>Company Eval</th><th>Lecturer Eval</th><th>Total</th><th>Grade</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?><br><small><?php echo htmlspecialchars(programme_short_label($row['student_course'])); ?> | <?php echo htmlspecialchars(strtoupper($row['student_intake'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['lecturer_name']); ?></td>
                                    <td><?php echo (int) $row['ce_count'] === 2 ? htmlspecialchars(number_format((weighted_company_score($row['ce_total_score'], 2) / 40) * 100, 1)) . '%<br><small>' . (int) $row['ce_first_score'] . '/84 + ' . (int) $row['ce_final_score'] . '/84</small>' : htmlspecialchars((int) $row['ce_count']) . '/2 submitted'; ?></td>
                                    <td><?php echo $row['le_total_score'] !== null ? htmlspecialchars(number_format(((float) $row['le_total_score'] / 60) * 100, 1)) . '%' : 'Pending'; ?></td>
                                    <td><?php echo (int) $row['ce_count'] === 2 && $row['le_id'] && $row['report_total_score'] !== null ? htmlspecialchars(number_format((float) $row['report_total_score'], 1)) . '%' : 'Pending'; ?></td>
                                    <td><?php echo (int) $row['ce_count'] === 2 && $row['le_id'] && $row['report_grade'] ? '<span class="badge bg-primary">' . htmlspecialchars($row['report_grade']) . '</span>' : 'Pending'; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['internship_status']); ?></span></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="evaluation_result.php?internship_id=<?php echo (int) $row['internship_id']; ?>">Transcript</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
