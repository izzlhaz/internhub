<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/student_import_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header('Location: ../login.php');
    exit();
}

if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['MATRIC NO', 'STUDENT NAME', 'EMAIL', 'COURSE', 'BATCH', 'PHONE', 'GENDER', 'IC', 'STATUS']);
    fputcsv($output, ['A25AC001', 'NUR AINA', 'student@example.com', 'B.Acct (IS)', '2025', '0123456789', 'FEMALE', '010101010101', 'ACTIVE']);
    fclose($output);
    exit();
}

$message = '';
$errors = [];
$credentials = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_file'])) {
    $file = $_FILES['student_file'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a valid Excel or CSV file.';
    } elseif (!in_array($extension, ['xlsx', 'csv'], true)) {
        $errors[] = 'Only .xlsx and .csv files are accepted.';
    } else {
        try {
            $rows = read_student_import_rows($file['tmp_name'], $extension);
            if (count($rows) < 2) {
                throw new RuntimeException('The file has no student data rows.');
            }
            $map = student_import_header_map(array_shift($rows));
            $required = ['matric_no', 'name', 'email', 'course', 'batch', 'gender', 'ic', 'status'];
            $missing = array_diff($required, array_keys($map));
            if ($missing) {
                throw new RuntimeException('Missing required columns: ' . strtoupper(implode(', ', $missing)) . '.');
            }

            foreach ($rows as $rowIndex => $row) {
                $line = $rowIndex + 2;
                $value = function (string $field) use ($row, $map): string {
                    return trim((string) ($row[$map[$field] ?? -1] ?? ''));
                };
                if (implode('', $row) === '') {
                    continue;
                }

                $matric = strtoupper($value('matric_no'));
                $name = strtoupper($value('name'));
                $email = strtolower($value('email'));
                $course = normalize_student_course($value('course'));
                $batch = strtoupper($value('batch'));
                $phone = $value('phone');
                $genderInput = strtoupper($value('gender'));
                $gender = ['MALE' => 'Male', 'FEMALE' => 'Female', 'OTHER' => 'Other'][$genderInput] ?? null;
                $ic = strtoupper($value('ic'));
                $status = strtoupper($value('status')) === 'INACTIVE' ? 'Inactive' : 'Active';

                if (!$matric || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$course || !$batch || !$gender || !$ic) {
                    $errors[] = "Row {$line}: invalid or incomplete student information.";
                    continue;
                }

                $duplicate = $pdo->prepare('SELECT user_id FROM user WHERE user_email = ? UNION SELECT user_id FROM student WHERE student_matric_no = ? OR student_email = ? OR student_ic = ?');
                $duplicate->execute([$email, $matric, $email, $ic]);
                if ($duplicate->fetch()) {
                    $errors[] = "Row {$line}: matric, email or IC already exists.";
                    continue;
                }

                $temporaryPassword = normalize_ic_password($ic);
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO user (user_name, user_email, user_password, user_status, user_created_by, user_role, user_must_change_password, user_ic_password_initialized) VALUES (?, ?, ?, ?, ?, 'student', 1, 1)");
                    $stmt->execute([$name, $email, password_hash($temporaryPassword, PASSWORD_DEFAULT), $status, $_SESSION['user_id']]);
                    $userId = (int) $pdo->lastInsertId();
                    $stmt = $pdo->prepare("
                        INSERT INTO student (student_matric_no, student_name, user_id, student_course, student_intake, student_phone, student_email, student_gender, student_ic, student_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$matric, $name, $userId, $course, $batch, $phone, $email, $gender, $ic, $status]);
                    $pdo->commit();
                    $credentials[] = ['matric' => $matric, 'name' => $name, 'email' => $email, 'password' => $temporaryPassword];
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = "Row {$line}: unable to import this student.";
                }
            }
            if ($credentials) {
                $message = count($credentials) . ' student(s) imported successfully. Their initial password is their IC number and must be changed after login.';
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import Students - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/theme.css">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style></head>
<body><div class="container-fluid"><div class="row"><?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?><div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<div class="d-flex justify-content-between align-items-center mb-3"><h2>Import Students</h2><a href="students.php" class="btn btn-secondary">Back to Students</a></div>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Some rows were not imported:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Upload Excel Student List</h5></div><div class="card-body">
<p>Accepted formats: <strong>.xlsx</strong> or <strong>.csv</strong>. Required columns are Matric No, Student Name, Email, Course, Batch, Gender, IC and Status. Phone is optional.</p>
<p class="text-muted">Course accepts B.Acct, B.Acct (IS), or the full programme name. Names, matric numbers and batches are automatically converted to uppercase. Email remains lowercase.</p>
<a class="btn btn-outline-primary mb-3" href="import_students.php?template=1"><i class="fas fa-download me-1"></i> Download Template</a>
<form method="POST" enctype="multipart/form-data" class="row g-3 align-items-end"><div class="col-md-9"><label class="form-label">Student File</label><input type="file" name="student_file" class="form-control" accept=".xlsx,.csv" required></div><div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-file-import me-1"></i> Import Students</button></div></form>
</div></div>
<?php if ($credentials): ?><div class="card"><div class="card-header"><h5 class="mb-0">Imported Login Details</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Matric No</th><th>Student</th><th>Email</th><th>Initial Password</th></tr></thead><tbody><?php foreach ($credentials as $credential): ?><tr><td><?php echo htmlspecialchars($credential['matric']); ?></td><td><?php echo htmlspecialchars($credential['name']); ?></td><td class="text-lowercase"><?php echo htmlspecialchars($credential['email']); ?></td><td>Student IC number</td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>
</div></div></div></body></html>
