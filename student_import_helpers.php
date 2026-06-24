<?php

function malaysia_states()
{
    return [
        'Johor',
        'Kedah',
        'Kelantan',
        'Melaka',
        'Negeri Sembilan',
        'Pahang',
        'Pulau Pinang',
        'Perak',
        'Perlis',
        'Sabah',
        'Sarawak',
        'Selangor',
        'Terengganu',
        'Wilayah Persekutuan',
    ];
}

function industry_types()
{
    return [
        'Accounting',
        'Audit',
        'Taxation',
        'Finance',
        'Banking',
        'Information Technology',
        'Business Analytics',
        'Consulting',
        'Education',
        'Government',
        'Manufacturing',
        'Retail',
        'Other',
    ];
}

function allowance_ranges()
{
    return ['RM0 - RM300', 'RM301 - RM600', 'RM601 - RM900', 'RM901 - RM1,200', 'Above RM1,200'];
}

function capacity_programmes()
{
    return ['B.Acct (IS)', 'B.Acct', 'Both Programs'];
}

function programme_short_label($programme)
{
    return stripos((string) $programme, 'Information System') !== false ? 'B.Acct (IS)' : 'B.Acct';
}

function company_approval_statuses()
{
    return ['Pending', 'Approved'];
}

function business_statuses()
{
    return ['Active', 'Inactive', 'Expired'];
}

