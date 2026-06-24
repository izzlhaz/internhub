<?php
// includes/auth_check.php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    // Get student_id from user_id
    require_once __DIR__ . '/../config/database.php';
    $stmt = $pdo->prepare("SELECT student_id FROM student WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();
    if ($student) {
        $_SESSION['student_id'] = $student['student_id'];
        $student_id = $student['student_id'];
    }
}
?>