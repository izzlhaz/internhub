<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
include __DIR__ . '/../includes/header.php';

$message = '';
$error = '';

// Check if student has active internship
$stmt = $pdo->prepare("SELECT internship_id FROM internship WHERE student_id = ? AND internship_status = 'Active'");
$stmt->execute([$student_id]);
$internship = $stmt->fetch();

if (!$internship) {
    echo '<div class="alert alert-danger">You need an active internship to submit logbook entries.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit();
}

$internship_id = $internship['internship_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_logbook'])) {
    $week_no = $_POST['week_no'];
    $task = $_POST['task'];
    $problem = $_POST['problem'];
    $solutions = $_POST['solutions'];
    
    // Check if logbook for this week already exists
    $check = $pdo->prepare("SELECT logbook_id FROM logbook WHERE internship_id = ? AND logbook_week_no = ?");
    $check->execute([$internship_id, $week_no]);
    
    if ($check->fetch()) {
        $error = "Logbook for week $week_no already exists!";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logbook (internship_id, logbook_week_no, logbook_task, logbook_problem, logbook_solutions, logbook_status, logbook_submitted_date)
                VALUES (?, ?, ?, ?, ?, 'Submit', CURDATE())
            ");
            $stmt->execute([$internship_id, $week_no, $task, $problem, $solutions]);
            $message = "Logbook entry for week $week_no submitted successfully!";
        } catch(Exception $e) {
            $error = "Failed to submit: " . $e->getMessage();
        }
    }
}

// Get existing logbooks
$stmt = $pdo->prepare("
    SELECT * FROM logbook 
    WHERE internship_id = ? 
    ORDER BY logbook_week_no DESC
");
$stmt->execute([$internship_id]);
$logbooks = $stmt->fetchAll();
?>

<h2>Internship Logbook</h2>

<?php if($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h4>Add New Logbook Entry</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>Week Number</label>
                        <input type="number" name="week_no" class="form-control" required min="1" max="52">
                    </div>
                    
                    <div class="mb-3">
                        <label>Tasks Performed</label>
                        <textarea name="task" class="form-control" rows="4" required placeholder="Describe the tasks you performed this week..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label>Problems Encountered</label>
                        <textarea name="problem" class="form-control" rows="3" placeholder="Any problems or challenges faced?"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label>Solutions / Learning Outcomes</label>
                        <textarea name="solutions" class="form-control" rows="3" placeholder="How did you solve the problems? What did you learn?"></textarea>
                    </div>
                    
                    <button type="submit" name="submit_logbook" class="btn btn-primary">Submit Logbook</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h4>My Logbook Entries</h4>
            </div>
            <div class="card-body">
                <?php if(count($logbooks) > 0): ?>
                    <div class="accordion" id="logbookAccordion">
                        <?php foreach($logbooks as $index => $log): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $log['logbook_id']; ?>">
                                        Week <?php echo $log['logbook_week_no']; ?> - Submitted: <?php echo date('d M Y', strtotime($log['logbook_submitted_date'])); ?>
                                        <span class="badge bg-<?php echo $log['logbook_status'] == 'Review' ? 'info' : 'secondary'; ?> ms-2">
                                            <?php echo $log['logbook_status']; ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $log['logbook_id']; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" data-bs-parent="#logbookAccordion">
                                    <div class="accordion-body">
                                        <h6>Tasks:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($log['logbook_task'])); ?></p>
                                        
                                        <?php if($log['logbook_problem']): ?>
                                            <h6>Problems:</h6>
                                            <p><?php echo nl2br(htmlspecialchars($log['logbook_problem'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if($log['logbook_solutions']): ?>
                                            <h6>Solutions:</h6>
                                            <p><?php echo nl2br(htmlspecialchars($log['logbook_solutions'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No logbook entries yet. Submit your first weekly report above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>