<?php
declare(strict_types=1);

landing_require('security/state-store.php');
landing_require('payments/hooks.php');

function landing_priority_payment_page_path(): string
{
    if (function_exists('landing_page_href')) {
        return landing_page_href('external-security-scan-priority-payment');
    }

    return '/landing/external-security-scan-priority-payment' . (landing_is_local_development() ? '.php' : '');
}

function landing_priority_payment_page_url(array $query = []): string
{
    $path = landing_priority_payment_page_path();
    if ($query === []) {
        return landing_url(ltrim($path, '/'));
    }

    return landing_url(ltrim($path, '/')) . '?' . http_build_query($query);
}

function landing_payment_store_defaults(array &$state): void
{
    if (!isset($state['requests']) || !is_array($state['requests'])) {
        $state['requests'] = [];
    }

    if (!isset($state['sessions']) || !is_array($state['sessions'])) {
        $state['sessions'] = [];
    }

    if (!isset($state['stripe_events']) || !is_array($state['stripe_events'])) {
        $state['stripe_events'] = [];
    }

    if (!isset($state['side_effects']) || !is_array($state['side_effects'])) {
        $state['side_effects'] = [];
    }
}

function landing_priority_pricing_details(array $serviceConfig): array
{
    $pricing = $serviceConfig['pricing'] ?? [];
    foreach ($pricing as $tier) {
        if (!is_array($tier) || strtolower((string) ($tier['tier'] ?? '')) !== 'priority') {
            continue;
        }

        $priceLabel = (string) ($tier['price'] ?? '$0');
        $numeric = preg_replace('/[^0-9.]/', '', $priceLabel) ?? '';
        $amountCents = (int) round(((float) $numeric) * 100);

        return [
            'price_label' => $priceLabel,
            'amount_cents' => max(0, $amountCents),
            'currency' => landing_global_config()['stripe_currency'],
            'delivery' => (string) ($tier['delivery'] ?? 'Priority turnaround'),
            'summary' => (string) ($tier['copy'] ?? ''),
            'points' => is_array($tier['points'] ?? null) ? $tier['points'] : [],
        ];
    }

    throw new RuntimeException('Priority pricing is not configured for this landing service.');
}

function landing_payment_amount_display(int $amountCents, string $currency): string
{
    $amount = number_format($amountCents / 100, 2);
    return strtoupper($currency) . ' ' . $amount;
}

function landing_register_priority_payment_request(array $serviceConfig, array $payload, array $crmResult): array
{
    if (strtolower((string) ($payload['delivery_tier'] ?? '')) !== 'priority') {
        throw new InvalidArgumentException('Priority payment registration is only valid for priority requests.');
    }

    $pricing = landing_priority_pricing_details($serviceConfig);
    $now = gmdate(DATE_ATOM);
    $requestId = (string) ($payload['request_id'] ?? '');

    if ($requestId === '') {
        throw new InvalidArgumentException('Priority payment registration requires a request_id.');
    }

    return landing_with_locked_json_store('payments', function (array &$state) use ($serviceConfig, $payload, $crmResult, $pricing, $now, $requestId): array {
        landing_payment_store_defaults($state);

        $existing = $state['requests'][$requestId] ?? [];
        $record = is_array($existing) ? $existing : [];
        $plainToken = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $plainToken);

        $record['request_id'] = $requestId;
        $record['service'] = (string) ($serviceConfig['slug'] ?? 'external-security-scan');
        $record['business_name'] = (string) ($payload['business_name'] ?? '');
        $record['business_domain'] = (string) ($payload['business_domain'] ?? '');
        $record['work_email'] = (string) ($payload['work_email'] ?? '');
        $record['delivery_tier'] = 'priority';
        $record['product_code'] = (string) ($payload['product_code'] ?? '');
        $record['campaign'] = (string) ($payload['campaign'] ?? '');
        $record['crm_reference'] = (string) ($crmResult['lead_id'] ?? ($record['crm_reference'] ?? ''));
        $record['turnaround_promise'] = $pricing['delivery'];
        $record['price_label'] = $pricing['price_label'];
        $record['amount_cents'] = $pricing['amount_cents'];
        $record['currency'] = $pricing['currency'];
        $record['payment_status'] = (string) ($record['payment_status'] ?? '');
        $record['created_at'] = (string) ($record['created_at'] ?? ($payload['submitted_at'] ?? $now));
        $record['updated_at'] = $now;
        $record['request_notes'] = (string) ($payload['request_notes'] ?? '');
        $record['payment_access_token_hash'] = $tokenHash;

        $state['requests'][$requestId] = $record;
        $record['payment_access_token'] = $plainToken;

        return $record;
    });
}

