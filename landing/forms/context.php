<?php
declare(strict_types=1);

function landing_issue_form_context(string $service, string $deliveryTier = 'standard'): array
{
    if (!in_array($service, landing_service_allowlist(), true)) {
        throw new InvalidArgumentException('Service is not allowlisted for landing forms.');
    }

    $deliveryTier = strtolower(trim($deliveryTier));
    if (!in_array($deliveryTier, ['standard', 'priority'], true)) {
        throw new InvalidArgumentException('Delivery tier is not valid for landing forms.');
    }

    landing_ensure_session_started();

    $issuedAt = time();
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['_landing_service_nonces'][$nonce] = [
        'service' => $service,
        'delivery_tier' => $deliveryTier,
        'issued_at' => $issuedAt,
    ];

    $message = $service . '|' . $deliveryTier . '|' . $issuedAt . '|' . $nonce;
    $signature = hash_hmac('sha256', $message, landing_required_secret('service_signing_secret'));

    return [
        'service' => $service,
        'delivery_tier' => $deliveryTier,
        'issued_at' => (string) $issuedAt,
        'nonce' => $nonce,
        'signature' => $signature,
    ];
}

function landing_resolve_service_context(array $input): array
{
    landing_ensure_session_started();

    $service = trim((string) ($input['service'] ?? ''));
    $deliveryTier = strtolower(trim((string) ($input['service_delivery_tier'] ?? '')));
    $issuedAt = trim((string) ($input['service_issued_at'] ?? ''));
    $nonce = trim((string) ($input['service_nonce'] ?? ''));
    $signature = trim((string) ($input['service_signature'] ?? ''));

    if ($service === '' || $issuedAt === '' || $nonce === '' || $signature === '') {
        throw new RuntimeException('Missing landing service context.');
    }

    if (!ctype_digit($issuedAt)) {
        throw new RuntimeException('Invalid landing service timestamp.');
    }

    if (!in_array($service, landing_service_allowlist(), true)) {
        throw new RuntimeException('Landing service is not allowlisted.');
    }

    $issuedAtInt = (int) $issuedAt;
    if (abs(time() - $issuedAtInt) > landing_session_ttl()) {
        throw new RuntimeException('Landing service context expired.');
    }

    $nonceState = $_SESSION['_landing_service_nonces'][$nonce] ?? null;
    if (!is_array($nonceState)) {
        throw new RuntimeException('Landing service nonce missing or already consumed.');
    }

    $nonceTier = strtolower(trim((string) ($nonceState['delivery_tier'] ?? '')));
    if ($deliveryTier === '') {
        $deliveryTier = $nonceTier;
    }

    if (!in_array($deliveryTier, ['standard', 'priority'], true)) {
        unset($_SESSION['_landing_service_nonces'][$nonce]);
        throw new RuntimeException('Landing service tier is invalid.');
    }

    if (
        ($nonceState['service'] ?? null) !== $service
        || $nonceTier !== $deliveryTier
        || (int) ($nonceState['issued_at'] ?? 0) !== $issuedAtInt
    ) {
        unset($_SESSION['_landing_service_nonces'][$nonce]);
        throw new RuntimeException('Landing service nonce does not match context.');
    }

    $expected = hash_hmac('sha256', $service . '|' . $deliveryTier . '|' . $issuedAt . '|' . $nonce, landing_required_secret('service_signing_secret'));
    if (!hash_equals($expected, $signature)) {
        unset($_SESSION['_landing_service_nonces'][$nonce]);
        throw new RuntimeException('Landing service context signature invalid.');
    }

    unset($_SESSION['_landing_service_nonces'][$nonce]);

    return [
        'service' => $service,
        'delivery_tier' => $deliveryTier,
        'config' => landing_service_config($service),
    ];
}
