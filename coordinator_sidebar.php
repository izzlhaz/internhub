<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'coordinator') {
    header("Location: ../login.php");
    exit();
}

$coordinator_id = $_SESSION['coordinator_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $stmt = $pdo->prepare("SELECT user_password FROM user WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['user_password'])) {
                $error = 'Current password is incorrect.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New password and confirm password do not match.';
            } elseif (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                $error = 'Password must be at least 8 characters and include uppercase, lowercase and number.';
            } elseif (password_verify($newPassword, $user['user_password'])) {
                $error = 'New password must be different from current password.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE user SET user_password = ? WHERE user_id = ?")->execute([$hash, $_SESSION['user_id']]);
                $message = 'Password changed successfully.';
            }
        } else {
            $stmt = $pdo->prepare("
                UPDATE coordinator
                SET coordinator_name = ?, coordinator_gender = ?, coordinator_email = ?, coordinator_phone = ?
                WHERE coordinator_id = ?
            ");
            $stmt->execute([
                trim($_POST['name']),
                $_POST['gender'],
                trim($_POST['email']),
                trim($_POST['phone']),
                $coordinator_id,
            ]);
            $pdo->prepare("UPDATE user SET user_name = ?, user_email = ? WHERE user_id = ?")
                ->execute([trim($_POST['name']), trim($_POST['email']), $_SESSION['user_id']]);
            $_SESSION['user_name'] = trim($_POST['name']);
            $_SESSION['user_email'] = trim($_POST['email']);
            $message = 'Coordinator profile updated successfully.';
        }
    } catch (Exception $e) {
        $error = 'Update failed: ' . $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT * FROM coordinator WHERE coordinator_id = ?");
$stmt->execute([$coordinator_id]);
$coordinator = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Profile - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        :root {
            --maroon: #4b0712;
            --maroon-soft: #8d183b;
            --gold: #d2aa45;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #260308 0%, var(--maroon) 100%);
            padding: 18px 14px;
        }
        .brand-card {
            background: #fff;
            border-radius: 8px;
            padding: 10px;
            margin: 0 auto 10px;
            max-width: 190px;
            text-align: center;
        }
        .brand-card img {
            max-width: 100%;
            height: 46px;
            object-fit: contain;
        }
        .brand-title {
            color: #fff;
            text-align: center;
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 14px;
        }
        .brand-title::after {
            content: "";
            display: block;
            width: 42px;
            height: 3px;
            background: var(--gold);
            margin: 7px auto 0;
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
            display: block;
            padding: 10px 14px;
            border-radius: 6px;
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .sidebar a.active,
        .sidebar a:hover {
            background: rgba(141, 24, 59, .9);
            border-left: 4px solid var(--gold);
        }
        @media (max-width: 900px) {
            .sidebar { min-height: auto; }
        }
    </style>
</head>
<body>
<div class="container-fluid"><div class="row">
    <?php require __DIR__ . '/../includes/coordinator_sidebar.php'; ?>
    <div class="col-md-10 content">
        <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinator'); ?></div>
        <h2>Coordinator Profile</h2>
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="row g-4">
            <div class="col-lg-8"><div class="card"><div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-4"><label class="form-label">Coordinator ID</label><input class="form-control" value="<?php echo htmlspecialchars($coordinator['coordinator_id']); ?>" readonly></div>
                    <div class="col-md-8"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($coordinator['coordinator_name']); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-control"><?php foreach (['Male','Female','Other'] as $gender): ?><option value="<?php echo $gender; ?>" <?php echo $coordinator['coordinator_gender'] === $gender ? 'selected' : ''; ?>><?php echo $gender; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($coordinator['coordinator_email']); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($coordinator['coordinator_phone'] ?? ''); ?>"></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Save Profile</button></div>
                </form>
            </div></div></div>
            <div class="col-lg-4"><div class="card"><div class="card-header"><h5 class="mb-0">Change Password</h5></div><div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="change_password" value="1">
                    <div class="col-12"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                    <div class="col-12"><button class="btn btn-warning w-100" type="submit">Change Password</button></div>
                </form>
            </div></div></div>
        </div>
    </div>
</div></div>
</body>
</html>
