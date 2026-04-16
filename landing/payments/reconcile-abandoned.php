<?php
declare(strict_types=1);

header('Cache-Control: no-store, max-age=0');
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'error' => 'deprecated_endpoint',
    'message' => 'Checkout expiration is handled by Stripe webhooks.',
], JSON_UNESCAPED_SLASHES);
