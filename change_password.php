<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
include __DIR__ . '/../includes/header.php';

// Get active internship
$stmt = $pdo->prepare("
    SELECT i.*, c.company_name, c.company_email, c.company_phone, l.lecturer_name, l.lecturer_email
    FROM internship i
    JOIN company c ON i.company_id = c.company_id
    JOIN lecturer l ON i.lecturer_id = l.lecturer_id
    WHERE i.student_id = ? AND i.internship_status = 'Active'
");
$stmt->execute([$student_id]);
$internship = $stmt->fetch();

// Get completed internships
$stmt2 = $pdo->prepare("
    SELECT i.*, c.company_name
    FROM internship i
    JOIN company c ON i.company_id = c.company_id
    WHERE i.student_id = ? AND i.internship_status = 'Completed'
    ORDER BY i.internship_end_date DESC
");
$stmt2->execute([$student_id]);
$past_internships = $stmt2->fetchAll();

?>

<h2>My Internship</h2>

<?php if($internship): ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">Active Internship</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Company Information</h5>
                    <p>
                        <strong>Name:</strong> <?php echo htmlspecialchars($internship['company_name']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($internship['company_email']); ?><br>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($internship['company_phone'] ?: 'Not provided'); ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <h5>Supervisor Information</h5>
                    <p>
                        <strong>Lecturer:</strong> <?php echo htmlspecialchars($internship['lecturer_name']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($internship['lecturer_email']); ?>
                    </p>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Internship Period</h5>
                    <p>
                        <strong>Start Date:</strong> <?php echo date('d/m/y', strtotime($internship['internship_start_date'])); ?><br>
                        <strong>End Date:</strong> <?php echo date('d/m/y', strtotime($internship['internship_end_date'])); ?><br>
                        <strong>Status:</strong> <span class="badge bg-success"><?php echo $internship['internship_status']; ?></span>
                    </p>
                </div>
                <div class="col-md-6">
                    <h5>Actions</h5>
                    <a href="logbook.php" class="btn btn-primary">Submit Logbook</a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        You don't have an active internship. 
        <a href="jobs.php" class="alert-link">Apply for internships now</a>
    </div>
<?php endif; ?>

<?php if(count($past_internships) > 0): ?>
    <div class="card">
        <div class="card-header">
            <h4>Past Internships</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($past_internships as $past): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($past['company_name']); ?></td>
                            <td><?php echo date('d/m/y', strtotime($past['internship_start_date'])); ?></td>
                            <td><?php echo date('d/m/y', strtotime($past['internship_end_date'])); ?></td>
                            <td><span class="badge bg-secondary">Completed</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