function application_statuses()
{
    return ['Pending', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'];
}

function lecturer_departments()
{
    return ['Accounting Information System', 'Taxation', 'Financial Accounting', 'Audit', 'Management Accounting'];
}

function student_offer_responses()
{
    return ['Pending', 'Accepted', 'Rejected'];
}

function normalise_application_status($status)
{
    $map = [
        'Review' => 'Shortlisted',
        'Accept' => 'Accepted',
        'Reject' => 'Rejected',
    ];

    return $map[$status] ?? $status;
}

function company_is_approved(PDO $pdo, $companyId)
{
    $stmt = $pdo->prepare("SELECT company_approval_status FROM company WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch();

    return ($company['company_approval_status'] ?? 'Pending') === 'Approved';
}

function require_company_approval(PDO $pdo, $companyId)
{
    if (!company_is_approved($pdo, $companyId)) {
        header("Location: dashboard.php");
        exit();
    }
}

function select_options($options, $selected)
{
    $html = '';
    foreach ($options as $option) {
        $isSelected = (string) $option === (string) $selected ? ' selected' : '';
        $safe = htmlspecialchars($option);
        $html .= '<option value="' . $safe . '"' . $isSelected . '>' . $safe . '</option>';
    }
    return $html;
}

function ensure_management_schema(PDO $pdo)
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM company');
    foreach ($stmt->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }

    $companyColumns = [
        'company_address_line' => "ALTER TABLE company ADD company_address_line varchar(255) DEFAULT NULL AFTER company_address",
        'company_state' => "ALTER TABLE company ADD company_state varchar(80) DEFAULT NULL AFTER company_address_line",
        'company_postcode' => "ALTER TABLE company ADD company_postcode varchar(10) DEFAULT NULL AFTER company_state",
        'company_registration_no' => "ALTER TABLE company ADD company_registration_no varchar(80) DEFAULT NULL AFTER company_email",
        'company_registration_date' => "ALTER TABLE company ADD company_registration_date date DEFAULT NULL AFTER company_type",
        'company_registration_expiry_date' => "ALTER TABLE company ADD company_registration_expiry_date date DEFAULT NULL AFTER company_registration_date",
        'company_business_status' => "ALTER TABLE company ADD company_business_status varchar(30) DEFAULT 'Active' AFTER company_registration_expiry_date",
        'company_owner_name' => "ALTER TABLE company ADD company_owner_name varchar(150) DEFAULT NULL AFTER company_business_status",
        'company_approval_status' => "ALTER TABLE company ADD company_approval_status varchar(30) DEFAULT 'Pending' AFTER company_owner_name",
        'company_contact_person' => "ALTER TABLE company ADD company_contact_person varchar(150) DEFAULT NULL AFTER company_type",
        'company_allowance_range' => "ALTER TABLE company ADD company_allowance_range varchar(40) DEFAULT NULL AFTER company_contact_person",
        'company_capacity_programme' => "ALTER TABLE company ADD company_capacity_programme varchar(40) DEFAULT 'Both Programs' AFTER company_allowance_range",
        'company_capacity_ais' => "ALTER TABLE company ADD company_capacity_ais int(11) DEFAULT 0 AFTER company_capacity_programme",
        'company_capacity_accounting' => "ALTER TABLE company ADD company_capacity_accounting int(11) DEFAULT 0 AFTER company_capacity_ais",
    ];

    foreach ($companyColumns as $column => $sql) {
        if (empty($columns[$column])) {
            $pdo->exec($sql);
        }
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM internship');
    $internshipColumns = [];
    foreach ($stmt->fetchAll() as $column) {
        $internshipColumns[$column['Field']] = true;
    }

    if (empty($internshipColumns['internship_field'])) {
        $pdo->exec("ALTER TABLE internship ADD internship_field varchar(150) DEFAULT NULL AFTER job_id");
    }

    $pdo->exec("ALTER TABLE internship MODIFY internship_status enum('Pending','Applied','Accepted','Rejected','Active','Completed') DEFAULT 'Pending'");

    $stmt = $pdo->query('SHOW COLUMNS FROM jobposting');
    $jobColumns = [];
    foreach ($stmt->fetchAll() as $column) {
        $jobColumns[$column['Field']] = true;
    }

    if (empty($jobColumns['job_allowance_range'])) {
        $pdo->exec("ALTER TABLE jobposting ADD job_allowance_range varchar(40) DEFAULT NULL AFTER job_location");
    }

    $jobPosterColumns = [
        'job_poster_name' => "ALTER TABLE jobposting ADD job_poster_name varchar(255) DEFAULT NULL AFTER job_requirement",
        'job_poster_type' => "ALTER TABLE jobposting ADD job_poster_type varchar(50) DEFAULT NULL AFTER job_poster_name",
        'job_poster_data' => "ALTER TABLE jobposting ADD job_poster_data longblob DEFAULT NULL AFTER job_poster_type",
        'job_poster_uploaded_at' => "ALTER TABLE jobposting ADD job_poster_uploaded_at datetime DEFAULT NULL AFTER job_poster_data",
    ];
    foreach ($jobPosterColumns as $column => $sql) {
        if (empty($jobColumns[$column])) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec("ALTER TABLE application MODIFY application_status enum('Pending','Shortlisted','Interview','Accepted','Rejected','Review','Accept','Reject') DEFAULT 'Pending'");
    $pdo->exec("UPDATE application SET application_status = 'Shortlisted' WHERE application_status = 'Review'");
    $pdo->exec("UPDATE application SET application_status = 'Accepted' WHERE application_status = 'Accept'");
    $pdo->exec("UPDATE application SET application_status = 'Rejected' WHERE application_status = 'Reject'");

    $stmt = $pdo->query('SHOW COLUMNS FROM student');
    $studentColumns = [];
    foreach ($stmt->fetchAll() as $column) {
        $studentColumns[$column['Field']] = true;
    }

    if (empty($studentColumns['student_address'])) {
        $pdo->exec("ALTER TABLE student ADD student_address text DEFAULT NULL AFTER student_phone");
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM lecturer');
    $lecturerColumns = [];
    foreach ($stmt->fetchAll() as $column) {
        $lecturerColumns[$column['Field']] = true;
    }

    if (empty($lecturerColumns['lecturer_department'])) {
        $pdo->exec("ALTER TABLE lecturer ADD lecturer_department varchar(100) DEFAULT NULL AFTER lecturer_office_phone");
    }

    if (empty($jobColumns['job_posted_date'])) {
        $pdo->exec("ALTER TABLE jobposting ADD job_posted_date datetime DEFAULT current_timestamp() AFTER job_allowance_range");
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM application');
    $applicationColumns = [];
    foreach ($stmt->fetchAll() as $column) {
        $applicationColumns[$column['Field']] = true;
    }

    if (empty($applicationColumns['application_student_response'])) {
        $pdo->exec("ALTER TABLE application ADD application_student_response enum('Pending','Accepted','Rejected') DEFAULT 'Pending' AFTER application_status");
    }
}

function validate_job_poster_upload($file, $required = false)
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new RuntimeException('Please upload the internship promotion poster.');
        }
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The poster upload could not be completed. Please try again.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!$imageInfo || $mime !== 'image/jpeg' || $imageInfo[2] !== IMAGETYPE_JPEG) {
        throw new RuntimeException('The internship poster must be a JPEG image only.');
    }

    return [
        'name' => basename($file['name']),
        'type' => 'image/jpeg',
        'data' => file_get_contents($file['tmp_name']),
    ];
}

function programme_bucket($course)
{
    return programme_short_label($course);
}

function semester_from_batch($batch)
{
    if (preg_match('/^A(\d{2})([12])$/i', trim((string) $batch), $matches)) {
        $year = 2000 + (int) $matches[1];
        return 'Semester ' . $matches[2] . ', Session ' . $year . '/' . ($year + 1);
    }
    return trim((string) $batch) ?: '-';
}

function student_batch_options(PDO $pdo)
{
    return $pdo->query("
        SELECT DISTINCT student_intake
        FROM student
        WHERE student_intake IS NOT NULL AND student_intake <> ''
        ORDER BY student_intake DESC
    ")->fetchAll(PDO::FETCH_COLUMN);
}
?>
