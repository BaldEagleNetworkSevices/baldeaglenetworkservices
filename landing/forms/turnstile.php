<?php
declare(strict_types=1);

function landing_turnstile_policy(array $serviceConfig, string $deliveryTier): array
{
    $global = landing_global_config();
    $turnstileConfig = $serviceConfig['turnstile'] ?? [];
    $standardRequired = (bool) ($turnstileConfig['standard_required'] ?? false);

    return [
        'priority' => [
            'mode' => 'REQUIRED_FOR_PRIORITY',
            'enforced' => $deliveryTier === 'priority',
        ],
        'standard' => [
            'mode' => $standardRequired ? 'REQUIRED' : 'RECOMMENDED',
            'enforced' => $standardRequired || !$global['standard_turnstile_bypass_allowed'],
        ],
    ];
}

function landing_turnstile_required(array $serviceConfig, string $deliveryTier): bool
{
    if (landing_is_local_development()) {
        return false;
    }

    $policy = landing_turnstile_policy($serviceConfig, $deliveryTier);
    return $deliveryTier === 'priority'
        ? $policy['priority']['enforced']
        : $policy['standard']['enforced'];
}

function landing_turnstile_site_key(): string
{
    return landing_global_config()['turnstile_site_key'];
}

function landing_turnstile_expected_hostname(): string
{
    $host = parse_url(landing_base_url(), PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : '';
}

function landing_verify_turnstile_token(array $serviceConfig, string $deliveryTier, ?string $token, string $clientIp): array
{
    if (landing_is_local_development()) {
        return [
            'success' => true,
            'reason_code' => 'turnstile_local_bypass',
        ];
    }

    $required = landing_turnstile_required($serviceConfig, $deliveryTier);
    $token = trim((string) $token);
    $secret = landing_global_config()['turnstile_secret_key'];

    if ($token === '') {
        return [
            'success' => !$required,
            'reason_code' => $required ? 'turnstile_missing' : 'turnstile_optional_missing',
        ];
    }

    if ($secret === '') {
        return [
            'success' => false,
            'reason_code' => 'turnstile_secret_missing',
        ];
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $clientIp,
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init(landing_global_config()['turnstile_verify_url']);
        if ($ch === false) {
            return ['success' => false, 'reason_code' => 'turnstile_client_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(landing_global_config()['turnstile_verify_url'], false, $context);
        $curlError = '';
        $statusCode = 0;
    }

    if (!is_string($response) || $response === '') {
        return ['success' => false, 'reason_code' => 'turnstile_no_response', 'detail' => $curlError];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'reason_code' => 'turnstile_invalid_response'];
    }

    if ($statusCode >= 400 || empty($decoded['success'])) {
        return [
            'success' => false,
            'reason_code' => 'turnstile_verification_failed',
            'errors' => array_values(array_filter((array) ($decoded['error-codes'] ?? []), 'is_string')),
        ];
    }

    $expectedHostname = landing_turnstile_expected_hostname();
    $actualHostname = strtolower((string) ($decoded['hostname'] ?? ''));
    if ($expectedHostname === '' || $actualHostname === '' || !hash_equals($expectedHostname, $actualHostname)) {
        return [
            'success' => false,
            'reason_code' => 'turnstile_hostname_mismatch',
        ];
    }

    $expectedAction = trim((string) (($serviceConfig['turnstile']['action'] ?? '')));
    if ($expectedAction !== '') {
        $actualAction = (string) ($decoded['action'] ?? '');
        if (!hash_equals($expectedAction, $actualAction)) {
            return [
                'success' => false,
                'reason_code' => 'turnstile_action_mismatch',
            ];
        }
    }

    return ['success' => true, 'reason_code' => 'turnstile_verified'];
}
