<?php
declare(strict_types=1);

function landing_render_faq(array $items): void
{
    echo '<div class="lp-faq">';
    foreach ($items as $item) {
        echo '<details class="lp-faq__item">';
        echo '<summary>' . landing_e($item['question']) . '</summary>';
        echo '<p>' . landing_e($item['answer']) . '</p>';
        echo '</details>';
    }
    echo '</div>';
}
