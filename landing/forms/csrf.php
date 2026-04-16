<?php
declare(strict_types=1);

function landing_session_ttl(): int
{
    return 3600;
}

function landing_ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) landing_session_ttl());
    session_set_cookie_params([
        'lifetime' => landing_session_ttl(),
        'path' => '/',
        'secure' => landing_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function landing_csrf_token_is_fresh(): bool
{
    $issuedAt = $_SESSION['_landing_csrf_issued_at'] ?? null;
    return is_int($issuedAt) && (time() - $issuedAt) <= landing_session_ttl();
}

function landing_rotate_csrf_token(): string
{
    landing_ensure_session_started();
    $_SESSION['_landing_csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['_landing_csrf_issued_at'] = time();

    return $_SESSION['_landing_csrf_token'];
}

function landing_csrf_token(): string
{
    landing_ensure_session_started();

    if (
        empty($_SESSION['_landing_csrf_token']) ||
        !is_string($_SESSION['_landing_csrf_token']) ||
        !landing_csrf_token_is_fresh()
    ) {
        return landing_rotate_csrf_token();
    }

    return $_SESSION['_landing_csrf_token'];
}

function landing_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . landing_e(landing_csrf_token()) . '">';
}

function landing_verify_csrf_token(?string $token): bool
{
    landing_ensure_session_started();

    if (
        !isset($_SESSION['_landing_csrf_token']) ||
        !is_string($_SESSION['_landing_csrf_token']) ||
        $token === null ||
        !landing_csrf_token_is_fresh()
    ) {
        unset($_SESSION['_landing_csrf_token'], $_SESSION['_landing_csrf_issued_at']);
        return false;
    }

    $valid = hash_equals($_SESSION['_landing_csrf_token'], $token);
    unset($_SESSION['_landing_csrf_token'], $_SESSION['_landing_csrf_issued_at']);

    return $valid;
}

function landing_request_origin_allowed(array $server): bool
{
    $global = landing_global_config();
    $allowed = $global['allowed_origins'];

    $origin = trim((string) ($server['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string) ($server['HTTP_REFERER'] ?? ''));
    $candidate = $origin !== '' ? $origin : $referer;
    $decision = 'rejected';
    $reason = 'candidate_missing';

    // DO NOT allow empty origin in production-like flows
    if ($candidate === '') {
        if (landing_is_local_development()) {
            error_log('landing origin decision: ' . json_encode([
                'candidate' => $candidate,
                'origin' => $origin,
                'referer' => $referer,
                'allowed_origins' => $allowed,
                'decision' => $decision,
                'reason' => $reason,
            ], JSON_UNESCAPED_SLASHES));
        }
        return false;
    }

    $candidateOrigin = rtrim((string) preg_replace('/^(https?:\/\/[^\/?#]+).*$/i', '$1', $candidate), '/');
    if (!preg_match('#^https?://#i', $candidateOrigin)) {
        $candidateOrigin = '';
    }

    if ($candidateOrigin === '') {
        $reason = 'candidate_invalid';
        if (landing_is_local_development()) {
            error_log('landing origin decision: ' . json_encode([
                'candidate' => $candidate,
                'origin' => $origin,
                'referer' => $referer,
                'allowed_origins' => $allowed,
                'decision' => $decision,
                'reason' => $reason,
            ], JSON_UNESCAPED_SLASHES));
        }
        return false;
    }

    foreach ($allowed as $allowedOrigin) {
        if (is_string($allowedOrigin) && $allowedOrigin !== '' && hash_equals(rtrim($allowedOrigin, '/'), $candidateOrigin)) {
            $decision = 'accepted';
            $reason = 'allowed_origin_match';
            if (landing_is_local_development()) {
                error_log('landing origin decision: ' . json_encode([
                    'candidate' => $candidate,
                    'candidate_origin' => $candidateOrigin,
                    'origin' => $origin,
                    'referer' => $referer,
                    'allowed_origins' => $allowed,
                    'decision' => $decision,
                    'reason' => $reason,
                ], JSON_UNESCAPED_SLASHES));
            }
            return true;
        }
    }

    $reason = 'allowed_origin_miss';
    if (landing_is_local_development()) {
        error_log('landing origin decision: ' . json_encode([
            'candidate' => $candidate,
            'candidate_origin' => $candidateOrigin,
            'origin' => $origin,
            'referer' => $referer,
            'allowed_origins' => $allowed,
            'decision' => $decision,
            'reason' => $reason,
        ], JSON_UNESCAPED_SLASHES));
    }

    return false;
}
