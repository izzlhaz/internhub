<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';
require_once __DIR__ . '/../includes/evaluation_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}

ensure_management_schema($pdo);
ensure_company_evaluation_round_schema($pdo);
$companySummary = company_evaluation_summary_sql('ce');

$sessionOptions = student_batch_options($pdo);
$timeFrame = strtoupper(trim($_GET['time_frame'] ?? ($sessionOptions[0] ?? 'all')));
$stateFilter = $_GET['state'] ?? 'all';
$userFilter = $_GET['user_filter'] ?? 'all';
$studentFilter = $_GET['student_filter'] ?? 'all';
$companyEvaluationRound = $_GET['company_evaluation_round'] ?? 'all';

$allowedTimeFrames = array_merge(['all'], array_map('strtoupper', $sessionOptions));
$allowedUsers = ['all', 'student', 'company', 'lecturer'];
$allowedStudents = ['all', 'intern', 'pending', 'not_intern'];
$allowedCompanyEvaluationRounds = ['all', '1', '2'];
$malaysiaStates = [
    'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis',
    'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur', 'Labuan', 'Putrajaya'
];

if (!in_array($timeFrame, $allowedTimeFrames, true)) $timeFrame = $sessionOptions ? strtoupper($sessionOptions[0]) : 'all';
if ($stateFilter !== 'all' && !in_array($stateFilter, $malaysiaStates, true)) $stateFilter = 'all';
if (!in_array($userFilter, $allowedUsers, true)) $userFilter = 'all';
if (!in_array($studentFilter, $allowedStudents, true)) $studentFilter = 'all';
if (!in_array($companyEvaluationRound, $allowedCompanyEvaluationRounds, true)) $companyEvaluationRound = 'all';

$sessionClause = $timeFrame !== 'all' ? " AND s.student_intake = " . $pdo->quote($timeFrame) : '';

$stateClause = $stateFilter !== 'all' ? " AND c.company_state = " . $pdo->quote($stateFilter) : '';
$internshipCompanyJoin = $stateFilter !== 'all' ? " JOIN company cfilter ON cfilter.company_id = i.company_id" : '';
$internshipStateClause = $stateFilter !== 'all' ? " AND cfilter.company_state = " . $pdo->quote($stateFilter) : '';
$internshipExists = "EXISTS (SELECT 1 FROM internship ii WHERE ii.student_id = s.student_id AND ii.internship_status IN ('Accepted','Active','Completed'))";
$applicationExists = "EXISTS (SELECT 1 FROM application a WHERE a.student_id = s.student_id AND a.application_status IN ('Pending','Shortlisted','Interview','Accepted','Review','Accept'))";
$studentWhere = '';
if ($studentFilter === 'intern') {
    $studentWhere = " WHERE $internshipExists";
} elseif ($studentFilter === 'pending') {
    $studentWhere = " WHERE NOT $internshipExists AND $applicationExists";
} elseif ($studentFilter === 'not_intern') {
    $studentWhere = " WHERE NOT $internshipExists AND NOT $applicationExists";
}

function selected_option($current, $value)
{
    return $current === $value ? 'selected' : '';
}

$studentBaseWhere = $timeFrame !== 'all' ? " WHERE s.student_intake = " . $pdo->quote($timeFrame) : '';
if ($studentWhere !== '') {
    $studentWhere .= $sessionClause;
} else {
    $studentWhere = $studentBaseWhere;
}
$totalStudents = (int) $pdo->query("SELECT COUNT(*) AS total FROM student s" . $studentBaseWhere)->fetch()['total'];
$stats = [
    'students' => (int) $pdo->query("SELECT COUNT(*) AS total FROM student s" . $studentWhere)->fetch()['total'],
    'student_intern' => (int) $pdo->query("SELECT COUNT(DISTINCT i.student_id) AS total FROM internship i JOIN student s ON s.student_id = i.student_id $internshipCompanyJoin WHERE i.internship_status IN ('Accepted','Active','Completed')" . $sessionClause . $internshipStateClause)->fetch()['total'],
    'companies' => (int) $pdo->query("SELECT COUNT(*) AS total FROM company")->fetch()['total'],
    'lecturers' => (int) $pdo->query("SELECT COUNT(*) AS total FROM lecturer")->fetch()['total'],
    'average_score' => $pdo->query("SELECT ROUND(AVG(r.report_total_score), 1) AS total FROM report r JOIN internship i ON i.internship_id = r.internship_id JOIN student s ON s.student_id = i.student_id JOIN {$companySummary} ON ce.internship_id = i.internship_id AND ce.ce_count = 2 JOIN lecturerevaluation le ON le.internship_id = i.internship_id $internshipCompanyJoin WHERE r.report_total_score IS NOT NULL" . $sessionClause . $internshipStateClause)->fetch()['total'] ?? 0,
];

