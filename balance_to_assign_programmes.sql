<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/management_helpers.php';
include __DIR__ . '/../includes/header.php';

ensure_management_schema($pdo);

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM application WHERE student_id = ?");
$stmt->execute([$student_id]);
$applicationsSent = (int) $stmt->fetch()['total'];

$stmt = $pdo->prepare("
    SELECT application_status, COUNT(*) AS total
    FROM application
    WHERE student_id = ?
    GROUP BY application_status
");
$stmt->execute([$student_id]);

$applicationStatusData = [
    'Pending' => 0,
    'Accepted' => 0,
    'Rejected' => 0,
    'Shortlisted' => 0,
    'Interview' => 0,
];
$statusCounts = [
    'accepted' => 0,
    'review' => 0,
    'rejected' => 0,
];

foreach ($stmt->fetchAll() as $row) {
    $status = normalise_application_status($row['application_status']);
    if (isset($applicationStatusData[$status])) {
        $applicationStatusData[$status] += (int) $row['total'];
    }
    if ($status === 'Accepted') {
        $statusCounts['accepted'] += (int) $row['total'];
    } elseif ($status === 'Rejected') {
        $statusCounts['rejected'] += (int) $row['total'];
    } else {
        $statusCounts['review'] += (int) $row['total'];
    }
}

$offersReceived = $statusCounts['accepted'];

$trend = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $trend[$date] = 0;
}

