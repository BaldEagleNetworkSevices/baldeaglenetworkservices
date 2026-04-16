<?php
declare(strict_types=1);

function landing_current_page_path(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/landing/external-security-scan.php');
    $path = parse_url($requestUri, PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/landing/external-security-scan.php';
}

function landing_render_pricing_grid(array $pricing): void
{
    $currentPath = landing_current_page_path();
    $selectedTier = function_exists('landing_selected_tier')
        ? landing_selected_tier(['pricing' => $pricing])
        : 'standard';
    echo '<div class="lp-pricing-grid">';
    foreach ($pricing as $tier) {
        $tierHref = landing_e($currentPath . '?tier=' . rawurlencode((string) $tier['tier']) . '#intake');
        $turnstileClass = $tier['turnstile'] === 'required' ? ' is-required' : ' is-recommended';
        $selectedClass = $selectedTier === strtolower((string) $tier['tier']) ? ' is-selected' : '';
        $verificationCopy = $tier['turnstile'] === 'required'
            ? 'Priority requests include a quick verification step before submission can continue.'
            : 'Some requests may show a quick verification step to keep the form usable.';
        $buttonLabel = $selectedTier === strtolower((string) $tier['tier'])
            ? 'Continue with ' . (string) $tier['name']
            : 'Choose ' . (string) $tier['name'];
        echo '<article class="lp-price-card' . $turnstileClass . $selectedClass . '">';
        echo '<p class="lp-price-card__tier">' . landing_e($tier['name']) . '</p>';
        if ($selectedTier === strtolower((string) $tier['tier'])) {
            echo '<p class="lp-price-card__selected">Selected below in the secure request form</p>';
        }
        echo '<h3>' . landing_e($tier['price']) . '</h3>';
        echo '<p class="lp-price-card__delivery">' . landing_e($tier['delivery']) . '</p>';
        echo '<p>' . landing_e($tier['copy']) . '</p>';
        echo '<ul class="lp-checklist">';
        foreach ($tier['points'] as $point) {
            echo '<li>' . landing_e($point) . '</li>';
        }
        echo '</ul>';
        echo '<p class="lp-price-card__badge">' . landing_e($verificationCopy) . '</p>';
        echo '<p><a class="lp-button lp-button--primary" href="' . $tierHref . '">' . landing_e($buttonLabel) . '</a></p>';
        echo '</article>';
    }
    echo '</div>';
}
