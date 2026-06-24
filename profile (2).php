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
    header('Content-Disposition: attachment; filename="lecturer_import_template.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['LECTURER ID', 'NAME', 'EMAIL', 'DEPARTMENT', 'PROGRAMME', 'GENDER', 'IC', 'PHONE', 'OFFICE PHONE', 'MAX STUDENT', 'STATUS']);
    fputcsv($output, ['TISSA104', 'Dr. Example Lecturer', 'lecturer@example.com', 'Accounting Information System', 'AIS', 'FEMALE', '900101020104', '0123456789', '+604 928 7000', '10', 'ACTIVE']);
    fclose($output);
    exit();
}

$message = '';
$errors = [];
$credentials = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lecturer_file'])) {
    $file = $_FILES['lecturer_file'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a valid Excel or CSV file.';
    } elseif (!in_array($extension, ['xlsx', 'csv'], true)) {
        $errors[] = 'Only .xlsx and .csv files are accepted.';
    } else {
        try {
            $rows = read_student_import_rows($file['tmp_name'], $extension);
            if (count($rows) < 2) {
                throw new RuntimeException('The file has no lecturer data rows.');
            }
            $map = lecturer_import_header_map(array_shift($rows));
            $required = ['staff_id', 'name', 'email', 'department', 'programme', 'gender', 'ic', 'max_student', 'status'];
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

                $staffId = strtoupper($value('staff_id'));
                $name = $value('name');
                $email = strtolower($value('email'));
                $department = normalize_lecturer_department($value('department'));
                $programme = normalize_lecturer_programme($value('programme'));
                $genderInput = strtoupper($value('gender'));
                $gender = ['MALE' => 'Male', 'FEMALE' => 'Female', 'OTHER' => 'Other'][$genderInput] ?? null;
                $ic = strtoupper($value('ic'));
                $phone = $value('phone');
                $officePhone = $value('office_phone');
                $maxStudent = filter_var($value('max_student'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $status = strtoupper($value('status')) === 'INACTIVE' ? 'Inactive' : 'Active';

                if (!$staffId || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$department || !$programme || !$gender || !$ic || $maxStudent === false) {
                    $errors[] = "Row {$line}: invalid or incomplete lecturer information.";
                    continue;
                }

                $duplicate = $pdo->prepare('SELECT user_id FROM user WHERE user_email = ? UNION SELECT user_id FROM lecturer WHERE lecturer_staff_id = ? OR lecturer_email = ? OR lecturer_ic = ?');
                $duplicate->execute([$email, $staffId, $email, $ic]);
                if ($duplicate->fetch()) {
                    $errors[] = "Row {$line}: lecturer ID, email or IC already exists.";
                    continue;
                }

                try {
                    $pdo->beginTransaction();
                    $temporaryPassword = normalize_ic_password($ic);
                    $stmt = $pdo->prepare("INSERT INTO user (user_name, user_email, user_password, user_status, user_created_by, user_role, user_must_change_password, user_ic_password_initialized) VALUES (?, ?, ?, ?, ?, 'lecturer', 1, 1)");
                    $stmt->execute([$name, $email, password_hash($temporaryPassword, PASSWORD_DEFAULT), $status, $_SESSION['user_id']]);
                    $userId = (int) $pdo->lastInsertId();
                    $stmt = $pdo->prepare("
                        INSERT INTO lecturer (
                            lecturer_staff_id, user_id, lecturer_programme, lecturer_name, lecturer_gender,
                            lecturer_ic, lecturer_email, lecturer_phone, lecturer_office_phone,
                            lecturer_department, lecturer_role, lecturer_max_student
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Supervisor', ?)
                    ");
                    $stmt->execute([$staffId, $userId, $programme, $name, $gender, $ic, $email, $phone, $officePhone, $department, $maxStudent]);
                    $pdo->commit();
                    $credentials[] = ['staff_id' => $staffId, 'name' => $name, 'email' => $email];
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = "Row {$line}: unable to import this lecturer.";
                }
            }
            if ($credentials) {
                $message = count($credentials) . ' lecturer(s) imported successfully. Their initial password is their IC number and must be changed after login.';
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import Lecturers - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/theme.css">
<style>.sidebar{min-height:100vh;background:#2c3e50}.sidebar a{color:white;text-decoration:none;padding:12px 20px;display:block}.sidebar a.active,.sidebar a:hover{background:#9b59b6}.content{padding:20px}</style></head>
<body><div class="container-fluid"><div class="row"><?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?><div class="col-md-10 content">
<div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
<div class="d-flex justify-content-between align-items-center mb-3"><h2>Import Lecturers</h2><a href="lecturers.php" class="btn btn-secondary">Back to Lecturers</a></div>
<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Some rows were not imported:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Upload Excel Lecturer List</h5></div><div class="card-body">
<p>Accepted formats: <strong>.xlsx</strong> or <strong>.csv</strong>. Required columns are Lecturer ID, Name, Email, Department, Programme, Gender, IC, Max Student and Status. Phone fields are optional.</p>
<p class="text-muted">Programme accepts AIS, PURE, B.Acct (IS), B.Acct or the full programme name. Supported departments are Accounting Information System, Taxation, Financial Accounting, Management Accounting and Audit.</p>
<a class="btn btn-outline-primary mb-3" href="import_lecturers.php?template=1"><i class="fas fa-download me-1"></i> Download Template</a>
<form method="POST" enctype="multipart/form-data" class="row g-3 align-items-end"><div class="col-md-9"><label class="form-label">Lecturer File</label><input type="file" name="lecturer_file" class="form-control" accept=".xlsx,.csv" required></div><div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-file-import me-1"></i> Import Lecturers</button></div></form>
</div></div>
<?php if ($credentials): ?><div class="card"><div class="card-header"><h5 class="mb-0">Imported Login Details</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Lecturer ID</th><th>Lecturer</th><th>Email</th><th>Initial Password</th></tr></thead><tbody><?php foreach ($credentials as $credential): ?><tr><td><?php echo htmlspecialchars($credential['staff_id']); ?></td><td><?php echo htmlspecialchars($credential['name']); ?></td><td class="text-lowercase"><?php echo htmlspecialchars($credential['email']); ?></td><td>Lecturer IC number</td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>
</div></div></div></body></html>
