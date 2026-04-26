<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/crm.php';

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

function wants_json_response(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requestedWith === 'fetch';
}

function allowed_form_content_type(): bool
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if ($contentType === '') {
        return true;
    }

    return str_starts_with($contentType, 'application/x-www-form-urlencoded')
        || str_starts_with($contentType, 'multipart/form-data');
}

function respond_form(int $status, array $payload, ?string $redirect = null): never
{
    if (wants_json_response()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        if (function_exists('ben_is_local_development') && ben_is_local_development() && isset($payload['debug_reason']) && is_string($payload['debug_reason']) && $payload['debug_reason'] !== '') {
            header('X-Contact-Debug-Reason: ' . $payload['debug_reason']);
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($redirect === null || $redirect === '') {
        $redirect = page_href('contact');
    }

    set_flash($payload);
    header('Location: ' . $redirect . '#contact-form', true, 303);
    exit;
}

function cleaned_text(mixed $value, int $maxLength = 2000): string
{
    $text = trim((string) $value);
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';

    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength);
    }

    return $text;
}

function client_ip(): string
{
    return cleaned_text($_SERVER['REMOTE_ADDR'] ?? 'unknown', 64);
}

function rate_limit_key(): string
{
    ensure_session_started();
    $sessionId = session_id();
    return hash('sha256', client_ip() . '|' . $sessionId);
}

function check_rate_limit(): ?string
{
    ensure_session_started();

    $now = time();
    $minSeconds = 15;
    $windowSeconds = 600;
    $maxAttempts = 5;

    $file = sys_get_temp_dir() . '/ben-rate-' . rate_limit_key() . '.json';
    $attempts = [];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            foreach ($decoded as $timestamp) {
                $timestamp = (int) $timestamp;
                if (($now - $timestamp) < $windowSeconds) {
                    $attempts[] = $timestamp;
                }
            }
        }
    }

    $lastAt = $attempts === [] ? 0 : max($attempts);
    if ($lastAt > 0 && ($now - $lastAt) < $minSeconds) {
        return 'Please wait a moment before sending another request.';
    }

    if (count($attempts) >= $maxAttempts) {
        return 'Too many requests from this session. Try again later.';
    }

    $attempts[] = $now;
    file_put_contents($file, json_encode($attempts), LOCK_EX);
    $_SESSION['_last_submission_at'] = $now;

    return null;
}

function persist_submission(array $payload): bool
{
    $file = site_config()['submission_store'];
    $directory = dirname($file);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $line = json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    return file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
}

function notify_submission(array $payload): void
{
    $config = site_config();
    if ($config['contact_to_email'] === '' || !function_exists('mail')) {
        return;
    }

    $subject = 'New Bald Eagle lead: ' . ($payload['service_type_label'] ?? 'Inquiry');
    $body = implode(PHP_EOL, [
        'Name: ' . $payload['name'],
        'Company: ' . $payload['company'],
        'Email: ' . $payload['email'],
        'Phone: ' . $payload['phone'],
        'Preferred Contact: ' . ($payload['preferred_contact'] !== '' ? $payload['preferred_contact'] : 'Not provided'),
        'Employee Count: ' . ($payload['employee_count'] !== '' ? $payload['employee_count'] : 'Not provided'),
        'Main Concern: ' . $payload['main_concern'],
        'Service: ' . $payload['service_type_label'],
        'Context: ' . $payload['form_context'],
        'Message:',
        $payload['message'],
    ]);

    $headers = [
        'From: ' . $config['from_email'],
        'Reply-To: ' . $payload['email'],
        'Content-Type: text/plain; charset=UTF-8',
    ];

    @mail($config['contact_to_email'], $subject, $body, implode("\r\n", $headers));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond_form(405, ['success' => false, 'message' => 'Method not allowed.']);
}

if (!allowed_form_content_type()) {
    respond_form(415, ['success' => false, 'message' => 'Unsupported submission format.']);
}

ensure_session_started();