function landing_payment_request(string $requestId): ?array
{
    if ($requestId === '') {
        return null;
    }

    return landing_with_locked_json_store('payments', function (array &$state) use ($requestId): ?array {
        landing_payment_store_defaults($state);
        $record = $state['requests'][$requestId] ?? null;

        return is_array($record) ? $record : null;
    });
}

function landing_payment_request_with_token(string $requestId, string $paymentToken): ?array
{
    if ($requestId === '' || $paymentToken === '') {
        return null;
    }

    $candidate = landing_payment_request($requestId);
    if (!is_array($candidate)) {
        return null;
    }

    $expected = (string) ($candidate['payment_access_token_hash'] ?? '');
    if ($expected === '' || !hash_equals($expected, hash('sha256', $paymentToken))) {
        return null;
    }

    return $candidate;
}

function landing_payment_access_query(array $requestRecord): array
{
    $requestId = (string) ($requestRecord['request_id'] ?? '');
    $token = (string) ($requestRecord['payment_access_token'] ?? '');
    if ($requestId === '' || $token === '') {
        throw new RuntimeException('Payment access query requires request_id and payment token.');
    }

    return [
        'request_id' => $requestId,
        'payment_token' => $token,
    ];
}

function landing_active_checkout_session_for_request(array $state, array $request): ?array
{
    $status = (string) ($request['payment_status'] ?? '');
    $sessionId = (string) ($request['current_session_id'] ?? '');
    if ($status !== 'checkout_started' || $sessionId === '') {
        return null;
    }

    $session = $state['sessions'][$sessionId] ?? null;
    if (!is_array($session)) {
        return null;
    }

    $sessionStatus = (string) ($session['payment_status'] ?? '');
    $checkoutUrl = trim((string) ($session['checkout_url'] ?? ''));
    if ($sessionStatus !== 'checkout_started' || $checkoutUrl === '') {
        return null;
    }

    $expiresAt = strtotime((string) ($session['checkout_expires_at'] ?? ''));
    if ($expiresAt !== false && $expiresAt <= time()) {
        return null;
    }

    return $session;
}

function landing_begin_checkout_session_attempt(string $requestId, string $paymentToken): array
{
    if ($requestId === '' || $paymentToken === '') {
        throw new InvalidArgumentException('Checkout session attempt requires request_id and payment token.');
    }

    $now = gmdate(DATE_ATOM);
    $lockToken = bin2hex(random_bytes(16));

    return landing_with_locked_json_store('payments', function (array &$state) use ($requestId, $paymentToken, $now, $lockToken): array {
        landing_payment_store_defaults($state);

        $request = $state['requests'][$requestId] ?? null;
        if (!is_array($request)) {
            throw new RuntimeException('Unknown priority payment request.');
        }

        $expected = (string) ($request['payment_access_token_hash'] ?? '');
        if ($expected === '' || !hash_equals($expected, hash('sha256', $paymentToken))) {
            throw new RuntimeException('Invalid payment access token.');
        }

        if ((string) ($request['payment_status'] ?? '') === 'paid_priority') {
            return ['action' => 'paid', 'request' => $request];
        }

        $activeSession = landing_active_checkout_session_for_request($state, $request);
        if (is_array($activeSession)) {
            return ['action' => 'reuse', 'request' => $request, 'session' => $activeSession];
        }

        $creationLock = is_array($request['checkout_creation_lock'] ?? null) ? $request['checkout_creation_lock'] : [];
        $startedAt = strtotime((string) ($creationLock['started_at'] ?? ''));
        $isFreshLock = $startedAt !== false && $startedAt >= (time() - 120);
        if ($isFreshLock) {
            return ['action' => 'locked', 'request' => $request];
        }

        $request['checkout_creation_lock'] = [
            'lock_token' => $lockToken,
            'started_at' => $now,
        ];
        $request['updated_at'] = $now;
        $state['requests'][$requestId] = $request;

        return ['action' => 'create', 'request' => $request, 'lock_token' => $lockToken];
    });
}

