<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$target = page_href('security-risk-assessments');
$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
if ($queryString !== '') {
    $target .= '?' . $queryString;
}

header('Location: ' . $target, true, 301);
exit;
