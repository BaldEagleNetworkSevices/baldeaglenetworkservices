<?php
declare(strict_types=1);

function landing_render_checklist_card(array $items, string $title): void
{
    echo '<section class="lp-card">';
    echo '<p class="lp-card__eyebrow">' . landing_e($title) . '</p>';
    echo '<ul class="lp-checklist">';
    foreach ($items as $item) {
        echo '<li>' . landing_e($item) . '</li>';
    }
    echo '</ul>';
    echo '</section>';
}
