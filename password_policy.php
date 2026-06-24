<?php
require_once __DIR__ . '/student_upload_helpers.php';
ensure_student_upload_schema($pdo);
$studentHeaderPhoto = false;
if (!empty($student_id)) {
    $studentHeaderStmt = $pdo->prepare('SELECT student_photo_data IS NOT NULL AS has_photo FROM student WHERE student_id = ?');
    $studentHeaderStmt->execute([$student_id]);
    $studentHeaderPhoto = (bool) $studentHeaderStmt->fetchColumn();
}
$studentDisplayName = uppercase_profile_value($_SESSION['user_name'] ?? 'STUDENT');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InternHub - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">


</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0 sidebar">
            <div class="sb-brand">
                <img class="sb-logo" src="../assets/img/logo-light.png" alt="TISSA &middot; Universiti Utara Malaysia">
                <span class="sb-wordmark">Intern<span>Hub</span></span>
            </div>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> My Profile
            </a>
            <a href="resume.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'resume.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Resume
            </a>
            <a href="jobs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'jobs.php' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i> Job Listings
            </a>
            <a href="applications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'applications.php' ? 'active' : ''; ?>">
                <i class="fas fa-paper-plane"></i> My Applications
            </a>
            <a href="internship.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'internship.php' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> My Internship
            </a>
            <a href="logbook.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'logbook.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Logbook
            </a>
            <a href="../logout.php" class="text-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10 content">
            <div class="d-flex align-items-center justify-content-end gap-2 text-muted small mb-2">
                <?php if ($studentHeaderPhoto): ?>
                    <img class="student-header-photo" src="../student_photo.php?id=<?php echo (int) $student_id; ?>" alt="PROFILE PICTURE">
                <?php else: ?>
                    <span class="student-header-photo d-inline-flex align-items-center justify-content-center"><i class="fas fa-user"></i></span>
                <?php endif; ?>
                <span>LOGGED IN AS <?php echo htmlspecialchars($studentDisplayName); ?></span>
            </div>
