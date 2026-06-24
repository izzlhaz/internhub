<?php

function normalize_ic_password(string $ic): string
{
    return trim($ic);
}

function ensure_password_policy_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $userColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM user')->fetchAll() as $column) {
        $userColumns[$column['Field']] = true;
    }
    if (empty($userColumns['user_must_change_password'])) {
        $pdo->exec("ALTER TABLE user ADD user_must_change_password tinyint(1) NOT NULL DEFAULT 0 AFTER user_role");
    }
    if (empty($userColumns['user_ic_password_initialized'])) {
        $pdo->exec("ALTER TABLE user ADD user_ic_password_initialized tinyint(1) NOT NULL DEFAULT 0 AFTER user_must_change_password");
    }

    $lecturerColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM lecturer')->fetchAll() as $column) {
        $lecturerColumns[$column['Field']] = true;
    }
    if (empty($lecturerColumns['lecturer_ic'])) {
        $pdo->exec("ALTER TABLE lecturer ADD lecturer_ic varchar(20) DEFAULT NULL AFTER lecturer_gender");
    }

    $students = $pdo->query("
        SELECT u.user_id, s.student_ic
        FROM user u
        JOIN student s ON s.user_id = u.user_id
        WHERE u.user_role = 'student' AND u.user_ic_password_initialized = 0
    ")->fetchAll();
    $update = $pdo->prepare('UPDATE user SET user_password = ?, user_must_change_password = 1, user_ic_password_initialized = 1 WHERE user_id = ?');
    foreach ($students as $student) {
        $ic = normalize_ic_password((string) $student['student_ic']);
        if ($ic !== '') {
            $update->execute([password_hash($ic, PASSWORD_DEFAULT), $student['user_id']]);
        }
    }

    $lecturers = $pdo->query("
        SELECT u.user_id, l.lecturer_ic
        FROM user u
        JOIN lecturer l ON l.user_id = u.user_id
        WHERE u.user_role = 'lecturer'
          AND u.user_ic_password_initialized = 0
          AND l.lecturer_ic IS NOT NULL
          AND l.lecturer_ic <> ''
    ")->fetchAll();
    foreach ($lecturers as $lecturer) {
        $update->execute([
            password_hash(normalize_ic_password($lecturer['lecturer_ic']), PASSWORD_DEFAULT),
            $lecturer['user_id'],
        ]);
    }

    $ready = true;
}

function enforce_required_password_change(PDO $pdo): void
{
    if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['student', 'lecturer'], true)) {
        return;
    }

    $stmt = $pdo->prepare('SELECT user_must_change_password FROM user WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $mustChange = (bool) $stmt->fetchColumn();
    $_SESSION['must_change_password'] = $mustChange;
    if (!$mustChange) {
        return;
    }

    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($currentPage, ['change_password.php', 'logout.php'], true)) {
        return;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = preg_replace('#/(student|lecturer|coordinator|company)/[^/]+$#', '', $script);
    if ($base === $script) {
        $base = rtrim(dirname($script), '/');
    }
    header('Location: ' . $base . '/change_password.php');
    exit();
}
