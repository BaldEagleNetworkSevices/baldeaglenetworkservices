<?php
declare(strict_types=1);

landing_require('security/domain-validation.php');

function landing_validate_step_one(array $input): array
{
    $errors = [];

    $firstName = trim(preg_replace('/\s+/', ' ', (string) ($input['first_name'] ?? '')) ?? '');
    $lastName = trim(preg_replace('/\s+/', ' ', (string) ($input['last_name'] ?? '')) ?? '');
    $businessName = trim(preg_replace('/\s+/', ' ', (string) ($input['business_name'] ?? '')) ?? '');
    $businessDomain = landing_normalize_domain((string) ($input['business_domain'] ?? ''));
    $workEmail = landing_normalize_email((string) ($input['work_email'] ?? ''));
    $phone = trim(preg_replace('/\s+/', ' ', (string) ($input['phone'] ?? '')) ?? '');
    $jobTitle = trim(preg_replace('/\s+/', ' ', (string) ($input['job_title'] ?? '')) ?? '');
    $department = trim(preg_replace('/\s+/', ' ', (string) ($input['department'] ?? '')) ?? '');
    $requestNotes = trim((string) ($input['request_notes'] ?? ''));
    $deliveryTier = trim((string) ($input['delivery_tier'] ?? 'standard'));
    $authorization = (string) ($input['authorization_checkbox'] ?? '');

    if ($firstName === '' || mb_strlen($firstName) > 80) {
        $errors['first_name'] = 'First name is required and must be 80 characters or fewer.';
    }

    if ($lastName === '' || mb_strlen($lastName) > 80) {
        $errors['last_name'] = 'Last name is required and must be 80 characters or fewer.';
    }

    if ($businessName === '' || mb_strlen($businessName) > 120) {
        $errors['business_name'] = 'Company name is required and must be 120 characters or fewer.';
    }

    if ($businessDomain === '' || !landing_is_valid_business_domain($businessDomain)) {
        $errors['business_domain'] = 'Enter the root business domain only, such as dec24.com.';
    }

    if (!filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['work_email'] = 'Work email is required and must be valid.';
    } elseif (!landing_email_matches_business_domain($workEmail, $businessDomain)) {
        $errors['work_email'] = 'Use your business email on the same domain as the website you are submitting.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-().\s]{7,30}$/', $phone)) {
        $errors['phone'] = 'Office phone must be a valid business phone number.';
    }

    if ($jobTitle !== '' && mb_strlen($jobTitle) > 100) {
        $errors['job_title'] = 'Job title must be 100 characters or fewer.';
    }

    if ($department !== '' && mb_strlen($department) > 100) {
        $errors['department'] = 'Department must be 100 characters or fewer.';
    }

    if ($requestNotes !== '' && mb_strlen($requestNotes) > 1500) {
        $errors['request_notes'] = 'Request notes must be 1,500 characters or fewer.';
    }

    if (!in_array($deliveryTier, ['standard', 'priority'], true)) {
        $errors['delivery_tier'] = 'Delivery tier must be standard or priority.';
    }

    if ($authorization !== 'yes') {
        $errors['authorization_checkbox'] = 'Authorization confirmation is required.';
    }

    return [
        'clean' => [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'business_name' => $businessName,
            'business_domain' => $businessDomain,
            'work_email' => $workEmail,
            'phone' => $phone,
            'job_title' => $jobTitle,
            'department' => $department,
            'request_notes' => $requestNotes,
            'delivery_tier' => $deliveryTier,
            'authorization_checkbox' => $authorization === 'yes' ? 'yes' : 'no',
        ],
        'errors' => $errors,
    ];
}

function landing_normalize_domain(string $domain): string
{
    $domain = strtolower(trim($domain));
    if ($domain === '') {
        return '';
    }

    $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain) ?? $domain;
    $domain = preg_replace('#^//#', '', $domain) ?? $domain;
    $domain = preg_split('/[\/?#]/', $domain, 2)[0] ?? '';
    $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;
    $domain = rtrim($domain, "./ \t\n\r\0\x0B");
    $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

    return $domain;
}

function landing_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function landing_normalized_payload_hash(array $clean, string $service): string
{
    return hash('sha256', json_encode([
        'service' => $service,
        'business_name' => $clean['business_name'] ?? '',
        'business_domain' => $clean['business_domain'] ?? '',
        'work_email' => $clean['work_email'] ?? '',
        'delivery_tier' => $clean['delivery_tier'] ?? '',
    ], JSON_UNESCAPED_SLASHES));
}
