<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$fallbackTarget = null;

if (is_string($requestPath) && preg_match('#^/landing/([A-Za-z0-9-]+)/?$#', $requestPath, $matches) === 1) {
    $candidate = __DIR__ . '/landing/' . $matches[1] . '.php';
    if (is_file($candidate)) {
        $fallbackTarget = $candidate;
    }
}

if (is_string($fallbackTarget) && $fallbackTarget !== '') {
    http_response_code(200);
    require $fallbackTarget;
    return;
}

render_site_page(page_definition('404'));
