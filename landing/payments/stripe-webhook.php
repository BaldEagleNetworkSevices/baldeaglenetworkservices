<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/global.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
landing_require('payments/store.php');
landing_require('payments/stripe.php');

header('Cache-Control: no-store, max-age=0');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

function landing_webhook_debug_log(string $event, array $context = []): void
{
    if (!landing_is_local_development()) {
        return;
    }

    try {
        landing_log_payment_event($event, $context);
    } catch (Throwable) {
    }
}

function landing_payment_response(int $statusCode, array $payload): never
{
    landing_webhook_debug_log('webhook_response', [
        'status_code' => $statusCode,
        'payload_status' => (string) ($payload['status'] ?? ''),
        'payload_error' => (string) ($payload['error'] ?? ''),
    ]);
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function landing_payment_amount_string(int $amountCents): string
{
    return number_format($amountCents / 100, 2, '.', '');
}

function landing_webhook_identifiers(array $object): array
{
    $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
    $requestId = trim((string) ($object['client_reference_id'] ?? ($metadata['request_id'] ?? '')));
    $sessionId = trim((string) ($object['id'] ?? ''));

    return [
        'request_id' => $requestId,
        'session_id' => $sessionId,
        'metadata' => $metadata,
    ];
}

function landing_webhook_safe_side_effect(string $label, callable $callback, array $context = []): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        $context['label'] = $label;
        $context['reason'] = $exception->getMessage();

        try {
            landing_log_payment_event('webhook_side_effect_failed', $context);
        } catch (Throwable) {
        }
    }
}

$rawPayload = (string) file_get_contents('php://input');
$signatureHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

landing_webhook_debug_log('webhook_hit', [
    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
    'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
    'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
    'content_length' => strlen($rawPayload),
    'signature_present' => $signatureHeader !== '',
]);

try {
    $event = landing_stripe_verify_event($rawPayload, $signatureHeader);
} catch (Throwable $exception) {
    try {
        landing_log_payment_event('webhook_invalid', ['reason' => $exception->getMessage()]);
    } catch (Throwable) {
    }
    landing_payment_response(400, ['error' => 'invalid_signature']);
}

$eventId = trim((string) ($event['id'] ?? ''));
$eventType = trim((string) ($event['type'] ?? ''));
if ($eventId === '' || $eventType === '') {
    landing_payment_response(400, ['error' => 'invalid_event']);
}

landing_webhook_debug_log('webhook_verified', [
    'event_id' => $eventId,
    'event_type' => $eventType,
]);

if (landing_has_processed_stripe_event($eventId)) {
    landing_payment_response(200, ['status' => 'already_processed']);
}

$object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
$eventMarkedProcessed = false;

