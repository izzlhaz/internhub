<?php

function ensure_student_upload_schema(PDO $pdo)
{
    $resumeColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM resume')->fetchAll() as $column) {
        $resumeColumns[$column['Field']] = true;
    }

    $resumeChanges = [
        'resume_file_name' => "ALTER TABLE resume ADD resume_file_name varchar(255) DEFAULT NULL AFTER student_id",
        'resume_file_type' => "ALTER TABLE resume ADD resume_file_type varchar(100) DEFAULT NULL AFTER resume_file_name",
        'resume_file_size' => "ALTER TABLE resume ADD resume_file_size bigint unsigned DEFAULT NULL AFTER resume_file_type",
        'resume_file_data' => "ALTER TABLE resume ADD resume_file_data longblob DEFAULT NULL AFTER resume_file_size",
        'resume_uploaded_at' => "ALTER TABLE resume ADD resume_uploaded_at datetime DEFAULT NULL AFTER resume_file_data",
    ];
    foreach ($resumeChanges as $column => $sql) {
        if (empty($resumeColumns[$column])) {
            $pdo->exec($sql);
        }
    }

    $studentColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM student')->fetchAll() as $column) {
        $studentColumns[$column['Field']] = true;
    }

    $photoChanges = [
        'student_photo_name' => "ALTER TABLE student ADD student_photo_name varchar(255) DEFAULT NULL AFTER student_state",
        'student_photo_type' => "ALTER TABLE student ADD student_photo_type varchar(100) DEFAULT NULL AFTER student_photo_name",
        'student_photo_data' => "ALTER TABLE student ADD student_photo_data longblob DEFAULT NULL AFTER student_photo_type",
        'student_photo_uploaded_at' => "ALTER TABLE student ADD student_photo_uploaded_at datetime DEFAULT NULL AFTER student_photo_data",
    ];
    foreach ($photoChanges as $column => $sql) {
        if (empty($studentColumns[$column])) {
            $pdo->exec($sql);
        }
    }
}

function uppercase_student_records(PDO $pdo)
{
    $pdo->exec("UPDATE student SET
        student_matric_no = UPPER(student_matric_no),
        student_name = UPPER(student_name),
        student_intake = UPPER(student_intake),
        student_address = UPPER(student_address),
        student_state = UPPER(student_state)");
    $pdo->exec("UPDATE user u JOIN student s ON s.user_id = u.user_id SET u.user_name = s.student_name");
}

function uppercase_profile_value($value)
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper(trim((string) $value), 'UTF-8')
        : strtoupper(trim((string) $value));
}

function upload_error_message($code)
{
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'The file is larger than the server upload allowance.',
        UPLOAD_ERR_FORM_SIZE => 'The file is larger than the form upload allowance.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Please choose a file to upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is unavailable.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload.',
    ];

    return $messages[$code] ?? 'The upload could not be completed.';
}
