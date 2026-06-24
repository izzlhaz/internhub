<?php

function grade_from_score($score)
{
    if ($score === null || $score === '') {
        return null;
    }

    $score = (float) $score;
    $thresholds = [
        89.44 => 'A+',
        79.44 => 'A',
        74.44 => 'A-',
        69.44 => 'B+',
        64.44 => 'B',
        59.44 => 'B-',
        54.44 => 'C+',
        49.44 => 'C',
        44.44 => 'C-',
        39.44 => 'D+',
        34.44 => 'D',
        0 => 'F',
    ];

    foreach ($thresholds as $minimum => $grade) {
        if ($score >= $minimum) {
            return $grade;
        }
    }

    return 'F';
}

function ensure_company_evaluation_round_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM companyevaluation')->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }
    if (empty($columns['ce_round'])) {
        $pdo->exec("ALTER TABLE companyevaluation ADD ce_round tinyint(1) NOT NULL DEFAULT 1 AFTER internship_id");
    }
    if (empty($columns['ce_submitted_at'])) {
        $pdo->exec("ALTER TABLE companyevaluation ADD ce_submitted_at datetime NOT NULL DEFAULT current_timestamp() AFTER ce_total_score");
    }

    $indexes = [];
    foreach ($pdo->query('SHOW INDEX FROM companyevaluation')->fetchAll() as $index) {
        $indexes[$index['Key_name']][] = $index['Column_name'];
    }
    if (isset($indexes['internship_id']) && $indexes['internship_id'] === ['internship_id']) {
        $pdo->exec('ALTER TABLE companyevaluation DROP INDEX internship_id');
    }
    if (empty($indexes['uq_companyevaluation_round'])) {
        $pdo->exec('ALTER TABLE companyevaluation ADD UNIQUE KEY uq_companyevaluation_round (internship_id, ce_round)');
    }

    $ready = true;
}

function company_evaluation_summary_sql(string $alias = 'ce'): string
{
    return "(
        SELECT internship_id,
               MAX(CASE WHEN ce_round = 1 THEN ce_id END) AS ce_first_id,
               MAX(CASE WHEN ce_round = 2 THEN ce_id END) AS ce_final_id,
               MAX(CASE WHEN ce_round = 1 THEN ce_total_score END) AS ce_first_score,
               MAX(CASE WHEN ce_round = 2 THEN ce_total_score END) AS ce_final_score,
               SUM(ce_total_score) AS ce_total_score,
               COUNT(DISTINCT ce_round) AS ce_count
        FROM companyevaluation
        GROUP BY internship_id
    ) {$alias}";
}

function weighted_company_score($rawScore, int $evaluationCount = 2)
{
    if ($rawScore === null || $rawScore === '') {
        return null;
    }

    $maximum = 84 * max(1, $evaluationCount);
    return round(((float) $rawScore / $maximum) * 40, 2);
}

function lecturer_weighted_scores(array $scores, bool $isAccountingIS): array
{
    $logRaw = $scores['log_organization'] + $scores['log_complete'] + $scores['log_support'] + ($scores['log_reflection'] * 2);
    $reportRaw =
        ($scores['report_introduction'] * 3) +
        ($scores['report_methodology'] * 2) +
        ($scores['report_analysis'] * 5) +
        ($scores['report_conclusion'] * 2) +
        $scores['report_organization'] +
        $scores['report_mechanism'] +
        $scores['report_aesthetics'] +
        $scores['report_timeliness'] +
        $scores['report_overall'];

    $weighted = [
        'logbook' => round(($logRaw / 60) * 5, 2),
        'report' => round(($reportRaw / 204) * ($isAccountingIS ? 20 : 55), 2),
        'system' => 0.0,
        'presentation' => 0.0,
    ];

    if ($isAccountingIS) {
        $systemRaw =
            $scores['system_analyze'] +
            $scores['system_security'] +
            $scores['system_interface'] +
            $scores['system_reports'] +
            $scores['system_queries'] +
            $scores['system_practicality'] +
            ($scores['system_ease_use'] * 2) +
            $scores['system_enhanced'] +
            $scores['system_creativity'];
        $presentationRaw =
            ($scores['present_organization'] * 2) +
            ($scores['present_subject'] * 3) +
            $scores['present_visual'] +
            $scores['present_non_verbal'] +
            ($scores['present_enthusiasm'] * 2) +
            $scores['present_elocution'];
        $weighted['system'] = round(($systemRaw / 120) * 25, 2);
        $weighted['presentation'] = round(($presentationRaw / 120) * 10, 2);
    }

    $weighted['total'] = round($weighted['logbook'] + $weighted['report'] + $weighted['system'] + $weighted['presentation'], 2);
    return $weighted;
}

