<?php
declare(strict_types=1);

require_once __DIR__ . '/config/global.php';
require_once __DIR__ . '/../includes/functions.php';
landing_require('forms/csrf.php');
landing_require('forms/context.php');
landing_require('forms/turnstile.php');
landing_require('shared/components/trust-strip.php');
landing_require('shared/components/checklist-card.php');
landing_require('shared/components/tier-state.php');
landing_require('shared/components/pricing-grid.php');
landing_require('shared/components/faq.php');
landing_require('shared/components/intake-form.php');

$landingPage = landing_service_config('external-security-scan');
$selectedTier = landing_selected_tier_details($landingPage);
$turnstileRender = landing_turnstile_render_state($landingPage, $selectedTier['tier']);
$landingPath = function_exists('landing_page_href')
    ? landing_page_href('external-security-scan')
    : '/landing/external-security-scan';
$turnstileScript = $turnstileRender['script_required']
    ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
    : '';
$page = [
    'slug' => 'external-security-scan',
    'path' => $landingPath,
    'nav_key' => 'services',
    'title' => $landingPage['page_title'],
    'description' => $landingPage['meta_description'],
    'body_class' => 'page-landing-external-security-scan',
    'canonical' => absolute_url($landingPath),
    'og_type' => 'website',
    'extra_head' => '<link rel="stylesheet" href="' . landing_e(landing_asset_path('shared/landing.css')) . '">' . PHP_EOL . $turnstileScript,
];

require_once __DIR__ . '/../includes/header.php';
?>
  <main id="main-content" class="site-main">
    <div class="lp-shell">
    <section class="lp-hero">
      <div class="lp-hero__content">
        <p class="lp-eyebrow"><?= landing_e($landingPage['hero_kicker']) ?></p>
        <h1><?= landing_e($landingPage['hero_title']) ?></h1>
        <p class="lp-lede"><?= landing_e($landingPage['hero_intro']) ?></p>
        <div class="lp-actions">
          <a class="lp-button lp-button--primary" href="#pricing"><?= landing_e($landingPage['primary_cta']) ?></a>
        </div>
      </div>
      <aside class="lp-hero__panel">
        <p class="lp-hero__panel-title">Where this fits</p>
        <p>This is a secondary offer for businesses that need fast exposure triage after a Recovery Readiness Test or urgent risk concern.</p>
        <ul class="lp-checklist">
          <li>DNS, TLS, and header review</li>
          <li>Email authentication posture snapshot</li>
          <li>Visible exposure indicators and next steps</li>
          <li>Delivered in 12 to 24 hours, or faster with Priority</li>
        </ul>
        <p><a class="lp-button lp-button--primary" href="#pricing">Start External Exposure Triage</a></p>
      </aside>
    </section>

    <?php landing_render_trust_strip($landingPage['trust_points']); ?>

    <section class="lp-section" id="included">
      <div class="lp-section__heading">
        <p class="lp-eyebrow">What This Scan Checks</p>
        <h2>Focused external checks that show what your public-facing setup is already revealing.</h2>
      </div>
      <div class="lp-grid-two">
        <?php landing_render_checklist_card($landingPage['included'], 'Included'); ?>
        <?php landing_render_checklist_card($landingPage['excluded'], 'Not Included'); ?>
      </div>
    </section>

    <section class="lp-section">
      <div class="lp-section__heading">
        <p class="lp-eyebrow">What You Get</p>
        <h2>A clear deliverable, not a vague security conversation.</h2>
      </div>
      <div class="lp-grid-two">
        <?php landing_render_checklist_card([
            'External exposure summary written for business review',
            'Prioritized findings grouped by practical risk',
            'Fast understanding of email, DNS, and website posture',
            'Clear next-step guidance for remediation or follow-up work',
        ], 'Deliverables'); ?>
        <?php landing_render_checklist_card([
            'No internal scanning',
            'No credential requests',
            'No exploit attempts',
            'No long questionnaire before you can buy',
        ], 'Why This Format Works'); ?>
      </div>
    </section>

    <section class="lp-section lp-section--dark" id="pricing">
      <div class="lp-section__heading">
        <p class="lp-eyebrow">Delivery Tiers</p>
        <h2>Choose the turnaround that matches your urgency and buy path.</h2>
      </div>
      <?php landing_render_pricing_grid($landingPage['pricing']); ?>
    </section>

    <section class="lp-section">
      <div class="lp-section__heading">
        <p class="lp-eyebrow">Request Your Scan</p>
        <h2><?= landing_e($selectedTier['heading']) ?></h2>
        <p class="lp-lede"><?= landing_e($selectedTier['delivery']) ?> <?= landing_e($selectedTier['summary']) ?></p>
      </div>
      <div class="lp-card lp-card--intake" id="intake">
        <?php landing_render_submission_feedback(); ?>
        <p><?= landing_e($selectedTier['subheading']) ?> Share your contact details, company details, and any business-safe context that will help us route the scan request correctly the first time.</p>
        <?php landing_render_intake_form($landingPage); ?>
      </div>
    </section>

    <section class="lp-section">
      <div class="lp-section__heading">
        <p class="lp-eyebrow">Before You Submit</p>
        <h2>Keep the request clean and business-focused.</h2>
      </div>
      <div class="lp-card">
        <p>Do not send credentials, internal network details, or other sensitive material. This product is built to start with only the minimum information needed to validate and process the request.</p>
        <ul class="lp-checklist">
          <?php foreach ($landingPage['prohibited_inputs'] as $item): ?>
            <li><?= landing_e($item) ?> are prohibited from Step 1.</li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="lp-section lp-section--faq">
      <div class="lp-section__heading">
        <p class="lp-eyebrow">FAQ</p>
        <h2>Scope, turnaround, and authorization requirements.</h2>
      </div>
      <?php landing_render_faq($landingPage['faq']); ?>
    </section>

    <section class="lp-section">
      <div class="lp-card">
        <p class="lp-eyebrow">Need this now</p>
        <h2>Start external exposure triage before downtime compounds the risk.</h2>
        <p class="lp-lede">If you need a standard turnaround, start there. If timing matters, choose Priority and move straight to the protected intake.</p>
        <div class="lp-actions">
          <a class="lp-button lp-button--primary" href="#pricing">Start External Exposure Triage</a>
        </div>
      </div>
    </section>
    </div>
  </main>
<?php
require_once __DIR__ . '/../includes/footer.php';
