<?php
declare(strict_types=1);

require_once __DIR__ . '/config/global.php';
require_once __DIR__ . '/../includes/functions.php';
landing_require('forms/csrf.php');
landing_require('payments/store.php');
landing_require('payments/stripe.php');

$requestId = trim((string) ($_GET['request_id'] ?? ''));
$paymentToken = trim((string) ($_GET['payment_token'] ?? ''));
$paymentRequest = ($requestId !== '' && $paymentToken !== '') ? landing_payment_request_with_token($requestId, $paymentToken) : null;
$requestValid = is_array($paymentRequest) && (($paymentRequest['delivery_tier'] ?? '') === 'priority');
$authorizedPaymentQuery = ($requestValid && is_array($paymentRequest))
    ? [
        'request_id' => (string) $paymentRequest['request_id'],
        'payment_token' => $paymentToken,
    ]
    : [];
$authorizedPaymentUrl = $authorizedPaymentQuery !== [] ? landing_priority_payment_page_url($authorizedPaymentQuery) : '';
$receiptDownloadUrl = $authorizedPaymentQuery !== []
    ? landing_priority_payment_page_url(array_merge($authorizedPaymentQuery, ['download' => 'receipt']))
    : '';
$paymentStatus = $requestValid ? (string) ($paymentRequest['payment_status'] ?? '') : '';
$paymentConfirmed = $paymentStatus === 'paid_priority';
$checkoutState = strtolower(trim((string) ($_GET['checkout'] ?? '')));
$stripeStatus = landing_stripe_configuration_status();
$stripeReady = $stripeStatus['checkout_ready'];
$paymentPath = landing_priority_payment_page_path();
$returnToProductUrl = landing_page_href('external-security-scan') . '#intake';

if ($requestValid && (string) ($_GET['download'] ?? '') === 'receipt') {
    if (!$paymentConfirmed) {
        http_response_code(409);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Receipt is not available until Stripe webhook confirmation completes.\n";
        exit;
    }

    $receiptLines = [
        'Bald Eagle Network Services',
        'Priority External Security Scan Receipt',
        '',
        'Request ID: ' . (string) ($paymentRequest['request_id'] ?? ''),
        'Payment Status: paid_priority',
        'Business Name: ' . (string) ($paymentRequest['business_name'] ?? ''),
        'Business Domain: ' . (string) ($paymentRequest['business_domain'] ?? ''),
        'Customer Email: ' . (string) ($paymentRequest['work_email'] ?? ''),
        'Service: Priority External Security Scan',
        'Delivery Tier: ' . ucfirst((string) ($paymentRequest['delivery_tier'] ?? 'priority')),
        'Amount: ' . (string) ($paymentRequest['price_label'] ?? ''),
        'Currency: ' . strtoupper((string) ($paymentRequest['currency'] ?? 'usd')),
        'Stripe Checkout Session: ' . (string) ($paymentRequest['stripe_checkout_session_id'] ?? ''),
        'Payment Reference: ' . (string) ($paymentRequest['payment_reference'] ?? ''),
        'Confirmed At: ' . (string) ($paymentRequest['payment_completed_at'] ?? ''),
    ];

    header('Cache-Control: no-store, max-age=0');
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="priority-external-security-scan-receipt-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($paymentRequest['request_id'] ?? 'receipt')) . '.txt"');
    echo implode(PHP_EOL, $receiptLines) . PHP_EOL;
    exit;
}

$page = [
    'slug' => 'external-security-scan-priority-payment',
    'path' => $paymentPath,
    'nav_key' => 'services',
    'title' => 'Priority External Security Scan Payment | Bald Eagle Network Services',
    'description' => 'Complete payment for your Priority External Security Scan request.',
    'body_class' => 'page-landing-external-security-scan-payment',
    'canonical' => absolute_url($paymentPath),
    'og_type' => 'website',
    'extra_head' => '<link rel="stylesheet" href="' . landing_e(landing_asset_path('shared/landing.css')) . '">' . PHP_EOL,
];

