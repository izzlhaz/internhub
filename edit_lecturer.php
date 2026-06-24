<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header('Location: ../login.php');
    exit();
}

ensure_management_schema($pdo);
$message = '';
$error = '';

$lecturers = $pdo->query("
    SELECT l.lecturer_id, l.lecturer_name, l.lecturer_programme, l.lecturer_max_student,
           COUNT(i.internship_id) AS assigned_total
    FROM lecturer l
    JOIN user u ON u.user_id = l.user_id AND u.user_status = 'Active'
    LEFT JOIN internship i ON i.lecturer_id = l.lecturer_id AND i.internship_status IN ('Accepted','Active')
    GROUP BY l.lecturer_id
    ORDER BY UPPER(l.lecturer_name) ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_batch'])) {
    $lecturerId = (int) ($_POST['lecturer_id'] ?? 0);
    $applicationIds = array_values(array_unique(array_filter(array_map('intval', $_POST['application_ids'] ?? []))));

    $lecturer = null;
    foreach ($lecturers as $row) {
        if ((int) $row['lecturer_id'] === $lecturerId) {
            $lecturer = $row;
            break;
        }
    }

    if (!$lecturer || !$applicationIds) {
        $error = 'Choose a lecturer and at least one student.';
    } else {
            $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
            $stmt = $pdo->prepare("
                SELECT a.application_id, s.student_id, s.student_name, s.student_course,
                       j.job_id, j.job_title, c.company_id
                FROM application a
                JOIN student s ON s.student_id = a.student_id
                JOIN jobposting j ON j.job_id = a.job_id
                JOIN company c ON c.company_id = j.company_id
                WHERE a.application_id IN ($placeholders)
                  AND a.application_status IN ('Accepted','Accept')
                  AND a.application_student_response = 'Accepted'
                  AND NOT EXISTS (
                      SELECT 1 FROM internship existing
                      WHERE existing.student_id = a.student_id
                        AND existing.internship_status IN ('Accepted','Active')
                  )
                ORDER BY UPPER(s.student_name) ASC
            ");
            $stmt->execute($applicationIds);
            $selectedStudents = $stmt->fetchAll();
            $isAisLecturer = stripos($lecturer['lecturer_programme'], 'Information Systems') !== false;

            if (count($selectedStudents) !== count($applicationIds)) {
                $error = 'One or more selected students are no longer available for assignment.';
            } else {
                foreach ($selectedStudents as $student) {
                    $isAisStudent = stripos($student['student_course'], 'Information Systems') !== false;
                    if ($isAisStudent && !$isAisLecturer) {
                        $error = 'B.Acct lecturers can only supervise B.Acct students.';
                        break;
                    }
                }
            }

            if (!$error) {
                try {
                    $pdo->beginTransaction();
                    $insert = $pdo->prepare("
                        INSERT INTO internship (student_id, company_id, lecturer_id, job_id, internship_field, internship_start_date, internship_end_date, internship_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
                    ");
                    foreach ($selectedStudents as $student) {
                        $insert->execute([$student['student_id'], $student['company_id'], $lecturerId, $student['job_id'], $student['job_title'], null, null]);
                    }
                    $pdo->commit();
                    $message = count($selectedStudents) . ' student(s) assigned to ' . $lecturer['lecturer_name'] . ' successfully.';
                    foreach ($lecturers as &$row) {
                        if ((int) $row['lecturer_id'] === $lecturerId) {
                            $row['assigned_total'] += count($selectedStudents);
                        }
                    }
                    unset($row);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Unable to complete the batch assignment.';
                }
            }
    }
}

$waitingApplications = $pdo->query("
    SELECT a.application_id, a.application_applied_date,
           s.student_id, s.student_name, s.student_course,
           j.job_id, j.job_title, c.company_name
    FROM application a
    JOIN student s ON s.student_id = a.student_id
    JOIN jobposting j ON j.job_id = a.job_id
    JOIN company c ON c.company_id = j.company_id
    WHERE a.application_status IN ('Accepted','Accept')
      AND a.application_student_response = 'Accepted'
      AND NOT EXISTS (
          SELECT 1 FROM internship i
          WHERE i.student_id = a.student_id AND i.internship_status IN ('Accepted','Active')
      )
    ORDER BY UPPER(s.student_name) ASC
")->fetchAll();

$assignedInternships = $pdo->query("
    SELECT i.*, s.student_name, s.student_course, c.company_name, j.job_title, l.lecturer_name
    FROM internship i
    JOIN student s ON s.student_id = i.student_id
    JOIN company c ON c.company_id = i.company_id
    JOIN lecturer l ON l.lecturer_id = i.lecturer_id
    LEFT JOIN jobposting j ON j.job_id = i.job_id
    WHERE i.internship_status IN ('Accepted','Active','Completed')
    ORDER BY UPPER(s.student_name) ASC
")->fetchAll();

$activeLecturerCount = count($lecturers);
$recommendedBatchSize = $activeLecturerCount ? (int) ceil(count($waitingApplications) / $activeLecturerCount) : 0;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assign Supervisor - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/theme.css">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}.assignment-tabs .nav-link{color:#611525;font-weight:700}.assignment-tabs .nav-link.active{color:#fff;background:#611525;border-color:#611525}.tab-count{border-radius:99px;padding:.1rem .45rem;background:#f0dfe4}.nav-link.active .tab-count{background:rgba(255,255,255,.2)}</style></head>
<body><div class="container-fluid"><div class="row"><?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?><div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<h2>Assign Student to Lecturer/Supervisor</h2>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<ul class="nav nav-tabs assignment-tabs mb-3"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#to-assign" type="button">To Assign <span class="tab-count"><?php echo count($waitingApplications); ?></span></button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#assigned" type="button">Assigned <span class="tab-count"><?php echo count($assignedInternships); ?></span></button></li></ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="to-assign">
<div class="alert alert-info"><strong>Balanced allocation:</strong> <?php echo count($waitingApplications); ?> waiting student(s) / <?php echo $activeLecturerCount; ?> active lecturer(s) = <strong><?php echo $recommendedBatchSize; ?> student(s) per lecturer</strong>. B.Acct (IS) lecturers receive B.Acct (IS) students first, then B.Acct students if places remain. B.Acct lecturers can supervise B.Acct students only.</div>
<form method="POST" id="batchAssignmentForm">
<div class="card mb-3"><div class="card-body"><div class="row g-3 align-items-end">
<div class="col-md-5"><label class="form-label">Lecturer/Supervisor</label><select class="form-select" name="lecturer_id" id="lecturerSelect" required><option value="">Choose lecturer</option><?php foreach ($lecturers as $lecturer): ?><option value="<?php echo (int) $lecturer['lecturer_id']; ?>" data-programme="<?php echo stripos($lecturer['lecturer_programme'], 'Information Systems') !== false ? 'ais' : 'accounting'; ?>"><?php echo htmlspecialchars($lecturer['lecturer_name']); ?> | <?php echo htmlspecialchars(programme_short_label($lecturer['lecturer_programme'])); ?> | <?php echo (int) $lecturer['assigned_total']; ?> currently assigned</option><?php endforeach; ?></select></div>
<div class="col-md-2"><button class="btn btn-primary w-100" name="assign_batch" type="submit">Assign</button></div>
<div class="col-12"><span id="selectionSummary" class="fw-bold text-primary">Choose a lecturer to auto-select students.</span></div>
</div></div></div>
<div class="card"><div class="card-body"><?php if ($waitingApplications): ?><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th style="width:45px">Select</th><th>Student</th><th>Programme</th><th>Company</th><th>Position</th><th>Accepted Date</th></tr></thead><tbody><?php foreach ($waitingApplications as $app): $programme = stripos($app['student_course'], 'Information Systems') !== false ? 'ais' : 'accounting'; ?><tr data-student-row data-programme="<?php echo $programme; ?>"><td><input class="form-check-input student-check" type="checkbox" name="application_ids[]" value="<?php echo (int) $app['application_id']; ?>"></td><td><strong><?php echo htmlspecialchars(strtoupper($app['student_name'])); ?></strong></td><td><?php echo htmlspecialchars(programme_short_label($app['student_course'])); ?></td><td><?php echo htmlspecialchars($app['company_name']); ?></td><td><?php echo htmlspecialchars($app['job_title']); ?></td><td><?php echo date('d M Y', strtotime($app['application_applied_date'])); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="alert alert-info mb-0">No students are waiting for supervisor assignment.</div><?php endif; ?></div></div>
</form></div>
<div class="tab-pane fade" id="assigned"><div class="card"><div class="card-body"><?php if ($assignedInternships): ?>
<div class="row mb-3"><div class="col-md-5"><label class="form-label fw-bold" for="assignedLecturerFilter">Filter by Lecturer</label><select class="form-select" id="assignedLecturerFilter"><option value="all">All Lecturers</option><?php foreach ($lecturers as $lecturer): ?><option value="<?php echo (int) $lecturer['lecturer_id']; ?>"><?php echo htmlspecialchars($lecturer['lecturer_name']); ?></option><?php endforeach; ?></select></div></div>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Student</th><th>Programme</th><th>Company</th><th>Position</th><th>Lecturer</th><th>Status</th></tr></thead><tbody><?php foreach ($assignedInternships as $internship): ?><tr class="assigned-row" data-lecturer-id="<?php echo (int) $internship['lecturer_id']; ?>"><td><strong><?php echo htmlspecialchars(strtoupper($internship['student_name'])); ?></strong></td><td><?php echo htmlspecialchars(programme_short_label($internship['student_course'])); ?></td><td><?php echo htmlspecialchars($internship['company_name']); ?></td><td><?php echo htmlspecialchars($internship['job_title'] ?: $internship['internship_field']); ?></td><td><?php echo htmlspecialchars($internship['lecturer_name']); ?></td><td><span class="badge bg-success"><?php echo htmlspecialchars($internship['internship_status']); ?></span></td></tr><?php endforeach; ?><tr id="noAssignedResults" class="d-none"><td colspan="6" class="text-center text-muted py-4">No assigned students found for this lecturer.</td></tr></tbody></table></div><?php else: ?><div class="alert alert-info mb-0">No internships have been assigned yet.</div><?php endif; ?></div></div></div>
</div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const recommended = <?php echo $recommendedBatchSize; ?>;
const lecturerSelect = document.getElementById('lecturerSelect');
const checks = Array.from(document.querySelectorAll('.student-check'));
const summary = document.getElementById('selectionSummary');
function autoSelectStudents() {
    checks.forEach(check => { check.checked = false; check.disabled = false; });
    const option = lecturerSelect.options[lecturerSelect.selectedIndex];
    if (!option || !option.value) {
        summary.textContent = 'Choose a lecturer to auto-select students.';
        return;
    }
    const lecturerProgramme = option.dataset.programme;
    const limit = recommended;
    let selected = 0;
    checks.forEach(check => {
        const rowProgramme = check.closest('[data-student-row]').dataset.programme;
        const compatible = lecturerProgramme === 'ais' || rowProgramme === 'accounting';
        check.disabled = !compatible;
    });
    const priorityChecks = lecturerProgramme === 'ais'
        ? checks.filter(check => check.closest('[data-student-row]').dataset.programme === 'ais')
            .concat(checks.filter(check => check.closest('[data-student-row]').dataset.programme === 'accounting'))
        : checks.filter(check => check.closest('[data-student-row]').dataset.programme === 'accounting');
    priorityChecks.forEach(check => {
        if (!check.disabled && selected < limit) {
            check.checked = true;
            selected++;
        }
    });
    summary.textContent = selected + ' compatible student(s) selected automatically. You may adjust the checked students before assigning.';
}
lecturerSelect.addEventListener('change', autoSelectStudents);
const assignedLecturerFilter = document.getElementById('assignedLecturerFilter');
if (assignedLecturerFilter) {
    assignedLecturerFilter.addEventListener('change', function () {
        const lecturerId = this.value;
        let visible = 0;
        document.querySelectorAll('.assigned-row').forEach(row => {
            const show = lecturerId === 'all' || row.dataset.lecturerId === lecturerId;
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        document.getElementById('noAssignedResults').classList.toggle('d-none', visible !== 0);
    });
}
</script></body></html>
