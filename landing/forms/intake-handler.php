<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/global.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
landing_require('forms/csrf.php');
landing_require('forms/context.php');
landing_require('forms/validation.php');
landing_require('forms/honeypot.php');
landing_require('forms/turnstile.php');
landing_require('security/rate-limit.php');
landing_require('security/abuse-checks.php');
landing_require('security/audit-log.php');
landing_require('crm/suitecrm-handler.php');
landing_require('payments/hooks.php');
landing_require('payments/store.php');

header('Cache-Control: no-store, max-age=0');

function landing_response_wants_json(array $server): bool
{
    $accept = strtolower((string) ($server['HTTP_ACCEPT'] ?? ''));
    return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
}

function landing_redirect_service_slug(array $input): string
{
    $service = trim((string) ($input['service'] ?? 'external-security-scan'));
    return in_array($service, landing_service_allowlist(), true) ? $service : 'external-security-scan';
}

function landing_redirect_delivery_tier(array $input): string
{
    $tier = strtolower(trim((string) ($input['delivery_tier'] ?? 'standard')));
    return in_array($tier, ['standard', 'priority'], true) ? $tier : 'standard';
}

function landing_service_return_path(string $service): string
{
    if (function_exists('landing_page_href')) {
        return landing_page_href($service);
    }

    $suffix = landing_is_local_development() ? '.php' : '';
    return '/landing/' . $service . $suffix;
}

function landing_redirect_with_feedback(string $service, array $query, array $flash, array $oldInput = []): never
{
    set_flash($flash);

    if ($oldInput !== []) {
        remember_form_input($oldInput);
    } else {
        clear_old_input();
    }

    $location = landing_service_return_path($service);
    $queryString = http_build_query($query);
    if ($queryString !== '') {
        $location .= '?' . $queryString;
    }
    $location .= '#intake';

    header('Location: ' . $location, true, 303);
    exit;
}