function landing_payment_status_banner(?array $request, string $checkoutState): array
{
    if (!is_array($request)) {
        return [
            'variant' => 'error',
            'title' => 'Request not found',
            'message' => 'Use the secure intake form first so payment is tied to a valid request.',
        ];
    }

    $status = (string) ($request['payment_status'] ?? '');
    if ($status === 'paid_priority') {
        return [
            'variant' => 'success',
            'title' => 'Priority payment confirmed',
            'message' => 'Your payment is confirmed and the request is now on the paid priority path.',
        ];
    }

    if ($checkoutState === 'success') {
        return [
            'variant' => 'temporary',
            'title' => 'Payment submitted',
            'message' => 'Stripe returned successfully. Final confirmation appears here after the webhook records payment.',
        ];
    }

    if ($checkoutState === 'cancel') {
        return [
            'variant' => 'limit',
            'title' => 'Checkout canceled',
            'message' => 'Your request is still saved. Start checkout again whenever you are ready.',
        ];
    }

    if ($checkoutState === 'error') {
        return [
            'variant' => 'error',
            'title' => 'Checkout unavailable',
            'message' => 'Secure checkout could not start. Try again in a moment.',
        ];
    }

    if ($status === 'checkout_abandoned') {
        return [
            'variant' => 'temporary',
            'title' => 'Previous checkout expired',
            'message' => 'Your earlier checkout was not completed. Start a new secure checkout below.',
        ];
    }

    if ($status === 'payment_failed') {
        return [
            'variant' => 'error',
            'title' => 'Payment was not completed',
            'message' => 'Your request is still on file. Start a new secure checkout below to continue.',
        ];
    }

    return [
        'variant' => 'success',
        'title' => 'Priority request received',
        'message' => 'Your request is on file. This page tracks checkout return, payment confirmation, and lets you recover checkout if it was interrupted.',
    ];
}

function landing_payment_cta_label(?array $request, string $checkoutState): string
{
    if (!is_array($request)) {
        return 'Pay Securely With Stripe';
    }

    $status = (string) ($request['payment_status'] ?? '');
    if ($checkoutState === 'cancel' || $checkoutState === 'error' || $status === 'checkout_abandoned' || $status === 'payment_failed') {
        return 'Restart Secure Checkout';
    }

    return 'Resume Stripe Checkout';
}

function landing_payment_status_class(string $variant): string
{
    return match ($variant) {
        'success' => 'lp-status lp-status--success',
        'limit' => 'lp-status lp-status--limit',
        'temporary' => 'lp-status lp-status--temporary',
        default => 'lp-status lp-status--error',
    };
}