try {
    switch ($eventType) {
        case 'checkout.session.completed':
            $ids = landing_webhook_identifiers($object);
            $result = landing_mark_payment_completed((string) $ids['session_id'], [
                'payment_reference' => (string) ($object['payment_intent'] ?? ''),
                'amount_cents' => (int) ($object['amount_total'] ?? 0),
                'currency' => strtolower((string) ($object['currency'] ?? landing_global_config()['stripe_currency'])),
            ]);

            $request = $result['request'];
            $requestId = (string) ($request['request_id'] ?? '');
            landing_mark_stripe_event_processed($eventId);
            $eventMarkedProcessed = true;

            $sharedPayload = [
                'request_id' => $requestId,
                'stripe_checkout_session_id' => (string) ($object['id'] ?? ''),
                'payment_reference' => (string) ($request['payment_reference'] ?? ''),
                'payment_status' => 'paid_priority',
                'payment_completed_at' => (string) ($request['payment_completed_at'] ?? ''),
                'amount_cents' => (int) ($request['amount_cents'] ?? 0),
                'amount' => landing_payment_amount_string((int) ($request['amount_cents'] ?? 0)),
                'currency' => strtoupper((string) ($request['currency'] ?? 'usd')),
                'business_name' => (string) ($request['business_name'] ?? ''),
                'business_domain' => (string) ($request['business_domain'] ?? ''),
                'customer_email' => (string) ($request['work_email'] ?? ''),
                'work_email' => (string) ($request['work_email'] ?? ''),
                'service' => (string) ($request['service'] ?? 'external-security-scan'),
                'service_name' => 'Priority External Security Scan',
                'delivery_tier' => (string) ($request['delivery_tier'] ?? 'priority'),
                'product_code' => (string) ($request['product_code'] ?? ''),
                'crm_reference' => (string) ($request['crm_reference'] ?? ''),
            ];

            $paymentReference = (string) ($sharedPayload['payment_reference'] ?? '');

            $emailKey = 'payment_completed:email:' . $requestId . ':' . $paymentReference;
            if (landing_mark_payment_side_effect_once($emailKey)) {
                landing_webhook_safe_side_effect('enqueue_email_payment_completed', static function () use ($emailKey, $sharedPayload): void {
                    landing_enqueue_email_hook('payment_completed', array_merge(['hook_id' => $emailKey], $sharedPayload));
                }, ['request_id' => $requestId, 'event_id' => $eventId]);
            }

            $qboKey = 'payment_completed:qbo:' . $requestId . ':' . $paymentReference;
            if (landing_mark_payment_side_effect_once($qboKey)) {
                landing_webhook_safe_side_effect('enqueue_qbo_payment_completed', static function () use ($qboKey, $sharedPayload): void {
                    landing_enqueue_qbo_hook('payment_completed', array_merge(['hook_id' => $qboKey], $sharedPayload));
                }, ['request_id' => $requestId, 'event_id' => $eventId]);
            }

            $crmKey = 'payment_completed:crm:' . $requestId . ':' . $paymentReference;
            if (landing_mark_payment_side_effect_once($crmKey)) {
                landing_webhook_safe_side_effect('enqueue_crm_payment_completed', static function () use ($crmKey, $sharedPayload): void {
                    landing_enqueue_crm_payment_hook('payment_completed', array_merge(['hook_id' => $crmKey], $sharedPayload));
                }, ['request_id' => $requestId, 'event_id' => $eventId]);
            }

            landing_webhook_safe_side_effect('log_payment_completed', static function () use ($sharedPayload): void {
                landing_log_payment_event('payment_completed', $sharedPayload);
            }, ['request_id' => $requestId, 'event_id' => $eventId]);
            break;

        case 'checkout.session.expired':
            $ids = landing_webhook_identifiers($object);
            $result = landing_mark_checkout_abandoned((string) $ids['request_id'], (string) $ids['session_id']);
            $request = $result['request'];
            landing_webhook_safe_side_effect('log_checkout_abandoned', static function () use ($request, $ids): void {
                landing_log_payment_event('checkout_abandoned', [
                    'request_id' => (string) ($request['request_id'] ?? ''),
                    'stripe_checkout_session_id' => (string) ($ids['session_id'] ?? ''),
                    'payment_status' => 'checkout_abandoned',
                ]);
            }, ['request_id' => (string) ($request['request_id'] ?? ''), 'event_id' => $eventId]);
            break;

        case 'checkout.session.async_payment_failed':
            $ids = landing_webhook_identifiers($object);
            $result = landing_mark_payment_failed((string) $ids['request_id'], (string) $ids['session_id'], [
                'payment_reference' => (string) ($object['payment_intent'] ?? ''),
            ]);
            $request = $result['request'];
            landing_webhook_safe_side_effect('log_payment_failed', static function () use ($request, $ids, $object): void {
                landing_log_payment_event('payment_failed', [
                    'request_id' => (string) ($request['request_id'] ?? ''),
                    'stripe_checkout_session_id' => (string) ($ids['session_id'] ?? ''),
                    'payment_reference' => (string) ($object['payment_intent'] ?? ''),
                ]);
            }, ['request_id' => (string) ($request['request_id'] ?? ''), 'event_id' => $eventId]);
            break;

        case 'payment_intent.payment_failed':
            $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
            $requestId = trim((string) ($metadata['request_id'] ?? ''));
            $result = landing_mark_payment_failed($requestId, '', [
                'payment_reference' => (string) ($object['id'] ?? ''),
            ]);
            $request = $result['request'];
            landing_webhook_safe_side_effect('log_payment_failed', static function () use ($request, $object): void {
                landing_log_payment_event('payment_failed', [
                    'request_id' => (string) ($request['request_id'] ?? ''),
                    'payment_reference' => (string) ($object['id'] ?? ''),
                ]);
            }, ['request_id' => (string) ($request['request_id'] ?? ''), 'event_id' => $eventId]);
            break;

        default:
            landing_webhook_safe_side_effect('log_webhook_ignored', static function () use ($eventId, $eventType): void {
                landing_log_payment_event('webhook_ignored', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                ]);
            }, ['event_id' => $eventId]);
            break;
    }
} catch (Throwable $exception) {
    try {
        landing_log_payment_event('webhook_failed', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'reason' => $exception->getMessage(),
        ]);
    } catch (Throwable) {
    }

    $errorPayload = ['error' => 'webhook_processing_failed'];
    if (landing_is_local_development()) {
        $errorPayload['message'] = $exception->getMessage();
    }

    landing_payment_response(500, $errorPayload);
}

if (!$eventMarkedProcessed) {
    landing_mark_stripe_event_processed($eventId);
}

landing_payment_response(200, ['status' => 'ok']);
