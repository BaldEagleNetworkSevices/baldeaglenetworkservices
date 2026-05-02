<?php
declare(strict_types=1);

/**
 * Archived public route wrapper for the inactive `managed-it-support-salt-lake-city` page.
 *
 * Restore by moving this file back to the repo root and re-adding the matching
 * page definition from `waiting_pages/inactive-page-definitions.php`.
 */

require_once __DIR__ . '/../includes/functions.php';

$inactivePages = require __DIR__ . '/inactive-page-definitions.php';
render_site_page($inactivePages['managed-it-support-salt-lake-city']);
