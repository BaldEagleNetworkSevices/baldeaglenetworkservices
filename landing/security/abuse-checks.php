<?php
declare(strict_types=1);

function landing_request_is_allowed(array $server): bool
{
    $contentType = strtolower((string) ($server['CONTENT_TYPE'] ?? ''));
    if ($contentType !== '' && !str_starts_with($contentType, 'application/x-www-form-urlencoded') && !str_starts_with($contentType, 'multipart/form-data')) {
        return false;
    }

    $userAgent = trim((string) ($server['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent === '') {
        return false;
    }

    return true;
}

function landing_calculate_abuse_score(array $input, array $requestMeta, array $errors, bool $honeypotTriggered): int
{
    $score = 0;

    if ($honeypotTriggered) {
        $score += 60;
    }

    if ($errors !== []) {
        $score += 20;
    }

    $userAgent = strtolower((string) ($requestMeta['user_agent'] ?? ''));
    if ($userAgent === '' || str_contains($userAgent, 'curl') || str_contains($userAgent, 'python-requests')) {
        $score += 20;
    }

    $payload = strtolower(json_encode($input, JSON_UNESCAPED_SLASHES) ?: '');
    foreach (['password', 'api_key', 'secret', 'mfa', 'token'] as $marker) {
        if (str_contains($payload, $marker)) {
            $score += 30;
        }
    }

    return min($score, 100);
}

function landing_abuse_rejection_reason(int $score): ?string
{
    return $score >= 50 ? 'abuse_score_rejected' : null;
}
