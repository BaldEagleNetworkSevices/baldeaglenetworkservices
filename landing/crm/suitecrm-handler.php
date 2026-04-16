<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/crm.php';

landing_require('crm/field-mapping.php');
landing_require('security/audit-log.php');

function landing_build_suitecrm_payload(array $serviceConfig, array $clean, array $requestMeta, array $systemState): array
{
    return [
        'request_id' => $systemState['request_id'],
        'service' => $serviceConfig['slug'],
        'first_name' => $clean['first_name'],
        'last_name' => $clean['last_name'],
        'business_name' => $clean['business_name'],
        'business_domain' => $clean['business_domain'],
        'work_email' => $clean['work_email'],
        'phone' => $clean['phone'],
        'job_title' => $clean['job_title'],
        'department' => $clean['department'],
        'request_notes' => $clean['request_notes'],
        'delivery_tier' => $clean['delivery_tier'],
        'lead_source' => 'Website Landing Page',
        'campaign' => $serviceConfig['campaign'],
        'product_code' => $serviceConfig['product_code'],
        'authorization_checkbox' => $clean['authorization_checkbox'],
        'client_ip' => $requestMeta['client_ip'],
        'user_agent' => $requestMeta['user_agent'],
        'honeypot_triggered' => $systemState['honeypot_triggered'] ? 'yes' : 'no',
        'abuse_score' => $systemState['abuse_score'],
        'submitted_at' => $requestMeta['submitted_at'],
        'validation_status' => $systemState['validation_status'],
        'scan_status' => 'queued',
        'follow_up_task' => $clean['delivery_tier'] === 'priority' ? 'priority_review' : 'standard_review',
        'risk_score' => 0,
        'payload_hash' => $systemState['payload_hash'],
        'turnstile_verified' => $systemState['turnstile_verified'] ? 'yes' : 'no',
        'suitecrm_fields' => landing_suitecrm_field_mapping(),
    ];
}

function landing_suitecrm_structured_fields(array $payload): array
{
    $config = site_config();
    $firstName = trim((string) ($payload['first_name'] ?? ''));
    $lastName = trim((string) ($payload['last_name'] ?? ''));
    $company = trim((string) ($payload['business_name'] ?? ''));
    $requestNotes = trim((string) ($payload['request_notes'] ?? ''));

    return [
        'first_name' => $firstName,
        'last_name' => $lastName !== '' ? $lastName : ($company !== '' ? $company : 'Unknown'),
        'account_name' => $company,
        'website' => (string) ($payload['business_domain'] ?? ''),
        'email1' => (string) ($payload['work_email'] ?? ''),
        'phone_work' => (string) ($payload['phone'] ?? ''),
        'title' => (string) ($payload['job_title'] ?? ''),
        'department' => (string) ($payload['department'] ?? ''),
        'description' => $requestNotes,
        'campaign_name' => (string) ($payload['campaign'] ?? ''),
        'delivery_tier_c' => (string) ($payload['delivery_tier'] ?? ''),
        'request_id_c' => (string) ($payload['request_id'] ?? ''),
        'product_code_c' => (string) ($payload['product_code'] ?? ''),
        'scan_status_c' => (string) ($payload['scan_status'] ?? ''),
        'assigned_user_id' => (string) ($config['suitecrm_assigned_user_id'] ?? ''),
    ];
}

function landing_queue_submission(array $serviceConfig, array $payload): array
{
    $global = landing_global_config();
    landing_ensure_directory(dirname($global['queue_file']));

    $queueRecord = [
        'request_id' => $payload['request_id'],
        'service' => $serviceConfig['slug'],
        'queued_at' => gmdate(DATE_ATOM),
        'payload' => $payload,
    ];

    $encoded = json_encode($queueRecord, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode gated queue record.');
    }

    $line = $encoded . PHP_EOL;
    $handle = fopen($global['queue_file'], 'ab');
    if ($handle === false) {
        throw new RuntimeException('Unable to open gated queue file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock gated queue file.');
        }

        $bytesToWrite = strlen($line);
        $bytesWritten = 0;
        while ($bytesWritten < $bytesToWrite) {
            $chunk = fwrite($handle, substr($line, $bytesWritten));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Unable to fully write gated queue record.');
            }
            $bytesWritten += $chunk;
        }

        if (!fflush($handle)) {
            throw new RuntimeException('Unable to flush gated queue file.');
        }

        if (function_exists('fsync') && !fsync($handle)) {
            throw new RuntimeException('Unable to sync gated queue file.');
        }

        flock($handle, LOCK_UN);
    } catch (Throwable $exception) {
        landing_audit_log('reject', 'queue_write_failed', [
            'client_ip' => $payload['client_ip'],
            'user_agent' => $payload['user_agent'],
            'delivery_tier' => $payload['delivery_tier'],
            'business_domain' => $payload['business_domain'],
            'service' => $payload['service'],
            'request_id' => $payload['request_id'],
        ]);
        fclose($handle);
        throw $exception;
    }

    fclose($handle);

    return $queueRecord;
}

