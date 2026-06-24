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

$message = '';
$error = '';
$programmes = ['Bachelor of Accounting (Hons)', 'Bachelor of Accounting (Information Systems) (Hons)'];
$departments = ['Taxation', 'Management Accounting', 'Financial Accounting', 'Auditing', 'Accounting Information System'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $ic = trim($_POST['ic']);
        $duplicate = $pdo->prepare('SELECT lecturer_id FROM lecturer WHERE lecturer_ic = ?');
        $duplicate->execute([$ic]);
        if ($duplicate->fetch()) {
            throw new RuntimeException('This IC number is already assigned to another lecturer.');
        }
        $hash = password_hash(normalize_ic_password($ic), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO user (user_name, user_email, user_password, user_status, user_created_by, user_role, user_must_change_password, user_ic_password_initialized) VALUES (?, ?, ?, 'Active', ?, 'lecturer', 1, 1)");
        $stmt->execute([trim($_POST['name']), trim($_POST['email']), $hash, $_SESSION['user_id']]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO lecturer (
                lecturer_staff_id, user_id, lecturer_programme, lecturer_name, lecturer_gender,
                lecturer_email, lecturer_phone, lecturer_office_phone, lecturer_department, lecturer_ic,
                lecturer_role, lecturer_max_student
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($_POST['staff_id']),
            $user_id,
            $_POST['programme'],
            trim($_POST['name']),
            $_POST['gender'],
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['office_phone']),
            trim($_POST['department']),
            $ic,
            'Supervisor',
            (int) $_POST['max_student'],
        ]);
        $pdo->commit();
        $message = 'Lecturer added successfully.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Failed to add lecturer: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Lecturer - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style>    <link rel="stylesheet" href="">
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body><div class="container-fluid"><div class="row">
<?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
<div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<h2>Add Lecturer</h2>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body"><form method="POST" class="row g-3">
<div class="col-md-3"><label class="form-label">Lecturer ID</label><input name="staff_id" class="form-control" required></div>
<div class="col-md-5"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Department</label><select name="department" class="form-select" required><?php foreach ($departments as $department): ?><option value="<?php echo htmlspecialchars($department); ?>"><?php echo htmlspecialchars($department); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Programme</label><select name="programme" class="form-control"><?php foreach ($programmes as $programme): ?><option value="<?php echo htmlspecialchars($programme); ?>"><?php echo htmlspecialchars(programme_short_label($programme)); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Role</label><input class="form-control" value="Supervisor" readonly><input type="hidden" name="role" value="Supervisor"></div>
<div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-control"><option>Male</option><option>Female</option><option>Other</option></select></div>
<div class="col-md-3"><label class="form-label">IC Number</label><input name="ic" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="col-md-3"><label class="form-label">Office Phone</label><input name="office_phone" class="form-control"></div>
<div class="col-md-3"><label class="form-label">Max Student</label><input type="number" name="max_student" class="form-control" min="1" value="10" required></div>
<div class="col-md-4"><label class="form-label">Initial Password</label><input class="form-control" value="Uses the lecturer's IC number" readonly><small class="text-muted">The lecturer must change it immediately after login.</small></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Add Lecturer</button><a href="lecturers.php" class="btn btn-secondary">Back</a></div>
</form></div></div>
</div></div></div></body></html>