$redirectBack = page_href('contact');
$input = [
    'name' => cleaned_text($_POST['name'] ?? '', 160),
    'company' => cleaned_text($_POST['company'] ?? '', 160),
    'email' => cleaned_text($_POST['email'] ?? '', 200),
    'phone' => cleaned_text($_POST['phone'] ?? '', 50),
    'preferred_contact' => cleaned_text($_POST['preferred_contact'] ?? '', 20),
    'service_type' => cleaned_text($_POST['service_type'] ?? '', 80),
    'employee_count' => cleaned_text($_POST['employee_count'] ?? '', 20),
    'main_concern' => cleaned_text($_POST['main_concern'] ?? '', 80),
    'message' => cleaned_text($_POST['message'] ?? '', 4000),
    'website' => cleaned_text($_POST['website'] ?? '', 80),
    'form_context' => cleaned_text($_POST['form_context'] ?? 'general', 80),
    'prefill_service_type' => cleaned_text($_POST['service_type'] ?? '', 80),
];

remember_form_input($input);

if ($input['website'] !== '') {
    respond_form(400, ['success' => false, 'message' => 'Unable to submit this request right now.']);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    respond_form(400, ['success' => false, 'message' => 'Your security token is invalid or expired. Refresh the page and try again.']);
}

if ($message = check_rate_limit()) {
    respond_form(429, ['success' => false, 'message' => $message]);
}

$errors = [];
if ($input['name'] === '') {
    $errors['name'] = 'Enter your name.';
}
if ($input['company'] === '') {
    $errors['company'] = 'Enter your company name.';
}
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid work email.';
}
if ($input['phone'] !== '' && !preg_match('/^[0-9\-\+\(\)\.\s]{7,25}$/', $input['phone'])) {
    $errors['phone'] = 'Enter a valid phone number or leave it blank.';
}
if (!array_key_exists($input['service_type'], site_config()['allowed_service_types'])) {
    $errors['service_type'] = 'Select a valid assessment type.';
}
if (!in_array($input['main_concern'], ['Backup recovery', 'Account access', 'Ransomware', 'Downtime', 'Not sure'], true)) {
    $errors['main_concern'] = 'Select the main concern.';
}
if ($input['message'] === '') {
    $errors['message'] = 'Provide a short summary of the issue, project, or risk.';
}

if ($errors !== []) {
    respond_form(422, [
        'success' => false,
        'message' => 'Please correct the highlighted fields.',
        'errors' => $errors,
    ]);
}

$payload = [
    'request_id' => bin2hex(random_bytes(8)),
    'submitted_at' => gmdate(DATE_ATOM),
    'ip' => client_ip(),
    'user_agent' => cleaned_text($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
    'name' => $input['name'],
    'company' => $input['company'],
    'email' => $input['email'],
    'phone' => $input['phone'],
    'preferred_contact' => $input['preferred_contact'],
    'service_type' => $input['service_type'],
    'service_type_label' => site_config()['allowed_service_types'][$input['service_type']],
    'employee_count' => $input['employee_count'],
    'main_concern' => $input['main_concern'],
    'form_context' => $input['form_context'],
    'message' => $input['message'],
];

if (!persist_submission($payload)) {
    crm_log('local_persist', [
        'request_id' => $payload['request_id'],
        'lead_ref' => crm_lead_ref($payload),
        'result' => 'failed',
        'reason' => 'The local submission store could not be written.',
    ]);
} else {
    crm_log('local_persist', [
        'request_id' => $payload['request_id'],
        'lead_ref' => crm_lead_ref($payload),
        'result' => 'success',
    ]);
}

$crmResult = create_crm_lead($payload);
if (!$crmResult['success']) {
    $message = site_config()['crm_required']
        ? 'Your request could not be entered into the intake system. Please call or email Bald Eagle directly.'
        : 'Your request was saved, but the CRM handoff did not complete. Bald Eagle will review it manually.';

    $status = site_config()['crm_required'] ? 502 : 202;
    $response = [
        'success' => false,
        'message' => $message,
        'crm_status' => 'failed',
    ];
    if (function_exists('ben_is_local_development') && ben_is_local_development()) {
        $response['debug_reason'] = (string) ($crmResult['reason'] ?? 'crm_handoff_failed');
        $response['crm_target'] = (string) ($crmResult['target'] ?? '');
    }
    respond_form($status, $response);
}

notify_submission($payload);
clear_old_input();

respond_form(200, [
    'success' => true,
    'message' => 'Risk assessment request received. Bald Eagle will follow up with next steps.',
    'crm_status' => 'queued',
]);
