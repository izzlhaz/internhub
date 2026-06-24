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

ensure_management_schema($pdo);

$studentId = (int) ($_GET['id'] ?? $_POST['student_id'] ?? 0);
$message = '';
$error = '';
$courses = ['Bachelor of Accounting (Hons)', 'Bachelor of Accounting (Information Systems) (Hons)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $intake = strtoupper(trim($_POST['intake'] ?? ''));
    if (!preg_match('/^A\d{2}[12]$/', $intake)) {
        $error = 'Intake must use format like A251 or A252.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE student
                SET student_matric_no = ?, student_name = ?, student_course = ?, student_intake = ?,
                    student_phone = ?, student_address = ?, student_email = ?, student_gender = ?,
                    student_ic = ?, student_status = ?
                WHERE student_id = ?
            ");
            $stmt->execute([
                strtoupper(trim($_POST['matric_no'] ?? '')),
                strtoupper(trim($_POST['name'] ?? '')),
                $_POST['course'] ?? $courses[0],
                $intake,
                trim($_POST['phone'] ?? ''),
                strtoupper(trim($_POST['address'] ?? '')),
                trim($_POST['email'] ?? ''),
                $_POST['gender'] ?? 'Female',
                trim($_POST['ic'] ?? ''),
                $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active',
                $studentId,
            ]);

            $stmt = $pdo->prepare("
                UPDATE user u
                JOIN student s ON s.user_id = u.user_id
                SET u.user_name = s.student_name, u.user_email = s.student_email, u.user_status = s.student_status
                WHERE s.student_id = ?
            ");
            $stmt->execute([$studentId]);
            $pdo->commit();
            $message = 'Student information updated successfully.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to update student: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM student WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: students.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Student - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style>
<link rel="stylesheet" href="../assets/css/theme.css"></head>
<body><div class="container-fluid"><div class="row">
<?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
<div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<h2>Edit Student</h2>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body"><form method="POST" class="row g-3">
<input type="hidden" name="student_id" value="<?php echo (int) $student['student_id']; ?>">
<div class="col-md-3"><label class="form-label">Student ID / Matric No</label><input name="matric_no" class="form-control" required value="<?php echo htmlspecialchars($student['student_matric_no']); ?>"></div>
<div class="col-md-5"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?php echo htmlspecialchars($student['student_name']); ?>"></div>
<div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($student['student_email']); ?>"></div>
<div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?php echo htmlspecialchars($student['student_phone'] ?? ''); ?>"></div>
<div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-control"><option value="Active" <?php echo $student['student_status'] === 'Active' ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?php echo $student['student_status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
<div class="col-md-4"><label class="form-label">Course</label><select name="course" class="form-control"><?php foreach ($courses as $course): ?><option value="<?php echo htmlspecialchars($course); ?>" <?php echo $student['student_course'] === $course ? 'selected' : ''; ?>><?php echo htmlspecialchars(programme_short_label($course)); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Intake</label><input name="intake" class="form-control" required value="<?php echo htmlspecialchars($student['student_intake']); ?>"></div>
<div class="col-md-4"><label class="form-label">IC</label><input name="ic" class="form-control" required value="<?php echo htmlspecialchars($student['student_ic']); ?>"></div>
<div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-control"><?php foreach (['Male','Female','Other'] as $gender): ?><option value="<?php echo $gender; ?>" <?php echo $student['student_gender'] === $gender ? 'selected' : ''; ?>><?php echo $gender; ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Student Address</label><textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($student['student_address'] ?? ''); ?></textarea></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Save Changes</button> <a href="students.php" class="btn btn-secondary">Back</a></div>
</form></div></div>
</div></div></div></body></html>