$companySkillGroups = [
    'Cognitive Skills' => [
        'ce_understand_organization_governance',
        'ce_knowledge_business_principles_practices',
        'ce_apply_knowledge_practices',
        'ce_problem_identification_supporting_evidence',
        'ce_proposed_solutions',
    ],
    'Practical Skill' => ['ce_application_it'],
    'Interpersonal Skills' => [
        'ce_attitude_towards_team_members',
        'ce_contribution_to_team',
        'ce_leadership_skills',
    ],
    'Communication Skills' => [
        'ce_attentiveness',
        'ce_answering_questions',
        'ce_questioning',
    ],
    'Personal Skills' => [
        'ce_seeking_information',
        'ce_being_resourceful',
        'ce_logbook',
        'ce_respect_for_others',
        'ce_punctuality',
        'ce_meeting_deadlines',
        'ce_personal_apperance',
    ],
    'Ethics & Professionalism' => [
        'ce_knowledge_of_ethics',
        'ce_ethical_behaviour',
    ],
];

$companySkillSelect = [];
foreach ($companySkillGroups as $label => $fields) {
    $companySkillSelect[] = 'ROUND(AVG((' . implode(' + ', array_map(fn($field) => 'ce.' . $field, $fields)) . ') / ' . count($fields) . '), 2) AS ' . $pdo->quote($label);
}
$companyRoundClause = $companyEvaluationRound === 'all' ? '' : ' AND ce.ce_round = ' . (int) $companyEvaluationRound;
$companySkillRows = $pdo->query("
    SELECT CASE WHEN s.student_course LIKE '%Information Systems%' THEN 'B.Acct (IS)' ELSE 'B.Acct' END AS programme,
           " . implode(",\n           ", $companySkillSelect) . "
    FROM companyevaluation ce
    JOIN internship i ON i.internship_id = ce.internship_id
    JOIN student s ON s.student_id = i.student_id
    LEFT JOIN company c ON c.company_id = i.company_id
    WHERE 1=1 $sessionClause $stateClause $companyRoundClause
    GROUP BY programme
")->fetchAll();
$companySkillData = ['B.Acct (IS)' => [], 'B.Acct' => []];
foreach ($companySkillRows as $row) {
    foreach (array_keys($companySkillGroups) as $label) {
        $companySkillData[$row['programme']][$label] = $row[$label] !== null ? (float) $row[$label] : null;
    }
}

$lecturerSkillGroups = [
    'Log Book' => ['log_organization', 'log_complete', 'log_support', 'log_reflection'],
    'Report Writing' => ['report_introduction', 'report_methodology', 'report_analysis', 'report_conclusion', 'report_organization', 'report_mechanism', 'report_aesthetics', 'report_timeliness', 'report_overall'],
    'System' => ['system_analyze', 'system_security', 'system_interface', 'system_reports', 'system_queries', 'system_practicality', 'system_ease_use', 'system_enhanced', 'system_creativity'],
    'Presentation' => ['present_organization', 'present_subject', 'present_visual', 'present_non_verbal', 'present_enthusiasm', 'present_elocution'],
];
$lecturerRows = $pdo->query("
    SELECT CASE WHEN s.student_course LIKE '%Information Systems%' THEN 'B.Acct (IS)' ELSE 'B.Acct' END AS programme,
           le.le_score_data
    FROM lecturerevaluation le
    JOIN internship i ON i.internship_id = le.internship_id
    JOIN student s ON s.student_id = i.student_id
    LEFT JOIN company c ON c.company_id = i.company_id
    WHERE le.le_score_data IS NOT NULL AND le.le_score_data <> '' $sessionClause $stateClause
")->fetchAll();
$lecturerSkillTotals = ['B.Acct (IS)' => [], 'B.Acct' => []];
foreach ($lecturerRows as $row) {
    $scores = json_decode((string) $row['le_score_data'], true);
    if (!is_array($scores)) continue;
    foreach ($lecturerSkillGroups as $label => $fields) {
        $values = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $scores)) $values[] = (float) $scores[$field];
        }
        if ($values) $lecturerSkillTotals[$row['programme']][$label][] = array_sum($values) / count($values);
    }
}
$lecturerSkillData = ['B.Acct (IS)' => [], 'B.Acct' => []];
foreach ($lecturerSkillTotals as $programme => $groups) {
    foreach (array_keys($lecturerSkillGroups) as $label) {
        $values = $groups[$label] ?? [];
        $lecturerSkillData[$programme][$label] = $values ? round(array_sum($values) / count($values), 2) : null;
    }
}

