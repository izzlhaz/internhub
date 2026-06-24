<?php

function internhub_brevo_config(): array
{
    $configPath = 'C:/xampp/internhub-secrets/brevo.php';
    if (!is_file($configPath)) {
        error_log('InternHub Brevo configuration file was not found: ' . $configPath);
        return [];
    }

    $config = require $configPath;
    if (!is_array($config)) {
        error_log('InternHub Brevo configuration file must return an array.');
        return [];
    }
    return $config;
}

function notify_coordinators_of_company_registration(PDO $pdo, array $company): bool
{
    $stmt = $pdo->query("
        SELECT DISTINCT c.coordinator_email
        FROM coordinator c
        JOIN user u ON u.user_id = c.user_id
        WHERE u.user_status = 'Active' AND c.coordinator_email <> ''
    ");
    $recipients = array_values(array_filter(array_column($stmt->fetchAll(), 'coordinator_email'), function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }));

    if (!$recipients) {
        error_log('InternHub could not send the company registration notification because no active coordinator email was found.');
        return false;
    }

    $config = internhub_brevo_config();
    $apiKey = trim((string) ($config['api_key'] ?? ''));
    $senderEmail = trim((string) ($config['sender_email'] ?? ''));
    $senderName = trim((string) ($config['sender_name'] ?? 'InternHub'));
    if ($apiKey === '' || $apiKey === 'PASTE_YOUR_BREVO_API_KEY_HERE' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('InternHub Brevo API key or sender email is not configured correctly.');
        return false;
    }

    $subject = 'InternHub: New company registration pending approval';
    $body = "A new company has registered and is waiting for coordinator approval.\n\n"
        . 'Company: ' . ($company['name'] ?? '-') . "\n"
        . 'Registration No: ' . ($company['registration_no'] ?? '-') . "\n"
        . 'Email: ' . ($company['email'] ?? '-') . "\n"
        . 'Phone: ' . ($company['phone'] ?? '-') . "\n\n"
        . "Please sign in to InternHub and review the Company Management page.";
    $payload = [
        'sender' => ['name' => $senderName, 'email' => $senderEmail],
        'to' => array_map(function ($email) {
            return ['email' => $email];
        }, $recipients),
        'subject' => $subject,
        'textContent' => $body,
    ];

    $curl = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);
    $response = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $statusCode < 200 || $statusCode >= 300) {
        error_log('InternHub Brevo email failed. HTTP ' . $statusCode . '; cURL: ' . $curlError . '; response: ' . (string) $response);
        return false;
    }
    return true;
}
