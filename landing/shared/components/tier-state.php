<?php
declare(strict_types=1);

function landing_selected_tier(array $page): string
{
    $requestedTier = strtolower(trim((string) ($_GET['tier'] ?? 'standard')));
    $allowedTiers = array_column($page['pricing'] ?? [], 'tier');

    return in_array($requestedTier, $allowedTiers, true) ? $requestedTier : 'standard';
}

function landing_selected_tier_details(array $page): array
{
    $selectedTier = landing_selected_tier($page);
    $tiers = $page['pricing'] ?? [];
    $selectedConfig = null;

    foreach ($tiers as $tier) {
        if (($tier['tier'] ?? '') === $selectedTier) {
            $selectedConfig = $tier;
            break;
        }
    }

    if ($selectedConfig === null) {
        foreach ($tiers as $tier) {
            if (($tier['tier'] ?? '') === 'standard') {
                $selectedConfig = $tier;
                $selectedTier = 'standard';
                break;
            }
        }
    }

    if ($selectedConfig === null) {
        $selectedConfig = [
            'tier' => 'standard',
            'name' => 'Standard',
            'delivery' => 'Report emailed within 12 to 24 hours',
            'copy' => 'Best fit when you want a clear external exposure review delivered on a normal business timeline.',
        ];
        $selectedTier = 'standard';
    }

    $isPriority = $selectedTier === 'priority';

    return [
        'tier' => $selectedTier,
        'name' => (string) ($selectedConfig['name'] ?? ucfirst($selectedTier)),
        'delivery' => (string) ($selectedConfig['delivery'] ?? ''),
        'summary' => (string) ($selectedConfig['copy'] ?? ''),
        'heading' => $isPriority ? 'Priority delivery selected.' : 'Standard delivery selected.',
        'subheading' => $isPriority
            ? 'You are on the faster turnaround path, with a quick verification step before submission can continue.'
            : 'You are on the standard turnaround path for delivery within 12 to 24 hours.',
        'badge' => $isPriority ? 'Selected: Priority' : 'Selected: Standard',
    ];
}
