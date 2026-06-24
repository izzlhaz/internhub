<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evaluation_helpers.php';
require_once __DIR__ . '/../includes/lecturer_assessment_rubric.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$internship_id = (int) ($_GET['internship_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT i.*, s.student_name, s.student_email, s.student_course, c.company_name
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    WHERE i.internship_id = ? AND i.lecturer_id = ?
");
$stmt->execute([$internship_id, $lecturer_id]);
$internship = $stmt->fetch();

if (!$internship) {
    header("Location: interns.php");
    exit();
}

$isAccountingIS = stripos($internship['student_course'], 'Information Systems') !== false;
ensure_lecturer_weighted_score_schema($pdo);
$assessmentRubric = lecturer_assessment_rubric($isAccountingIS);
$guidelineLabel = $isAccountingIS ? 'View B.Acct (IS) Guideline' : 'View B.Acct Guideline';

$sections = [
    'log' => [
        'title' => 'Log Book',
        'items' => [
            'log_organization' => 'Organization',
            'log_complete' => 'Complete',
            'log_support' => 'Support',
            'log_reflection' => 'Reflection',
        ],
    ],
    'report' => [
        'title' => 'Report Writing',
        'items' => [
            'report_introduction' => 'Introduction',
            'report_methodology' => 'Methodology',
            'report_analysis' => 'Analysis / Discussion',
            'report_conclusion' => 'Conclusion',
            'report_organization' => 'Organization',
            'report_mechanism' => 'Mechanism',
            'report_aesthetics' => 'Aesthetics',
            'report_timeliness' => 'Timeliness',
            'report_overall' => 'Overall',
        ],
    ],
];

if ($isAccountingIS) {
    $sections['system'] = [
        'title' => 'System',
        'items' => [
            'system_analyze' => 'Ability to Analyze',
            'system_security' => 'Security & Control',
            'system_interface' => 'Interface',
            'system_reports' => 'Reports',
            'system_queries' => 'Queries',
            'system_practicality' => 'Practicality',
            'system_ease_use' => 'Ease of Use',
            'system_enhanced' => 'Enhanced Feature',
            'system_creativity' => 'Creativity',
        ],
    ];
    $sections['presentation'] = [
        'title' => 'Presentation',
        'items' => [
            'present_organization' => 'Content: Organization',
            'present_subject' => 'Content: Subject Knowledge',
            'present_visual' => 'Visual Aids',
            'present_non_verbal' => 'Non Verbal Skill',
            'present_enthusiasm' => 'Enthusiasm',
            'present_elocution' => 'Elocution',
        ],
    ];
}

$stmt = $pdo->prepare("SELECT * FROM lecturerevaluation WHERE internship_id = ?");
$stmt->execute([$internship_id]);
$existing_eval = $stmt->fetch();

$message = '';
$error = '';

