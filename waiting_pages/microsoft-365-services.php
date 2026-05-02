<?php
declare(strict_types=1);

/**
 * Archived public route wrapper for the inactive `microsoft-365-services` page.
 *
 * Restore by moving this file back to the repo root and re-adding the matching
 * page definition from `waiting_pages/inactive-page-definitions.php`.
 */

require_once __DIR__ . '/../includes/functions.php';

$inactivePages = require __DIR__ . '/inactive-page-definitions.php';
render_site_page($inactivePages['microsoft-365-services']);
