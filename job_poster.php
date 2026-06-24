<?php
if (PHP_SAPI === 'cli') {
    ini_set('session.save_path', sys_get_temp_dir());
}

require_once __DIR__ . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This maintenance script can only be run from the command line.');
}

$lecturers = $pdo->query("
    SELECT lecturer_id, lecturer_staff_id, user_id
    FROM lecturer
    WHERE lecturer_staff_id LIKE 'TISSA%'
    ORDER BY lecturer_staff_id
")->fetchAll();

$updateLecturer = $pdo->prepare('UPDATE lecturer SET lecturer_ic = ? WHERE lecturer_id = ?');
$updateUser = $pdo->prepare("
    UPDATE user
    SET user_password = ?, user_must_change_password = 1, user_ic_password_initialized = 1
    WHERE user_id = ? AND user_role = 'lecturer'
");

$pdo->beginTransaction();
try {
    foreach ($lecturers as $lecturer) {
        $sequence = (int) substr($lecturer['lecturer_staff_id'], 5);
        if ($sequence < 1) {
            throw new RuntimeException('Invalid lecturer staff ID: ' . $lecturer['lecturer_staff_id']);
        }

        $dummyIc = '90010102' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        $updateLecturer->execute([$dummyIc, $lecturer['lecturer_id']]);
        $updateUser->execute([
            password_hash(normalize_ic_password($dummyIc), PASSWORD_DEFAULT),
            $lecturer['user_id'],
        ]);
    }

    $pdo->commit();
    echo count($lecturers) . " lecturer dummy IC numbers and passwords updated.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
