<?php
declare(strict_types=1);

function landing_is_valid_business_domain(string $domain): bool
{
    if ($domain === '' || strlen($domain) > 253) {
        return false;
    }

    if (str_contains($domain, '://') || str_contains($domain, '/') || str_contains($domain, '@')) {
        return false;
    }

    return (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain);
}

function landing_email_matches_business_domain(string $email, string $domain): bool
{
    if ($email === '' || $domain === '' || !str_contains($email, '@')) {
        return false;
    }

    $emailDomain = landing_normalize_domain((string) substr(strrchr($email, '@') ?: '', 1));
    return $emailDomain === $domain || str_ends_with($emailDomain, '.' . $domain);
}
