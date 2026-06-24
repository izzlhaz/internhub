<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$internshipId = (int) ($_GET['internship_id'] ?? 0);
header('Location: evaluate.php?internship_id=' . $internshipId);
exit();
