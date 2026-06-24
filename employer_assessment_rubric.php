<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_student'])) {
    $studentId = (int) $_POST['student_id'];
    $status = $_POST['status'] === 'Active' ? 'Active' : 'Inactive';
    $stmt = $pdo->prepare("UPDATE student s JOIN user u ON u.user_id = s.user_id SET s.student_status = ?, u.user_status = ? WHERE s.student_id = ?");
    $stmt->execute([$status, $status, $studentId]);
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$params = [];
$where = [];
if ($search !== '') {
    $where[] = "(s.student_name LIKE ? OR s.student_matric_no LIKE ? OR s.student_email LIKE ? OR c.company_name LIKE ?)";
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($statusFilter !== '') {
    $where[] = "s.student_status = ?";
    $params[] = $statusFilter;
}

$sql = "
    SELECT s.*,
           CASE
               WHEN i.internship_id IS NOT NULL THEN 'Intern'
               WHEN EXISTS (SELECT 1 FROM application a WHERE a.student_id = s.student_id AND a.application_status IN ('Pending','Review','Accept')) THEN 'Pending'
               ELSE 'Not Intern'
           END AS dashboard_status,
           i.internship_start_date, i.internship_end_date, c.company_name
    FROM student s
    LEFT JOIN internship i ON i.student_id = s.student_id AND i.internship_status = 'Active'
    LEFT JOIN company c ON c.company_id = i.company_id
";
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare($sql . $whereSql . " ORDER BY UPPER(s.student_name) ASC");
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style>    <link rel="stylesheet" href="">
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body><div class="container-fluid"><div class="row">
<?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
<div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<div class="d-flex justify-content-between align-items-center mb-3"><h2>Student Dashboard List</h2><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="import_students.php">Import Excel</a><a class="btn btn-primary" href="add_student.php">Add Student</a></div></div>
<form class="card card-body mb-3" method="GET"><div class="row g-2 align-items-end">
<div class="col-md-6"><label class="form-label">Search Students</label><input class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, matric, email or company"></div>
<div class="col-md-3"><label class="form-label">Account Status</label><select class="form-select" name="status"><option value="">All</option><option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?php echo $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
<div class="col-md-3"><button class="btn btn-primary" type="submit">Filter</button> <a class="btn btn-secondary" href="students.php">Reset</a></div>
</div></form>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-hover ih-table">
<thead><tr><th>Student</th><th>Contact</th><th>Status</th><th>Course</th><th>Intake</th><th>IC</th><th>Internship</th><th>Action</th></tr></thead>
<tbody><?php foreach ($students as $student): ?><tr>
<td><strong><?php echo htmlspecialchars(strtoupper($student['student_name'])); ?></strong><br><small><?php echo htmlspecialchars(strtoupper($student['student_matric_no'])); ?></small></td>
<td><?php echo htmlspecialchars($student['student_email']); ?><br><small><?php echo htmlspecialchars($student['student_phone'] ?: '-'); ?></small></td>
<td><span class="badge bg-<?php echo $student['dashboard_status'] === 'Intern' ? 'success' : ($student['dashboard_status'] === 'Pending' ? 'warning text-dark' : 'secondary'); ?>"><?php echo htmlspecialchars(strtoupper($student['dashboard_status'])); ?></span><br><small><?php echo htmlspecialchars(strtoupper($student['student_status'])); ?></small></td>
<td><?php echo htmlspecialchars(programme_short_label($student['student_course'])); ?></td>
<td><?php echo htmlspecialchars(strtoupper($student['student_intake'])); ?></td>
<td><?php echo htmlspecialchars($student['student_ic']); ?></td>
<td class="ih-wrap"><?php echo $student['company_name'] ? htmlspecialchars($student['company_name']) . '<br><small>' . htmlspecialchars($student['internship_start_date']) . ' to ' . htmlspecialchars($student['internship_end_date']) . '</small>' : '-'; ?></td>
<td><div class="d-flex gap-2 flex-wrap"><a class="btn btn-sm btn-outline-primary" href="edit_student.php?id=<?php echo (int) $student['student_id']; ?>">Edit</a><form method="POST" onsubmit="return confirm('Are you sure you want to <?php echo $student['student_status'] === 'Active' ? 'deactivate' : 'activate'; ?> this student account?');"><input type="hidden" name="student_id" value="<?php echo (int) $student['student_id']; ?>"><input type="hidden" name="status" value="<?php echo $student['student_status'] === 'Active' ? 'Inactive' : 'Active'; ?>"><button class="btn btn-sm btn-outline-<?php echo $student['student_status'] === 'Active' ? 'danger' : 'success'; ?>" name="toggle_student" type="submit"><?php echo $student['student_status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button></form></div></td>
</tr><?php endforeach; ?></tbody>
</table></div></div></div>
</div></div></div></body></html>
