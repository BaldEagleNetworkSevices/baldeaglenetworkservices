<?php
declare(strict_types=1);

function landing_old_input_value(string $key, string $default = ''): string
{
    if (function_exists('old_input')) {
        return old_input($key, $default);
    }

    return $default;
}

function landing_old_input_checked(string $key, string $expected = 'yes'): bool
{
    return function_exists('old_input') && old_input($key) === $expected;
}

function landing_submission_feedback(): ?array
{
    static $feedback;

    if ($feedback !== null) {
        return $feedback;
    }

    $flash = function_exists('pull_flash') ? pull_flash() : null;
    $submission = strtolower(trim((string) ($_GET['submission'] ?? '')));

    if (!is_array($flash) && $submission === '') {
        $feedback = false;
        return null;
    }

    $variant = (string) ($flash['variant'] ?? '');
    if ($variant === '') {
        $variant = match ($submission) {
            'queued' => 'success',
            'temporary' => 'temporary',
            default => 'error',
        };
    }

    $message = (string) ($flash['message'] ?? '');
    if ($message === '') {
        $message = match ($variant) {
            'success' => 'Your request has been queued for secure processing.',
            'temporary' => 'The secure request path is temporarily unavailable. Please try again in a moment.',
            default => 'Please review the intake form and try again.',
        };
    }

    $feedback = [
        'variant' => $variant,
        'message' => $message,
        'request_id' => (string) ($flash['request_id'] ?? ''),
        'debug_reason' => (string) ($flash['debug_reason'] ?? ''),
        'debug_fields' => is_array($flash['debug_fields'] ?? null) ? $flash['debug_fields'] : [],
    ];

    return $feedback;
}

function landing_render_submission_feedback(): void
{
    $feedback = landing_submission_feedback();
    if (!is_array($feedback)) {
        return;
    }

    $class = 'lp-status';
    $title = 'Request update';
    $next = '';

    if ($feedback['variant'] === 'success') {
        $class .= ' lp-status--success';
        $title = 'Request received';
        $next = 'We will process the request using the delivery option you selected and contact you at your work email.';
    } elseif ($feedback['variant'] === 'limit') {
        $class .= ' lp-status--limit';
        $title = 'Please wait before resubmitting';
    } elseif ($feedback['variant'] === 'temporary') {
        $class .= ' lp-status--temporary';
        $title = 'Temporarily unavailable';
    } else {
        $class .= ' lp-status--error';
        $title = 'Please review your request';
    }
    ?>
    <div class="<?= landing_e($class) ?>">
      <strong><?= landing_e($title) ?></strong>
      <span><?= landing_e($feedback['message']) ?></span>
      <?php if ($next !== ''): ?>
        <span><?= landing_e($next) ?></span>
      <?php endif; ?>
      <?php if ($feedback['request_id'] !== ''): ?>
        <small>Reference: <?= landing_e($feedback['request_id']) ?></small>
      <?php endif; ?>
      <?php if (landing_is_local_development() && $feedback['debug_reason'] !== ''): ?>
        <details class="lp-devnote">
          <summary>Local debug</summary>
          <small><?= landing_e($feedback['debug_reason']) ?><?php if ($feedback['debug_fields'] !== []): ?> (<?= landing_e(implode(', ', $feedback['debug_fields'])) ?>)<?php endif; ?></small>
        </details>
      <?php endif; ?>
    </div>
    <?php
}

function landing_intake_fallback_tier(array $page): array
{
    try {
        return landing_selected_tier_details($page);
    } catch (Throwable) {
        return [
            'tier' => 'standard',
            'name' => 'Standard',
            'delivery' => 'Report emailed within 12 to 24 hours',
            'subheading' => 'You are on the standard turnaround path while the secure request form reloads.',
            'badge' => 'Selected: Standard',
        ];
    }
}

function landing_render_intake_unavailable(array $selectedTier): void
{
    ?>
    <div class="lp-callout lp-callout--tier">
      <strong><?= landing_e($selectedTier['badge']) ?></strong>
      <span><?= landing_e($selectedTier['delivery']) ?> <?= landing_e($selectedTier['subheading']) ?></span>
    </div>
    <div class="lp-callout">
      <strong>Secure request form temporarily unavailable.</strong>
      <span>Please refresh the page and try again. If the problem continues, use the main contact page so we can help you complete the request safely.</span>
    </div>
    <p><a class="lp-button lp-button--ghost" href="<?= landing_e(page_href('contact')) ?>">Go to Contact</a></p>
    <?php
}

