<?php
$coordinatorPage = basename($_SERVER['PHP_SELF'] ?? '');
$coordinatorSections = [
    'dashboard.php' => 'dashboard',
    'profile.php' => 'profile',
    'students.php' => 'students',
    'add_student.php' => 'students',
    'import_students.php' => 'students',
    'edit_student.php' => 'students',
    'lecturers.php' => 'lecturers',
    'add_lecturer.php' => 'lecturers',
    'import_lecturers.php' => 'lecturers',
    'edit_lecturer.php' => 'lecturers',
    'companies.php' => 'companies',
    'assign_internship.php' => 'assign',
    'evaluations.php' => 'evaluations',
    'evaluation_result.php' => 'evaluations',
    'export_reports.php' => 'reports',
];
$coordinatorActiveSection = $coordinatorSections[$coordinatorPage] ?? '';
$coordinatorNavGroups = [
    'Overview' => [
        ['dashboard', 'dashboard.php', 'fa-gauge-high', 'Dashboard'],
    ],
    'Manage' => [
        ['students', 'students.php', 'fa-user-graduate', 'Students'],
        ['lecturers', 'lecturers.php', 'fa-chalkboard-user', 'Lecturers'],
        ['companies', 'companies.php', 'fa-building', 'Companies'],
        ['assign', 'assign_internship.php', 'fa-user-check', 'Assign Supervisor'],
    ],
    'Programme' => [
        ['evaluations', 'evaluations.php', 'fa-chart-column', 'Evaluations'],
        ['reports', 'export_reports.php', 'fa-file-export', 'Export Reports'],
    ],
    'Account' => [
        ['profile', 'profile.php', 'fa-user', 'Profile'],
    ],
];
$coordinatorName = $_SESSION['user_name'] ?? 'Coordinator';
$coordinatorInitial = strtoupper(substr($coordinatorName, 0, 1));
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/coordinator-sidebar.css?v=20260615-v2">
<aside class="col-md-2 coordinator-sidebar no-print">
    <div class="cs-brand">
        <img class="cs-logo" src="../assets/img/logo-light.png" alt="TISSA &middot; Universiti Utara Malaysia">
        <span class="cs-wordmark">Intern<span>Hub</span></span>
    </div>

    <nav class="cs-nav" aria-label="Coordinator navigation">
        <?php foreach ($coordinatorNavGroups as $groupLabel => $items): ?>
            <div class="cs-group-label"><?php echo htmlspecialchars($groupLabel); ?></div>
            <?php foreach ($items as [$section, $href, $icon, $label]): ?>
                <a href="<?php echo $href; ?>"<?php echo $coordinatorActiveSection === $section ? ' class="active" aria-current="page"' : ''; ?>>
                    <i class="fas <?php echo $icon; ?>" aria-hidden="true"></i>
                    <span><?php echo $label; ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="cs-user">
        <span class="cs-user-avatar"><?php echo htmlspecialchars($coordinatorInitial); ?></span>
        <span class="cs-user-meta">
            <strong><?php echo htmlspecialchars($coordinatorName); ?></strong>
            <small>Coordinator</small>
        </span>
        <a class="cs-logout" href="../logout.php" title="Log out" aria-label="Log out">
            <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
        </a>
    </div>
</aside>
