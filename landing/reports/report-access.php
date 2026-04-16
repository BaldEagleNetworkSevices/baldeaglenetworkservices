<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/global.php';
landing_require('reports/token.php');

function landing_report_access_requirements(): array
{
    return [
        'token_policy' => landing_report_token_policy(),
        'rules' => [
            'No predictable URLs',
            'Server-side expiration enforcement on every request',
            'Revocation support required',
            'Single-use or limited-use strongly recommended',
        ],
    ];
}

function landing_resolve_report_file(array $record): ?string
{
    $global = landing_global_config();
    $base = realpath($global['report_base_dir']);
    if ($base === false) {
        return null;
    }

    $candidate = realpath($base . DIRECTORY_SEPARATOR . ltrim((string) ($record['relative_path'] ?? ''), '/'));
    if ($candidate === false || !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return is_file($candidate) ? $candidate : null;
}

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'report-access.php') {
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        http_response_code(400);
        exit('Invalid report link.');
    }

    $validation = landing_validate_report_token($token);
    if (empty($validation['valid'])) {
        http_response_code(404);
        exit('This report link is invalid or expired.');
    }

    $path = landing_resolve_report_file($validation['record']);
    if ($path === null) {
        http_response_code(404);
        exit('This report is unavailable.');
    }

    if (!is_readable($path)) {
        http_response_code(404);
        exit('This report is unavailable.');
    }

    $consumption = landing_consume_report_token($token);
    if (empty($consumption['valid'])) {
        http_response_code(404);
        exit('This report link is invalid or expired.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}
