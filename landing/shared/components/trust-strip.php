<?php
declare(strict_types=1);

function landing_render_trust_strip(array $points): void
{
    echo '<div class="lp-trust-strip">';
    foreach ($points as $point) {
        echo '<span>' . landing_e($point) . '</span>';
    }
    echo '</div>';
}
