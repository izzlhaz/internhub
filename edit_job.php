<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';
require_once __DIR__ . '/../includes/evaluation_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

ensure_management_schema($pdo);
ensure_company_evaluation_round_schema($pdo);

$company_id = $_SESSION['company_id'];

$stmt = $pdo->prepare("SELECT * FROM company WHERE company_id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM jobposting WHERE company_id = ? AND job_status = 'Active'");
$stmt->execute([$company_id]);
$active_posts = (int) $stmt->fetch()['total'];

$stmt = $pdo->prepare("
    SELECT a.application_status, COUNT(*) AS total
    FROM application a
    JOIN jobposting j ON a.job_id = j.job_id
    WHERE j.company_id = ?
    GROUP BY a.application_status
");
$stmt->execute([$company_id]);

$status_distribution = [
    'pending' => 0,
    'shortlisted' => 0,
    'interviewed' => 0,
    'accepted' => 0,
    'rejected' => 0,
];

foreach ($stmt->fetchAll() as $row) {
    $status = normalise_application_status($row['application_status']);
    if ($status === 'Pending') {
        $status_distribution['pending'] += (int) $row['total'];
    } elseif ($status === 'Shortlisted') {
        $status_distribution['shortlisted'] += (int) $row['total'];
    } elseif ($status === 'Interview') {
        $status_distribution['interviewed'] += (int) $row['total'];
    } elseif ($status === 'Accepted') {
        $status_distribution['accepted'] += (int) $row['total'];
    } elseif ($status === 'Rejected') {
        $status_distribution['rejected'] += (int) $row['total'];
    }
}

$total_applications = array_sum($status_distribution);
$shortlisted_count = $status_distribution['shortlisted'];

$trend = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $trend[$date] = 0;
}

$stmt = $pdo->prepare("
    SELECT DATE(a.application_applied_date) AS applied_date, COUNT(*) AS total
    FROM application a
    JOIN jobposting j ON a.job_id = j.job_id
    WHERE j.company_id = ? AND a.application_applied_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(a.application_applied_date)
");
$stmt->execute([$company_id]);
foreach ($stmt->fetchAll() as $row) {
    if (isset($trend[$row['applied_date']])) {
        $trend[$row['applied_date']] = (int) $row['total'];
    }
}

$applications = [
    'total' => $total_applications,
    'shortlisted' => $shortlisted_count,
    'trend' => array_map(
        fn($date, $count) => ['date' => $date, 'count' => $count],
        array_keys($trend),
        array_values($trend)
    ),
    'statusDistribution' => $status_distribution,
];

$intern_group = $_GET['intern_group'] ?? 'status';
$trend_months = (int) ($_GET['trend_months'] ?? 8);
$evaluation_period = $_GET['evaluation_period'] ?? 'weekly';

$allowed_intern_groups = ['status', 'gender', 'department'];
$allowed_trend_months = [6, 8, 12];
$allowed_evaluation_periods = ['weekly', 'monthly', 'all'];

if (!in_array($intern_group, $allowed_intern_groups, true)) {
    $intern_group = 'status';
}
if (!in_array($trend_months, $allowed_trend_months, true)) {
    $trend_months = 8;
}
if (!in_array($evaluation_period, $allowed_evaluation_periods, true)) {
    $evaluation_period = 'weekly';
}

if ($intern_group === 'gender') {
    $intern_sql = "
        SELECT COALESCE(NULLIF(s.student_gender, ''), 'Not Set') AS label, COUNT(*) AS total
        FROM internship i
        JOIN student s ON s.student_id = i.student_id
        WHERE i.company_id = ?
        GROUP BY label
        ORDER BY total DESC, label
    ";
} elseif ($intern_group === 'department') {
    $intern_sql = "
        SELECT
            CASE
                WHEN s.student_course LIKE '%Information Systems%' THEN 'B.Acct (IS)'
                ELSE 'B.Acct'
            END AS label,
            COUNT(*) AS total
        FROM internship i
        JOIN student s ON s.student_id = i.student_id
        WHERE i.company_id = ?
        GROUP BY label
        ORDER BY total DESC, label
    ";
} else {
    $intern_sql = "
        SELECT COALESCE(NULLIF(i.internship_status, ''), 'Not Set') AS label, COUNT(*) AS total
        FROM internship i
        WHERE i.company_id = ?
        GROUP BY label
        ORDER BY total DESC, label
    ";
}
$stmt = $pdo->prepare($intern_sql);
$stmt->execute([$company_id]);
$intern_chart_rows = $stmt->fetchAll();
$intern_total = array_sum(array_map(fn($row) => (int) $row['total'], $intern_chart_rows));

$month_labels = [];
$month_lookup = [];
for ($i = $trend_months - 1; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("first day of -{$i} months"));
    $month_labels[] = date('M Y', strtotime($month_key . '-01'));
    $month_lookup[$month_key] = ['total' => 0, 'shortlisted' => 0];
}

$stmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(a.application_applied_date, '%Y-%m') AS month_key,
        COUNT(*) AS total,
        SUM(CASE WHEN a.application_status IN ('Shortlisted', 'Interview') THEN 1 ELSE 0 END) AS shortlisted
    FROM application a
    JOIN jobposting j ON j.job_id = a.job_id
    WHERE j.company_id = ?
      AND a.application_applied_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m-01')
    GROUP BY month_key
    ORDER BY month_key
");
$stmt->execute([$company_id, $trend_months - 1]);
foreach ($stmt->fetchAll() as $row) {
    if (isset($month_lookup[$row['month_key']])) {
        $month_lookup[$row['month_key']] = [
            'total' => (int) $row['total'],
            'shortlisted' => (int) $row['shortlisted'],
        ];
    }
}
$application_trend_chart = [
    'labels' => $month_labels,
    'totalApplications' => array_column($month_lookup, 'total'),
    'shortlistedApplications' => array_column($month_lookup, 'shortlisted'),
];

$stmt = $pdo->prepare("
    SELECT
        s.student_name,
        COALESCE(ce.ce_total_score, 0) AS score
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    LEFT JOIN " . company_evaluation_summary_sql('ce') . " ON ce.internship_id = i.internship_id
    WHERE i.company_id = ?
    ORDER BY s.student_name
    LIMIT 8
");
$stmt->execute([$company_id]);
$evaluation_rows = array_map(function ($row) {
    $percentage = min(100, max(0, round(((float) $row['score'] / 168) * 100)));
    return [
        'name' => $row['student_name'],
        'percentage' => $percentage,
    ];
}, $stmt->fetchAll());

$posts = ['active' => $active_posts];

$recentActivities = [
    ['id' => 1, 'message' => 'Company profile awaiting coordinator approval.', 'timestamp' => date('Y-m-d H:i'), 'type' => 'approval'],
    ['id' => 2, 'message' => $active_posts . ' active job post(s) currently visible to students.', 'timestamp' => date('Y-m-d H:i'), 'type' => 'post'],
    ['id' => 3, 'message' => $total_applications . ' application(s) received across all posts.', 'timestamp' => date('Y-m-d H:i'), 'type' => 'application'],
];

$deadlines = [
    ['id' => 1, 'title' => 'Review pending applications', 'daysLeft' => 3, 'type' => 'application'],
    ['id' => 2, 'title' => 'Update open student slots', 'daysLeft' => 7, 'type' => 'profile'],
    ['id' => 3, 'title' => 'Confirm shortlisted interview schedule', 'daysLeft' => 10, 'type' => 'interview'],
];

$dashboardData = [
    'posts' => $posts,
    'applications' => $applications,
    'internChart' => [
        'labels' => array_column($intern_chart_rows, 'label'),
        'values' => array_map('intval', array_column($intern_chart_rows, 'total')),
        'total' => $intern_total,
    ],
    'applicationTrendChart' => $application_trend_chart,
    'evaluationInternChart' => $evaluation_rows,
    'recentActivities' => $recentActivities,
    'deadlines' => $deadlines,
];

$approval_status = $company['company_approval_status'] ?? 'Pending';
$is_approved = $approval_status === 'Approved';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-chart { border-radius: 16px; }
        .chart-card-header { display: flex; align-items: center; justify-content: space-between; gap: .6rem; flex-wrap: wrap; }
        .chart-card-header h5 { margin: 0; }
        .chart-card-header select { width: auto; min-width: 110px; max-width: 100%; flex: 0 1 auto; }
        .chart-area { height: 260px; position: relative; }
        .doughnut-wrap { height: 230px; position: relative; }
        .doughnut-center {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            pointer-events: none;
        }
        .doughnut-center strong { font-size: 2rem; line-height: 1; }
        .breakdown-list { display: grid; gap: .65rem; }
        .breakdown-item { display: flex; justify-content: space-between; gap: 1rem; font-size: .95rem; }
        .evaluation-list { display: grid; gap: 1rem; }
        .evaluation-row { display: grid; grid-template-columns: minmax(110px, 1fr) 2fr auto; gap: .75rem; align-items: center; }
        .evaluation-row .progress { height: 10px; border-radius: 999px; }
        canvas { max-width: 100%; }
        @media (max-width: 768px) {
            .chart-card-header { align-items: flex-start; flex-direction: column; }
            .chart-card-header select { width: 100%; }
            .evaluation-row { grid-template-columns: 1fr; gap: .35rem; }
        }
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
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="profile.php"><i class="fas fa-building"></i> Company Profile</a>
            <a href="jobs.php"><i class="fas fa-briefcase"></i> Manage Jobs</a>
            <a href="applications.php"><i class="fas fa-users"></i> Applications</a>
            <a href="interns.php"><i class="fas fa-user-graduate"></i> My Interns</a>
            <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="col-md-10 content">
            <div class="ih-pagehead">
                <div>
                    <div class="ih-kicker">Company &middot; Overview</div>
                    <h1 class="ih-title"><?php echo htmlspecialchars($company['company_name']); ?></h1>
                </div>
                <div class="ih-pagehead-right">
                    <span class="badge bg-<?php echo $approval_status === 'Approved' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($approval_status); ?></span>
                    <a class="ih-userpill" href="profile.php" title="Company profile">
                        <span class="ih-avatar"><?php echo htmlspecialchars(strtoupper(substr($company['company_name'] ?? 'C', 0, 1))); ?></span>
                        <span class="nm"><strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Company'); ?></strong><small>Company</small></span>
                    </a>
                </div>
            </div>

            <?php if (!$is_approved): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-hourglass-half fa-4x text-warning mb-4"></i>
                        <h3>Registration Pending Approval</h3>
                        <p class="text-muted mb-4">
                            Your company registration has been submitted and is waiting for coordinator approval.
                            You cannot post jobs, view applications, or access company module features until approval is complete.
                        </p>
                        <div class="alert alert-warning d-inline-block mb-4">
                            Current status: <strong><?php echo htmlspecialchars($approval_status); ?></strong>
                        </div>
                        <div>
                            <a href="../logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>

            <div class="ih-stats">
                <article class="ih-stat">
                    <div class="ih-stat-top"><span class="ih-stat-label">Active Posts</span><span class="ih-stat-ic"><i class="fas fa-briefcase"></i></span></div>
                    <div class="ih-stat-num"><?php echo (int) $posts['active']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Live vacancies</span></div>
                </article>
                <article class="ih-stat ih-stat--blue">
                    <div class="ih-stat-top"><span class="ih-stat-label">Total Applications</span><span class="ih-stat-ic"><i class="fas fa-file-lines"></i></span></div>
                    <div class="ih-stat-num"><?php echo (int) $applications['total']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Received to date</span></div>
                </article>
                <article class="ih-stat ih-stat--green">
                    <div class="ih-stat-top"><span class="ih-stat-label">Shortlisted</span><span class="ih-stat-ic"><i class="fas fa-user-check"></i></span></div>
                    <div class="ih-stat-num"><?php echo (int) $applications['shortlisted']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Candidates advanced</span></div>
                </article>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-xl-4 col-lg-6">
                    <div class="card dashboard-chart h-100">
                        <div class="card-body">
                            <div class="chart-card-header mb-3">
                                <h5 class="mb-0">Intern</h5>
                                <select class="form-select form-select-sm chart-filter" data-param="intern_group">
                                    <option value="status" <?php echo $intern_group === 'status' ? 'selected' : ''; ?>>Internship Status</option>
                                    <option value="gender" <?php echo $intern_group === 'gender' ? 'selected' : ''; ?>>Gender</option>
                                    <option value="department" <?php echo $intern_group === 'department' ? 'selected' : ''; ?>>Department</option>
                                </select>
                            </div>
                            <div class="doughnut-wrap mb-3">
                                <canvas id="internDoughnut"></canvas>
                                <div class="doughnut-center">
                                    <strong><?php echo (int) $dashboardData['internChart']['total']; ?></strong>
                                    <span class="text-muted small">Interns</span>
                                </div>
                            </div>
                            <div class="breakdown-list">
                                <?php if (empty($intern_chart_rows)): ?>
                                    <div class="text-muted">No intern records yet.</div>
                                <?php endif; ?>
                                <?php foreach ($intern_chart_rows as $row): ?>
                                    <div class="breakdown-item">
                                        <span><?php echo htmlspecialchars($row['label']); ?></span>
                                        <strong><?php echo (int) $row['total']; ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-lg-6">
                    <div class="card dashboard-chart h-100">
                        <div class="card-body">
                            <div class="chart-card-header mb-3">
                                <h5 class="mb-0">Application Trend</h5>
                                <select class="form-select form-select-sm chart-filter" data-param="trend_months">
                                    <option value="6" <?php echo $trend_months === 6 ? 'selected' : ''; ?>>Last 6 Months</option>
                                    <option value="8" <?php echo $trend_months === 8 ? 'selected' : ''; ?>>Last 8 Months</option>
                                    <option value="12" <?php echo $trend_months === 12 ? 'selected' : ''; ?>>Last 12 Months</option>
                                </select>
                            </div>
                            <div class="chart-area">
                                <canvas id="applicationTrend"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-12">
                    <div class="card dashboard-chart h-100">
                        <div class="card-body">
                            <div class="chart-card-header mb-3">
                                <h5 class="mb-0">Evaluation Intern</h5>
                                <select class="form-select form-select-sm chart-filter" data-param="evaluation_period">
                                    <option value="weekly" <?php echo $evaluation_period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $evaluation_period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="all" <?php echo $evaluation_period === 'all' ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>
                            <div class="evaluation-list">
                                <?php if (empty($evaluation_rows)): ?>
                                    <div class="text-muted">No evaluation records yet.</div>
                                <?php endif; ?>
                                <?php foreach ($evaluation_rows as $row): ?>
                                    <div class="evaluation-row">
                                        <span><?php echo htmlspecialchars($row['name']); ?></span>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo (int) $row['percentage']; ?>%;" aria-valuenow="<?php echo (int) $row['percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <strong><?php echo (int) $row['percentage']; ?>%</strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($is_approved): ?>
<script>
const dashboardData = <?php echo json_encode($dashboardData); ?>;

if (window.Chart) {
    Chart.defaults.font.family = "'Hanken Grotesk', system-ui, sans-serif";
    Chart.defaults.color = '#5c4a50';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 14;
}
const ihPalette = ['#8a1a38', '#cda23f', '#34589c', '#2f7d5b', '#b6841c', '#a83052', '#6e1228'];

new Chart(document.getElementById('internDoughnut'), {
    type: 'doughnut',
    data: {
        labels: dashboardData.internChart.labels,
        datasets: [{ data: dashboardData.internChart.values, backgroundColor: ihPalette, borderColor: '#fff', borderWidth: 2 }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
        },
        cutout: '70%'
    }
});

new Chart(document.getElementById('applicationTrend'), {
    type: 'line',
    data: {
        labels: dashboardData.applicationTrendChart.labels,
        datasets: [
            {
                label: 'Total Applications',
                data: dashboardData.applicationTrendChart.totalApplications,
                borderColor: '#8a1a38',
                backgroundColor: 'rgba(138,26,56,.12)',
                pointBackgroundColor: '#cda23f',
                fill: true,
                tension: 0.35
            },
            {
                label: 'Shortlisted Applications',
                data: dashboardData.applicationTrendChart.shortlistedApplications,
                borderColor: '#cda23f',
                backgroundColor: 'rgba(205,162,63,.12)',
                pointBackgroundColor: '#8a1a38',
                tension: 0.35
            }
        ]
    },
    options: {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', align: 'start' },
            tooltip: { enabled: true }
        },
        scales: {
            x: { title: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

document.querySelectorAll('.chart-filter').forEach((select) => {
    select.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set(select.dataset.param, select.value);
        window.location.href = url.toString();
    });
});
</script>
<?php endif; ?>
</body>
</html>
