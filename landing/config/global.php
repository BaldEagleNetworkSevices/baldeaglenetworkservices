<?php
declare(strict_types=1);

function landing_env(string $key, ?string $default = null): ?string
{
    landing_load_project_env_files();
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return is_string($value) ? trim($value) : $default;
}

function landing_load_project_env_files(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $loaded = true;
    $root = dirname(__DIR__, 2);
    foreach ([$root . '/.env', $root . '/.env.local'] as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function landing_env_bool(string $key, bool $default = false): bool
{
    $value = landing_env($key);
    if ($value === null) {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function landing_root(): string
{
    return realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
}

function landing_require(string $relativePath): void
{
    require_once landing_root() . '/' . ltrim($relativePath, '/');
}

function landing_global_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $projectRoot = dirname(landing_root());
    $isLocal = landing_is_local_development();
    $csrfSecret = landing_resolved_secret_value('LANDING_CSRF_SECRET', 'csrf_secret', $isLocal);
    $serviceSigningSecret = landing_resolved_secret_value('LANDING_SERVICE_SIGNING_SECRET', 'service_signing_secret', $isLocal);
    $allowedOrigins = landing_resolve_allowed_origins($isLocal);

    $config = [
        'brand_name' => 'Bald Eagle Network Services',
        'default_host' => landing_env('LANDING_DEFAULT_HOST', 'www.baldeaglenetworkservices.com'),
        'site_url' => landing_env('LANDING_SITE_URL', ''),
        'currency' => 'USD',
        'storage_dir' => $projectRoot . '/storage/landing',
        'queue_file' => $projectRoot . '/storage/landing/intake-queue.ndjson',
        'audit_log_file' => $projectRoot . '/storage/landing/audit.ndjson',
        'state_file' => $projectRoot . '/storage/landing/intake-state.json',
        'payment_state_file' => $projectRoot . '/storage/landing/payment-state.json',
        'payment_event_log_file' => $projectRoot . '/storage/landing/payment-events.ndjson',
        'email_hook_queue_file' => $projectRoot . '/storage/landing/email-events.ndjson',
        'qbo_hook_queue_file' => $projectRoot . '/storage/landing/qbo-events.ndjson',
        'crm_payment_hook_queue_file' => $projectRoot . '/storage/landing/crm-payment-events.ndjson',
        'report_token_store' => $projectRoot . '/storage/landing/report-tokens.json',
        'report_base_dir' => $projectRoot . '/storage/landing/reports',
        'csrf_secret' => $csrfSecret,
        'service_signing_secret' => $serviceSigningSecret,
        'turnstile_site_key' => landing_env('TURNSTILE_SITE_KEY', ''),
        'turnstile_secret_key' => landing_env('TURNSTILE_SECRET_KEY', ''),
        'turnstile_verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'allowed_origins' => $allowedOrigins,
        'standard_turnstile_bypass_allowed' => landing_env_bool('LANDING_STANDARD_TURNSTILE_BYPASS', true),
        'report_ttl_hours' => [
            'priority' => 24,
            'standard_min' => 120,
            'standard_max' => 168,
        ],
        'stripe_secret_key' => landing_env('STRIPE_SECRET_KEY', ''),
        'stripe_webhook_secret' => landing_env('STRIPE_WEBHOOK_SECRET', ''),
        'stripe_price_id_priority_scan' => landing_env('STRIPE_PRICE_ID_PRIORITY_SCAN', ''),
        'stripe_currency' => strtolower((string) landing_env('STRIPE_CURRENCY', 'usd')),
    ];

    return $config;
}

function landing_is_local_development(): bool
{
    if (function_exists('ben_is_local_development')) {
        return ben_is_local_development();
    }

    $explicitPhpPaths = landing_env('USE_PHP_PATHS');
    if ($explicitPhpPaths !== null) {
        return landing_env_bool('USE_PHP_PATHS', false);
    }

    $appEnv = strtolower((string) (landing_env('SITE_ENV') ?? landing_env('APP_ENV') ?? ''));
    if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
        return true;
    }

    if (in_array($appEnv, ['production', 'prod', 'staging', 'stage'], true)) {
        return false;
    }

    if (PHP_SAPI === 'cli') {
        return true;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? '';

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    if ($host === 'network.avalanche') {
        return true;
    }

    if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
        return true;
    }

    return (bool) preg_match('/\.ngrok-free\.(dev|app)$/i', $host);
}

function landing_prefers_php_paths(): bool
{
    if (function_exists('ben_prefer_php_paths')) {
        return ben_prefer_php_paths();
    }

    $explicitPhpPaths = landing_env('USE_PHP_PATHS');
    if ($explicitPhpPaths !== null) {
        return landing_env_bool('USE_PHP_PATHS', false);
    }

    if (!empty($_SERVER['HTTP_HOST']) && landing_host_is_current_dev_origin((string) $_SERVER['HTTP_HOST'])) {
        return true;
    }

    $appEnv = strtolower((string) (landing_env('SITE_ENV') ?? landing_env('APP_ENV') ?? ''));
    if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
        return true;
    }

    if (in_array($appEnv, ['production', 'prod', 'staging', 'stage'], true)) {
        return false;
    }

    if (PHP_SAPI === 'cli') {
        return true;
    }

    return landing_host_is_current_dev_origin((string) ($_SERVER['HTTP_HOST'] ?? ''));
}

function landing_local_fallback_secret(string $keyName): string
{
    $seedParts = [
        $keyName,
        landing_root(),
        php_uname('n') ?: 'local',
        PHP_VERSION,
    ];

    return hash('sha256', implode('|', $seedParts));
}

function landing_resolved_secret_value(string $envKey, string $secretName, bool $isLocal): string
{
    $value = landing_env($envKey, landing_env('APP_SECRET', 'change-me'));

    if ($value !== null && $value !== '' && $value !== 'change-me') {
        return $value;
    }

    if ($isLocal) {
        return landing_local_fallback_secret($secretName);
    }

    return 'change-me';
}

function landing_service_config(string $service): array
{
    $configPath = __DIR__ . '/' . $service . '.php';
    if (!is_file($configPath)) {
        throw new InvalidArgumentException('Unknown landing service config: ' . $service);
    }

    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException('Landing service config must return an array.');
    }

    return $config;
}

