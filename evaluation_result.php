<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}

$lecturerId = (int) ($_GET['id'] ?? $_POST['lecturer_id'] ?? 0);
$message = '';
$error = '';
$departments = ['Taxation', 'Management Accounting', 'Financial Accounting', 'Auditing', 'Accounting Information System'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $ic = trim($_POST['lecturer_ic']);
        $duplicate = $pdo->prepare('SELECT lecturer_id FROM lecturer WHERE lecturer_ic = ? AND lecturer_id <> ?');
        $duplicate->execute([$ic, $lecturerId]);
        if ($duplicate->fetch()) {
            throw new RuntimeException('This IC number is already assigned to another lecturer.');
        }
        $stmt = $pdo->prepare("
            UPDATE lecturer l
            JOIN user u ON u.user_id = l.user_id
            SET l.lecturer_name = ?, l.lecturer_staff_id = ?, l.lecturer_programme = ?,
                l.lecturer_gender = ?, l.lecturer_ic = ?, l.lecturer_email = ?, l.lecturer_phone = ?,
                l.lecturer_office_phone = ?, l.lecturer_department = ?,
                l.lecturer_role = ?, l.lecturer_max_student = ?,
                u.user_name = ?, u.user_email = ?
            WHERE l.lecturer_id = ?
        ");
        $stmt->execute([
            $_POST['lecturer_name'],
            $_POST['lecturer_staff_id'],
            $_POST['lecturer_programme'],
            $_POST['lecturer_gender'],
            $ic,
            $_POST['lecturer_email'],
            $_POST['lecturer_phone'],
            $_POST['lecturer_office_phone'],
            $_POST['lecturer_department'],
            'Supervisor',
            (int) $_POST['lecturer_max_student'],
            $_POST['lecturer_name'],
            $_POST['lecturer_email'],
            $lecturerId,
        ]);
        $initialize = $pdo->prepare("
            UPDATE user u
            JOIN lecturer l ON l.user_id = u.user_id
            SET u.user_password = ?, u.user_must_change_password = 1, u.user_ic_password_initialized = 1
            WHERE l.lecturer_id = ? AND u.user_ic_password_initialized = 0
        ");
        $initialize->execute([password_hash(normalize_ic_password($ic), PASSWORD_DEFAULT), $lecturerId]);
        $pdo->commit();
        $message = 'Lecturer profile updated.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Unable to update lecturer: ' . $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT * FROM lecturer WHERE lecturer_id = ?");
$stmt->execute([$lecturerId]);
$lecturer = $stmt->fetch();

if (!$lecturer) {
    header("Location: lecturers.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Lecturer - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style><link rel="stylesheet" href="../assets/css/theme.css"></head>
<body><div class="container-fluid"><div class="row">
<?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
<div class="col-md-10 content"><div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div><h2>Edit Lecturer Profile</h2>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body"><form method="POST" class="row g-3">
<input type="hidden" name="lecturer_id" value="<?php echo (int) $lecturer['lecturer_id']; ?>">
<div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="lecturer_name" required value="<?php echo htmlspecialchars($lecturer['lecturer_name']); ?>"></div>
<div class="col-md-4"><label class="form-label">Staff ID</label><input class="form-control" name="lecturer_staff_id" value="<?php echo htmlspecialchars($lecturer['lecturer_staff_id']); ?>"></div>
<div class="col-md-4"><label class="form-label">Programme</label><select class="form-select" name="lecturer_programme"><option value="Bachelor of Accounting (Information Systems) (Hons)" <?php echo $lecturer['lecturer_programme'] === 'Bachelor of Accounting (Information Systems) (Hons)' ? 'selected' : ''; ?>>B.Acct (IS)</option><option value="Bachelor of Accounting (Hons)" <?php echo $lecturer['lecturer_programme'] === 'Bachelor of Accounting (Hons)' ? 'selected' : ''; ?>>B.Acct</option></select></div>
<div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="lecturer_gender"><option <?php echo $lecturer['lecturer_gender'] === 'Male' ? 'selected' : ''; ?>>Male</option><option <?php echo $lecturer['lecturer_gender'] === 'Female' ? 'selected' : ''; ?>>Female</option><option <?php echo $lecturer['lecturer_gender'] === 'Other' ? 'selected' : ''; ?>>Other</option></select></div>
<div class="col-md-3"><label class="form-label">IC Number</label><input class="form-control" name="lecturer_ic" required value="<?php echo htmlspecialchars($lecturer['lecturer_ic'] ?? ''); ?>"><small class="text-muted">For accounts not yet initialized, this becomes the initial password.</small></div>
<div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" name="lecturer_email" required value="<?php echo htmlspecialchars($lecturer['lecturer_email']); ?>"></div>
<div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="lecturer_phone" value="<?php echo htmlspecialchars($lecturer['lecturer_phone']); ?>"></div>
<div class="col-md-3"><label class="form-label">Office Phone</label><input class="form-control" name="lecturer_office_phone" value="<?php echo htmlspecialchars($lecturer['lecturer_office_phone']); ?>"></div>
<div class="col-md-4"><label class="form-label">Department</label><select class="form-select" name="lecturer_department" required><?php foreach ($departments as $department): ?><option value="<?php echo htmlspecialchars($department); ?>" <?php echo $lecturer['lecturer_department'] === $department ? 'selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Role</label><input class="form-control" value="Supervisor" readonly><input type="hidden" name="lecturer_role" value="Supervisor"></div>
<div class="col-md-4"><label class="form-label">Supervision Capacity</label><input type="number" min="1" class="form-control" name="lecturer_max_student" value="<?php echo (int) $lecturer['lecturer_max_student']; ?>"></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Save Lecturer</button> <a class="btn btn-secondary" href="lecturers.php">Back</a></div>
</form></div></div></div></div></div></body></html>
