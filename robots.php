<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /app/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /deploy/\n";
echo "Disallow: /systemd/\n";
echo "Disallow: /landing/payments/\n";
echo "Disallow: /landing/security/\n";
echo "Disallow: /landing/crm/\n";
echo "Disallow: /landing/forms/\n";
echo "Disallow: /landing/config/\n";
echo "Disallow: /landing/reports/report-access.php\n";
echo "Disallow: /assets/contact-handler.php\n";
echo 'Sitemap: ' . absolute_url('/sitemap.xml') . "\n";