$programmeStats = $pdo->query("
    SELECT
        SUM(CASE WHEN student_course LIKE '%Information Systems%' THEN 1 ELSE 0 END) AS ais_total,
        SUM(CASE WHEN student_course NOT LIKE '%Information Systems%' THEN 1 ELSE 0 END) AS accounting_total
    FROM student s
    $studentWhere
")->fetch();

$studentStatus = $pdo->query("
    SELECT
        SUM(CASE WHEN i.internship_id IS NOT NULL THEN 1 ELSE 0 END) AS intern_total,
        SUM(CASE WHEN i.internship_id IS NULL AND EXISTS (
            SELECT 1 FROM application a
            WHERE a.student_id = s.student_id
              AND a.application_status IN ('Pending','Shortlisted','Interview','Accepted','Review','Accept')
        ) THEN 1 ELSE 0 END) AS pending_total,
        SUM(CASE WHEN i.internship_id IS NULL AND NOT EXISTS (
            SELECT 1 FROM application a
            WHERE a.student_id = s.student_id
              AND a.application_status IN ('Pending','Shortlisted','Interview','Accepted','Review','Accept')
        ) THEN 1 ELSE 0 END) AS not_intern_total
    FROM student s
    LEFT JOIN internship i ON i.student_id = s.student_id AND i.internship_status IN ('Accepted','Active','Completed')
    $studentWhere
")->fetch();

$stateStats = $pdo->query("
    SELECT COALESCE(NULLIF(c.company_state, ''), 'Not Set') AS label, COUNT(i.internship_id) AS total
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    WHERE 1=1 $sessionClause $stateClause
    GROUP BY label
    ORDER BY total DESC, label
")->fetchAll();

$allowanceStats = $pdo->query("
    SELECT COALESCE(NULLIF(c.company_allowance_range, ''), 'Not Set') AS label, COUNT(i.internship_id) AS total
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    WHERE 1=1 $sessionClause $stateClause
    GROUP BY label
    ORDER BY FIELD(label, 'RM0 - RM300', 'RM301 - RM600', 'RM601 - RM900', 'RM901 - RM1,200', 'Above RM1,200'), label
")->fetchAll();

$industryStats = $pdo->query("
    SELECT COALESCE(NULLIF(c.company_type, ''), 'Not Set') AS label, COUNT(i.internship_id) AS total
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    WHERE 1=1 $sessionClause $stateClause
    GROUP BY label
    ORDER BY total DESC, label
")->fetchAll();

$fieldStats = $pdo->query("
    SELECT COALESCE(NULLIF(i.internship_field, ''), NULLIF(j.job_title, ''), 'Not Set') AS label, COUNT(i.internship_id) AS total
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    LEFT JOIN jobposting j ON j.job_id = i.job_id
    LEFT JOIN company c ON c.company_id = i.company_id
    WHERE 1=1 $sessionClause
    $stateClause
    GROUP BY label
    ORDER BY total DESC, label
")->fetchAll();

function chart_labels($rows) { return array_column($rows, 'label'); }
function chart_totals($rows) { return array_map('intval', array_column($rows, 'total')); }
function chart_map($rows) {
    $map = [];
    foreach ($rows as $row) {
        $map[$row['label']] = (int) $row['total'];
    }
    return $map;
}

$stateCoordinates = [
    'Johor' => [1.4854, 103.7618],
    'Kedah' => [6.1184, 100.3685],
    'Kelantan' => [6.1254, 102.2381],
    'Melaka' => [2.1896, 102.2501],
    'Negeri Sembilan' => [2.7258, 101.9424],
    'Pahang' => [3.8126, 103.3256],
    'Perak' => [4.5921, 101.0901],
    'Perlis' => [6.4449, 100.2048],
    'Pulau Pinang' => [5.4164, 100.3327],
    'Sabah' => [5.9804, 116.0735],
    'Sarawak' => [1.5533, 110.3592],
    'Selangor' => [3.0738, 101.5183],
    'Terengganu' => [5.3117, 103.1324],
    'Kuala Lumpur' => [3.1390, 101.6869],
    'Labuan' => [5.2831, 115.2308],
    'Putrajaya' => [2.9264, 101.6964],
];
$stateMapData = [];
foreach ($stateStats as $row) {
    $state = $row['label'];
    if (!isset($stateCoordinates[$state])) {
        continue;
    }

    $stateMapData[] = [
        'name' => $state,
        'lat' => $stateCoordinates[$state][0],
        'lng' => $stateCoordinates[$state][1],
        'total' => (int) $row['total'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/internhub.css?v=20260615-v2">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="../assets/js/chart.umd.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .leaflet-container { font-family: inherit; }
        .leaflet-control-attribution { font-size: 9px; }
        .ih-map-tools { position: absolute; top: 10px; right: 10px; z-index: 500; display: flex; gap: 5px; }
        .ih-map-tools span { width: 26px; height: 26px; border-radius: 7px; background: rgba(255,255,255,.95); border: 1px solid var(--ih-line); display: inline-flex; align-items: center; justify-content: center; color: var(--ih-maroon); font-size: 12px; box-shadow: var(--ih-shadow-1); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>

        <main class="col-md-10 content ih-main">
            <header class="ih-topbar">
                <div>
                    <div class="ih-kicker">Coordinator &middot; Control Centre</div>
                    <h1 class="ih-title">Programme Dashboard</h1>
                </div>
                <div class="ih-topbar-right">
                    <div class="ih-datechip">
                        <strong><?php echo date('d M Y, l'); ?></strong>
                        <small id="liveClock"><?php echo date('h:i A'); ?> &middot; <?php echo htmlspecialchars($timeFrame === 'all' ? 'All Sessions' : $timeFrame); ?></small>
                    </div>
                    <button type="button" class="ih-iconbtn" title="Notifications"><i class="fas fa-bell"></i><span class="ih-dot"></span></button>
                    <a class="ih-userpill" href="profile.php" title="Open profile">
                        <span class="ih-avatar"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['user_name'] ?? 'C', 0, 1))); ?></span>
                        <span class="nm">
                            <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></strong>
                            <small>Coordinator</small>
                        </span>
                    </a>
                </div>
            </header>

            <div class="ih-wrap">

            <form class="ih-controls" method="GET">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label>Session</label>
                        <select class="form-select" name="time_frame">
                            <option value="all" <?php echo selected_option($timeFrame, 'all'); ?>>All Sessions</option>
                            <?php foreach ($sessionOptions as $session): ?>
                                <option value="<?php echo htmlspecialchars(strtoupper($session)); ?>" <?php echo selected_option($timeFrame, strtoupper($session)); ?>><?php echo htmlspecialchars(strtoupper($session)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label>State</label>
                        <select class="form-select" name="state">
                            <option value="all" <?php echo selected_option($stateFilter, 'all'); ?>>All States</option>
                            <?php foreach ($malaysiaStates as $state): ?>
                                <option value="<?php echo htmlspecialchars($state); ?>" <?php echo selected_option($stateFilter, $state); ?>><?php echo htmlspecialchars($state); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label>User</label>
                        <select class="form-select" name="user_filter">
                            <option value="all" <?php echo selected_option($userFilter, 'all'); ?>>All</option>
                            <option value="student" <?php echo selected_option($userFilter, 'student'); ?>>Student</option>
                            <option value="company" <?php echo selected_option($userFilter, 'company'); ?>>Company</option>
                            <option value="lecturer" <?php echo selected_option($userFilter, 'lecturer'); ?>>Lecturer</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label>Student</label>
                        <select class="form-select" name="student_filter">
                            <option value="all" <?php echo selected_option($studentFilter, 'all'); ?>>All</option>
                            <option value="intern" <?php echo selected_option($studentFilter, 'intern'); ?>>Student Intern</option>
                            <option value="pending" <?php echo selected_option($studentFilter, 'pending'); ?>>Pending</option>
                            <option value="not_intern" <?php echo selected_option($studentFilter, 'not_intern'); ?>>Not Intern</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label>Company Evaluation Round</label>
                        <select class="form-select" name="company_evaluation_round">
                            <option value="all" <?php echo selected_option($companyEvaluationRound, 'all'); ?>>All Submitted Rounds</option>
                            <option value="1" <?php echo selected_option($companyEvaluationRound, '1'); ?>>First 3 Month</option>
                            <option value="2" <?php echo selected_option($companyEvaluationRound, '2'); ?>>Final Month</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Apply</button>
                        <a class="btn btn-outline-secondary" href="dashboard.php"><i class="fas fa-rotate-left me-1"></i> Reset</a>
                        <?php if ($userFilter !== 'all'): ?>
                            <?php
                                $targetMap = [
                                    'student' => 'students.php',
                                    'company' => 'companies.php',
                                    'lecturer' => 'lecturers.php',
                                ];
                            ?>
                            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($targetMap[$userFilter]); ?>">Open <?php echo htmlspecialchars(ucfirst($userFilter)); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="ih-stats">
                <article class="ih-stat">
                    <div class="ih-stat-top"><span class="ih-stat-label">Students</span><span class="ih-stat-ic"><i class="fas fa-user-graduate"></i></span></div>
                    <div class="ih-stat-num"><?php echo $stats['students']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Total students</span></div>
                </article>
                <article class="ih-stat ih-stat--green">
                    <div class="ih-stat-top"><span class="ih-stat-label">Student Interns</span><span class="ih-stat-ic"><i class="fas fa-user-check"></i></span></div>
                    <div class="ih-stat-num"><?php echo $stats['student_intern']; ?><span class="u">/<?php echo $totalStudents; ?></span></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Placed students</span></div>
                </article>
                <article class="ih-stat ih-stat--blue">
                    <div class="ih-stat-top"><span class="ih-stat-label">Companies</span><span class="ih-stat-ic"><i class="fas fa-building"></i></span></div>
                    <div class="ih-stat-num"><?php echo $stats['companies']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Registered companies</span></div>
                </article>
                <article class="ih-stat">
                    <div class="ih-stat-top"><span class="ih-stat-label">Lecturers</span><span class="ih-stat-ic"><i class="fas fa-chalkboard-user"></i></span></div>
                    <div class="ih-stat-num"><?php echo $stats['lecturers']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Supervisors</span></div>
                </article>
                <article class="ih-stat ih-stat--gold">
                    <div class="ih-stat-top"><span class="ih-stat-label">Avg. Evaluation Score</span><span class="ih-stat-ic"><i class="fas fa-star"></i></span></div>
                    <div class="ih-stat-num"><?php echo htmlspecialchars($stats['average_score'] ?: '0'); ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Overall score</span></div>
                </article>
                <article class="ih-stat">
                    <div class="ih-stat-top"><span class="ih-stat-label">B.Acct Students</span><span class="ih-stat-ic"><i class="fas fa-graduation-cap"></i></span></div>
                    <div class="ih-stat-num"><?php echo (int) $programmeStats['accounting_total']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Accounting programme</span></div>
                </article>
                <article class="ih-stat ih-stat--blue">
                    <div class="ih-stat-top"><span class="ih-stat-label">B.Acct (IS) Students</span><span class="ih-stat-ic"><i class="fas fa-laptop-code"></i></span></div>
                    <div class="ih-stat-num"><?php echo (int) $programmeStats['ais_total']; ?></div>
                    <div class="ih-stat-foot"><span class="ih-rule"></span><span class="ih-stat-sub">Information systems</span></div>
                </article>
            </div>

            <div class="ih-grid-3">
                <section class="ih-panel">
                    <div class="ih-panel-head">
                        <div class="ih-panel-eyebrow">Geography</div>
                        <h3 class="ih-panel-title">Placements across Malaysia</h3>
                    </div>
                    <div class="ih-panel-body">
                        <div class="ih-map">
                            <div class="ih-map-tools" aria-label="Map controls">
                                <span title="Map view"><i class="fas fa-map"></i></span>
                                <span title="Chart view"><i class="fas fa-chart-bar"></i></span>
                            </div>
                            <div id="coordinatorStateMap" style="height: 100%; width: 100%;"></div>
                        </div>
                    </div>
                </section>
                <section class="ih-panel">
                    <div class="ih-panel-head">
                        <div class="ih-panel-eyebrow">Compensation</div>
                        <h3 class="ih-panel-title">Allowance Range</h3>
                    </div>
                    <div class="ih-panel-body"><div class="ih-chart"><canvas id="allowanceChart"></canvas></div></div>
                </section>
                <section class="ih-panel">
                    <div class="ih-panel-head">
                        <div class="ih-panel-eyebrow">Status</div>
                        <h3 class="ih-panel-title">Placement Status</h3>
                    </div>
                    <div class="ih-panel-body"><div class="ih-chart"><canvas id="placementChart"></canvas></div></div>
                </section>
            </div>

            <div class="ih-grid-2">
                <section class="ih-panel">
                    <div class="ih-panel-head">
                        <div class="ih-panel-eyebrow">Sectors</div>
                        <h3 class="ih-panel-title">Industry Type Distribution</h3>
                    </div>
                    <div class="ih-panel-body"><div class="ih-chart lg"><canvas id="industryChart"></canvas></div></div>
                </section>
                <section class="ih-panel">
                    <div class="ih-panel-head">
                        <div class="ih-panel-eyebrow">Disciplines</div>
                        <h3 class="ih-panel-title">Internship Field Distribution</h3>
                    </div>
                    <div class="ih-panel-body"><div class="ih-chart lg"><canvas id="fieldChart"></canvas></div></div>
                </section>
            </div>

            <div class="ih-grid-2">
                <section class="ih-panel">
                    <div class="ih-panel-head">
                        <div class="ih-panel-eyebrow">Company Rubric</div>
                        <h3 class="ih-panel-title">Company Evaluation &mdash; Skill Achievement</h3>
                        <p class="ih-panel-note">Average rubric score out of 4 by programme &middot; <?php echo $companyEvaluationRound === '1' ? 'First 3-Month Evaluation' : ($companyEvaluationRound === '2' ? 'Final Month Evaluation' : 'All submitted company evaluations'); ?></p>
                    </div>
                    <div class="ih-panel-body"><div class="ih-chart lg"><canvas id="companySkillsChart"></canvas></div></div>
                </section>
                <section class="ih-panel">
                    <div class="ih-panel-head">
                        <div class="ih-panel-eyebrow">Lecturer Rubric</div>
                        <h3 class="ih-panel-title">Lecturer Evaluation &mdash; Skill Achievement</h3>
                        <p class="ih-panel-note">Average rubric score out of 12 by programme &middot; System &amp; Presentation apply to B.Acct (IS) only</p>
                    </div>
                    <div class="ih-panel-body"><div class="ih-chart lg"><canvas id="lecturerSkillsChart"></canvas></div></div>
                </section>
            </div>

            </div><!-- /.ih-wrap -->
        </main>
    </div>
</div>

<script>
if (window.Chart) {
    Chart.defaults.font.family = "'Hanken Grotesk', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#5c4a50';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 14;
}
const palette = ['#8a1a38', '#cda23f', '#34589c', '#2f7d5b', '#b6841c', '#a83052', '#6e1228'];
const coordinatorStateMapData = <?php echo json_encode($stateMapData); ?>;

const coordinatorMap = L.map('coordinatorStateMap', {
    zoomControl: true,
    scrollWheelZoom: true,
    dragging: true
}).setView([4.4, 109.5], 5);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(coordinatorMap);

coordinatorStateMapData.forEach((item) => {
    L.circleMarker([item.lat, item.lng], {
        radius: Math.max(6, Math.min(18, item.total * 3)),
        color: '#8a1a38',
        fillColor: '#cda23f',
        fillOpacity: 0.6,
        weight: 2
    })
        .addTo(coordinatorMap)
        .bindTooltip(`${item.name}: ${item.total}`, { permanent: false })
        .bindPopup(`<strong>${item.name}</strong><br>${item.total} student(s)`);
});

function makeBar(id, labels, data, horizontal = false) {
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: palette, borderRadius: 6 }] },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            maintainAspectRatio: false,
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        autoSkip: false,
                        maxRotation: horizontal ? 0 : 0,
                        minRotation: 0,
                        font: { size: horizontal ? 11 : 9 },
                        callback: function(value) {
                            const label = this.getLabelForValue(value);
                            if (horizontal) return label;
                            const shortLabels = {
                                'Accounting System': 'Acct.\nSystem',
                                'Accounting Systems': 'Acct.\nSystems',
                                'Audit Analytics': 'Audit\nAnalytics',
                                'Auditing': 'Auditing',
                                'Data Analytics': 'Data\nAnalytics',
                                'Financial Reporting': 'Financial\nReporting',
                                'Management Accounting': 'Mgmt.\nAccounting',
                                'Tax Compliance': 'Tax\nCompliance'
                            };
                            return (shortLabels[label] || label).split('\n');
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        autoSkip: false,
                        font: { size: 11 },
                        callback: function(value) {
                            const label = this.getLabelForValue ? this.getLabelForValue(value) : value;
                            return horizontal && typeof label === 'string' && label.length > 22 ? label.replace('Management Accounting', 'Mgmt. Accounting') : label;
                        }
                    }
                }
            }
        }
    });
}

