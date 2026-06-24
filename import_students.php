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

$internship_id = (int) ($_GET['internship_id'] ?? 0);
ensure_lecturer_weighted_score_schema($pdo);
sync_report($pdo, $internship_id);

$stmt = $pdo->prepare("
    SELECT i.*, s.student_name, s.student_matric_no, s.student_course, s.student_intake,
           c.company_name, l.lecturer_name,
           le.le_total_score, le.le_logbook_score, le.le_report_score, le.le_system_score, le.le_presentation_score,
           r.report_total_score, r.report_grade,
           ls.ls_information, ls.ls_impact_task,
           rws.rws_coherence, rws.rws_information, rws.rws_analysis, rws.rws_grammar_spelling, rws.rws_appearance, rws.rws_sources_references,
           ss.ss_data_structure, ss.ss_coding_standard, ss.ss_system_control, ss.ss_user_interface, ss.ss_data_maintenance, ss.ss_output, ss.ss_ability_solve_problem,
           ops.ops_organization, ops.ops_idea_delivery, ops.ops_multimedia_support, ops.ops_non_verbal_skills, ops.ops_verbal_skills
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    JOIN lecturer l ON l.lecturer_id = i.lecturer_id
    LEFT JOIN lecturerevaluation le ON le.internship_id = i.internship_id
    LEFT JOIN report r ON r.internship_id = i.internship_id
    LEFT JOIN internshiplogbookscore ls ON ls.ls_id = le.ls_id
    LEFT JOIN internshipreportwritingscore rws ON rws.rws_id = le.rws_id
    LEFT JOIN internshipsystemscore ss ON ss.ss_id = le.ss_id
    LEFT JOIN internshiporalpresentationscore ops ON ops.ops_id = le.ops_id
    WHERE i.internship_id = ?
");
$stmt->execute([$internship_id]);
$result = $stmt->fetch();

if (!$result) {
    header("Location: evaluations.php");
    exit();
}

function scaled_mark($raw, $max)
{
    if ($raw === null || $max <= 0) {
        return null;
    }
    return round(((float) $raw / $max) * 12, 2);
}

function grade_for_score($score)
{
    if ($score === null) {
        return '-';
    }
    $pct = ($score / 12) * 100;
    if ($pct >= 85) return 'A';
    if ($pct >= 75) return 'A-';
    if ($pct >= 65) return 'B+';
    if ($pct >= 55) return 'B';
    if ($pct >= 45) return 'C';
    return 'F';
}

function company_group_mark(?array $evaluation, array $fields)
{
    if (!$evaluation) {
        return null;
    }
    $raw = 0;
    foreach ($fields as $field) {
        if ($evaluation[$field] === null) {
            return null;
        }
        $raw += (float) $evaluation[$field];
    }
    return round($raw / count($fields), 2);
}

$stmt = $pdo->prepare('SELECT * FROM companyevaluation WHERE internship_id = ? ORDER BY ce_round');
$stmt->execute([$internship_id]);
$companyEvaluations = $stmt->fetchAll();
$companyRawTotal = count($companyEvaluations) === 2 ? array_sum(array_column($companyEvaluations, 'ce_total_score')) : null;

$hasLecturerEvaluation = $result['le_total_score'] !== null;
$isAccountingIS = stripos($result['student_course'], 'Information Systems') !== false;
$hasStoredBreakdown = $result['le_logbook_score'] !== null && $result['le_report_score'] !== null;
$lecturerRows = [];
if ($hasLecturerEvaluation && $hasStoredBreakdown) {
    $lecturerRows[] = ['Log Book', scaled_mark($result['le_logbook_score'], 5)];
    $lecturerRows[] = ['Report Writing', scaled_mark($result['le_report_score'], $isAccountingIS ? 20 : 55)];
    if ($isAccountingIS) {
        $lecturerRows[] = ['System', scaled_mark($result['le_system_score'], 25)];
        $lecturerRows[] = ['Presentation', scaled_mark($result['le_presentation_score'], 10)];
    }
} elseif ($hasLecturerEvaluation) {
    $legacyLogRaw = $result['ls_information'] !== null ? (float) $result['ls_information'] : null;
    $legacyReportRaw = $result['rws_coherence'] !== null ? array_sum([
        (float) $result['rws_coherence'],
        (float) $result['rws_information'],
        (float) $result['rws_analysis'],
        (float) $result['rws_grammar_spelling'],
        (float) $result['rws_appearance'],
        (float) $result['rws_sources_references'],
    ]) : null;
    $lecturerRows[] = ['Log Book', scaled_mark($legacyLogRaw, 60)];
    $lecturerRows[] = ['Report Writing', scaled_mark($legacyReportRaw, 72)];
    if ($isAccountingIS) {
        $legacySystemRaw = $result['ss_data_structure'] !== null ? array_sum([
            (float) $result['ss_data_structure'],
            (float) $result['ss_coding_standard'],
            (float) $result['ss_system_control'],
            (float) $result['ss_user_interface'],
            (float) $result['ss_data_maintenance'],
            (float) $result['ss_output'],
            (float) $result['ss_ability_solve_problem'],
        ]) : null;
        $legacyPresentationRaw = $result['ops_organization'] !== null ? array_sum([
            (float) $result['ops_organization'],
            (float) $result['ops_idea_delivery'],
            (float) $result['ops_multimedia_support'],
            (float) $result['ops_non_verbal_skills'],
            (float) $result['ops_verbal_skills'],
        ]) : null;
        $lecturerRows[] = ['System', scaled_mark($legacySystemRaw, 84)];
        $lecturerRows[] = ['Presentation', scaled_mark($legacyPresentationRaw, 60)];
    }
}

$overallGrade = $result['report_total_score'] !== null ? ($result['report_grade'] ?: grade_from_score($result['report_total_score'])) : null;
$companyFourMark = $companyRawTotal !== null ? round((float) $companyRawTotal / 42, 2) : null;
$lecturerTwelveMark = scaled_mark($result['le_total_score'], 60);
$finalTwelveMark = scaled_mark($result['report_total_score'], 100);
$lecturerRows[] = ['Overall Lecturer Evaluation', $lecturerTwelveMark];

$companySkillFields = [
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
    'Ethics and Professionalism' => [
        'ce_knowledge_of_ethics',
        'ce_ethical_behaviour',
    ],
];
$companySections = [];
foreach ([
    1 => 'First 3 Month Evaluation',
    2 => 'Final Month Evaluation',
] as $round => $title) {
    $evaluation = null;
    foreach ($companyEvaluations as $candidate) {
        if ((int) $candidate['ce_round'] === $round) {
            $evaluation = $candidate;
            break;
        }
    }
    $rows = [];
    foreach ($companySkillFields as $label => $fields) {
        $rows[] = [$label, company_group_mark($evaluation, $fields)];
    }
    $rows[] = ['Evaluation Total', $evaluation ? round((float) $evaluation['ce_total_score'] / 21, 2) : null];
    $companySections[] = ['title' => $title, 'rows' => $rows];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Result - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        .result-title { background:#2d2d2d; color:#fff; font-weight:700; padding:8px 12px; }
        .transcript-table th { background:#2d2d2d!important; color:#fff!important; }
        .transcript-table td, .transcript-table th { border:1px solid #bfbfbf; padding:6px 8px; }
        .transcript-table .section-row td { background:#f2e5e9; color:#4b0712; font-weight:800; text-transform:uppercase; }
        .transcript-table .subsection-row td { background:#faf5f7; color:#78152a; font-weight:800; }
        .transcript-table .total-row td { background:#4b0712; color:#fff; font-weight:800; }
    </style>
</head>
<body>
<div class="container-fluid">
<div class="row">
    <?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
    <main class="col-md-10 content">
        <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
        <a href="evaluations.php" class="btn btn-secondary mb-3">Back to Evaluations</a>
        <div class="card">
        <div class="result-title">EXAMINATION RESULT FOR INTERNSHIP EVALUATION</div>
        <div class="card-body">
            <p class="mb-1"><strong>Student:</strong> <?php echo htmlspecialchars($result['student_name']); ?> (<?php echo htmlspecialchars($result['student_matric_no']); ?>)</p>
            <p class="mb-1"><strong>Programme:</strong> <?php echo htmlspecialchars(programme_short_label($result['student_course'])); ?></p>
            <p class="mb-1"><strong>Company:</strong> <?php echo htmlspecialchars($result['company_name']); ?></p>
            <p class="mb-1"><strong>Supervisor:</strong> <?php echo htmlspecialchars($result['lecturer_name']); ?></p>
            <p class="mb-1"><strong>Batch:</strong> <?php echo htmlspecialchars(strtoupper($result['student_intake'])); ?></p>
            <p class="mb-3"><strong>Semester:</strong> <?php echo htmlspecialchars(semester_from_batch($result['student_intake'])); ?></p>

            <div class="table-responsive">
                <table class="table transcript-table">
                    <thead>
                        <tr><th>COURSE</th><th style="width:120px">TOTAL MARKS</th><th style="width:90px">GRED</th></tr>
                    </thead>
                    <tbody>
                        <tr class="section-row"><td colspan="3">Lecturer Evaluation</td></tr>
                        <?php foreach ($lecturerRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row[0]); ?></td>
                                <td><?php echo $row[1] !== null ? htmlspecialchars(number_format((float) $row[1], 1)) . '/12' : 'Pending'; ?></td>
                                <td><?php echo $row[1] !== null ? htmlspecialchars(grade_from_score(((float) $row[1] / 12) * 100)) : 'Pending'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="section-row"><td colspan="3">Company Evaluation</td></tr>
                        <?php foreach ($companySections as $section): ?>
                            <tr class="subsection-row"><td colspan="3"><?php echo htmlspecialchars($section['title']); ?></td></tr>
                            <?php foreach ($section['rows'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row[0]); ?></td>
                                    <td><?php echo $row[1] !== null ? htmlspecialchars(number_format((float) $row[1], 1)) . '/4' : 'Pending'; ?></td>
                                    <td><?php echo $row[1] !== null ? htmlspecialchars(grade_from_score(((float) $row[1] / 4) * 100)) : 'Pending'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <tr>
                            <td><strong>Overall Company Evaluation</strong></td>
                            <td><?php echo $companyFourMark !== null ? htmlspecialchars(number_format((float) $companyFourMark, 1)) . '/4' : 'Pending'; ?></td>
                            <td><?php echo $companyFourMark !== null ? htmlspecialchars(grade_from_score(((float) $companyFourMark / 4) * 100)) : 'Pending'; ?></td>
                        </tr>
                        <tr class="total-row">
                            <td>Total Grade</td>
                            <td><?php echo $finalTwelveMark !== null ? htmlspecialchars(number_format((float) $finalTwelveMark, 1)) . '/12' : 'Pending'; ?></td>
                            <td><?php echo htmlspecialchars($overallGrade ?: 'Pending'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </main>
</div>
</div>
</body>
</html>