function landing_render_intake_form(array $page): void
{
    $selectedTier = landing_intake_fallback_tier($page);
    try {
        $context = landing_issue_form_context($page['slug'], $selectedTier['tier']);
        $csrfField = landing_csrf_field();
        $siteKey = landing_turnstile_site_key();
    } catch (Throwable) {
        landing_render_intake_unavailable($selectedTier);
        return;
    }
    ?>
    <form class="lp-form" action="<?= landing_e(landing_url('landing/forms/intake-handler.php')) ?>" method="post" novalidate>
      <?= $csrfField ?>
      <input type="hidden" name="service" value="<?= landing_e($context['service']) ?>">
      <input type="hidden" name="service_delivery_tier" value="<?= landing_e((string) ($context['delivery_tier'] ?? $selectedTier['tier'])) ?>">
      <input type="hidden" name="service_issued_at" value="<?= landing_e($context['issued_at']) ?>">
      <input type="hidden" name="service_nonce" value="<?= landing_e($context['nonce']) ?>">
      <input type="hidden" name="service_signature" value="<?= landing_e($context['signature']) ?>">
      <input type="hidden" name="delivery_tier" value="<?= landing_e($selectedTier['tier']) ?>">
      <div class="lp-callout lp-callout--tier">
        <strong><?= landing_e($selectedTier['badge']) ?></strong>
        <span><?= landing_e($selectedTier['delivery']) ?></span>
        <small><?= landing_e($selectedTier['subheading']) ?></small>
      </div>
      <div class="lp-form__honeypot" aria-hidden="true">
        <label for="company_website">Leave this field blank</label>
        <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
      </div>
      <div class="lp-form__section">
        <div class="lp-form__section-head">
          <strong>Contact details</strong>
          <span>Tell us who is requesting the scan so the response lands with the right person.</span>
        </div>
        <div class="lp-form__grid lp-form__grid--lead">
          <label>
            First Name
            <input name="first_name" type="text" maxlength="80" autocomplete="given-name" value="<?= landing_e(landing_old_input_value('first_name')) ?>" required>
          </label>
          <label>
            Last Name
            <input name="last_name" type="text" maxlength="80" autocomplete="family-name" value="<?= landing_e(landing_old_input_value('last_name')) ?>" required>
          </label>
          <label>
            Company Name
            <input name="business_name" type="text" maxlength="120" autocomplete="organization" value="<?= landing_e(landing_old_input_value('business_name')) ?>" required>
            <small>Enter the legal or commonly used business name tied to the domain you want reviewed.</small>
          </label>
          <label>
            Work Email
            <input name="work_email" type="email" maxlength="254" autocomplete="email" value="<?= landing_e(landing_old_input_value('work_email')) ?>" required>
            <small>Use your business email on the same domain as the website you are submitting.</small>
          </label>
          <label>
            Office Phone
            <input name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= landing_e(landing_old_input_value('phone')) ?>">
            <small>Optional. Add a direct business number if you want a faster follow-up.</small>
          </label>
          <label>
            Website / Business Domain
            <input name="business_domain" type="text" maxlength="253" inputmode="url" autocapitalize="none" spellcheck="false" placeholder="dec24.com" value="<?= landing_e(landing_old_input_value('business_domain')) ?>" required>
            <small>Enter the root business domain, such as dec24.com. We normalize entries like www.dec24.com or https://www.dec24.com/path.</small>
          </label>
          <label>
            Job Title
            <input name="job_title" type="text" maxlength="100" autocomplete="organization-title" value="<?= landing_e(landing_old_input_value('job_title')) ?>">
            <small>Optional. Helps us frame findings for the right role.</small>
          </label>
          <label>
            Department
            <input name="department" type="text" maxlength="100" autocomplete="organization" value="<?= landing_e(landing_old_input_value('department')) ?>">
            <small>Optional. Useful when security, operations, or leadership teams share the inbox.</small>
          </label>
        </div>
      </div>
      <div class="lp-form__section">
        <div class="lp-form__section-head">
          <strong>Request details</strong>
          <span>Keep the first request focused. We only need enough context to validate and process the scan safely.</span>
        </div>
        <div class="lp-form__grid">
          <label class="lp-form__full">
            Delivery Tier
            <div class="lp-tier-readout" role="status" aria-live="polite">
              <strong><?= landing_e($selectedTier['name']) ?></strong>
              <span><?= landing_e($selectedTier['delivery']) ?></span>
              <small><?= landing_e($selectedTier['summary']) ?></small>
            </div>
          </label>
          <label class="lp-form__full">
            What Needs Attention
            <textarea name="request_notes" rows="5" maxlength="1500" placeholder="Optional: share any external security concerns, known issues, or timing context."><?= landing_e(landing_old_input_value('request_notes')) ?></textarea>
            <small>Optional. Use this for business-relevant scan context only. Do not include passwords, MFA codes, API keys, internal network details, or other sensitive material.</small>
          </label>
        </div>
      </div>
      <div class="lp-callout lp-callout--soft">
        <strong>Selected delivery:</strong>
        <span><?= landing_e($selectedTier['name']) ?>. You can switch tiers in the pricing section above before submitting.</span>
      </div>
      <label class="lp-form__checkbox">
        <input name="authorization_checkbox" type="checkbox" value="yes"<?= landing_old_input_checked('authorization_checkbox') ? ' checked' : '' ?> required>
        <span><?= landing_e($page['authorized_checkbox']) ?></span>
      </label>
      <div class="lp-callout lp-callout--soft">
        <strong>Do not submit secrets.</strong>
        <span>Do not enter passwords, MFA codes, API keys, payment data, internal network details, or other sensitive material.</span>
      </div>
      <div class="lp-turnstile-slot">
        <p>To keep the request form usable, some submissions include a quick verification step to confirm a real person is sending the request. Priority requests include this step before submission can continue.</p>
        <?php if ($siteKey !== ''): ?>
          <div class="cf-turnstile" data-sitekey="<?= landing_e($siteKey) ?>"></div>
        <?php elseif (landing_is_local_development()): ?>
          <small>Local development bypass is active for the verification step.</small>
        <?php endif; ?>
      </div>
      <div class="lp-submit-row">
        <button class="lp-button lp-button--primary" type="submit"><?= landing_e($page['primary_cta']) ?></button>
        <p class="lp-submit-note">
          <?php if ($selectedTier['tier'] === 'priority'): ?>
            After you submit, we will validate the request and send you to secure payment so the paid Priority path can be activated.
          <?php else: ?>
            After you submit, we will confirm receipt inside this page and process the request using the delivery tier you selected.
          <?php endif; ?>
        </p>
      </div>
    </form>
    <?php

    if (function_exists('clear_old_input')) {
        clear_old_input();
    }
}