function landing_finalize_checkout_session_attempt(string $requestId, string $paymentToken, string $lockToken, array $sessionData): array
{
    if ($requestId === '' || $paymentToken === '' || $lockToken === '') {
        throw new InvalidArgumentException('Checkout session finalization requires request_id, payment token, and lock token.');
    }

    $sessionId = trim((string) ($sessionData['stripe_checkout_session_id'] ?? ''));
    $checkoutUrl = trim((string) ($sessionData['checkout_url'] ?? ''));
    if ($sessionId === '' || $checkoutUrl === '') {
        throw new InvalidArgumentException('Checkout session finalization requires stripe session id and checkout url.');
    }

    $now = gmdate(DATE_ATOM);

    return landing_with_locked_json_store('payments', function (array &$state) use ($requestId, $paymentToken, $lockToken, $sessionData, $sessionId, $checkoutUrl, $now): array {
        landing_payment_store_defaults($state);

        $request = $state['requests'][$requestId] ?? null;
        if (!is_array($request)) {
            throw new RuntimeException('Unknown priority payment request.');
        }

        $expected = (string) ($request['payment_access_token_hash'] ?? '');
        if ($expected === '' || !hash_equals($expected, hash('sha256', $paymentToken))) {
            throw new RuntimeException('Invalid payment access token.');
        }

        $creationLock = is_array($request['checkout_creation_lock'] ?? null) ? $request['checkout_creation_lock'] : [];
        if ((string) ($creationLock['lock_token'] ?? '') !== $lockToken) {
            $activeSession = landing_active_checkout_session_for_request($state, $request);
            if (is_array($activeSession)) {
                return ['action' => 'reuse', 'request' => $request, 'session' => $activeSession];
            }
            throw new RuntimeException('Checkout creation lock was lost before finalization.');
        }

        unset($request['checkout_creation_lock']);
        $request['payment_status'] = 'checkout_started';
        $request['stripe_checkout_session_id'] = $sessionId;
        $request['current_session_id'] = $sessionId;
        $request['checkout_started_at'] = $now;
        $request['updated_at'] = $now;
        $request['amount_cents'] = (int) ($sessionData['amount_cents'] ?? ($request['amount_cents'] ?? 0));
        $request['currency'] = (string) ($sessionData['currency'] ?? ($request['currency'] ?? 'usd'));

        $session = [
            'request_id' => $requestId,
            'stripe_checkout_session_id' => $sessionId,
            'payment_status' => 'checkout_started',
            'checkout_started_at' => $now,
            'updated_at' => $now,
            'amount_cents' => (int) ($sessionData['amount_cents'] ?? 0),
            'currency' => (string) ($sessionData['currency'] ?? 'usd'),
            'checkout_url' => $checkoutUrl,
            'checkout_expires_at' => (string) ($sessionData['checkout_expires_at'] ?? ''),
            'payment_reference' => (string) ($sessionData['payment_reference'] ?? ''),
            'delivery_tier' => (string) ($sessionData['delivery_tier'] ?? ($request['delivery_tier'] ?? 'priority')),
            'product_code' => (string) ($sessionData['product_code'] ?? ($request['product_code'] ?? '')),
            'crm_reference' => (string) ($sessionData['crm_reference'] ?? ($request['crm_reference'] ?? '')),
        ];

        $state['requests'][$requestId] = $request;
        $state['sessions'][$sessionId] = $session;

        return ['action' => 'created', 'request' => $request, 'session' => $session];
    });
}

