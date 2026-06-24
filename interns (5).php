<?php

function spreadsheet_column_index(string $reference): int
{
    preg_match('/^[A-Z]+/i', $reference, $matches);
    $letters = strtoupper($matches[0] ?? 'A');
    $index = 0;
    foreach (str_split($letters) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }
    return $index - 1;
}

function read_xlsx_rows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the Excel file.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        foreach ($xml->si as $item) {
            $parts = [];
            if (isset($item->t)) {
                $parts[] = (string) $item->t;
            }
            foreach ($item->r as $run) {
                $parts[] = (string) $run->t;
            }
            $sharedStrings[] = implode('', $parts);
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('The first worksheet could not be read.');
    }

    $sheet = simplexml_load_string($sheetXml);
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $values = [];
        foreach ($row->c as $cell) {
            $index = spreadsheet_column_index((string) $cell['r']);
            $type = (string) $cell['t'];
            if ($type === 'inlineStr') {
                $value = (string) $cell->is->t;
            } else {
                $value = (string) $cell->v;
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
            }
            $values[$index] = trim($value);
        }
        if ($values) {
            $max = max(array_keys($values));
            $rows[] = array_replace(array_fill(0, $max + 1, ''), $values);
        }
    }
    return $rows;
}

function read_student_import_rows(string $path, string $extension): array
{
    if ($extension === 'xlsx') {
        return read_xlsx_rows($path);
    }

    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to read the uploaded file.');
    }
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_map('trim', $row);
    }
    fclose($handle);
    return $rows;
}

function normalize_import_header(string $header): string
{
    return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header)));
}

function student_import_header_map(array $headers): array
{
    $aliases = [
        'matric_no' => ['matric_no', 'matric', 'student_id', 'student_matric_no'],
        'name' => ['name', 'student_name', 'full_name'],
        'email' => ['email', 'student_email'],
        'course' => ['course', 'programme', 'program', 'student_course'],
        'batch' => ['batch', 'intake', 'student_intake'],
        'phone' => ['phone', 'student_phone', 'phone_number'],
        'gender' => ['gender', 'student_gender'],
        'ic' => ['ic', 'identity_card', 'student_ic', 'ic_number'],
        'status' => ['status', 'student_status', 'account_status'],
    ];
    $normalized = array_map('normalize_import_header', $headers);
    $map = [];
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            $index = array_search($name, $normalized, true);
            if ($index !== false) {
                $map[$field] = $index;
                break;
            }
        }
    }
    return $map;
}

function lecturer_import_header_map(array $headers): array
{
    $aliases = [
        'staff_id' => ['lecturer_id', 'staff_id', 'lecturer_staff_id'],
        'name' => ['name', 'lecturer_name', 'full_name'],
        'email' => ['email', 'lecturer_email'],
        'department' => ['department', 'lecturer_department', 'unit'],
        'programme' => ['programme', 'program', 'course', 'lecturer_programme'],
        'gender' => ['gender', 'lecturer_gender'],
        'ic' => ['ic', 'identity_card', 'lecturer_ic', 'ic_number'],
        'phone' => ['phone', 'lecturer_phone', 'mobile_phone'],
        'office_phone' => ['office_phone', 'office_contact', 'contact'],
        'max_student' => ['max_student', 'capacity', 'student_capacity'],
        'status' => ['status', 'account_status'],
    ];
    $normalized = array_map('normalize_import_header', $headers);
    $map = [];
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            $index = array_search($name, $normalized, true);
            if ($index !== false) {
                $map[$field] = $index;
                break;
            }
        }
    }
    return $map;
}

function normalize_student_course(string $course): ?string
{
    $value = strtoupper(trim($course));
    if (in_array($value, ['AIS', 'B.ACCT (IS)', 'B ACCT (IS)'], true) || str_contains($value, 'INFORMATION SYSTEM')) {
        return 'Bachelor of Accounting (Information Systems) (Hons)';
    }
    if ($value === 'PURE' || $value === 'ACCOUNTING' || str_contains($value, 'ACCOUNTING')) {
        return 'Bachelor of Accounting (Hons)';
    }
    return null;
}

function normalize_lecturer_programme(string $programme): ?string
{
    return normalize_student_course($programme);
}

function normalize_lecturer_department(string $department): ?string
{
    $value = strtoupper(trim($department));
    $departments = [
        'ACCOUNTING INFORMATION SYSTEM' => 'Accounting Information System',
        'ACCOUNTING INFORMATION SYSTEMS' => 'Accounting Information System',
        'AIS' => 'Accounting Information System',
        'TAXATION' => 'Taxation',
        'TAX' => 'Taxation',
        'FINANCIAL ACCOUNTING' => 'Financial Accounting',
        'MANAGEMENT ACCOUNTING' => 'Management Accounting',
        'AUDIT' => 'Audit',
        'AUDITING' => 'Audit',
    ];
    return $departments[$value] ?? null;
}

function generate_student_temporary_password(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $password = '';
    for ($i = 0; $i < 14; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $password;
}
