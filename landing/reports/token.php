<?php
declare(strict_types=1);

landing_require('security/state-store.php');

function landing_report_token_policy(string $tier = 'standard'): array
{
    $global = landing_global_config();
    $ttlHours = $tier === 'priority'
        ? (int) $global['report_ttl_hours']['priority']
        : (int) $global['report_ttl_hours']['standard_max'];

    return [
        'token_shape' => 'Opaque random identifier plus server-side signature binding',
        'predictability' => 'No sequential or guessable report URLs',
        'revocation' => 'Required',
        'usage_controls' => 'Single-use or limited-use supported',
        'ttl_hours' => $ttlHours,
        'server_enforcement' => 'Expiration must be checked server-side on every access',
    ];
}

function landing_issue_report_token(string $service, string $tier, string $relativePath, ?int $maxUses = 1): array
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $tokenHash = hash('sha256', $token);
    $expiresAt = time() + (landing_report_token_policy($tier)['ttl_hours'] * 3600);

    landing_with_locked_json_store('report_tokens', function (array &$state) use ($tokenHash, $service, $tier, $relativePath, $expiresAt, $maxUses): void {
        $state += ['tokens' => []];
        $state['tokens'][$tokenHash] = [
            'service' => $service,
            'tier' => $tier,
            'relative_path' => ltrim($relativePath, '/'),
            'expires_at' => $expiresAt,
            'revoked' => false,
            'uses' => 0,
            'max_uses' => $maxUses,
            'issued_at' => time(),
        ];
    });

    return [
        'token' => $token,
        'expires_at' => gmdate(DATE_ATOM, $expiresAt),
        'url' => landing_url('landing/reports/report-access.php?token=' . rawurlencode($token)),
    ];
}

function landing_revoke_report_token(string $token): bool
{
    $tokenHash = hash('sha256', $token);

    return landing_with_locked_json_store('report_tokens', function (array &$state) use ($tokenHash): bool {
        if (!isset($state['tokens'][$tokenHash]) || !is_array($state['tokens'][$tokenHash])) {
            return false;
        }

        $state['tokens'][$tokenHash]['revoked'] = true;
        return true;
    });
}

function landing_validate_report_token(string $token): array
{
    $tokenHash = hash('sha256', $token);

    return landing_with_locked_json_store('report_tokens', function (array &$state) use ($tokenHash): array {
        $state += ['tokens' => []];

        foreach ($state['tokens'] as $hash => $record) {
            if (!is_array($record)) {
                unset($state['tokens'][$hash]);
                continue;
            }
            if ((int) ($record['expires_at'] ?? 0) <= time()) {
                unset($state['tokens'][$hash]);
            }
        }

        $record = $state['tokens'][$tokenHash] ?? null;
        if (!is_array($record)) {
            return ['valid' => false, 'reason_code' => 'token_not_found'];
        }

        if (!empty($record['revoked'])) {
            return ['valid' => false, 'reason_code' => 'token_revoked'];
        }

        if ((int) ($record['expires_at'] ?? 0) <= time()) {
            unset($state['tokens'][$tokenHash]);
            return ['valid' => false, 'reason_code' => 'token_expired'];
        }

        $uses = (int) ($record['uses'] ?? 0);
        $maxUses = $record['max_uses'] === null ? null : (int) $record['max_uses'];
        if ($maxUses !== null && $uses >= $maxUses) {
            return ['valid' => false, 'reason_code' => 'token_use_limit_exceeded'];
        }

        return ['valid' => true, 'record' => $record];
    });
}

function landing_consume_report_token(string $token): array
{
    $tokenHash = hash('sha256', $token);

    return landing_with_locked_json_store('report_tokens', function (array &$state) use ($tokenHash): array {
        $state += ['tokens' => []];

        $record = $state['tokens'][$tokenHash] ?? null;
        if (!is_array($record)) {
            return ['valid' => false, 'reason_code' => 'token_not_found'];
        }

        if (!empty($record['revoked'])) {
            return ['valid' => false, 'reason_code' => 'token_revoked'];
        }

        if ((int) ($record['expires_at'] ?? 0) <= time()) {
            unset($state['tokens'][$tokenHash]);
            return ['valid' => false, 'reason_code' => 'token_expired'];
        }

        $uses = (int) ($record['uses'] ?? 0);
        $maxUses = $record['max_uses'] === null ? null : (int) $record['max_uses'];
        if ($maxUses !== null && $uses >= $maxUses) {
            return ['valid' => false, 'reason_code' => 'token_use_limit_exceeded'];
        }

        $record['uses'] = $uses + 1;
        $state['tokens'][$tokenHash] = $record;

        return ['valid' => true, 'record' => $record];
    });
}
