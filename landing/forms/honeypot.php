<?php
declare(strict_types=1);

function landing_honeypot_triggered(array $input): bool
{
    return trim((string) ($input['company_website'] ?? '')) !== '';
}
