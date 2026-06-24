<?php
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['lecturer_id']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(403);
    exit('Access denied.');
}

$internshipId = (int) ($_GET['internship_id'] ?? 0);
$stmt = $pdo->prepare('SELECT s.student_course FROM internship i JOIN student s ON s.student_id = i.student_id WHERE i.internship_id = ? AND i.lecturer_id = ?');
$stmt->execute([$internshipId, (int) $_SESSION['lecturer_id']]);
$course = $stmt->fetchColumn();
if (!$course) {
    http_response_code(404);
    exit('Guideline not found.');
}

$isAccountingIS = stripos($course, 'Information Systems') !== false;
$file = __DIR__ . '/assets/guidelines/' . ($isAccountingIS ? 'ais-practicum-guideline.pdf' : 'accounting-practicum-guideline.pdf');
$displayName = $isAccountingIS ? 'B.Acct (IS) Practicum Report Writing Guideline.pdf' : 'B.Acct Practicum Report Writing Guideline.pdf';
if (!is_file($file)) {
    http_response_code(404);
    exit('Guideline file is unavailable.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $displayName . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
