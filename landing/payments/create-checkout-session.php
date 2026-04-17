<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/global.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
landing_require('forms/csrf.php');
landing_require('payments/store.php');
landing_require('payments/stripe.php');

header('Cache-Control: no-store, max-age=0');

function landing_payment_wants_json(array $server): bool
{
    $accept = strtolower((string) ($server['HTTP_ACCEPT'] ?? ''));
    return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
}

function landing_payment_redirect_back(string $requestId, string $paymentToken, array $query = []): never
{
    $query['request_id'] = $requestId;
    $query['payment_token'] = $paymentToken;
    $location = landing_priority_payment_page_path() . '?' . http_build_query($query);
    header('Location: ' . $location, true, 303);
    exit;
}

function landing_payment_fail(string $requestId, string $paymentToken, int $statusCode, string $message, string $reason): never
{
    try {
        landing_log_payment_event('checkout_create_failed', [
            'request_id' => $requestId,
            'reason' => $reason,
        ]);
    } catch (Throwable) {
    }

    if (!landing_payment_wants_json($_SERVER) && $requestId !== '' && $paymentToken !== '') {
        landing_payment_redirect_back($requestId, $paymentToken, ['checkout' => 'error']);
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    $payload = ['error' => 'checkout_unavailable', 'message' => $message];
    if (landing_is_local_development()) {
        $payload['debug_reason'] = $reason;
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    landing_payment_fail('', '', 405, 'Start checkout from the payment page.', 'method_not_allowed');
}

if (!landing_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    landing_payment_fail(
        trim((string) ($_POST['request_id'] ?? '')),
        trim((string) ($_POST['payment_token'] ?? '')),
        403,
        'Your secure payment session expired. Reload the page and try again.',
        'csrf_invalid'
    );
}

$requestId = trim((string) ($_POST['request_id'] ?? ''));
$paymentToken = trim((string) ($_POST['payment_token'] ?? ''));

try {
    $checkout = landing_start_priority_checkout($requestId, $paymentToken);
} catch (Throwable $exception) {
    $reason = $exception->getMessage();
    $statusCode = match ($reason) {
        'request_not_found' => 404,
        'checkout_creation_in_progress' => 409,
        default => 503,
    };
    $message = $reason === 'request_not_found'
        ? 'A valid priority request is required before checkout can begin.'
        : ($reason === 'checkout_creation_in_progress'
            ? 'A secure checkout session is already being prepared. Please wait a moment and try again.'
            : 'Secure checkout is temporarily unavailable. Please try again in a moment.');
    landing_payment_fail($requestId, $paymentToken, $statusCode, $message, $reason);
}

if (($checkout['status'] ?? '') === 'already_paid') {
    if (!landing_payment_wants_json($_SERVER)) {
        landing_payment_redirect_back($requestId, $paymentToken);
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'already_paid',
        'request_id' => $requestId,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$checkoutUrl = trim((string) ($checkout['checkout_url'] ?? ''));
if ($checkoutUrl === '') {
    landing_payment_fail($requestId, $paymentToken, 503, 'Secure checkout is temporarily unavailable. Please try again in a moment.', 'checkout_url_missing');
}

if (!landing_payment_wants_json($_SERVER)) {
    header('Location: ' . $checkoutUrl, true, 303);
    exit;
}

http_response_code(201);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => (string) ($checkout['status'] ?? 'checkout_started'),
    'request_id' => $requestId,
    'stripe_checkout_session_id' => (string) ($checkout['stripe_checkout_session_id'] ?? ''),
    'checkout_url' => $checkoutUrl,
    'reused' => (bool) ($checkout['reused'] ?? false),
], JSON_UNESCAPED_SLASHES);
exit;
