<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/management_helpers.php';
require_once __DIR__ . '/../includes/student_upload_helpers.php';
require_once __DIR__ . '/../includes/photo_uploader.php';

ensure_management_schema($pdo);
ensure_student_upload_schema($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = uppercase_profile_value($_POST['phone'] ?? '');
    $address = uppercase_profile_value($_POST['address'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $photo = $_FILES['formal_photo'] ?? null;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE student SET student_phone = ?, student_address = ?, student_gender = ? WHERE student_id = ?');
        $stmt->execute([$phone, $address, $gender, $student_id]);

        $photo = decode_cropped_image($_POST['formal_photo_cropped'] ?? null);
        if ($photo) {
            $stmt = $pdo->prepare('UPDATE student SET student_photo_name = ?, student_photo_type = ?, student_photo_data = ?, student_photo_uploaded_at = NOW() WHERE student_id = ?');
            $stmt->bindValue(1, $photo['name']);
            $stmt->bindValue(2, $photo['type']);
            $stmt->bindValue(3, $photo['data'], PDO::PARAM_LOB);
            $stmt->bindValue(4, (int) $student_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        $pdo->commit();
        $message = 'PROFILE UPDATED SUCCESSFULLY.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    try {
        $stmt = $pdo->prepare('SELECT user_password FROM user WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['user_password'])) {
            $error = 'CURRENT PASSWORD IS INCORRECT.';
        } elseif (strlen($new) < 8) {
            $error = 'NEW PASSWORD MUST BE AT LEAST 8 CHARACTERS.';
        } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $error = 'NEW PASSWORD MUST INCLUDE UPPERCASE, LOWERCASE, AND A NUMBER.';
        } elseif ($new !== $confirm) {
            $error = 'NEW PASSWORDS DO NOT MATCH.';
        } elseif (password_verify($new, $user['user_password'])) {
            $error = 'NEW PASSWORD MUST BE DIFFERENT FROM CURRENT PASSWORD.';
        } else {
            $stmt = $pdo->prepare('UPDATE user SET user_password = ? WHERE user_id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $message = 'PASSWORD CHANGED SUCCESSFULLY.';
        }
    } catch (Exception $e) {
        $error = 'PASSWORD UPDATE FAILED.';
    }
}

$stmt = $pdo->prepare('SELECT s.*, u.user_name, u.user_email FROM student s JOIN user u ON s.user_id = u.user_id WHERE s.student_id = ?');
$stmt->execute([$student_id]);
$student = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
photo_uploader_assets();
?>

<style>
    .profile-uppercase input:not([type="email"]),
    .profile-uppercase textarea,
    .profile-uppercase select { text-transform: uppercase; }
    .formal-photo {
        width: 180px;
        height: 220px;
        object-fit: cover;
        border-radius: 12px;
        border: 3px solid #f0d7dc;
        background: #f8f9fa;
    }
</style>

<div class="row profile-uppercase">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h4>MY PROFILE</h4></div>
            <div class="card-body">
                <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label">FORMAL PICTURE</label>
                        <?php photo_uploader_field([
                            'name' => 'formal_photo_cropped',
                            'aspect' => '7 / 10',
                            'outW' => 700,
                            'outH' => 1000,
                            'frameW' => 182,
                            'ratioLabel' => '7:10 passport',
                            'current' => !empty($student['student_photo_data']) ? '../student_photo.php?id=' . (int) $student_id . '&v=' . urlencode($student['student_photo_uploaded_at'] ?? '') : '',
                            'help' => 'Use a clear, front-facing formal photo. Drag to position and zoom to fit the frame.',
                        ]); ?>
                    </div>

                    <div class="mb-3"><label>STUDENT ID / MATRIC NUMBER</label><input type="text" class="form-control" value="<?php echo htmlspecialchars(uppercase_profile_value($student['student_matric_no'])); ?>" readonly></div>
                    <div class="mb-3"><label>FULL NAME</label><input type="text" class="form-control" value="<?php echo htmlspecialchars(uppercase_profile_value($student['student_name'])); ?>" readonly></div>
                    <div class="mb-3"><label>EMAIL</label><input type="email" class="form-control text-lowercase" value="<?php echo htmlspecialchars($student['user_email']); ?>" readonly></div>
                    <div class="mb-3"><label>IC NUMBER</label><input type="text" class="form-control" value="<?php echo htmlspecialchars(uppercase_profile_value($student['student_ic'])); ?>" readonly></div>
                    <div class="mb-3"><label>COURSE</label><input type="text" class="form-control" value="<?php echo htmlspecialchars(programme_short_label($student['student_course'])); ?>" readonly></div>
                    <div class="mb-3"><label>SESSION</label><input type="text" class="form-control" value="<?php echo htmlspecialchars(uppercase_profile_value($student['student_intake'])); ?>" readonly></div>
                    <div class="mb-3"><label>PHONE NUMBER</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars(uppercase_profile_value($student['student_phone'] ?? '')); ?>"></div>
                    <div class="mb-3"><label>STUDENT ADDRESS</label><textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars(uppercase_profile_value($student['student_address'] ?? '')); ?></textarea></div>
                    <div class="mb-3"><label>GENDER</label><select name="gender" class="form-control"><option value="Male" <?php echo $student['student_gender'] === 'Male' ? 'selected' : ''; ?>>MALE</option><option value="Female" <?php echo $student['student_gender'] === 'Female' ? 'selected' : ''; ?>>FEMALE</option><option value="Other" <?php echo $student['student_gender'] === 'Other' ? 'selected' : ''; ?>>OTHER</option></select></div>
                    <button type="submit" name="update_profile" class="btn btn-primary">UPDATE PROFILE</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h4>CHANGE PASSWORD</h4></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3"><label>CURRENT PASSWORD</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
                    <div class="mb-3"><label>NEW PASSWORD</label><input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password"></div>
                    <div class="mb-3"><label>CONFIRM NEW PASSWORD</label><input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password"></div>
                    <button type="submit" name="change_password" class="btn btn-warning w-100">CHANGE PASSWORD</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
