<?php
declare(strict_types=1);

function landing_audit_log(string $decision, string $reasonCode, array $context): void
{
    $global = landing_global_config();
    landing_ensure_directory(dirname($global['audit_log_file']));

    $entry = [
        'ts' => gmdate(DATE_ATOM),
        'decision' => $decision,
        'reason_code' => $reasonCode,
        'ip' => (string) ($context['client_ip'] ?? 'unknown'),
        'user_agent' => substr((string) ($context['user_agent'] ?? ''), 0, 255),
        'tier' => (string) ($context['delivery_tier'] ?? ''),
        'domain' => (string) ($context['business_domain'] ?? ''),
        'service' => (string) ($context['service'] ?? ''),
        'request_id' => (string) ($context['request_id'] ?? ''),
    ];

    if (landing_is_local_development() && !empty($context['debug_fields']) && is_array($context['debug_fields'])) {
        $entry['debug_fields'] = array_values(array_filter($context['debug_fields'], 'is_string'));
    }

    file_put_contents(
        $global['audit_log_file'],
        json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
