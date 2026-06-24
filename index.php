<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/student_upload_helpers.php';

ensure_student_upload_schema($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_resume'])) {
    $file = $_FILES['resume_pdf'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE);
    } else {
        $data = file_get_contents($file['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $isPdf = $mime === 'application/pdf' && strncmp($data, '%PDF-', 5) === 0;

        if (!$isPdf) {
            $error = 'Only a valid PDF resume can be uploaded.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT resume_id FROM resume WHERE student_id = ? FOR UPDATE');
                $stmt->execute([$student_id]);
                $resumeId = $stmt->fetchColumn();
                $fileName = basename($file['name']);

                if ($resumeId) {
                    $stmt = $pdo->prepare("UPDATE resume SET resume_file_name = ?, resume_file_type = 'application/pdf', resume_file_size = ?, resume_file_data = ?, resume_uploaded_at = NOW() WHERE resume_id = ?");
                    $stmt->bindValue(1, $fileName);
                    $stmt->bindValue(2, (int) $file['size'], PDO::PARAM_INT);
                    $stmt->bindValue(3, $data, PDO::PARAM_LOB);
                    $stmt->bindValue(4, (int) $resumeId, PDO::PARAM_INT);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO resume (student_id, resume_file_name, resume_file_type, resume_file_size, resume_file_data, resume_uploaded_at) VALUES (?, ?, 'application/pdf', ?, ?, NOW())");
                    $stmt->bindValue(1, (int) $student_id, PDO::PARAM_INT);
                    $stmt->bindValue(2, $fileName);
                    $stmt->bindValue(3, (int) $file['size'], PDO::PARAM_INT);
                    $stmt->bindValue(4, $data, PDO::PARAM_LOB);
                }
                $stmt->execute();
                $pdo->commit();
                $message = 'Your PDF resume was uploaded successfully.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Resume upload failed. Please try again.';
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT resume_id, resume_file_name, resume_file_size, resume_uploaded_at, resume_file_data IS NOT NULL AS has_file FROM resume WHERE student_id = ?');
$stmt->execute([$student_id]);
$resume = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<h2>Resume Upload</h2>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h4 class="mb-0">Upload Your PDF Resume</h4></div>
            <div class="card-body p-4">
                <p class="text-muted">Upload your own completed resume in PDF format. Uploading a new file replaces your previous resume.</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label" for="resume_pdf">PDF Resume</label>
                        <input type="file" id="resume_pdf" name="resume_pdf" class="form-control" accept="application/pdf,.pdf" required>
                    </div>
                    <button type="submit" name="upload_resume" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload Resume
                    </button>
                </form>

                <?php if ($resume && $resume['has_file']): ?>
                    <hr class="my-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h5 class="mb-1">Current Resume</h5>
                            <div><?php echo htmlspecialchars($resume['resume_file_name']); ?></div>
                            <small class="text-muted">
                                <?php echo number_format(((int) $resume['resume_file_size']) / 1048576, 2); ?> MB
                                <?php if ($resume['resume_uploaded_at']): ?> | Uploaded <?php echo date('d M Y, h:i A', strtotime($resume['resume_uploaded_at'])); ?><?php endif; ?>
                            </small>
                        </div>
                        <a class="btn btn-outline-primary" href="../download_resume.php?id=<?php echo (int) $resume['resume_id']; ?>" target="_blank" rel="noopener">
                            <i class="fas fa-file-pdf me-2"></i>View PDF
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
