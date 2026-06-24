<?php
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['student', 'lecturer'], true)) {
    header('Location: login.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_SESSION['user_role'];
    $table = $role === 'student' ? 'student' : 'lecturer';
    $icColumn = $role === 'student' ? 'student_ic' : 'lecturer_ic';
    $stmt = $pdo->prepare("SELECT {$icColumn} FROM {$table} WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $ic = normalize_ic_password((string) $stmt->fetchColumn());

    if (strlen($newPassword) < 8) {
        $error = 'New password must contain at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } elseif ($ic !== '' && hash_equals($ic, $newPassword)) {
        $error = 'Your new password cannot be the same as your IC number.';
    } else {
        $stmt = $pdo->prepare('UPDATE user SET user_password = ?, user_must_change_password = 0 WHERE user_id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        $_SESSION['must_change_password'] = false;
        $destination = $role === 'student' ? 'student/dashboard.php' : 'lecturer/lecturer_dashboard.php';
        header('Location: ' . $destination);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password - InternHub</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/theme.css">
<style>body{min-height:100vh;display:grid;place-items:center;padding:1.5rem}.password-card{width:min(100%,480px)}.brand-mark{width:72px;height:4px;background:#d7b52b;border-radius:99px;margin:.75rem auto 1.5rem}</style></head>
<body><main class="password-card"><div class="card"><div class="card-body p-4 p-md-5"><div class="text-center"><h2 class="mb-0">Set Your New Password</h2><div class="brand-mark"></div></div>
<div class="alert alert-warning">For security, your IC number is only your initial password. You must create a new password before entering InternHub.</div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="POST"><div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="8" required autocomplete="new-password"><div class="form-text">Use at least 8 characters and do not reuse your IC number.</div></div>
<div class="mb-4"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password"></div>
<button class="btn btn-primary w-100" type="submit">Change Password and Continue</button><a class="btn btn-link w-100 mt-2" href="logout.php">Logout</a></form>
</div></div></main></body></html>
