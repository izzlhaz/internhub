<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/management_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

ensure_management_schema($pdo);

$company_id = $_SESSION['company_id'];
require_company_approval($pdo, $company_id);
$message = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM company WHERE company_id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $company_allowance_range = $_POST['company_allowance_range'] ?? '';
        $company_capacity_ais = (int) ($_POST['company_capacity_ais'] ?? 0);
        $company_capacity_accounting = (int) ($_POST['company_capacity_accounting'] ?? 0);

        try {
            $stmt = $pdo->prepare("
                UPDATE company SET
                    company_allowance_range = ?,
                    company_capacity_ais = ?,
                    company_capacity_accounting = ?
                WHERE company_id = ?
            ");
            $stmt->execute([$company_allowance_range, $company_capacity_ais, $company_capacity_accounting, $company_id]);
            $message = 'Profile updated successfully.';

            $stmt = $pdo->prepare("SELECT * FROM company WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $company = $stmt->fetch();
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT user_password FROM user WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();

        if (!password_verify($current, $user_data['user_password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
            $stmt->execute([$hashed, $_SESSION['user_id']]);
            $message = 'Password changed successfully.';
        }
    }
}

function readonly_value($value)
{
    return htmlspecialchars($value ?: '-');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - InternHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0 sidebar">
            <div class="sb-brand">
                <img class="sb-logo" src="../assets/img/logo-light.png" alt="TISSA &middot; Universiti Utara Malaysia">
                <span class="sb-wordmark">Intern<span>Hub</span></span>
            </div>
            <hr class="bg-white">
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php" class="active">Company Profile</a>
            <a href="jobs.php">Manage Jobs</a>
            <a href="applications.php">Applications</a>
            <a href="interns.php">My Interns</a>
            <a href="../logout.php" class="text-danger">Logout</a>
        </div>

        <div class="col-md-10 content">
            <div class="text-end text-muted small mb-2">Logged in as <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Company'); ?></div>
            <h2>Company Profile</h2>
            <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header"><h4 class="mb-0">Registration Details</h4></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Business Name</label><input class="form-control" value="<?php echo readonly_value($company['company_name'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Business Registration Number</label><input class="form-control" value="<?php echo readonly_value($company['company_registration_no'] ?? ''); ?>" readonly></div>
                                <div class="col-12"><label class="form-label">Main Business Address</label><textarea class="form-control" rows="2" readonly><?php echo readonly_value($company['company_address'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><label class="form-label">Business Type</label><input class="form-control" value="<?php echo readonly_value($company['company_type'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Status</label><input class="form-control" value="<?php echo readonly_value($company['company_business_status'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Registration Date</label><input class="form-control" value="<?php echo readonly_value($company['company_registration_date'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Registration Expiry Date</label><input class="form-control" value="<?php echo readonly_value($company['company_registration_expiry_date'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Owner Name</label><input class="form-control" value="<?php echo readonly_value($company['company_owner_name'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Business Email</label><input class="form-control" value="<?php echo readonly_value($company['company_email'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Business Phone Number</label><input class="form-control" value="<?php echo readonly_value($company['company_phone'] ?? ''); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Coordinator Approval</label><input class="form-control" value="<?php echo readonly_value($company['company_approval_status'] ?? 'Pending'); ?>" readonly></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h4 class="mb-0">Internship Offering</h4></div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Internship Allowance Range</label>
                                    <select name="company_allowance_range" class="form-select">
                                        <?php echo select_options(allowance_ranges(), $company['company_allowance_range'] ?? ''); ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Open Slots for B.Acct (IS) Students</label>
                                    <input type="number" name="company_capacity_ais" class="form-control" min="0" value="<?php echo (int) ($company['company_capacity_ais'] ?? 0); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Open Slots for Accounting Students</label>
                                    <input type="number" name="company_capacity_accounting" class="form-control" min="0" value="<?php echo (int) ($company['company_capacity_accounting'] ?? 0); ?>">
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h4 class="mb-0">Change Password</h4></div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                                <button type="submit" name="change_password" class="btn btn-warning w-100">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