function makeDoughnut(id, labels, data) {
    new Chart(document.getElementById(id), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: palette, borderColor: '#fff', borderWidth: 2 }] },
        options: { maintainAspectRatio: false, responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '58%' }
    });
}

function makeLine(id, labels, data) {
    new Chart(document.getElementById(id), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Students',
                data,
                borderColor: '#8a1a38',
                backgroundColor: 'rgba(138, 26, 56, 0.12)',
                pointBackgroundColor: '#cda23f',
                pointBorderColor: '#6e1228',
                pointRadius: 4,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxRotation: 45, minRotation: 0 } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
}

function makeSkillChart(id, labels, aisData, accountingData, maxScore) {
    new Chart(document.getElementById(id), {
        data: {
            labels,
            datasets: [
                { type: 'bar', label: 'B.Acct (IS)', data: aisData, backgroundColor: '#8a1a38', borderRadius: 5 },
                { type: 'bar', label: 'B.Acct', data: accountingData, backgroundColor: '#cda23f', borderRadius: 5 }
            ]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: context => `${context.dataset.label}: ${context.raw === null ? 'No data' : Number(context.raw).toFixed(2)}` } }
            },
            scales: {
                x: { ticks: { autoSkip: false, maxRotation: 35, minRotation: 0, font: { size: 10 } } },
                y: { beginAtZero: true, min: 0, max: maxScore, ticks: { stepSize: 1 }, title: { display: true, text: `Average Score (0-${maxScore})` } }
            }
        }
    });
}