function landing_crm_transport_payload(array $serviceConfig, array $payload): array
{
    $deliveryTier = (string) ($payload['delivery_tier'] ?? 'standard');
    $requestId = (string) ($payload['request_id'] ?? '');
    $businessDomain = (string) ($payload['business_domain'] ?? '');

    $descriptionLines = [
        'Landing service: External Security Scan',
        'Request ID: ' . $requestId,
        'Requested by: ' . trim(((string) ($payload['first_name'] ?? '')) . ' ' . ((string) ($payload['last_name'] ?? ''))),
        'Delivery tier: ' . $deliveryTier,
        'Business domain: ' . $businessDomain,
        'Job title: ' . (string) ($payload['job_title'] ?? ''),
        'Department: ' . (string) ($payload['department'] ?? ''),
        'Campaign: ' . (string) ($payload['campaign'] ?? ''),
        'Product code: ' . (string) ($payload['product_code'] ?? ''),
        'Authorization confirmed: ' . (string) ($payload['authorization_checkbox'] ?? 'no'),
        'Scan status: ' . (string) ($payload['scan_status'] ?? 'queued'),
        'Submitted at: ' . (string) ($payload['submitted_at'] ?? ''),
        'Client IP: ' . (string) ($payload['client_ip'] ?? ''),
        'User agent: ' . (string) ($payload['user_agent'] ?? ''),
        'Payload hash: ' . (string) ($payload['payload_hash'] ?? ''),
    ];

    $requestNotes = trim((string) ($payload['request_notes'] ?? ''));
    if ($requestNotes !== '') {
        $descriptionLines[] = 'Request notes: ' . $requestNotes;
    }

    return [
        'request_id' => $requestId,
        'submitted_at' => (string) ($payload['submitted_at'] ?? ''),
        'ip' => (string) ($payload['client_ip'] ?? ''),
        'user_agent' => (string) ($payload['user_agent'] ?? ''),
        'name' => trim(((string) ($payload['first_name'] ?? '')) . ' ' . ((string) ($payload['last_name'] ?? ''))),
        'company' => (string) ($payload['business_name'] ?? ''),
        'email' => (string) ($payload['work_email'] ?? ''),
        'phone' => (string) ($payload['phone'] ?? ''),
        'service_type' => (string) ($serviceConfig['slug'] ?? 'external-security-scan'),
        'service_type_label' => 'External Security Scan',
        'form_context' => 'landing_external_security_scan',
        'message' => implode(PHP_EOL, $descriptionLines),
        'crm_fields' => landing_suitecrm_structured_fields($payload),
    ];
}

function landing_handoff_to_crm(array $serviceConfig, array $payload, array $queueRecord): array
{
    $transportPayload = landing_crm_transport_payload($serviceConfig, $payload);
    $result = create_crm_lead($transportPayload);

    if (landing_is_local_development()) {
        landing_audit_log(
            $result['success'] ? 'accept' : 'reject',
            $result['success'] ? 'crm_handoff_succeeded' : 'crm_handoff_failed',
            [
                'client_ip' => $payload['client_ip'] ?? '',
                'user_agent' => $payload['user_agent'] ?? '',
                'delivery_tier' => $payload['delivery_tier'] ?? '',
                'business_domain' => $payload['business_domain'] ?? '',
                'service' => $payload['service'] ?? '',
                'request_id' => $queueRecord['request_id'] ?? '',
                'crm_mode' => $result['mode'] ?? crm_mode(),
                'crm_target' => $result['target'] ?? crm_target_label(),
                'crm_reason' => $result['reason'] ?? '',
                'crm_lead_id' => $result['lead_id'] ?? '',
            ]
        );
    }

    return $result;
}
