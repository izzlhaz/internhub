<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/student_upload_helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    http_response_code(403);
    exit('Access denied.');
}

ensure_student_upload_schema($pdo);
$resumeId = (int) ($_GET['id'] ?? 0);
$role = $_SESSION['user_role'];
$params = [$resumeId];
$accessSql = '';

if ($role === 'student') {
    $accessSql = ' AND r.student_id = ?';
    $params[] = (int) ($_SESSION['student_id'] ?? 0);
} elseif ($role === 'company') {
    $accessSql = ' AND EXISTS (SELECT 1 FROM application a JOIN jobposting j ON j.job_id = a.job_id WHERE a.resume_id = r.resume_id AND j.company_id = ?)';
    $params[] = (int) ($_SESSION['company_id'] ?? 0);
} elseif (!in_array($role, ['coordinator', 'lecturer'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$stmt = $pdo->prepare('SELECT r.resume_file_name, r.resume_file_type, r.resume_file_size, r.resume_file_data FROM resume r WHERE r.resume_id = ? AND r.resume_file_data IS NOT NULL' . $accessSql);
$stmt->execute($params);
$resume = $stmt->fetch();

if (!$resume) {
    http_response_code(404);
    exit('Resume not found.');
}

$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $resume['resume_file_name'] ?: 'resume.pdf');
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($resume['resume_file_data']));
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
echo $resume['resume_file_data'];
