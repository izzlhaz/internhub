<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evaluation_helpers.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header('Location: ../login.php');
    exit();
}

ensure_management_schema($pdo);
ensure_company_evaluation_round_schema($pdo);
$companySummary = company_evaluation_summary_sql('ce');
$batchOptions = student_batch_options($pdo);
$sessionFilter = strtoupper(trim($_GET['session'] ?? $_GET['batch'] ?? ''));
$semesterFilter = $_GET['semester'] ?? '';
$format = ($_GET['format'] ?? '') === 'xls' ? 'xls' : '';
if (!in_array($semesterFilter, ['', '1', '2'], true)) {
    $semesterFilter = '';
}

$fullyEvaluated = $pdo->query("
    SELECT i.internship_id
    FROM internship i
    JOIN {$companySummary} ON ce.internship_id = i.internship_id AND ce.ce_count = 2
    JOIN lecturerevaluation le ON le.internship_id = i.internship_id
")->fetchAll();
foreach ($fullyEvaluated as $internship) {
    sync_report($pdo, $internship['internship_id']);
}

$where = [
    'ce.ce_count = 2',
    'le.le_id IS NOT NULL',
    'r.report_total_score IS NOT NULL',
];
$params = [];
if ($sessionFilter !== '') {
    $where[] = 's.student_intake = ?';
    $params[] = $sessionFilter;
}
if ($semesterFilter !== '') {
    $where[] = 'RIGHT(s.student_intake, 1) = ?';
    $params[] = $semesterFilter;
}

$stmt = $pdo->prepare("
    SELECT s.student_matric_no, s.student_name, s.student_email, s.student_phone,
           s.student_course, s.student_intake,
           c.company_name, c.company_type,
           ce.ce_total_score, le.le_total_score, r.report_total_score, r.report_grade
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    JOIN {$companySummary} ON ce.internship_id = i.internship_id
    JOIN lecturerevaluation le ON le.internship_id = i.internship_id
    JOIN report r ON r.internship_id = i.internship_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.student_intake DESC, UPPER(s.student_name) ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$headers = ['Student Profile', 'Session', 'Semester', 'Programme', 'Internship Company', 'Industry Type', 'Lecturer Marks', 'Company Marks', 'Total Marks', 'Grade'];

$query = http_build_query(array_filter(['session' => $sessionFilter, 'semester' => $semesterFilter], fn($value) => $value !== ''));
$fileSuffix = $sessionFilter !== '' ? '_' . preg_replace('/[^A-Z0-9]/', '', $sessionFilter) : ($semesterFilter !== '' ? '_semester_' . $semesterFilter : '_all_sessions');

if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="fully_evaluated_reports' . $fileSuffix . '.xls"');
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Reports Export - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<?php if ($format !== 'xls'): ?><link rel="stylesheet" href="../assets/css/theme.css"><?php endif; ?>
<style>
.report-card .card-body{padding:1.25rem}
.report-table{width:100%;table-layout:fixed;margin-bottom:0;font-size:.82rem}
.report-table th,.report-table td{padding:.75rem .55rem;vertical-align:middle;overflow-wrap:anywhere;word-break:normal}
.report-table thead th{white-space:normal;overflow-wrap:normal;word-break:keep-all;hyphens:none;line-height:1.2;text-align:center;vertical-align:middle;font-family:'Hanken Grotesk',system-ui,sans-serif !important;text-transform:none !important;letter-spacing:0 !important;font-size:.72rem}
.report-table td:nth-child(2),.report-table td:nth-child(9),.report-table td:nth-child(10){text-align:center}
.report-table td:nth-child(2),.report-table td:nth-child(6){white-space:nowrap}
.student-name{display:block;font-weight:700;color:#3f0711;margin-bottom:.2rem}
.student-meta{display:block;color:#6c757d;font-size:.76rem;line-height:1.45}
.mark-value{display:inline-block;min-width:4.5rem;padding:.35rem .45rem;border-radius:.45rem;background:#f8ebef;color:#4b0b16;font-weight:700;text-align:center;white-space:nowrap}
.total-value{background:#f4e4a6;color:#3d3000}
.grade-value{display:inline-flex;align-items:center;justify-content:center;min-width:2.7rem;padding:.38rem .55rem;border-radius:999px;background:#8f1738;color:#fff;font-weight:800}
@media (max-width:1199.98px){.report-table{font-size:.76rem}.report-table th,.report-table td{padding:.6rem .4rem}.report-card .card-body{padding:.75rem}}
@media print{
    @page{size:landscape;margin:8mm}
    .no-print,.sidebar{display:none!important}
    .content{width:100%!important;max-width:none!important;padding:0!important}
    .card{border:0!important;box-shadow:none!important}
    .report-card .card-body{padding:0!important}
    .report-table{font-size:8pt}
    .report-table th,.report-table td{padding:4pt 3pt}
    .mark-value,.grade-value{padding:0;background:transparent!important;color:#000!important;min-width:0}
}
</style></head>
<body>
<?php if ($format !== 'xls'): ?>
<div class="container-fluid"><div class="row"><?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?><div class="col-md-10 content">
<div class="text-end text-muted small mb-2 no-print">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<h2>Export Fully Evaluated Reports</h2>
<form class="card card-body mb-3 no-print" method="GET"><div class="row g-3 align-items-end">
<div class="col-md-4"><label class="form-label">Session</label><select class="form-select" name="session"><option value="">All Sessions</option><?php foreach ($batchOptions as $session): ?><option value="<?php echo htmlspecialchars($session); ?>" <?php echo $sessionFilter === strtoupper($session) ? 'selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($session)); ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Semester</label><select class="form-select" name="semester"><option value="">All Semesters</option><option value="1" <?php echo $semesterFilter === '1' ? 'selected' : ''; ?>>Semester 1</option><option value="2" <?php echo $semesterFilter === '2' ? 'selected' : ''; ?>>Semester 2</option></select></div>
<div class="col-md-5"><button class="btn btn-primary" type="submit">Apply Filter</button> <a class="btn btn-outline-secondary" href="export_reports.php">Reset</a></div>
</div></form>
<div class="card mb-3 no-print"><div class="card-body d-flex flex-wrap gap-2"><a class="btn btn-primary" href="export_reports.php?<?php echo htmlspecialchars($query ? $query . '&' : ''); ?>format=xls">Export Excel</a><button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print / Save PDF</button><span class="ms-auto text-muted align-self-center"><?php echo count($rows); ?> fully evaluated record(s)</span></div></div>
<div class="card report-card"><div class="card-body">
<?php endif; ?>
<table class="table table-bordered table-striped report-table">
<colgroup>
<col style="width:18%"><col style="width:6%"><col style="width:8%"><col style="width:13%"><col style="width:11%"><col style="width:9%"><col style="width:10%"><col style="width:10%"><col style="width:8%"><col style="width:7%">
</colgroup>
<thead><tr><?php foreach ($headers as $header): ?><th><?php echo htmlspecialchars($header); ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td><span class="student-name"><?php echo htmlspecialchars($row['student_name']); ?></span><span class="student-meta"><?php echo htmlspecialchars($row['student_matric_no']); ?><br><?php echo htmlspecialchars($row['student_email']); ?><br><?php echo htmlspecialchars($row['student_phone'] ?: '-'); ?></span></td>
<td><?php echo htmlspecialchars(strtoupper($row['student_intake'])); ?></td>
<td><?php echo htmlspecialchars(semester_from_batch($row['student_intake'])); ?></td>
<td><?php echo htmlspecialchars(programme_short_label($row['student_course'])); ?></td>
<td><?php echo htmlspecialchars($row['company_name']); ?></td>
<td><?php echo htmlspecialchars($row['company_type'] ?: '-'); ?></td>
<td class="text-center"><span class="mark-value"><?php echo htmlspecialchars($row['le_total_score']); ?>/60</span></td>
<td class="text-center"><span class="mark-value"><?php echo htmlspecialchars(weighted_company_score($row['ce_total_score'], 2)); ?>/40</span></td>
<td><span class="mark-value total-value"><?php echo htmlspecialchars($row['report_total_score']); ?>/100</span></td>
<td><span class="grade-value"><?php echo htmlspecialchars($row['report_grade']); ?></span></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No students evaluated by both company and lecturer match this session or semester.</td></tr><?php endif; ?>
</tbody>
</table>
<?php if ($format !== 'xls'): ?>
</div></div></div></div></div>
<?php endif; ?>
</body></html>