function landing_release_checkout_session_attempt(string $requestId, string $lockToken): void
{
    if ($requestId === '' || $lockToken === '') {
        return;
    }

    landing_with_locked_json_store('payments', function (array &$state) use ($requestId, $lockToken): null {
        landing_payment_store_defaults($state);
        $request = $state['requests'][$requestId] ?? null;
        if (!is_array($request)) {
            return null;
        }

        $creationLock = is_array($request['checkout_creation_lock'] ?? null) ? $request['checkout_creation_lock'] : [];
        if ((string) ($creationLock['lock_token'] ?? '') === $lockToken) {
            unset($request['checkout_creation_lock']);
            $request['updated_at'] = gmdate(DATE_ATOM);
            $state['requests'][$requestId] = $request;
        }

        return null;
    });
}

function landing_mark_payment_completed(string $sessionId, array $paymentData): array
{
    if ($sessionId === '') {
        throw new InvalidArgumentException('Payment completion requires a stripe session id.');
    }

    $now = gmdate(DATE_ATOM);

    return landing_with_locked_json_store('payments', function (array &$state) use ($sessionId, $paymentData, $now): array {
        landing_payment_store_defaults($state);

        if (!isset($state['sessions'][$sessionId]) || !is_array($state['sessions'][$sessionId])) {
            throw new RuntimeException('Unknown Stripe checkout session.');
        }

        $session = $state['sessions'][$sessionId];
        $requestId = (string) ($session['request_id'] ?? '');
        if ($requestId === '' || !isset($state['requests'][$requestId]) || !is_array($state['requests'][$requestId])) {
            throw new RuntimeException('Payment session is not linked to a valid request.');
        }

        $request = $state['requests'][$requestId];
        $amountCents = (int) ($paymentData['amount_cents'] ?? ($session['amount_cents'] ?? $request['amount_cents'] ?? 0));
        $currency = strtolower((string) ($paymentData['currency'] ?? ($session['currency'] ?? $request['currency'] ?? 'usd')));
        $paymentReference = (string) ($paymentData['payment_reference'] ?? '');

        $request['payment_status'] = 'paid_priority';
        $request['stripe_checkout_session_id'] = $sessionId;
        $request['current_session_id'] = $sessionId;
        $request['payment_reference'] = $paymentReference;
        $request['amount_cents'] = $amountCents;
        $request['currency'] = $currency;
        $request['payment_completed_at'] = $now;
        $request['updated_at'] = $now;

        $session['payment_status'] = 'paid_priority';
        $session['payment_reference'] = $paymentReference;
        $session['amount_cents'] = $amountCents;
        $session['currency'] = $currency;
        $session['payment_completed_at'] = $now;
        $session['updated_at'] = $now;

        $state['requests'][$requestId] = $request;
        $state['sessions'][$sessionId] = $session;

        return [
            'request' => $request,
            'session' => $session,
        ];
    });
}