$banner = landing_payment_status_banner($paymentRequest, $checkoutState);

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" class="site-main">
  <div class="lp-shell">
    <section class="lp-section">
      <div class="lp-section__heading">
        <p class="lp-eyebrow">Priority Payment</p>
        <h1 class="lp-payment-title">Priority Scan Payment</h1>
        <p class="lp-lede">This page is the secure return and confirmation view for a valid Priority request, with the same request-bound access token used for retry and receipt status.</p>
      </div>

      <div class="<?= landing_e(landing_payment_status_class($banner['variant'])) ?>">
        <strong><?= landing_e($banner['title']) ?></strong>
        <span><?= landing_e($banner['message']) ?></span>
        <?php if ($requestValid && (string) ($paymentRequest['request_id'] ?? '') !== ''): ?>
          <small>Reference: <?= landing_e((string) $paymentRequest['request_id']) ?></small>
        <?php endif; ?>
      </div>

      <div class="lp-grid-two lp-payment-grid">
        <div class="lp-card lp-payment-card">
          <p class="lp-card__eyebrow">Request Summary</p>
          <?php if ($requestValid): ?>
            <div class="lp-payment-summary">
              <div>
                <strong>Business</strong>
                <span><?= landing_e((string) $paymentRequest['business_name']) ?></span>
              </div>
              <div>
                <strong>Website / Domain</strong>
                <span><?= landing_e((string) $paymentRequest['business_domain']) ?></span>
              </div>
              <div>
                <strong>Delivery Tier</strong>
                <span><?= landing_e(ucfirst((string) $paymentRequest['delivery_tier'])) ?></span>
              </div>
              <div>
                <strong>Turnaround</strong>
                <span><?= landing_e((string) $paymentRequest['turnaround_promise']) ?></span>
              </div>
              <div>
                <strong>Price</strong>
                <span><?= landing_e((string) $paymentRequest['price_label']) ?></span>
              </div>
              <div>
                <strong>What happens next</strong>
                <span>Webhook confirmation records payment first, then follow-up events are queued behind it.</span>
              </div>
            </div>
          <?php else: ?>
            <p>This page only works after a valid Priority request has been created through the intake form.</p>
            <p><a class="lp-button lp-button--ghost" href="<?= landing_e($returnToProductUrl) ?>">Return to intake</a></p>
          <?php endif; ?>
        </div>

        <div class="lp-card lp-payment-card">
          <p class="lp-card__eyebrow">Secure Checkout</p>
          <?php if ($paymentConfirmed): ?>
            <div class="lp-callout lp-callout--tier">
              <strong>Payment confirmed</strong>
              <span>Your Priority External Security Scan is queued on the paid priority path.</span>
              <small>Payment confirmation is recorded server-side from the Stripe webhook.</small>
            </div>
            <div class="lp-callout lp-callout--soft">
              <strong>Confirmed payment details</strong>
              <span>Payment reference: <?= landing_e((string) ($paymentRequest['payment_reference'] ?? 'pending')) ?></span>
              <small>Confirmed at: <?= landing_e((string) ($paymentRequest['payment_completed_at'] ?? 'Pending confirmation')) ?></small>
            </div>
            <div class="lp-submit-row">
              <a class="lp-button" href="<?= landing_e($authorizedPaymentUrl) ?>">View Payment Status</a>
              <a class="lp-button lp-button--ghost" href="<?= landing_e($receiptDownloadUrl) ?>">Download Receipt</a>
            </div>
          <?php elseif ($requestValid && $checkoutState === 'success'): ?>
            <div class="lp-callout lp-callout--soft">
              <strong>Verification in progress</strong>
              <span>Stripe sent you back successfully. Refresh this page in a few seconds if confirmation has not appeared yet.</span>
            </div>
            <div class="lp-submit-row">
              <a class="lp-button" href="<?= landing_e($authorizedPaymentUrl) ?>">Refresh Payment Status</a>
            </div>
          <?php elseif ($requestValid && !$stripeReady): ?>
            <div class="lp-callout">
              <strong>Checkout unavailable</strong>
              <span>The payment endpoint is not fully configured yet. Contact Bald Eagle directly to finish this Priority request safely.</span>
              <?php if (landing_is_local_development() && $stripeStatus['missing'] !== []): ?>
                <small>Missing Stripe config: <?= landing_e(implode(', ', $stripeStatus['missing'])) ?></small>
              <?php endif; ?>
            </div>
          <?php elseif ($requestValid): ?>
            <div class="lp-callout lp-callout--tier">
              <strong>Secure Stripe Checkout</strong>
              <span>You will complete payment on Stripe and then return here for confirmation.</span>
              <small>Your checkout remains tied to request <?= landing_e((string) $paymentRequest['request_id']) ?>.</small>
            </div>
            <form method="post" action="<?= landing_e(landing_url('landing/payments/create-checkout-session.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= landing_e(landing_csrf_token()) ?>">
              <input type="hidden" name="request_id" value="<?= landing_e((string) $paymentRequest['request_id']) ?>">
              <input type="hidden" name="payment_token" value="<?= landing_e($paymentToken) ?>">
              <div class="lp-submit-row">
                <button class="lp-button lp-button--primary" type="submit"><?= landing_e(landing_payment_cta_label($paymentRequest, $checkoutState)) ?></button>
              </div>
            </form>
            <?php if ($checkoutState === 'cancel'): ?>
              <p class="lp-submit-note">Your request is still saved. Start checkout again whenever you are ready.</p>
            <?php else: ?>
              <p class="lp-submit-note">After payment, Stripe sends you back here while the webhook records the confirmed paid state.</p>
            <?php endif; ?>
            <?php if (landing_is_local_development()): ?>
              <div class="lp-callout lp-callout--soft">
                <strong>Local payment debug</strong>
                <span>Status: <?= landing_e($paymentStatus !== '' ? $paymentStatus : 'not_started') ?></span>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($requestValid): ?>
        <p><a class="lp-button lp-button--ghost" href="<?= landing_e($returnToProductUrl) ?>">Back to product page</a></p>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php
require_once __DIR__ . '/../includes/footer.php';
