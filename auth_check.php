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

$pdo->exec("UPDATE lecturer SET lecturer_role = 'Supervisor' WHERE lecturer_role IS NULL OR lecturer_role <> 'Supervisor'");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_lecturer'])) {
    $lecturerId = (int) $_POST['lecturer_id'];
    $status = $_POST['status'] === 'Active' ? 'Active' : 'Inactive';
    $stmt = $pdo->prepare("UPDATE lecturer l JOIN user u ON u.user_id = l.user_id SET u.user_status = ? WHERE l.lecturer_id = ?");
    $stmt->execute([$status, $lecturerId]);
}

$stmt = $pdo->query("
    SELECT l.*, u.user_status,
           COUNT(i.internship_id) AS assigned_total,
           SUM(CASE WHEN le.le_id IS NOT NULL THEN 1 ELSE 0 END) AS evaluated_total
    FROM lecturer l
    JOIN user u ON u.user_id = l.user_id
    LEFT JOIN internship i ON i.lecturer_id = l.lecturer_id
    LEFT JOIN lecturerevaluation le ON le.internship_id = i.internship_id
    GROUP BY l.lecturer_id
    ORDER BY l.lecturer_name
");
$lecturers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lecturers - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style>    <link rel="stylesheet" href="">
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body><div class="container-fluid"><div class="row">
<?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
<div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<div class="d-flex justify-content-between align-items-center mb-3"><h2>Lecturer Evaluation Dashboard</h2><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="import_lecturers.php"><i class="fas fa-file-import me-1"></i> Import Lecturers</a><a class="btn btn-primary" href="add_lecturer.php">Add Lecturer</a></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-hover ih-table">
<thead><tr><th>Lecturer</th><th>Department</th><th>Programme</th><th>Contact</th><th>Role</th><th>Capacity</th><th>Evaluated</th><th>Action</th></tr></thead>
<tbody><?php foreach ($lecturers as $lecturer): $pct = $lecturer['assigned_total'] ? round(($lecturer['evaluated_total'] / $lecturer['assigned_total']) * 100, 1) : 0; ?><tr>
<td><strong><?php echo htmlspecialchars($lecturer['lecturer_name']); ?></strong><br><small><?php echo htmlspecialchars($lecturer['lecturer_staff_id'] ?? '-'); ?> | <?php echo htmlspecialchars($lecturer['user_status']); ?></small><br><?php if (!empty($lecturer['lecturer_ic'])): ?><small>IC: <?php echo htmlspecialchars($lecturer['lecturer_ic']); ?></small><?php else: ?><span class="badge bg-warning text-dark">IC required</span><?php endif; ?></td>
<td><?php echo htmlspecialchars($lecturer['lecturer_department'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars(programme_short_label($lecturer['lecturer_programme'])); ?></td>
<td class="ih-wrap"><?php echo htmlspecialchars($lecturer['lecturer_email']); ?><br><small><?php echo htmlspecialchars($lecturer['lecturer_phone'] ?: '-'); ?> | Office: <?php echo htmlspecialchars($lecturer['lecturer_office_phone'] ?: '-'); ?></small></td>
<td><?php echo htmlspecialchars($lecturer['lecturer_role'] ?? '-'); ?></td>
<td><?php echo (int) $lecturer['assigned_total']; ?> / <?php echo (int) $lecturer['lecturer_max_student']; ?></td>
<td><div class="progress" style="height: 22px;"><div class="progress-bar" style="width: <?php echo $pct; ?>%;"><?php echo $pct; ?>%</div></div><small><?php echo (int) $lecturer['evaluated_total']; ?> of <?php echo (int) $lecturer['assigned_total']; ?> students</small></td>
<td><a class="btn btn-sm btn-outline-primary mb-1" href="edit_lecturer.php?id=<?php echo (int) $lecturer['lecturer_id']; ?>">Edit</a><form method="POST"><input type="hidden" name="lecturer_id" value="<?php echo (int) $lecturer['lecturer_id']; ?>"><input type="hidden" name="status" value="<?php echo $lecturer['user_status'] === 'Active' ? 'Inactive' : 'Active'; ?>"><button class="btn btn-sm btn-outline-<?php echo $lecturer['user_status'] === 'Active' ? 'danger' : 'success'; ?>" name="toggle_lecturer" type="submit"><?php echo $lecturer['user_status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button></form></td>
</tr><?php endforeach; ?></tbody>
</table></div></div></div>
</div></div></div></body></html>