function require_score($key, &$error)
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value < 0 || $value > 12) {
        $error = 'Please choose a score from 0 to 12 for every lecturer evaluation item.';
        return null;
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_eval) {
    $scores = [];
    foreach ($sections as $section) {
        foreach ($section['items'] as $key => $label) {
            $scores[$key] = require_score($key, $error);
            if ($error) {
                break 2;
            }
        }
    }

    if (!$error) {
        $weightedScores = lecturer_weighted_scores($scores, $isAccountingIS);
        $logRaw = $scores['log_organization'] + $scores['log_complete'] + $scores['log_support'] + ($scores['log_reflection'] * 2);
        $lecturerTotal = $weightedScores['total'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO internshiplogbookscore (internship_id, ls_information, ls_impact_task)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$internship_id, $logRaw, $scores['log_reflection']]);
            $ls_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO internshipreportwritingscore (
                    internship_id, rws_coherence, rws_information, rws_analysis,
                    rws_grammar_spelling, rws_appearance, rws_sources_references
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $internship_id,
                $scores['report_introduction'],
                $scores['report_methodology'],
                $scores['report_analysis'],
                $scores['report_conclusion'],
                $scores['report_organization'],
                $scores['report_overall'],
            ]);
            $rws_id = $pdo->lastInsertId();

            $ss_id = null;
            $ops_id = null;

            if ($isAccountingIS) {
                $stmt = $pdo->prepare("
                    INSERT INTO internshipsystemscore (
                        internship_id, ss_data_structure, ss_coding_standard, ss_system_control,
                        ss_user_interface, ss_data_maintenance, ss_output, ss_ability_solve_problem
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $internship_id,
                    $scores['system_analyze'],
                    $scores['system_security'],
                    $scores['system_interface'],
                    $scores['system_reports'],
                    $scores['system_queries'],
                    $scores['system_practicality'],
                    $scores['system_creativity'],
                ]);
                $ss_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO internshiporalpresentationscore (
                        internship_id, ops_organization, ops_idea_delivery, ops_multimedia_support,
                        ops_non_verbal_skills, ops_verbal_skills
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $internship_id,
                    $scores['present_organization'],
                    $scores['present_subject'],
                    $scores['present_visual'],
                    $scores['present_non_verbal'],
                    $scores['present_elocution'],
                ]);
                $ops_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("
                INSERT INTO lecturerevaluation (
                    internship_id, rws_id, ls_id, ss_id, ops_id,
                    le_logbook_score, le_report_score, le_system_score, le_presentation_score, le_score_data,
                    le_total_score
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $internship_id, $rws_id, $ls_id, $ss_id, $ops_id,
                $weightedScores['logbook'], $weightedScores['report'], $weightedScores['system'], $weightedScores['presentation'],
                json_encode($scores, JSON_UNESCAPED_SLASHES),
                $lecturerTotal,
            ]);

            $pdo->commit();
            sync_report($pdo, $internship_id);

            $message = 'Lecturer evaluation submitted successfully.';
            $stmt = $pdo->prepare("SELECT * FROM lecturerevaluation WHERE internship_id = ?");
            $stmt->execute([$internship_id]);
$existing_eval = $stmt->fetch();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to submit evaluation: ' . $e->getMessage();
        }
    }
}