function ensure_lecturer_weighted_score_schema(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM lecturerevaluation')->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }
    $definitions = [
        'le_logbook_score' => "ALTER TABLE lecturerevaluation ADD le_logbook_score decimal(6,2) DEFAULT NULL AFTER ops_id",
        'le_report_score' => "ALTER TABLE lecturerevaluation ADD le_report_score decimal(6,2) DEFAULT NULL AFTER le_logbook_score",
        'le_system_score' => "ALTER TABLE lecturerevaluation ADD le_system_score decimal(6,2) DEFAULT NULL AFTER le_report_score",
        'le_presentation_score' => "ALTER TABLE lecturerevaluation ADD le_presentation_score decimal(6,2) DEFAULT NULL AFTER le_system_score",
        'le_score_data' => "ALTER TABLE lecturerevaluation ADD le_score_data longtext DEFAULT NULL AFTER le_presentation_score",
    ];
    foreach ($definitions as $column => $sql) {
        if (empty($columns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function sync_report(PDO $pdo, $internshipId)
{
    ensure_company_evaluation_round_schema($pdo);
    $companySummary = company_evaluation_summary_sql('ce');
    $stmt = $pdo->prepare("
        SELECT
            ce.ce_first_id,
            ce.ce_final_id,
            ce.ce_count,
            ce.ce_total_score,
            le.le_id,
            le.le_total_score
        FROM internship i
        LEFT JOIN {$companySummary} ON ce.internship_id = i.internship_id
        LEFT JOIN lecturerevaluation le ON le.internship_id = i.internship_id
        WHERE i.internship_id = ?
    ");
    $stmt->execute([$internshipId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $companyWeighted = (int) $row['ce_count'] === 2 ? weighted_company_score($row['ce_total_score'], 2) : null;
    $lecturerWeighted = $row['le_total_score'] !== null ? (float) $row['le_total_score'] : null;
    $isComplete = $row['ce_first_id'] && $row['ce_final_id'] && $row['le_id'] && $companyWeighted !== null && $lecturerWeighted !== null;
    $total = $isComplete ? round($companyWeighted + $lecturerWeighted, 2) : null;
    $grade = $isComplete ? grade_from_score($total) : null;

    $stmt = $pdo->prepare("SELECT report_id FROM report WHERE internship_id = ?");
    $stmt->execute([$internshipId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE report
            SET ce_id = ?, le_id = ?, report_total_score = ?, report_grade = ?
            WHERE internship_id = ?
        ");
        $stmt->execute([$row['ce_final_id'], $row['le_id'], $total, $grade, $internshipId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO report (internship_id, ce_id, le_id, report_total_score, report_grade)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$internshipId, $row['ce_final_id'], $row['le_id'], $total, $grade]);
    }

    return [
        'company_weighted' => $companyWeighted,
        'lecturer_weighted' => $lecturerWeighted,
        'total' => $total,
        'grade' => $grade,
    ];
}

function get_evaluation_summary(PDO $pdo, $internshipId)
{
    sync_report($pdo, $internshipId);
    $companySummary = company_evaluation_summary_sql('ce');

    $stmt = $pdo->prepare("
        SELECT
            i.internship_id,
            s.student_name,
            s.student_course,
            c.company_name,
            l.lecturer_name,
            ce.ce_first_id,
            ce.ce_final_id,
            ce.ce_count,
            ce.ce_total_score,
            le.le_id,
            le.le_total_score,
            r.report_total_score,
            r.report_grade
        FROM internship i
        JOIN student s ON s.student_id = i.student_id
        JOIN company c ON c.company_id = i.company_id
        JOIN lecturer l ON l.lecturer_id = i.lecturer_id
        LEFT JOIN {$companySummary} ON ce.internship_id = i.internship_id
        LEFT JOIN lecturerevaluation le ON le.internship_id = i.internship_id
        LEFT JOIN report r ON r.internship_id = i.internship_id
        WHERE i.internship_id = ?
    ");
    $stmt->execute([$internshipId]);
    $summary = $stmt->fetch();

    if ($summary) {
        $summary['company_weighted'] = (int) $summary['ce_count'] === 2 ? weighted_company_score($summary['ce_total_score'], 2) : null;
    }

    return $summary;
}

function score_radio_group($name, $max, $selected = null, array $criterionRubric = [], $readonly = false)
{
    $html = '<div class="score-options">';
    for ($i = 0; $i <= $max; $i++) {
        $id = htmlspecialchars($name . '_' . $i);
        $checked = ((string) $selected === (string) $i) ? ' checked' : '';
        $required = $i === 0 ? ' required' : '';
        $hint = function_exists('lecturer_score_hint') ? lecturer_score_hint($i, $criterionRubric) : '';
        $safeHint = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
        $selectedClass = $checked ? ' score-selected' : '';
        $disabled = $readonly ? ' disabled' : '';
        $html .= '<label class="score-choice' . $selectedClass . '" for="' . $id . '" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $safeHint . '" aria-label="Score ' . $i . ': ' . $safeHint . '">';
        $html .= '<input type="radio" id="' . $id . '" name="' . htmlspecialchars($name) . '" value="' . $i . '"' . $checked . $required . $disabled . '> ';
        $html .= '<span>' . $i . '</span></label>';
    }
    $html .= '</div>';

    return $html;
}
?>
