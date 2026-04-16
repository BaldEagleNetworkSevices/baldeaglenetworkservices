<?php
declare(strict_types=1);

landing_require('payments/store.php');

function landing_stripe_is_configured(): bool
{
    $status = landing_stripe_configuration_status();
    return $status['payment_flow_ready'];
}

function landing_stripe_configuration_status(): array
{
    $global = landing_global_config();
    $status = [
        'secret_key' => $global['stripe_secret_key'] !== '',
        'webhook_secret' => $global['stripe_webhook_secret'] !== '',
        'price_id' => $global['stripe_price_id_priority_scan'] !== '',
        'currency' => trim((string) ($global['stripe_currency'] ?? '')) !== '',
    ];

    $missing = [];
    foreach ($status as $key => $ready) {
        if (!$ready) {
            $missing[] = $key;
        }
    }

    $status['checkout_ready'] = $status['secret_key'] && $status['price_id'] && $status['currency'];
    $status['payment_flow_ready'] = $status['checkout_ready'] && $status['webhook_secret'];
    $status['missing'] = $missing;

    return $status;
}

function landing_stripe_secret_key(): string
{
    $secret = landing_global_config()['stripe_secret_key'];
    if ($secret === '') {
        throw new RuntimeException('Stripe secret key is not configured.');
    }

    return $secret;
}

function landing_stripe_webhook_secret(): string
{
    $secret = landing_global_config()['stripe_webhook_secret'];
    if ($secret === '') {
        throw new RuntimeException('Stripe webhook secret is not configured.');
    }

    return $secret;
}

function landing_stripe_http_post_form(string $url, array $fields, array $headers = []): array
{
    $encoded = http_build_query($fields);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize Stripe transport.');
        }

        $defaultHeaders = array_merge([
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($encoded),
        ], $headers);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $defaultHeaders,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }

        if ($body === false) {
            throw new RuntimeException($error !== '' ? $error : 'Stripe request failed.');
        }

        return ['status' => $status, 'body' => (string) $body];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", array_merge([
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($encoded),
            ], $headers)),
            'content' => $encoded,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusLine = is_array($responseHeaders) && isset($responseHeaders[0]) ? (string) $responseHeaders[0] : '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $status = isset($matches[1]) ? (int) $matches[1] : 0;

    if ($body === false) {
        throw new RuntimeException('Stripe request failed.');
    }

    return ['status' => $status, 'body' => (string) $body];
}

function landing_stripe_create_checkout_session(array $paymentRequest, string $paymentToken): array
{
    $global = landing_global_config();
    $priceId = $global['stripe_price_id_priority_scan'];
    if ($priceId === '') {
        throw new RuntimeException('Stripe price id for priority scan is not configured.');
    }

    $requestId = (string) ($paymentRequest['request_id'] ?? '');
    if ($requestId === '') {
        throw new RuntimeException('Stripe checkout requires a valid request id.');
    }

    if ($paymentToken === '') {
        throw new RuntimeException('Stripe checkout requires a payment access token.');
    }

    $successUrl = landing_priority_payment_page_url([
        'request_id' => $requestId,
        'payment_token' => $paymentToken,
        'checkout' => 'success',
    ]) . '&session_id={CHECKOUT_SESSION_ID}';

    $cancelUrl = landing_priority_payment_page_url([
        'request_id' => $requestId,
        'payment_token' => $paymentToken,
        'checkout' => 'cancel',
    ]);

    $fields = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'client_reference_id' => $requestId,
        'line_items[0][price]' => $priceId,
        'line_items[0][quantity]' => '1',
        'metadata[request_id]' => $requestId,
        'metadata[product_code]' => (string) ($paymentRequest['product_code'] ?? ''),
        'metadata[delivery_tier]' => (string) ($paymentRequest['delivery_tier'] ?? 'priority'),
        'metadata[crm_reference]' => (string) ($paymentRequest['crm_reference'] ?? ''),
        'metadata[service]' => (string) ($paymentRequest['service'] ?? 'external-security-scan'),
        'payment_intent_data[metadata][request_id]' => $requestId,
        'payment_intent_data[metadata][product_code]' => (string) ($paymentRequest['product_code'] ?? ''),
        'payment_intent_data[metadata][delivery_tier]' => (string) ($paymentRequest['delivery_tier'] ?? 'priority'),
        'payment_intent_data[metadata][crm_reference]' => (string) ($paymentRequest['crm_reference'] ?? ''),
    ];

    $customerEmail = trim((string) ($paymentRequest['work_email'] ?? ''));
    if ($customerEmail !== '') {
        $fields['customer_email'] = $customerEmail;
    }

    $response = landing_stripe_http_post_form(
        'https://api.stripe.com/v1/checkout/sessions',
        $fields,
        ['Authorization: Bearer ' . landing_stripe_secret_key()]
    );

    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe returned an unreadable checkout session response.');
    }

    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300 || !empty($decoded['error'])) {
        $message = is_array($decoded['error'] ?? null)
            ? (string) ($decoded['error']['message'] ?? 'Stripe checkout session creation failed.')
            : 'Stripe checkout session creation failed.';
        throw new RuntimeException($message);
    }

    $sessionId = (string) ($decoded['id'] ?? '');
    $checkoutUrl = (string) ($decoded['url'] ?? '');
    if ($sessionId === '' || $checkoutUrl === '') {
        throw new RuntimeException('Stripe checkout session response is missing required fields.');
    }

    return [
        'stripe_checkout_session_id' => $sessionId,
        'checkout_url' => $checkoutUrl,
        'checkout_expires_at' => isset($decoded['expires_at']) ? gmdate(DATE_ATOM, (int) $decoded['expires_at']) : '',
        'amount_cents' => (int) ($decoded['amount_total'] ?? ($paymentRequest['amount_cents'] ?? 0)),
        'currency' => strtolower((string) ($decoded['currency'] ?? ($paymentRequest['currency'] ?? $global['stripe_currency']))),
        'payment_reference' => (string) ($decoded['payment_intent'] ?? ''),
    ];
}

function landing_stripe_verify_event(string $payload, string $signatureHeader): array
{
    $secret = landing_stripe_webhook_secret();
    if ($payload === '' || $signatureHeader === '') {
        throw new RuntimeException('Missing Stripe webhook payload or signature.');
    }

    $parts = [];
    foreach (explode(',', $signatureHeader) as $chunk) {
        $pair = explode('=', trim($chunk), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]][] = $pair[1];
        }
    }

    $timestamp = isset($parts['t'][0]) ? (string) $parts['t'][0] : '';
    $signatures = array_values(array_filter($parts['v1'] ?? [], 'is_string'));
    if ($timestamp === '' || $signatures === []) {
        throw new RuntimeException('Malformed Stripe signature header.');
    }

    if (abs(time() - (int) $timestamp) > 300) {
        throw new RuntimeException('Stripe webhook timestamp is outside the allowed tolerance.');
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    $verified = false;
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            $verified = true;
            break;
        }
    }

    if (!$verified) {
        throw new RuntimeException('Stripe webhook signature verification failed.');
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe webhook payload is not valid JSON.');
    }

    return $decoded;
}