makeLine('allowanceChart', <?php echo json_encode(chart_labels($allowanceStats)); ?>, <?php echo json_encode(chart_totals($allowanceStats)); ?>);
makeDoughnut('placementChart', ['Student Intern', 'Pending', 'Not Intern'], [<?php echo (int) $studentStatus['intern_total']; ?>, <?php echo (int) $studentStatus['pending_total']; ?>, <?php echo (int) $studentStatus['not_intern_total']; ?>]);
makeBar('industryChart', <?php echo json_encode(chart_labels($industryStats)); ?>, <?php echo json_encode(chart_totals($industryStats)); ?>, true);
makeBar('fieldChart', <?php echo json_encode(chart_labels($fieldStats)); ?>, <?php echo json_encode(chart_totals($fieldStats)); ?>);
makeSkillChart(
    'companySkillsChart',
    <?php echo json_encode(array_keys($companySkillGroups)); ?>,
    <?php echo json_encode(array_map(fn($label) => $companySkillData['B.Acct (IS)'][$label] ?? null, array_keys($companySkillGroups))); ?>,
    <?php echo json_encode(array_map(fn($label) => $companySkillData['B.Acct'][$label] ?? null, array_keys($companySkillGroups))); ?>,
    4
);
makeSkillChart(
    'lecturerSkillsChart',
    <?php echo json_encode(array_keys($lecturerSkillGroups)); ?>,
    <?php echo json_encode(array_map(fn($label) => $lecturerSkillData['B.Acct (IS)'][$label] ?? null, array_keys($lecturerSkillGroups))); ?>,
    <?php echo json_encode(array_map(fn($label) => $lecturerSkillData['B.Acct'][$label] ?? null, array_keys($lecturerSkillGroups))); ?>,
    12
);

setInterval(() => {
    document.getElementById('liveClock').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}, 1000);
</script>
</body>
</html>
