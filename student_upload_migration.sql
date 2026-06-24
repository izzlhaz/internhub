<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/management_helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    http_response_code(403);
    exit;
}

ensure_management_schema($pdo);
$jobId = (int) ($_GET['id'] ?? 0);
$role = $_SESSION['user_role'];
$params = [$jobId];
$accessSql = '';

if ($role === 'student') {
    $accessSql = " AND j.job_status = 'Active'";
} elseif ($role === 'company') {
    $accessSql = ' AND j.company_id = ?';
    $params[] = (int) ($_SESSION['company_id'] ?? 0);
} elseif (!in_array($role, ['coordinator', 'lecturer'], true)) {
    http_response_code(403);
    exit;
}

$stmt = $pdo->prepare('SELECT j.job_poster_data FROM jobposting j WHERE j.job_id = ? AND j.job_poster_data IS NOT NULL' . $accessSql);
$stmt->execute($params);
$poster = $stmt->fetchColumn();
if ($poster === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($poster));
header('Content-Disposition: inline');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $poster;
