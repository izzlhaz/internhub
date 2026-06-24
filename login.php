<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/management_helpers.php';
require_once __DIR__ . '/includes/notification_helpers.php';

ensure_management_schema($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $company_registration_no = trim($_POST['company_registration_no'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');
    $company_type = $_POST['company_type'] ?? '';
    $company_registration_date = $_POST['company_registration_date'] ?? '';
    $company_registration_expiry_date = $_POST['company_registration_expiry_date'] ?? '';
    $company_business_status = $_POST['company_business_status'] ?? 'Active';
    $company_owner_name = trim($_POST['company_owner_name'] ?? '');
    $company_email = trim($_POST['company_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $company_phone = trim($_POST['company_phone'] ?? '');

    if ($company_name === '' || $company_registration_no === '' || $company_address === '' || $company_type === '' || $company_owner_name === '' || $company_email === '' || $password === '' || $company_phone === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password confirmation does not match.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM user WHERE user_email = ? UNION SELECT user_id FROM company WHERE company_email = ?");
            $stmt->execute([$company_email, $company_email]);

            if ($stmt->fetch()) {
                $error = 'This email is already registered.';
            } else {
                $pdo->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO user (user_name, user_email, user_password, user_status, user_created_by, user_role) VALUES (?, ?, ?, 'Active', NULL, 'company')");
                $stmt->execute([$company_name, $company_email, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO company (
                        user_id, company_name, company_email, company_registration_no, company_phone,
                        company_address, company_type, company_registration_date,
                        company_registration_expiry_date, company_business_status, company_owner_name,
                        company_approval_status, company_contact_person
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
                ");
                $stmt->execute([
                    $user_id,
                    $company_name,
                    $company_email,
                    $company_registration_no,
                    $company_phone,
                    $company_address,
                    $company_type,
                    $company_registration_date ?: null,
                    $company_registration_expiry_date ?: null,
                    $company_business_status,
                    $company_owner_name,
                    $company_owner_name,
                ]);
                $companyId = (int) $pdo->lastInsertId();

                $pdo->commit();
                $notificationSent = notify_coordinators_of_company_registration($pdo, [
                    'name' => $company_name,
                    'registration_no' => $company_registration_no,
                    'email' => $company_email,
                    'phone' => $company_phone,
                ]);
                $message = $notificationSent
                    ? 'Registration successful. The coordinator has been notified. You can now log in as Company to view your approval status.'
                    : 'Registration successful. Your company is now waiting for coordinator approval. Email notification could not be sent by this server.';
                if (!$notificationSent) {
                    error_log('InternHub could not send the company registration email notification for company ID ' . $companyId);
                }
                $_POST = [];
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Unable to complete registration: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InternHub - Company Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Company Registration</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($message); ?>
                                <a href="login.php" class="alert-link">Go to login</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Registration Number <span class="text-danger">*</span></label>
                                <input type="text" name="company_registration_no" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_registration_no'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Main Business Address <span class="text-danger">*</span></label>
                                <input type="text" name="company_address" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_address'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Type <span class="text-danger">*</span></label>
                                <select name="company_type" class="form-select" required>
                                    <option value="">Select business type</option>
                                    <?php echo select_options(industry_types(), $_POST['company_type'] ?? ''); ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="company_business_status" class="form-select" required>
                                    <?php echo select_options(business_statuses(), $_POST['company_business_status'] ?? 'Active'); ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registration Date</label>
                                <input type="date" name="company_registration_date" class="form-control" value="<?php echo htmlspecialchars($_POST['company_registration_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registration Expiry Date</label>
                                <input type="date" name="company_registration_expiry_date" class="form-control" value="<?php echo htmlspecialchars($_POST['company_registration_expiry_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Owner Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_owner_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_owner_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Email <span class="text-danger">*</span></label>
                                <input type="email" name="company_email" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="company_phone" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </button>
                                <a href="login.php" class="btn btn-outline-secondary">Back to Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
