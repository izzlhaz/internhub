<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/management_helpers.php';
include __DIR__ . '/../includes/header.php';

ensure_management_schema($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['student_response'])) {
    $applicationId = (int) $_POST['application_id'];
    $response = $_POST['student_response'];

    if (!in_array($response, ['Accepted', 'Rejected'], true)) {
        $error = 'Invalid offer response.';
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, j.company_id
            FROM application a
            JOIN jobposting j ON j.job_id = a.job_id
            WHERE a.application_id = ? AND a.student_id = ?
        ");
        $stmt->execute([$applicationId, $student_id]);
        $offer = $stmt->fetch();

        if (!$offer || normalise_application_status($offer['application_status']) !== 'Accepted') {
            $error = 'Only received offers can be accepted or rejected.';
        } elseif ($response === 'Accepted') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total
                FROM application
                WHERE student_id = ? AND application_student_response = 'Accepted' AND application_id <> ?
            ");
            $stmt->execute([$student_id, $applicationId]);
            $alreadyAccepted = (int) $stmt->fetch()['total'] > 0;

            if ($alreadyAccepted) {
                $error = 'You can only accept one company offer.';
            } else {
                $stmt = $pdo->prepare("UPDATE application SET application_student_response = 'Accepted' WHERE application_id = ? AND student_id = ?");
                $stmt->execute([$applicationId, $student_id]);
                $message = 'Offer accepted successfully.';
            }
        } else {
            $stmt = $pdo->prepare("UPDATE application SET application_student_response = 'Rejected' WHERE application_id = ? AND student_id = ?");
            $stmt->execute([$applicationId, $student_id]);
            $message = 'Offer rejected.';
        }
    }
}

// Get applications
$stmt = $pdo->prepare("
    SELECT a.*, j.job_title, j.job_location, c.company_name, c.company_id
    FROM application a
    JOIN jobposting j ON a.job_id = j.job_id
    JOIN company c ON j.company_id = c.company_id
    WHERE a.student_id = ?
    ORDER BY a.application_applied_date DESC
");
$stmt->execute([$student_id]);
$applications = $stmt->fetchAll();

// Status colors
$status_colors = [
    'Pending' => 'warning',
    'Shortlisted' => 'info',
    'Interview' => 'primary',
    'Accepted' => 'success',
    'Rejected' => 'danger'
];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM application WHERE student_id = ? AND application_student_response = 'Accepted'");
$stmt->execute([$student_id]);
$hasAcceptedOffer = (int) $stmt->fetch()['total'] > 0;
?>

<h2>My Job Applications</h2>

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if(count($applications) > 0): ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Company</th>
                    <th>Position</th>
                    <th>Location</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Offer Decision</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($applications as $app): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                        <td><?php echo htmlspecialchars($app['job_location'] ?: 'Not specified'); ?></td>
                        <td><?php echo date('d M Y, h:i A', strtotime($app['application_applied_date'])); ?></td>
                        <td>
                            <?php $displayStatus = normalise_application_status($app['application_status']); ?>
                            <span class="badge bg-<?php echo $status_colors[$displayStatus] ?? 'secondary'; ?>">
                                <?php echo htmlspecialchars($displayStatus); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($app['application_student_response'] ?? 'Pending'); ?></td>
                        <td>
                            <?php if ($displayStatus === 'Accepted' && ($app['application_student_response'] ?? 'Pending') === 'Pending'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="application_id" value="<?php echo (int) $app['application_id']; ?>">
                                    <input type="hidden" name="student_response" value="Accepted">
                                    <button type="submit" class="btn btn-sm btn-success" <?php echo $hasAcceptedOffer ? 'disabled' : ''; ?>>Accept</button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="application_id" value="<?php echo (int) $app['application_id']; ?>">
                                    <input type="hidden" name="student_response" value="Rejected">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                            <?php elseif (($app['application_student_response'] ?? 'Pending') === 'Accepted'): ?>
                                <span class="badge bg-success">Offer Accepted</span>
                            <?php elseif (($app['application_student_response'] ?? 'Pending') === 'Rejected'): ?>
                                <span class="badge bg-danger">Offer Rejected</span>
                            <?php else: ?>
                                <span class="text-muted">No offer yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        You haven't applied for any jobs yet. <a href="jobs.php">Browse available internships</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
