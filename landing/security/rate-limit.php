<?php
declare(strict_types=1);

landing_require('security/state-store.php');

function landing_collect_request_meta(array $server): array
{
    return [
        'client_ip' => trim((string) ($server['REMOTE_ADDR'] ?? 'unknown')),
        'user_agent' => substr(trim((string) ($server['HTTP_USER_AGENT'] ?? '')), 0, 255),
        'submitted_at' => gmdate(DATE_ATOM),
        'request_uri' => trim((string) ($server['REQUEST_URI'] ?? '')),
    ];
}

function landing_evaluate_rate_limits(array $requestMeta, array $clean, string $service): array
{
    $now = time();
    $payloadHash = landing_normalized_payload_hash($clean, $service);
    $ipKey = $requestMeta['client_ip'] ?? 'unknown';
    $emailKey = $clean['work_email'] ?? '';
    $domainKey = $clean['business_domain'] ?? '';

    return landing_with_locked_json_store('intake', function (array &$state) use ($now, $payloadHash, $ipKey, $emailKey, $domainKey): array {
        $state += [
            'ip_attempts' => [],
            'email_attempts' => [],
            'domain_attempts' => [],
            'payload_hashes' => [],
            'ip_blocks' => [],
        ];

        foreach ($state['ip_blocks'] as $ip => $until) {
            if ((int) $until <= $now) {
                unset($state['ip_blocks'][$ip]);
            }
        }

        if (isset($state['ip_blocks'][$ipKey]) && (int) $state['ip_blocks'][$ipKey] > $now) {
            return ['accepted' => false, 'reason_code' => 'ip_temporarily_blocked'];
        }

        $state['ip_attempts'][$ipKey] = landing_prune_timestamps((array) ($state['ip_attempts'][$ipKey] ?? []), $now - 900);
        $state['email_attempts'][$emailKey] = landing_prune_timestamps((array) ($state['email_attempts'][$emailKey] ?? []), $now - 86400);
        $state['domain_attempts'][$domainKey] = landing_prune_timestamps((array) ($state['domain_attempts'][$domainKey] ?? []), $now - 86400);

        foreach ($state['payload_hashes'] as $hash => $timestamp) {
            if ((int) $timestamp <= ($now - 3600)) {
                unset($state['payload_hashes'][$hash]);
            }
        }

        $ipCount = count($state['ip_attempts'][$ipKey]);
        if ($ipCount >= 10) {
            $state['ip_blocks'][$ipKey] = $now + 3600;
            return ['accepted' => false, 'reason_code' => 'ip_burst_blocked'];
        }

        if ($ipCount >= 5) {
            $state['ip_attempts'][$ipKey][] = $now;
            return ['accepted' => false, 'reason_code' => 'ip_rate_limited'];
        }

        if ($emailKey !== '' && count($state['email_attempts'][$emailKey]) >= 3) {
            $state['ip_attempts'][$ipKey][] = $now;
            return ['accepted' => false, 'reason_code' => 'email_rate_limited'];
        }

        if ($domainKey !== '' && count($state['domain_attempts'][$domainKey]) >= 2) {
            $state['ip_attempts'][$ipKey][] = $now;
            return ['accepted' => false, 'reason_code' => 'domain_rate_limited'];
        }

        if (isset($state['payload_hashes'][$payloadHash])) {
            $state['ip_attempts'][$ipKey][] = $now;
            return ['accepted' => false, 'reason_code' => 'duplicate_payload'];
        }

        $state['ip_attempts'][$ipKey][] = $now;
        if ($emailKey !== '') {
            $state['email_attempts'][$emailKey][] = $now;
        }
        if ($domainKey !== '') {
            $state['domain_attempts'][$domainKey][] = $now;
        }
        $state['payload_hashes'][$payloadHash] = $now;

        return [
            'accepted' => true,
            'reason_code' => 'accepted',
            'payload_hash' => $payloadHash,
        ];
    });
}

function landing_prune_timestamps(array $timestamps, int $minTimestamp): array
{
    $kept = [];
    foreach ($timestamps as $timestamp) {
        $value = (int) $timestamp;
        if ($value > $minTimestamp) {
            $kept[] = $value;
        }
    }

    return $kept;
}