function landing_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function landing_base_url(): string
{
    $global = landing_global_config();
    $configured = trim((string) ($global['site_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    if (!landing_is_local_development()) {
        return 'https://baldeaglenetworkservices.com';
    }

    $host = $_SERVER['HTTP_HOST'] ?? $global['default_host'];
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto === 'https') {
        $scheme = 'https';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    }

    return rtrim($scheme . '://' . preg_replace('/[^a-z0-9\.\-:]/i', '', (string) $host), '/');
}

function landing_request_base_url(): string
{
    $origin = landing_origin_from_server($_SERVER);
    if ($origin !== '') {
        return $origin;
    }

    return landing_base_url();
}

function landing_request_host(): string
{
    $host = preg_replace('/[^a-z0-9\.\-:]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (is_string($host) && $host !== '') {
        return strtolower($host);
    }

    $originHost = parse_url(landing_request_base_url(), PHP_URL_HOST);
    return is_string($originHost) ? strtolower($originHost) : '';
}

function landing_origin_from_server(array $server): string
{
    $host = preg_replace('/[^a-z0-9\.\-:]/i', '', (string) ($server['HTTP_HOST'] ?? ''));
    if ($host === '' || $host === null) {
        return '';
    }

    $forwardedProto = strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto === 'https') {
        $scheme = 'https';
    } else {
        $https = strtolower((string) ($server['HTTPS'] ?? ''));
        $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
    }

    return rtrim($scheme . '://' . $host, '/');
}

function landing_host_is_current_dev_origin(string $host): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
    if ($host === '') {
        return false;
    }

    if (in_array($host, ['localhost', '127.0.0.1', '::1', 'network.avalanche'], true)) {
        return true;
    }

    if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
        return true;
    }

    return (bool) preg_match('/\.ngrok-free\.(dev|app)$/i', $host);
}

function landing_resolve_allowed_origins(bool $isLocal): array
{
    $configured = array_values(array_filter(array_map('trim', explode(',', landing_env('LANDING_ALLOWED_ORIGINS', '')))));
    $defaultOrigin = trim((string) landing_env('LANDING_SITE_URL', ''));
    if ($defaultOrigin === '') {
        $defaultOrigin = $isLocal ? '' : 'https://baldeaglenetworkservices.com';
    }

    $activeOrigin = landing_origin_from_server($_SERVER);
    $activeHost = (string) parse_url($activeOrigin, PHP_URL_HOST);

    if ($activeOrigin !== '' && ($isLocal || landing_host_is_current_dev_origin($activeHost))) {
        $configured[] = $activeOrigin;
    }

    if (!$isLocal) {
        if ($defaultOrigin !== '') {
            $configured[] = rtrim($defaultOrigin, '/');
        }

        return array_values(array_unique(array_filter($configured, static fn (mixed $origin): bool => is_string($origin) && $origin !== '')));
    }

    if ($activeOrigin !== '' && landing_host_is_current_dev_origin($activeHost)) {
        $configured[] = $activeOrigin;
    }

    if ($defaultOrigin !== '' && landing_host_is_current_dev_origin((string) parse_url($defaultOrigin, PHP_URL_HOST))) {
        $configured[] = rtrim($defaultOrigin, '/');
    }

    return array_values(array_unique(array_filter($configured, static fn (mixed $origin): bool => is_string($origin) && $origin !== '')));
}

function landing_url(string $path = ''): string
{
    return landing_request_base_url() . '/' . ltrim($path, '/');
}

function landing_is_https(): bool
{
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto === 'https') {
        return true;
    }

    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

function landing_ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create landing storage directory: ' . $path);
    }
}

function landing_service_allowlist(): array
{
    $files = glob(__DIR__ . '/*.php') ?: [];
    $services = [];

    foreach ($files as $file) {
        $name = basename($file, '.php');
        if ($name === 'global') {
            continue;
        }

        $services[] = $name;
    }

    return $services;
}

function landing_required_secret(string $keyName): string
{
    $value = (string) landing_global_config()[$keyName];
    if ($value === '' || $value === 'change-me') {
        if (landing_is_local_development()) {
            return landing_local_fallback_secret($keyName);
        }

        throw new RuntimeException('Required landing secret is not configured: ' . $keyName);
    }

    return $value;
}