function landing_reject(string $reasonCode, int $statusCode, array $context): never
{
    landing_audit_log('reject', $reasonCode, $context);

    if (!landing_response_wants_json($_SERVER)) {
        $service = landing_redirect_service_slug($_POST);
        $tier = landing_redirect_delivery_tier($_POST);
        $flash = [
            'variant' => in_array($reasonCode, ['queue_write_failed', 'rate_limit_store_failure'], true) ? 'temporary' : 'error',
            'success' => false,
            'message' => 'Your request could not be submitted yet. Please review the form and try again.',
        ];

        if ($reasonCode === 'validation_failed') {
            $flash['message'] = 'Please review the highlighted intake requirements and try again.';
            if (!empty($context['debug_fields']) && count($context['debug_fields']) === 1) {
                $onlyField = (string) $context['debug_fields'][0];
                if ($onlyField === 'work_email') {
                    $flash['message'] = 'Use your business email on the same domain as the website you are submitting, then try again.';
                } elseif ($onlyField === 'business_domain') {
                    $flash['message'] = 'Enter the root business domain only, such as dec24.com.';
                } elseif ($onlyField === 'business_name') {
                    $flash['message'] = 'Enter the company name tied to the domain you want reviewed.';
                } elseif ($onlyField === 'first_name' || $onlyField === 'last_name') {
                    $flash['message'] = 'Enter the contact name for the person requesting the scan.';
                } elseif ($onlyField === 'authorization_checkbox') {
                    $flash['message'] = 'Confirm that you are authorized to request a scan for this business and domain.';
                }
            }
        } elseif (in_array($reasonCode, ['duplicate_payload', 'email_rate_limited', 'domain_rate_limited', 'ip_rate_limited', 'ip_burst_blocked', 'ip_temporarily_blocked'], true)) {
            $flash['variant'] = 'limit';
            $flash['message'] = match ($reasonCode) {
                'duplicate_payload' => 'We already received a similar request recently. Please wait a moment before submitting the same request again.',
                'email_rate_limited' => 'We already received multiple requests from this work email recently. Please wait before sending another one.',
                'domain_rate_limited' => 'We already received multiple requests for this domain recently. Please wait before sending another one.',
                default => 'We are limiting repeat submissions right now to keep the request queue clean. Please wait a moment and try again.',
            };
        } elseif (in_array($reasonCode, ['queue_write_failed', 'rate_limit_store_failure', 'crm_handoff_failed', 'payment_store_failed'], true)) {
            $flash['message'] = 'The secure request path is temporarily unavailable. Please try again in a moment.';
        } elseif (in_array($reasonCode, ['csrf_invalid', 'service_context_invalid'], true)) {
            $flash['message'] = 'Your secure request session expired. Please reload the page and submit again.';
        } elseif (str_starts_with($reasonCode, 'turnstile_')) {
            $flash['message'] = 'The verification step did not complete. Please try again and resubmit your request.';
        }

        if (landing_is_local_development()) {
            $flash['debug_reason'] = $reasonCode;
            if (!empty($context['debug_fields']) && is_array($context['debug_fields'])) {
                $flash['debug_fields'] = array_values(array_filter($context['debug_fields'], 'is_string'));
            }
        }

        $oldInput = [
            'first_name' => (string) ($_POST['first_name'] ?? ''),
            'last_name' => (string) ($_POST['last_name'] ?? ''),
            'business_name' => (string) ($_POST['business_name'] ?? ''),
            'business_domain' => (string) ($_POST['business_domain'] ?? ''),
            'work_email' => (string) ($_POST['work_email'] ?? ''),
            'phone' => (string) ($_POST['phone'] ?? ''),
            'job_title' => (string) ($_POST['job_title'] ?? ''),
            'department' => (string) ($_POST['department'] ?? ''),
            'request_notes' => (string) ($_POST['request_notes'] ?? ''),
            'authorization_checkbox' => (string) ($_POST['authorization_checkbox'] ?? ''),
        ];

        $query = [
            'tier' => $tier,
            'submission' => match ($flash['variant']) {
                'temporary' => 'temporary',
                'limit' => 'limit',
                default => 'error',
            },
        ];

        if ($reasonCode === 'validation_failed' && !empty($context['debug_fields'][0]) && is_string($context['debug_fields'][0])) {
            $query['field'] = $context['debug_fields'][0];
        }

        landing_redirect_with_feedback($service, $query, $flash, $oldInput);
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    $payload = ['error' => 'submission_rejected'];

    if (landing_is_local_development()) {
        header('X-Landing-Debug-Reason: ' . $reasonCode);
        $payload['debug_reason'] = $reasonCode;
        if (!empty($context['debug_fields']) && is_array($context['debug_fields'])) {
            $payload['debug_fields'] = array_values(array_filter($context['debug_fields'], 'is_string'));
        }
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if (!landing_response_wants_json($_SERVER)) {
        landing_redirect_with_feedback('external-security-scan', [
            'submission' => 'error',
            'tier' => 'standard',
        ], [
            'variant' => 'error',
            'success' => false,
            'message' => 'Submit the request form from the landing page.',
        ]);
    }

    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$requestMeta = landing_collect_request_meta($_SERVER);
if (!landing_request_is_allowed($_SERVER)) {
    landing_reject('request_blocked', 403, $requestMeta);
}

if (!landing_request_origin_allowed($_SERVER)) {
    landing_reject('origin_rejected', 403, $requestMeta);
}

if (!landing_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    landing_reject('csrf_invalid', 403, $requestMeta);
}

try {
    $serviceContext = landing_resolve_service_context($_POST);
} catch (Throwable $exception) {
    landing_reject('service_context_invalid', 403, $requestMeta);
}

$validation = landing_validate_step_one($_POST);
$clean = $validation['clean'];
$errors = $validation['errors'];
$submittedTier = (string) ($clean['delivery_tier'] ?? 'standard');
$resolvedTier = strtolower(trim((string) ($serviceContext['delivery_tier'] ?? $submittedTier)));
if (!in_array($resolvedTier, ['standard', 'priority'], true)) {
    landing_reject('service_context_invalid', 403, $requestMeta);
}

$clean['delivery_tier'] = $resolvedTier;
$requestContext = array_merge($requestMeta, $clean, ['service' => $serviceContext['service']]);
$honeypotTriggered = landing_honeypot_triggered($_POST);
$abuseScore = landing_calculate_abuse_score($_POST, $requestMeta, $errors, $honeypotTriggered);
$turnstileCheck = landing_verify_turnstile_token(
    $serviceContext['config'],
    $clean['delivery_tier'],
    $_POST['cf-turnstile-response'] ?? null,
    $requestMeta['client_ip']
);

if ($honeypotTriggered) {
    landing_reject('honeypot_triggered', 400, $requestContext);
}

if ($errors !== []) {
    landing_reject('validation_failed', 422, array_merge($requestContext, [
        'debug_fields' => array_keys($errors),
    ]));
}

if (!$turnstileCheck['success']) {
    $status = landing_turnstile_required($serviceContext['config'], $clean['delivery_tier']) ? 403 : 400;
    landing_reject($turnstileCheck['reason_code'], $status, $requestContext);
}

$rateLimitOutcome = null;
try {
    $rateLimitOutcome = landing_evaluate_rate_limits($requestMeta, $clean, $serviceContext['service']);
} catch (Throwable $exception) {
    landing_reject('rate_limit_store_failure', 503, $requestContext);
}

if (!$rateLimitOutcome['accepted']) {
    landing_reject($rateLimitOutcome['reason_code'], 429, $requestContext);
}

if ($abuseScore >= 50) {
    landing_reject('abuse_score_rejected', 403, $requestContext);
}

if (landing_is_local_development() && $submittedTier !== $resolvedTier) {
    landing_audit_log('observe', 'delivery_tier_context_override', array_merge($requestContext, [
        'debug_fields' => ['submitted:' . $submittedTier, 'resolved:' . $resolvedTier],
    ]));
}

$requestId = bin2hex(random_bytes(16));
$payload = landing_build_suitecrm_payload($serviceContext['config'], $clean, $requestMeta, [
    'request_id' => $requestId,
    'honeypot_triggered' => $honeypotTriggered,
    'abuse_score' => $abuseScore,
    'validation_status' => 'gated_and_queued',
    'turnstile_verified' => $turnstileCheck['success'],
    'payload_hash' => $rateLimitOutcome['payload_hash'],
]);

try {
    $queueRecord = landing_queue_submission($serviceContext['config'], $payload);
} catch (Throwable $exception) {
    landing_reject('queue_write_failed', 503, array_merge($requestContext, ['request_id' => $requestId]));
}

try {
    $crmResult = landing_handoff_to_crm($serviceContext['config'], $payload, $queueRecord);
} catch (Throwable $exception) {
    landing_reject('crm_handoff_failed', 503, array_merge($requestContext, ['request_id' => $requestId]));
}

if (!$crmResult['success']) {
    $statusCode = site_config()['crm_required'] ? 503 : 202;
    if (site_config()['crm_required']) {
        landing_reject('crm_handoff_failed', $statusCode, array_merge($requestContext, [
            'request_id' => $requestId,
        ]));
    }
}

landing_audit_log('accept', 'queued', array_merge($requestContext, ['request_id' => $requestId]));

$emailLeadPayload = [
    'hook_id' => 'new_lead_created:' . $queueRecord['request_id'],
    'request_id' => $queueRecord['request_id'],
    'service' => $serviceContext['service'],
    'delivery_tier' => $clean['delivery_tier'],
    'business_name' => $clean['business_name'],
    'business_domain' => $clean['business_domain'],
    'work_email' => $clean['work_email'],
    'crm_reference' => (string) ($crmResult['lead_id'] ?? ''),
];
landing_enqueue_email_hook('new_lead_created', $emailLeadPayload);

if ($clean['delivery_tier'] === 'priority') {
    try {
        $paymentRequest = landing_register_priority_payment_request($serviceContext['config'], $payload, $crmResult);
    } catch (Throwable $exception) {
        landing_reject('payment_store_failed', 503, array_merge($requestContext, [
            'request_id' => $requestId,
        ]));
    }
}

clear_old_input();

if (!landing_response_wants_json($_SERVER)) {
    if ($clean['delivery_tier'] === 'priority') {
        $location = landing_priority_payment_page_path() . '?' . http_build_query(
            landing_payment_access_query($paymentRequest)
        );
        if (landing_is_local_development()) {
            header('X-Landing-Resolved-Tier: ' . $clean['delivery_tier']);
            header('X-Landing-Request-Id: ' . $queueRecord['request_id']);
            header('X-Landing-Payment-Token-Created: ' . (!empty($paymentRequest['payment_access_token']) ? 'yes' : 'no'));
            header('X-Landing-Redirect-Target: ' . $location);
            landing_log_payment_event('priority_intake_redirect', [
                'request_id' => $queueRecord['request_id'],
                'resolved_tier' => $clean['delivery_tier'],
                'redirect_target' => $location,
                'payment_token_created' => !empty($paymentRequest['payment_access_token']),
            ]);
        }
        header('Location: ' . $location, true, 303);
        exit;
    }

    landing_redirect_with_feedback($serviceContext['service'], [
        'submission' => 'queued',
        'tier' => $clean['delivery_tier'],
    ], [
        'variant' => 'success',
        'success' => true,
        'message' => 'Your scan request has been received and queued for secure processing.',
        'request_id' => $queueRecord['request_id'],
    ]);
}

http_response_code(202);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => 'queued',
    'request_id' => $queueRecord['request_id'],
    'message' => 'Your request has been queued for secure processing.',
    'payment_required' => $clean['delivery_tier'] === 'priority',
    'payment_url' => $clean['delivery_tier'] === 'priority'
        ? landing_priority_payment_page_url(landing_payment_access_query($paymentRequest))
        : '',
    'debug_resolved_tier' => landing_is_local_development() ? $clean['delivery_tier'] : null,
    'debug_redirect_target' => landing_is_local_development() && $clean['delivery_tier'] === 'priority'
        ? landing_priority_payment_page_url(landing_payment_access_query($paymentRequest))
        : null,
], JSON_UNESCAPED_SLASHES);