$stmt = $pdo->prepare("
    SELECT DATE(application_applied_date) AS applied_date, COUNT(*) AS total
    FROM application
    WHERE student_id = ? AND application_applied_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(application_applied_date)
");
$stmt->execute([$student_id]);
foreach ($stmt->fetchAll() as $row) {
    if (isset($trend[$row['applied_date']])) {
        $trend[$row['applied_date']] = (int) $row['total'];
    }
}

$stmt = $pdo->prepare("
    SELECT i.*, c.company_name, c.company_address, c.company_state, c.company_type,
           l.lecturer_name
    FROM internship i
    JOIN company c ON c.company_id = i.company_id
    LEFT JOIN lecturer l ON l.lecturer_id = i.lecturer_id
    WHERE i.student_id = ?
      AND i.internship_status IN ('Accepted', 'Active', 'Completed')
    ORDER BY i.internship_start_date DESC
    LIMIT 1
");
$stmt->execute([$student_id]);
$internshipInfo = $stmt->fetch();

$progressRows = [
    ['task' => 'Logbook', 'percentage' => 0],
    ['task' => 'Report', 'percentage' => 0],
    ['task' => 'Attendance', 'percentage' => 0],
    ['task' => 'Supervisor Evaluation', 'percentage' => 0],
];

if ($internshipInfo) {
    $internshipId = (int) $internshipInfo['internship_id'];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM logbook WHERE internship_id = ?");
    $stmt->execute([$internshipId]);
    $submittedLogbooks = (int) $stmt->fetch()['total'];
    $progressRows[0]['percentage'] = min(100, (int) round(($submittedLogbooks / 12) * 100));

    $stmt = $pdo->prepare("SELECT report_total_score FROM report WHERE internship_id = ?");
    $stmt->execute([$internshipId]);
    $reportRow = $stmt->fetch();
    $progressRows[1]['percentage'] = $reportRow && $reportRow['report_total_score'] !== null ? 100 : ($reportRow ? 50 : 0);

    if (!empty($internshipInfo['internship_start_date']) && !empty($internshipInfo['internship_end_date'])) {
        $start = strtotime($internshipInfo['internship_start_date']);
        $end = strtotime($internshipInfo['internship_end_date']);
        $today = time();
        if ($end > $start) {
            $progressRows[2]['percentage'] = min(100, max(0, (int) round((($today - $start) / ($end - $start)) * 100)));
        }
    }

    $stmt = $pdo->prepare("SELECT le_total_score FROM lecturerevaluation WHERE internship_id = ?");
    $stmt->execute([$internshipId]);
    $lecturerEvaluation = $stmt->fetch();
    $progressRows[3]['percentage'] = $lecturerEvaluation && $lecturerEvaluation['le_total_score'] !== null
        ? min(100, (int) round(((float) $lecturerEvaluation['le_total_score'] / 60) * 100))
        : 0;
}

$stmt = $pdo->prepare("SELECT job_id FROM application WHERE student_id = ?");
$stmt->execute([$student_id]);
$appliedJobIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$jobSql = "
    SELECT
        j.job_id,
        j.job_title,
        j.job_location,
        j.job_requirement,
        j.job_posted_date,
        c.company_name,
        c.company_type,
        c.open_slots,
        c.company_capacity_ais,
        c.company_capacity_accounting,
        COUNT(a.application_id) AS applicants,
        ROUND(AVG(r.report_total_score), 1) AS score
    FROM jobposting j
    JOIN company c ON c.company_id = j.company_id
    LEFT JOIN application a ON a.job_id = j.job_id
    LEFT JOIN internship i ON i.company_id = c.company_id
    LEFT JOIN report r ON r.internship_id = i.internship_id
    WHERE j.job_status = 'Active'
";
$jobParams = [];
if (!empty($appliedJobIds)) {
    $jobSql .= " AND j.job_id NOT IN (" . implode(',', array_fill(0, count($appliedJobIds), '?')) . ")";
    $jobParams = array_merge($jobParams, $appliedJobIds);
}
$jobSql .= " GROUP BY j.job_id ORDER BY j.job_posted_date DESC LIMIT 3";
$stmt = $pdo->prepare($jobSql);
$stmt->execute($jobParams);
$jobRecommendations = array_map(function ($job) {
    $postedDate = $job['job_posted_date'] ?? null;
    $daysOpen = $postedDate ? max(0, (int) floor((time() - strtotime($postedDate)) / 86400)) : 0;
    $slots = (int) ($job['open_slots'] ?: ((int) $job['company_capacity_ais'] + (int) $job['company_capacity_accounting']));
    return [
        'job_id' => (int) $job['job_id'],
        'job_title' => $job['job_title'],
        'company_name' => $job['company_name'],
        'category' => $job['company_type'] ?: 'Internship',
        'applicants' => (int) $job['applicants'],
        'vacancies' => $slots,
        'score' => $job['score'] !== null ? (float) $job['score'] : 0,
        'duration' => $daysOpen === 0 ? 'Posted today' : $daysOpen . ' day' . ($daysOpen === 1 ? '' : 's') . ' open',
        'logo' => strtoupper(substr($job['company_name'], 0, 1)),
    ];
}, $stmt->fetchAll());

$dashboardData = [
    'kpis' => [
        'applicationsSent' => $applicationsSent,
        'offersReceived' => $offersReceived,
    ],
    'statusTracker' => [
        ['name' => 'Applications', 'Accepted' => $statusCounts['accepted'], 'Review' => $statusCounts['review'], 'Rejected' => $statusCounts['rejected']],
    ],
    'trend' => array_map(
        fn($date, $count) => ['date' => date('d M', strtotime($date)), 'count' => $count],
        array_keys($trend),
        array_values($trend)
    ),
    'distribution' => [
        ['name' => 'Accepted', 'value' => $statusCounts['accepted']],
        ['name' => 'Review', 'value' => $statusCounts['review']],
        ['name' => 'Rejected', 'value' => $statusCounts['rejected']],
    ],
    'progress' => $progressRows,
    'applicationStatus' => [
        'labels' => array_keys($applicationStatusData),
        'values' => array_values($applicationStatusData),
    ],
    'jobs' => $jobRecommendations,
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .chart-box { height: 280px; position: relative; }
    .sd-progress { display: grid; gap: 16px; }
    .sd-prow { display: grid; grid-template-columns: 150px 1fr 54px; align-items: center; gap: 14px; }
    .sd-prow strong { font-size: 14px; color: var(--ih-ink); }
    .sd-prow .progress { height: 10px; border-radius: 999px; background: var(--surface-inset); }
    .sd-pval { font-family: 'Spectral', Georgia, serif; font-weight: 700; font-size: 19px; text-align: right; color: var(--ih-maroon); font-variant-numeric: tabular-nums; }
    .sd-jobs { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
    .sd-job { border: 1px solid var(--ih-line); border-radius: 14px; padding: 18px; text-align: center; background: var(--ih-card); transition: transform .2s var(--ih-ease), box-shadow .2s var(--ih-ease); }
    .sd-job:hover { transform: translateY(-3px); box-shadow: var(--ih-shadow-2); }
    .sd-job-logo { width: 60px; height: 60px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--ih-maroon), var(--ih-gold)); color: #fff; font-size: 24px; font-weight: 800; font-family: 'Spectral', Georgia, serif; margin-bottom: 12px; }
    .sd-job h6 { font-weight: 700; margin-bottom: 2px; }
    .sd-jmeta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 14px 0; }
    .sd-jmeta span { font-size: 10px; color: var(--ih-faint); font-family: 'IBM Plex Mono', monospace; text-transform: uppercase; letter-spacing: .06em; }
    .sd-jmeta strong { display: block; font-family: 'Spectral', Georgia, serif; font-size: 18px; color: var(--ih-ink); }
    .sd-info p { margin-bottom: .55rem; } .sd-info strong { color: var(--ih-ink); }
</style>

<div class="ih-pagehead">
    <div>
        <div class="ih-kicker">Student &middot; Overview</div>
        <h1 class="ih-title">Welcome, <?php echo htmlspecialchars(ucwords(strtolower($_SESSION['user_name']))); ?>.</h1>
    </div>
    <div class="ih-pagehead-right">
        <span class="badge bg-<?php echo $internshipInfo ? 'success' : 'secondary'; ?>"><?php echo $internshipInfo ? htmlspecialchars($internshipInfo['internship_status']) . ' Placement' : 'No Placement Yet'; ?></span>
    </div>
</div>

<div class="ih-stats">
    <article class="ih-stat">
        <div class="ih-stat-top"><span class="ih-stat-label">Applications Sent</span><span class="ih-stat-ic"><i class="fas fa-paper-plane"></i></span></div>
        <div class="ih-stat-num"><?php echo (int) $dashboardData['kpis']['applicationsSent']; ?></div>
        <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Total submitted</span></div>
    </article>
    <article class="ih-stat ih-stat--green">
        <div class="ih-stat-top"><span class="ih-stat-label">Offers Received</span><span class="ih-stat-ic"><i class="fas fa-circle-check"></i></span></div>
        <div class="ih-stat-num"><?php echo (int) $dashboardData['kpis']['offersReceived']; ?></div>
        <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Accepted applications</span></div>
    </article>
    <article class="ih-stat ih-stat--gold">
        <div class="ih-stat-top"><span class="ih-stat-label">Internship Status</span><span class="ih-stat-ic"><i class="fas fa-calendar-check"></i></span></div>
        <div class="ih-stat-num" style="font-size:1.6rem;"><?php echo $internshipInfo ? htmlspecialchars($internshipInfo['internship_status']) : '&mdash;'; ?></div>
        <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Current placement</span></div>
    </article>
</div>

<div class="ih-grid-2">
    <section class="ih-panel">
        <div class="ih-panel-head">
            <div class="ih-panel-eyebrow">Milestones</div>
            <h3 class="ih-panel-title">Your Progress</h3>
        </div>
        <div class="ih-panel-body">
            <div class="sd-progress">
                <?php foreach ($dashboardData['progress'] as $item): ?>
                    <div class="sd-prow">
                        <strong><?php echo htmlspecialchars($item['task']); ?></strong>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo (int) $item['percentage']; ?>%;" aria-valuenow="<?php echo (int) $item['percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="sd-pval"><?php echo (int) $item['percentage']; ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="ih-panel">
        <div class="ih-panel-head">
            <div class="ih-panel-eyebrow">Pipeline</div>
            <h3 class="ih-panel-title">Application Status</h3>
        </div>
        <div class="ih-panel-body">
            <div class="chart-box"><canvas id="applicationStatusChart"></canvas></div>
        </div>
    </section>
</div>

<?php if (!$internshipInfo): ?>
<section class="ih-panel">
    <div class="ih-panel-head">
        <div class="ih-panel-eyebrow">For You</div>
        <h3 class="ih-panel-title">Recommended Vacancies</h3>
    </div>
    <div class="ih-panel-body">
        <?php if (empty($dashboardData['jobs'])): ?>
            <div class="alert alert-info mb-0"><i class="fas fa-circle-info me-2"></i>No new job recommendations available right now.</div>
        <?php else: ?>
        <div class="sd-jobs">
            <?php foreach ($dashboardData['jobs'] as $job): ?>
                <article class="sd-job">
                    <div class="sd-job-logo"><?php echo htmlspecialchars($job['logo']); ?></div>
                    <h6><?php echo htmlspecialchars($job['job_title']); ?></h6>
                    <div class="text-muted small"><?php echo htmlspecialchars($job['company_name']); ?></div>
                    <div class="kicker mt-1"><?php echo htmlspecialchars($job['category']); ?></div>
                    <div class="sd-jmeta">
                        <span><strong><?php echo (int) $job['applicants']; ?></strong>Applicants</span>
                        <span><strong><?php echo htmlspecialchars(number_format((float) $job['score'], 1)); ?></strong>Score</span>
                        <span><strong><?php echo (int) $job['vacancies']; ?></strong>Slots</span>
                    </div>
                    <div class="text-muted small mb-3"><?php echo htmlspecialchars($job['duration']); ?></div>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Skip</button>
                        <a class="btn btn-sm btn-primary" href="apply_job.php?job_id=<?php echo (int) $job['job_id']; ?>">Apply</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($internshipInfo): ?>
<section class="ih-panel">
    <div class="ih-panel-head">
        <div class="ih-panel-eyebrow">Placement</div>
        <h3 class="ih-panel-title">Internship Information</h3>
    </div>
    <div class="ih-panel-body sd-info">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Company Name:</strong> <?php echo htmlspecialchars($internshipInfo['company_name']); ?></p>
                <p><strong>Company Address:</strong> <?php echo htmlspecialchars($internshipInfo['company_address'] ?: '-'); ?></p>
                <p><strong>Industry Type:</strong> <?php echo htmlspecialchars($internshipInfo['company_type'] ?: '-'); ?></p>
                <p><strong>Internship State:</strong> <?php echo htmlspecialchars($internshipInfo['company_state'] ?: '-'); ?></p>
                <p><strong>Supervisor Name:</strong> <?php echo htmlspecialchars($internshipInfo['lecturer_name'] ?: '-'); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Internship Period:</strong> <?php echo !empty($internshipInfo['internship_start_date']) && !empty($internshipInfo['internship_end_date']) ? date('d/m/y', strtotime($internshipInfo['internship_start_date'])) . ' to ' . date('d/m/y', strtotime($internshipInfo['internship_end_date'])) : '-'; ?></p>
                <p><strong>Internship Status:</strong> <span class="badge bg-success"><?php echo htmlspecialchars($internshipInfo['internship_status']); ?></span></p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
const dashboardData = <?php echo json_encode($dashboardData); ?>;

if (window.Chart) {
    Chart.defaults.font.family = "'Hanken Grotesk', system-ui, sans-serif";
    Chart.defaults.color = '#5c4a50';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 14;
}
const ihPalette = ['#b6841c', '#2f7d5b', '#b3261e', '#8a1a38', '#34589c'];

function renderDoughnutChart(canvasId, labels, values) {
    const total = values.reduce((sum, value) => sum + Number(value), 0) || 1;
    new Chart(document.getElementById(canvasId), {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: ihPalette, borderColor: '#fff', borderWidth: 2 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                tooltip: { enabled: true },
                legend: { position: 'bottom' }
            }
        },
        plugins: [{
            id: 'percentageLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                ctx.save();
                ctx.font = '12px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                meta.data.forEach((arc, index) => {
                    const value = Number(values[index]);
                    if (!value) return;
                    const position = arc.tooltipPosition();
                    const percent = Math.round((value / total) * 100);
                    ctx.fillText(`${labels[index]} ${percent}%`, position.x, position.y);
                });
                ctx.restore();
            }
        }]
    });
}

if (document.getElementById('applicationStatusChart')) {
    renderDoughnutChart(
        'applicationStatusChart',
        dashboardData.applicationStatus.labels,
        dashboardData.applicationStatus.values
    );
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
