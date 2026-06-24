<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/evaluation_helpers.php';
require_once __DIR__ . '/../includes/management_helpers.php';
require_once __DIR__ . '/../includes/employer_assessment_rubric.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
ensure_company_evaluation_round_schema($pdo);
ensure_management_schema($pdo);
require_company_approval($pdo, $company_id);
$internship_id = (int) ($_GET['internship_id'] ?? 0);
$evaluationRound = (int) ($_GET['round'] ?? $_POST['round'] ?? 1);
if (!in_array($evaluationRound, [1, 2], true)) {
    $evaluationRound = 1;
}

$stmt = $pdo->prepare("
    SELECT i.*, s.student_name, s.student_email, s.student_course,
           l.lecturer_name, j.job_title
    FROM internship i
    JOIN student s ON i.student_id = s.student_id
    JOIN lecturer l ON i.lecturer_id = l.lecturer_id
    LEFT JOIN jobposting j ON i.job_id = j.job_id
    WHERE i.internship_id = ? AND i.company_id = ?
");
$stmt->execute([$internship_id, $company_id]);
$internship = $stmt->fetch();

if (!$internship) {
    header("Location: interns.php");
    exit();
}

$criteriaGroups = [
    'Cognitive Skills (LOC2)' => [
        'ce_understand_organization_governance' => "Understanding of Organization's Governance",
        'ce_knowledge_business_principles_practices' => 'Knowledge of Key Business Principles and Practices',
        'ce_apply_knowledge_practices' => 'Ability to Apply Knowledge to Practices',
        'ce_problem_identification_supporting_evidence' => 'Problem Identification and Supporting Evidence',
        'ce_proposed_solutions' => 'Proposed Solution(s)',
    ],
    'Practical Skill (LOC3A)' => [
        'ce_application_it' => 'Application of Information Technology (IT)',
    ],
    'Interpersonal Skills (LOC3B)' => [
        'ce_attitude_towards_team_members' => 'Attitude toward Team Members',
        'ce_contribution_to_team' => 'Contribution to the Team',
        'ce_leadership_skills' => 'Leadership Skills',
    ],
    'Communication Skills (LOC3C)' => [
        'ce_attentiveness' => 'Attentiveness',
        'ce_answering_questions' => 'Answering Questions',
        'ce_questioning' => 'Questioning',
    ],
    'Personal Skills (LOC4A)' => [
        'ce_seeking_information' => 'Seeking Information',
        'ce_being_resourceful' => 'Being Resourceful',
        'ce_logbook' => 'Log Book',
        'ce_respect_for_others' => 'Respect for Others',
        'ce_punctuality' => 'Punctuality',
        'ce_meeting_deadlines' => 'Meeting Deadlines',
        'ce_personal_apperance' => 'Personal Appearance',
    ],
    'Ethics & Professionalism (LOC5)' => [
        'ce_knowledge_of_ethics' => 'Knowledge of Ethics',
        'ce_ethical_behaviour' => 'Ethical Behaviour',
    ],
];

$flatCriteria = [];
foreach ($criteriaGroups as $items) {
    $flatCriteria = array_merge($flatCriteria, $items);
}
$assessmentRubric = employer_assessment_rubric();

$stmt = $pdo->prepare("SELECT * FROM companyevaluation WHERE internship_id = ? AND ce_round = ?");
$stmt->execute([$internship_id, $evaluationRound]);
$existing_eval = $stmt->fetch();

$stmt = $pdo->prepare("SELECT ce_round, ce_total_score FROM companyevaluation WHERE internship_id = ? ORDER BY ce_round");
$stmt->execute([$internship_id]);
$submittedEvaluations = [];
foreach ($stmt->fetchAll() as $evaluation) {
    $submittedEvaluations[(int) $evaluation['ce_round']] = $evaluation;
}

$firstEvaluationDate = !empty($internship['internship_start_date']) ? date('Y-m-d', strtotime($internship['internship_start_date'] . ' +3 months')) : null;
$finalEvaluationDate = !empty($internship['internship_end_date']) ? date('Y-m-d', strtotime($internship['internship_end_date'] . ' -1 month')) : null;
$roundTitle = $evaluationRound === 1 ? 'First 3 Month Evaluation' : 'Final Evaluation (Last Month)';
$openingDate = $evaluationRound === 1 ? $firstEvaluationDate : $finalEvaluationDate;
$roundAvailable = $openingDate === null || date('Y-m-d') >= $openingDate;
if ($evaluationRound === 2 && empty($submittedEvaluations[1])) {
    $roundAvailable = false;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_eval) {
    if (!$roundAvailable) {
        $error = $evaluationRound === 2 && empty($submittedEvaluations[1])
            ? 'Submit the first 3 month evaluation before completing the final evaluation.'
            : $roundTitle . ' is not open yet.';
    }
    $scores = [];
    foreach ($flatCriteria as $column => $label) {
        if ($error) {
            break;
        }
        $value = filter_input(INPUT_POST, $column, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1 || $value > 4) {
            $error = 'Please choose a score from 1 to 4 for every item.';
            break;
        }
        $scores[$column] = $value;
    }

    if (!$error) {
        $total_score = array_sum($scores);

        try {
            $columns = array_keys($scores);
            $placeholders = implode(', ', array_fill(0, count($columns) + 3, '?'));
            $stmt = $pdo->prepare("
                INSERT INTO companyevaluation (
                    internship_id,
                    ce_round,
                    " . implode(",\n                    ", $columns) . ",
                    ce_total_score
                ) VALUES ($placeholders)
            ");
            $stmt->execute(array_merge([$internship_id, $evaluationRound], array_values($scores), [$total_score]));

            sync_report($pdo, $internship_id);
            $message = $roundTitle . ' submitted successfully.';

            $stmt = $pdo->prepare("SELECT * FROM companyevaluation WHERE internship_id = ? AND ce_round = ?");
            $stmt->execute([$internship_id, $evaluationRound]);
            $existing_eval = $stmt->fetch();
            $submittedEvaluations[$evaluationRound] = $existing_eval;
        } catch (Exception $e) {
            $error = 'Failed to submit evaluation: ' . $e->getMessage();
        }
    }
}

function company_score_options($name, $hints, $selected = null, $readonly = false)
{
    $labels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Excellent'];
    $html = '<div class="score-options">';
    foreach ($labels as $value => $label) {
        $id = htmlspecialchars($name . '_' . $value);
        $checked = ((string) $selected === (string) $value) ? ' checked' : '';
        $required = $value === 1 ? ' required' : '';
        $hint = htmlspecialchars($hints[$value] ?? '', ENT_QUOTES, 'UTF-8');
        $selectedClass = $checked ? ' score-selected' : '';
        $disabled = $readonly ? ' disabled' : '';
        $html .= '<label class="score-choice' . $selectedClass . '" for="' . $id . '" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $hint . '" aria-label="' . $value . ' ' . $label . ': ' . $hint . '">';
        $html .= '<input type="radio" id="' . $id . '" name="' . htmlspecialchars($name) . '" value="' . $value . '"' . $checked . $required . $disabled . '> ';
        $html .= '<span>' . $value . '</span><small>' . $label . '</small></label>';
    }
    $html .= '</div>';
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluate Intern - InternHub</title>
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
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">Company Profile</a>
            <a href="jobs.php">Manage Jobs</a>
            <a href="applications.php">Applications</a>
            <a href="interns.php" class="active">My Interns</a>
            <a href="../logout.php" class="text-danger">Logout</a>
        </div>

        <div class="col-md-10 content">
            <div class="ih-pagehead">
                <div>
                    <div class="ih-kicker">Company &middot; Evaluation</div>
                    <h1 class="ih-title">Employer Supervisor Assessment</h1>
                </div>
                <div class="ih-pagehead-right">
                    <a class="btn <?php echo $evaluationRound === 1 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="evaluate.php?internship_id=<?php echo $internship_id; ?>&round=1">First 3-Month</a>
                    <a class="btn <?php echo $evaluationRound === 2 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="evaluate.php?internship_id=<?php echo $internship_id; ?>&round=2">Final</a>
                </div>
            </div>

            <div class="ev-subject">
                <div>
                    <div class="nm"><?php echo htmlspecialchars($internship['student_name']); ?></div>
                    <div class="meta">
                        <?php if (!empty($internship['job_title'])): ?><i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($internship['job_title']); ?> &middot; <?php endif; ?>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($roundTitle); ?></span><br>
                        Each assessment is scored 1&ndash;4 per item, out of 84. Both rounds combine as (total / 168) &times; 40 for the final company mark.
                    </div>
                </div>
                <div class="ev-runtotal">
                    <span class="kicker">Running Total</span>
                    <div class="ev-rt-main"><span id="rtScore">0</span><span class="ev-rt-max" id="rtMax">/84</span></div>
                    <div class="ev-rt-pct"><span id="rtPct">0</span>% achieved</div>
                    <div class="ev-rt-bar"><span id="rtBar"></span></div>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-circle-check me-2"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="alert alert-light border">
                <strong>Schedule:</strong> First evaluation after 3 months<?php echo $firstEvaluationDate ? ' (' . htmlspecialchars(date('d M Y', strtotime($firstEvaluationDate))) . ')' : ''; ?>;
                final evaluation during the last month<?php echo $finalEvaluationDate ? ' (from ' . htmlspecialchars(date('d M Y', strtotime($finalEvaluationDate))) . ')' : ''; ?>.
            </div>

            <?php if ($existing_eval): ?>
                <div class="alert alert-info">
                    <?php echo htmlspecialchars($roundTitle); ?> already submitted (read-only). Total score: <strong><?php echo htmlspecialchars($existing_eval['ce_total_score']); ?>/84</strong>.
                    <?php if (count($submittedEvaluations) === 2): ?>
                        <?php $combinedRaw = array_sum(array_column($submittedEvaluations, 'ce_total_score')); ?>
                        Combined company mark: <strong><?php echo htmlspecialchars($combinedRaw); ?>/168 = <?php echo htmlspecialchars(weighted_company_score($combinedRaw, 2)); ?>/40</strong>
                    <?php else: ?>
                        The combined company mark remains pending until both evaluations are submitted.
                    <?php endif; ?>
                </div>
                <?php foreach ($criteriaGroups as $groupName => $criteria): ?>
                    <div class="ev-section">
                        <h5><?php echo htmlspecialchars($groupName); ?><span class="ev-sec-total"></span></h5>
                        <?php foreach ($criteria as $column => $label): ?>
                            <div class="criterion-row">
                                <div><strong><?php echo htmlspecialchars($label); ?></strong><i class="fas fa-circle-info rubric-help ms-1" data-bs-toggle="tooltip" title="Hover over each score to view its specific criterion description."></i></div>
                                <div><?php echo company_score_options($column, $assessmentRubric[$column] ?? [], $existing_eval[$column] ?? null, true); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <a href="interns.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>Back to Interns</a>
            <?php elseif (!$roundAvailable): ?>
                <div class="alert alert-warning">
                    <?php if ($evaluationRound === 2 && empty($submittedEvaluations[1])): ?>
                        The first 3 month evaluation must be submitted before the final evaluation.
                    <?php else: ?>
                        This evaluation opens on <strong><?php echo htmlspecialchars(date('d M Y', strtotime($openingDate))); ?></strong>.
                    <?php endif; ?>
                </div>
                <a href="interns.php" class="btn btn-secondary btn-lg">Back to Interns</a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="round" value="<?php echo $evaluationRound; ?>">
                    <?php foreach ($criteriaGroups as $groupName => $criteria): ?>
                        <div class="ev-section">
                            <h5><?php echo htmlspecialchars($groupName); ?><span class="ev-sec-total"></span></h5>
                            <?php foreach ($criteria as $column => $label): ?>
                                <div class="criterion-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($label); ?></strong>
                                        <i class="fas fa-circle-info rubric-help ms-1" data-bs-toggle="tooltip" title="Hover over each score to view its specific criterion description."></i>
                                    </div>
                                    <div><?php echo company_score_options($column, $assessmentRubric[$column] ?? []); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-2"></i>Submit <?php echo htmlspecialchars($roundTitle); ?></button>
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

// Live running total (each item 1-4; section + overall achievement %)
function ihRecompute() {
    var grand = 0, grandMax = 0;
    document.querySelectorAll('.ev-section').forEach(function (sec) {
        var sum = 0, count = 0;
        sec.querySelectorAll('.score-options').forEach(function (opt) {
            count++;
            var c = opt.querySelector('input:checked');
            if (c) sum += parseInt(c.value, 10);
        });
        var max = count * 4;
        grand += sum; grandMax += max;
        var pct = max ? Math.round((sum / max) * 100) : 0;
        var badge = sec.querySelector('.ev-sec-total');
        if (badge) badge.textContent = sum + ' / ' + max + '  ·  ' + pct + '%';
    });
    var tpct = grandMax ? Math.round((grand / grandMax) * 100) : 0;
    var rs = document.getElementById('rtScore'); if (rs) rs.textContent = grand;
    var rm = document.getElementById('rtMax'); if (rm) rm.textContent = '/' + grandMax;
    var rp = document.getElementById('rtPct'); if (rp) rp.textContent = tpct;
    var rb = document.getElementById('rtBar'); if (rb) rb.style.width = tpct + '%';
}
document.querySelectorAll('.score-options').forEach(function (g) { g.addEventListener('change', ihRecompute); });
ihRecompute();
</script>
</body>
</html>