$existingScores = [];
$legacySectionReview = [];
$legacyScoresReconstructed = false;
if ($existing_eval) {
    $decodedScores = json_decode((string) ($existing_eval['le_score_data'] ?? ''), true);
    if (is_array($decodedScores)) {
        $existingScores = $decodedScores;
    } else {
        $stmt = $pdo->prepare("
            SELECT ls.ls_information,
                   rws.rws_coherence, rws.rws_information, rws.rws_analysis, rws.rws_grammar_spelling, rws.rws_appearance, rws.rws_sources_references,
                   ss.ss_data_structure, ss.ss_coding_standard, ss.ss_system_control, ss.ss_user_interface, ss.ss_data_maintenance, ss.ss_output, ss.ss_ability_solve_problem,
                   ops.ops_organization, ops.ops_idea_delivery, ops.ops_multimedia_support, ops.ops_non_verbal_skills, ops.ops_verbal_skills
            FROM lecturerevaluation le
            LEFT JOIN internshiplogbookscore ls ON ls.ls_id = le.ls_id
            LEFT JOIN internshipreportwritingscore rws ON rws.rws_id = le.rws_id
            LEFT JOIN internshipsystemscore ss ON ss.ss_id = le.ss_id
            LEFT JOIN internshiporalpresentationscore ops ON ops.ops_id = le.ops_id
            WHERE le.internship_id = ?
        ");
        $stmt->execute([$internship_id]);
        $legacy = $stmt->fetch() ?: [];
        if (($legacy['ls_information'] ?? null) !== null) {
            $legacySectionReview['Log Book'] = round(((float) $legacy['ls_information'] / 60) * 12, 1);
        }
        if (($legacy['rws_coherence'] ?? null) !== null) {
            $legacySectionReview['Report Writing'] = round((array_sum(array_map('floatval', array_slice($legacy, 1, 6))) / 72) * 12, 1);
        }
        if ($isAccountingIS && ($legacy['ss_data_structure'] ?? null) !== null) {
            $systemValues = array_intersect_key($legacy, array_flip(['ss_data_structure','ss_coding_standard','ss_system_control','ss_user_interface','ss_data_maintenance','ss_output','ss_ability_solve_problem']));
            $legacySectionReview['System'] = round((array_sum(array_map('floatval', $systemValues)) / 84) * 12, 1);
        }
        if ($isAccountingIS && ($legacy['ops_organization'] ?? null) !== null) {
            $presentationValues = array_intersect_key($legacy, array_flip(['ops_organization','ops_idea_delivery','ops_multimedia_support','ops_non_verbal_skills','ops_verbal_skills']));
            $legacySectionReview['Presentation'] = round((array_sum(array_map('floatval', $presentationValues)) / 60) * 12, 1);
        }

        foreach ($sections as $section) {
            $sectionMark = $legacySectionReview[$section['title']] ?? null;
            if ($sectionMark === null) {
                continue;
            }
            $displayScore = max(0, min(12, (int) round($sectionMark)));
            foreach ($section['items'] as $key => $label) {
                $existingScores[$key] = $displayScore;
            }
        }
        $legacyScoresReconstructed = !empty($existingScores);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Evaluation - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <a href="lecturer_dashboard.php">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="interns.php" class="active">My Interns</a>
            <a href="logbooks.php">Logbooks</a>
            <a href="report.php">Reports</a>
            <a href="../logout.php" class="text-danger">Logout</a>
        </div>
        <div class="col-md-10 content">
            <div class="ih-pagehead">
                <div>
                    <div class="ih-kicker">Lecturer &middot; Evaluation</div>
                    <h1 class="ih-title">Lecturer Evaluation</h1>
                </div>
                <div class="ih-pagehead-right">
                    <a href="../lecturer_guideline.php?internship_id=<?php echo (int) $internship_id; ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
                        <i class="fas fa-file-pdf me-2"></i><?php echo htmlspecialchars($guidelineLabel); ?>
                    </a>
                </div>
            </div>

            <div class="ev-subject">
                <div>
                    <div class="nm"><?php echo htmlspecialchars($internship['student_name']); ?></div>
                    <div class="meta">
                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($internship['company_name']); ?>
                        &middot; <?php echo htmlspecialchars(programme_short_label($internship['student_course'])); ?><br>
                        Scores use a 0&ndash;12 scale. Lecturer weighting: Logbook 5%, Report <?php echo $isAccountingIS ? '20%, System 25%, Presentation 10%' : '55%'; ?>. Hover a score to view its rubric hint.
                    </div>
                </div>
                <div class="ev-runtotal">
                    <span class="kicker">Running Total</span>
                    <div class="ev-rt-main"><span id="rtScore">0</span><span class="ev-rt-max" id="rtMax">/0</span></div>
                    <div class="ev-rt-pct"><span id="rtPct">0.0</span>% of 60% weighted</div>
                    <div class="ev-rt-bar"><span id="rtBar"></span></div>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-circle-check me-2"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if ($existing_eval): ?>
                <div class="alert alert-info">
                    Lecturer evaluation already submitted. The saved scores are shown below in read-only mode.
                    Weighted score: <strong><?php echo htmlspecialchars($existing_eval['le_total_score']); ?>/60</strong>
                </div>
                <?php if ($legacyScoresReconstructed): ?><div class="alert alert-warning">This older evaluation predates individual-answer storage. Its highlighted read-only choices are reconstructed from each saved section mark.</div><?php endif; ?>
                <?php foreach ($sections as $sectionKey => $section): ?>
                    <div class="ev-section">
                        <h5><?php echo htmlspecialchars($section['title']); ?><span class="ev-sec-total" data-section="<?php echo htmlspecialchars($sectionKey); ?>"></span></h5>
                        <?php foreach ($section['items'] as $key => $label): ?>
                            <div class="criterion-row">
                                <div><strong><?php echo htmlspecialchars($label); ?></strong><i class="fas fa-circle-info rubric-help ms-1" data-bs-toggle="tooltip" title="Scores 0-3 are Poor, 4-6 Fair, 7-9 Good, and 10-12 Excellent."></i></div>
                                <div><?php echo score_radio_group($key, 12, $existingScores[$key] ?? null, $assessmentRubric[$key] ?? [], true); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <a href="interns.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>Back to Interns</a>
            <?php else: ?>
                <form method="POST">
                    <?php foreach ($sections as $sectionKey => $section): ?>
                        <div class="ev-section">
                            <h5><?php echo htmlspecialchars($section['title']); ?><span class="ev-sec-total" data-section="<?php echo htmlspecialchars($sectionKey); ?>"></span></h5>
                            <?php foreach ($section['items'] as $key => $label): ?>
                                <div class="criterion-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($label); ?></strong>
                                        <i class="fas fa-circle-info rubric-help ms-1" data-bs-toggle="tooltip" title="Scores 0-3 are Poor, 4-6 Fair, 7-9 Good, and 10-12 Excellent. Hover over each score for the official criterion description."></i>
                                    </div>
                                    <div><?php echo score_radio_group($key, 12, null, $assessmentRubric[$key] ?? []); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-2"></i>Submit Evaluation</button>
                        <a href="interns.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
    new bootstrap.Tooltip(element, {container: 'body'});
});
// Live highlight of the chosen score chip
document.querySelectorAll('.score-options').forEach(function (group) {
    group.addEventListener('change', function (e) {
        if (!e.target.matches('input[type="radio"]')) return;
        group.querySelectorAll('.score-choice').forEach(function (lbl) { lbl.classList.remove('score-selected'); });
        var lbl = e.target.closest('.score-choice');
        if (lbl) lbl.classList.add('score-selected');
    });
});

// ---- Live running total ----
// Each item is scored 0-12. Section "%" = (raw / raw max) x section weight,
// so a fully-maxed evaluation reaches the programme weight total (60%).
const LEC_SECTIONS = {
    log: { weight: 5, items: ['log_organization','log_complete','log_support','log_reflection'] },
    report: { weight: <?php echo $isAccountingIS ? 20 : 55; ?>, items: ['report_introduction','report_methodology','report_analysis','report_conclusion','report_organization','report_mechanism','report_aesthetics','report_timeliness','report_overall'] },
<?php if ($isAccountingIS): ?>
    system: { weight: 25, items: ['system_analyze','system_security','system_interface','system_reports','system_queries','system_practicality','system_ease_use','system_enhanced','system_creativity'] },
    presentation: { weight: 10, items: ['present_organization','present_subject','present_visual','present_non_verbal','present_enthusiasm','present_elocution'] },
<?php endif; ?>
};
function ihVal(name){ var el = document.querySelector('input[name="' + name + '"]:checked'); return el ? parseInt(el.value, 10) : 0; }
function ihRecompute(){
    var rawAll = 0, rawMaxAll = 0, pctAll = 0;
    Object.keys(LEC_SECTIONS).forEach(function(key){
        var s = LEC_SECTIONS[key], sum = 0, n = s.items.length;
        s.items.forEach(function(item){ sum += ihVal(item); });
        var rawMax = n * 12;
        var secPct = rawMax ? (sum / rawMax) * s.weight : 0;
        rawAll += sum; rawMaxAll += rawMax; pctAll += secPct;
        var badge = document.querySelector('.ev-sec-total[data-section="' + key + '"]');
        if (badge) badge.textContent = sum + '/' + rawMax + ' · ' + secPct.toFixed(1) + '% of ' + s.weight + '%';
    });
    var rs = document.getElementById('rtScore'); if (rs) rs.textContent = rawAll;
    var rm = document.getElementById('rtMax'); if (rm) rm.textContent = '/' + rawMaxAll;
    var rp = document.getElementById('rtPct'); if (rp) rp.textContent = pctAll.toFixed(1);
    var rb = document.getElementById('rtBar'); if (rb) rb.style.width = (pctAll / 60 * 100) + '%';
}
document.querySelectorAll('.score-options').forEach(function(g){ g.addEventListener('change', ihRecompute); });
ihRecompute();
</script>
</body>
</html>