function landing_mark_payment_failed(string $requestId, string $sessionId, array $paymentData = []): array
{
    $now = gmdate(DATE_ATOM);

    return landing_with_locked_json_store('payments', function (array &$state) use ($requestId, $sessionId, $paymentData, $now): array {
        landing_payment_store_defaults($state);

        if ($requestId === '' && $sessionId !== '' && isset($state['sessions'][$sessionId]['request_id'])) {
            $requestId = (string) $state['sessions'][$sessionId]['request_id'];
        }

        if ($requestId === '' || !isset($state['requests'][$requestId]) || !is_array($state['requests'][$requestId])) {
            throw new RuntimeException('Unknown payment request for failed payment.');
        }

        $request = $state['requests'][$requestId];
        $request['payment_status'] = 'payment_failed';
        $request['updated_at'] = $now;
        $request['payment_failed_at'] = $now;
        if (isset($paymentData['payment_reference'])) {
            $request['payment_reference'] = (string) $paymentData['payment_reference'];
        }

        $state['requests'][$requestId] = $request;

        $session = [];
        $effectiveSessionId = $sessionId !== '' ? $sessionId : (string) ($request['current_session_id'] ?? '');
        if ($effectiveSessionId !== '' && isset($state['sessions'][$effectiveSessionId]) && is_array($state['sessions'][$effectiveSessionId])) {
            $session = $state['sessions'][$effectiveSessionId];
            $session['payment_status'] = 'payment_failed';
            $session['updated_at'] = $now;
            $session['payment_failed_at'] = $now;
            if (isset($paymentData['payment_reference'])) {
                $session['payment_reference'] = (string) $paymentData['payment_reference'];
            }
            $state['sessions'][$effectiveSessionId] = $session;
        }

        return [
            'request' => $request,
            'session' => $session,
        ];
    });
}

function landing_mark_checkout_abandoned(string $requestId, string $sessionId = ''): array
{
    $now = gmdate(DATE_ATOM);

    return landing_with_locked_json_store('payments', function (array &$state) use ($requestId, $sessionId, $now): array {
        landing_payment_store_defaults($state);

        if ($requestId === '' || !isset($state['requests'][$requestId]) || !is_array($state['requests'][$requestId])) {
            throw new RuntimeException('Unknown payment request for abandonment.');
        }

        $request = $state['requests'][$requestId];
        if ((string) ($request['payment_status'] ?? '') !== 'paid_priority') {
            $request['payment_status'] = 'checkout_abandoned';
            $request['updated_at'] = $now;
            $request['checkout_abandoned_at'] = $now;
            $state['requests'][$requestId] = $request;
        }

        $session = [];
        $effectiveSessionId = $sessionId !== '' ? $sessionId : (string) ($request['current_session_id'] ?? '');
        if ($effectiveSessionId !== '' && isset($state['sessions'][$effectiveSessionId]) && is_array($state['sessions'][$effectiveSessionId])) {
            $session = $state['sessions'][$effectiveSessionId];
            if ((string) ($session['payment_status'] ?? '') !== 'paid_priority') {
                $session['payment_status'] = 'checkout_abandoned';
                $session['updated_at'] = $now;
                $session['checkout_abandoned_at'] = $now;
                $state['sessions'][$effectiveSessionId] = $session;
            }
        }

        return [
            'request' => $request,
            'session' => $session,
        ];
    });
}

function landing_has_processed_stripe_event(string $eventId): bool
{
    if ($eventId === '') {
        return false;
    }

    return landing_with_locked_json_store('payments', function (array &$state) use ($eventId): bool {
        landing_payment_store_defaults($state);
        return isset($state['stripe_events'][$eventId]);
    });
}

function landing_mark_stripe_event_processed(string $eventId): bool
{
    if ($eventId === '') {
        return false;
    }

    return landing_with_locked_json_store('payments', function (array &$state) use ($eventId): bool {
        landing_payment_store_defaults($state);

        if (isset($state['stripe_events'][$eventId])) {
            return false;
        }

        $state['stripe_events'][$eventId] = gmdate(DATE_ATOM);
        return true;
    });
}

function landing_mark_payment_side_effect_once(string $sideEffectKey): bool
{
    if ($sideEffectKey === '') {
        return false;
    }

    return landing_with_locked_json_store('payments', function (array &$state) use ($sideEffectKey): bool {
        landing_payment_store_defaults($state);

        if (isset($state['side_effects'][$sideEffectKey])) {
            return false;
        }

        $state['side_effects'][$sideEffectKey] = gmdate(DATE_ATOM);
        return true;
    });
}
