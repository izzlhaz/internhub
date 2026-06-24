<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

$job_id = $_GET['job_id'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM internship WHERE student_id = ? AND internship_status IN ('Accepted','Active')");
$stmt->execute([$student_id]);
if ((int) $stmt->fetch()['total'] > 0) {
    $_SESSION['error'] = 'You are already enrolled in an internship. Job applications are closed while your internship is active.';
    header("Location: jobs.php");
    exit();
}

// Check if job exists
$stmt = $pdo->prepare("SELECT * FROM jobposting WHERE job_id = ? AND job_status = 'Active'");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: jobs.php");
    exit();
}

// Check if already applied
$stmt = $pdo->prepare("SELECT * FROM application WHERE student_id = ? AND job_id = ?");
$stmt->execute([$student_id, $job_id]);
if ($stmt->fetch()) {
    $_SESSION['error'] = "You have already applied for this position";
    header("Location: jobs.php");
    exit();
}

// Check if a PDF resume has been uploaded.
$stmt = $pdo->prepare("SELECT * FROM resume WHERE student_id = ? AND resume_file_data IS NOT NULL");
$stmt->execute([$student_id]);
$resume = $stmt->fetch();

if (!$resume) {
    $_SESSION['error'] = "Please upload your PDF resume before applying";
    header("Location: resume.php");
    exit();
}

// Apply
$stmt = $pdo->prepare("
    INSERT INTO application (student_id, job_id, resume_id, application_status, application_applied_date)
    VALUES (?, ?, ?, 'Pending', NOW())
");
$stmt->execute([$student_id, $job_id, $resume['resume_id']]);

$_SESSION['success'] = "Application submitted successfully!";
header("Location: applications.php");
exit();
?>
