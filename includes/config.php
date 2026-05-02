<?php
declare(strict_types=1);

if (!function_exists('site_config')) {
    function ben_load_project_env_files(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $loaded = true;
        $root = dirname(__DIR__);
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

    function ben_env(string $key, ?string $default = null): ?string
    {
        ben_load_project_env_files();
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return is_string($value) ? trim($value) : $default;
    }

    function ben_scheme(): string
    {
        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwarded === 'https') {
            return 'https';
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        return ($https !== '' && $https !== 'off') ? 'https' : 'http';
    }

    function ben_host(): string
    {
        $host = ben_env('HTTP_X_FORWARDED_HOST')
            ?? ben_env('HTTP_HOST')
            ?? ben_env('SERVER_NAME')
            ?? 'localhost';

        return preg_replace('/[^a-z0-9\.\-:]/i', '', $host) ?: 'localhost';
    }

    function ben_base_url(): string
    {
        $configured = ben_env('SITE_URL');
        if ($configured !== null) {
            return rtrim($configured, '/');
        }

        if (ben_is_local_development()) {
            return rtrim(ben_scheme() . '://' . ben_host(), '/');
        }

        return 'https://baldeaglenetworkservices.com';
    }

    function ben_csp_nonce(): string
    {
        static $nonce;
        if (is_string($nonce) && $nonce !== '') {
            return $nonce;
        }

        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        return $nonce;
    }

    function ben_active_config_source(): string
    {
        return __FILE__;
    }

    function ben_app_env(): string
    {
        return strtolower((string) (ben_env('SITE_ENV') ?? ben_env('APP_ENV') ?? ''));
    }

    function ben_host_is_localish(string $host): bool
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1', 'network.avalanche'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return true;
        }

        return (bool) preg_match('/\.ngrok-free\.(dev|app)$/i', $host);
    }

    function ben_has_explicit_request_host(): bool
    {
        return ben_env('HTTP_X_FORWARDED_HOST') !== null
            || ben_env('HTTP_HOST') !== null
            || ben_env('SERVER_NAME') !== null;
    }

    function ben_is_local_development(): bool
    {
        $explicitPhpPaths = ben_env('USE_PHP_PATHS');
        if ($explicitPhpPaths !== null) {
            return ben_env_bool('USE_PHP_PATHS', false);
        }

        $appEnv = ben_app_env();
        if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
            return true;
        }

        if (in_array($appEnv, ['production', 'prod', 'staging', 'stage'], true)) {
            return false;
        }

        if (PHP_SAPI === 'cli') {
            return true;
        }

        return ben_host_is_localish(ben_host());
    }

    function ben_prefer_php_paths(): bool
    {
        $explicitPhpPaths = ben_env('USE_PHP_PATHS');
        if ($explicitPhpPaths !== null) {
            return ben_env_bool('USE_PHP_PATHS', false);
        }

        if (ben_has_explicit_request_host() && ben_host_is_localish(ben_host())) {
            return true;
        }

        $appEnv = ben_app_env();
        if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
            return true;
        }

        if (in_array($appEnv, ['production', 'prod', 'staging', 'stage'], true)) {
            return false;
        }

        if (PHP_SAPI === 'cli') {
            return true;
        }

        return ben_host_is_localish(ben_host());
    }
    
    function ben_env_bool(string $key, bool $default = false): bool
    {
        $value = ben_env($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    function ben_default_intake_api_url(): string
    {
        if (ben_env_bool('USE_LOCAL_INTAKE_API', false)) {
            return 'http://127.0.0.1:5000/api/consult';
        }

        if (ben_is_local_development()) {
            return 'http://127.0.0.1:5000/api/consult';
        }

        return 'https://api-prod.baldeaglenetworkservices.com/api/consult';
    }

    function site_config(): array
    {
        static $config;

        if ($config !== null) {
            return $config;
        }

        $rootDir = dirname(__DIR__);
        $siteUrl = ben_base_url();

        if (!headers_sent()) {
            $nonce = ben_csp_nonce();
            $isLocal = ben_is_local_development();
            $isHttps = ben_scheme() === 'https';
            $csp = [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "img-src 'self' data: https:",
                "style-src 'self'",
                "script-src 'self' 'nonce-" . $nonce . "' https://challenges.cloudflare.com",
                "connect-src 'self' https://challenges.cloudflare.com",
                "frame-src 'self' https://challenges.cloudflare.com",
                "font-src 'self' data:",
            ];

            if ($isHttps && !$isLocal) {
                $csp[] = 'upgrade-insecure-requests';
            }

            header('Content-Security-Policy: ' . implode('; ', $csp));
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
            if ($isHttps && !$isLocal) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        }

        $config = [
            'site_name' => 'Bald Eagle Network Services',
            'site_url' => $siteUrl,
            'prefer_php_paths' => ben_prefer_php_paths(),
            'tagline' => 'Recovery readiness testing for Salt Lake metro small businesses',
            'business_email' => ben_env('BUSINESS_EMAIL', ''),
            'business_phone' => ben_env('BUSINESS_PHONE', ''),
            'contact_to_email' => ben_env('CONTACT_TO_EMAIL', ''),
            'from_email' => ben_env('FROM_EMAIL', 'no-reply@' . preg_replace('/:\d+$/', '', ben_host())),
            'service_area' => 'Salt Lake metro within a 30-mile radius',
            'city' => 'Salt Lake City',
            'region' => 'UT',
            'hours' => 'Mon-Fri 08:00-18:00',
            'submission_store' => $rootDir . '/storage/contact-submissions.ndjson',
            'crm_mode' => ben_env('CRM_MODE', 'queue_api'),
            'crm_log' => $rootDir . '/storage/crm-sync.log',
            'crm_required' => ben_env_bool('CRM_REQUIRED', true),
            'config_source' => ben_active_config_source(),
            'intake_api_url' => ben_env('INTAKE_API_URL', ben_default_intake_api_url()),
            'suitecrm_endpoint' => ben_env('SUITECRM_ENDPOINT', ''),
            'suitecrm_username' => ben_env('SUITECRM_USERNAME', ''),
            'suitecrm_password' => ben_env('SUITECRM_PASSWORD', ''),
            'suitecrm_assigned_user_id' => ben_env('SUITECRM_ASSIGNED_USER_ID', ''),
            'suitecrm_team_id' => ben_env('SUITECRM_TEAM_ID', ''),
            'suitecrm_team_set_id' => ben_env('SUITECRM_TEAM_SET_ID', ''),
            'suitecrm_campaign_id' => ben_env('SUITECRM_CAMPAIGN_ID', ''),
            'suitecrm_source' => ben_env('SUITECRM_SOURCE', 'Website'),
            'suitecrm_status' => ben_env('SUITECRM_STATUS', 'New'),
            'allowed_service_types' => [
                'risk-assessment' => 'Recovery Assessment',
                'backup-recovery-failure' => 'Backup Recovery Failure Review',
                'security-recovery-risk' => 'Security Recovery Risk Review',
                'backup-dr' => 'Backup Recovery Verification',
                'monthly-backup-verification' => 'Monthly Backup and Recovery Verification',
                'network-security' => 'Network Security Hardening',
                'voip' => 'Communications Continuity Review',
                'compliance' => 'Continuity & Control Review',
                'other' => 'General Consultation',
            ],
        ];

        return $config;
    }
}
