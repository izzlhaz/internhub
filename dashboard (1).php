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
$courses = ['Bachelor of Accounting (Hons)', 'Bachelor of Accounting (Information Systems) (Hons)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $intake = strtoupper(trim($_POST['intake']));
    if (!preg_match('/^A\d{2}[12]$/', $intake)) {
        $error = 'Intake must use format like A251 or A252.';
    } else {
        try {
            $pdo->beginTransaction();
            $ic = trim($_POST['ic']);
            $hash = password_hash(normalize_ic_password($ic), PASSWORD_DEFAULT);
            $userStatus = $_POST['status'] === 'Active' ? 'Active' : 'Inactive';
            $stmt = $pdo->prepare("INSERT INTO user (user_name, user_email, user_password, user_status, user_created_by, user_role, user_must_change_password, user_ic_password_initialized) VALUES (?, ?, ?, ?, ?, 'student', 1, 1)");
            $studentName = strtoupper(trim($_POST['name']));
            $stmt->execute([$studentName, trim($_POST['email']), $hash, $userStatus, $_SESSION['user_id']]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO student (
                    student_matric_no, student_name, user_id, student_course, student_intake,
                    student_phone, student_email, student_gender, student_ic, student_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                strtoupper(trim($_POST['matric_no'])),
                $studentName,
                $user_id,
                $_POST['course'],
                $intake,
                trim($_POST['phone']),
                trim($_POST['email']),
                $_POST['gender'],
                $ic,
                $_POST['status'],
            ]);
            $pdo->commit();
            $message = 'Student added successfully.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to add student: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Student - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style>    <link rel="stylesheet" href="">
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body><div class="container-fluid"><div class="row">
<?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
<div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<h2>Add Student</h2>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body"><form method="POST" class="row g-3">
<div class="col-md-3"><label class="form-label">Student ID / Matric No</label><input name="matric_no" class="form-control" required></div>
<div class="col-md-5"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-control"><option value="Active">Active</option><option value="Inactive">Not Active</option></select></div>
<div class="col-md-4"><label class="form-label">Course</label><select name="course" class="form-control"><?php foreach ($courses as $course): ?><option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars(programme_short_label($course)); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Intake</label><input name="intake" class="form-control" placeholder="A251" required><small class="text-muted">A251 = 2025 semester 1</small></div>
<div class="col-md-4"><label class="form-label">IC</label><input name="ic" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-control"><option>Male</option><option>Female</option><option>Other</option></select></div>
<div class="col-md-4"><label class="form-label">Initial Password</label><input class="form-control" value="Uses the student's IC number" readonly><small class="text-muted">The student must change it immediately after login.</small></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Add Student</button><a href="students.php" class="btn btn-secondary">Back</a></div>
</form></div></div>
</div></div></div></body></html>
