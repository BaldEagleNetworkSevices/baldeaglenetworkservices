<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/page-definitions.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function absolute_url(string $path = '/'): string
{
    $base = rtrim(site_config()['site_url'], '/');
    $cleanPath = '/' . ltrim($path, '/');

    return $cleanPath === '/' ? $base . '/' : $base . $cleanPath;
}

function asset_path(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    $url = asset_path($path);
    $file = dirname(__DIR__) . $url;
    if (!is_file($file)) {
        return $url;
    }

    return $url . '?v=' . (string) filemtime($file);
}

function landing_asset_path(string $path): string
{
    return '/landing/' . ltrim($path, '/');
}

function page_catalog(): array
{
    static $pages;

    if ($pages !== null) {
        return $pages;
    }

    $pages = [
        'home' => ['path' => '/', 'label' => 'Home'],
        'services' => ['path' => '/services', 'label' => 'Services'],
        'plans' => ['path' => '/monthly-it-support-plans', 'label' => 'Monthly Backup Verification'],
        'about' => ['path' => '/about', 'label' => 'About'],
        'case-study-backup-recovery-failure' => ['path' => '/case-study-backup-recovery-failure', 'label' => 'Case Study'],
        'case-study-security-recovery-failure' => ['path' => '/case-study-security-recovery-failure', 'label' => 'Security Recovery Case Study'],
        'service-area' => ['path' => '/service-area', 'label' => 'Service Area'],
        'contact' => ['path' => '/contact', 'label' => 'Request Recovery Assessment'],
        'faq' => ['path' => '/faq', 'label' => 'FAQ'],
        'privacy-policy' => ['path' => '/privacy-policy', 'label' => 'Privacy Policy'],
        'terms' => ['path' => '/terms', 'label' => 'Terms'],
        'network-security' => ['path' => '/network-security', 'label' => 'Security Hardening'],
        'backup-disaster-recovery' => ['path' => '/backup-disaster-recovery', 'label' => 'Backup & Disaster Recovery'],
        'security-risk-assessments' => ['path' => '/recovery-assessment', 'label' => 'Recovery Assessment'],
        'compliance-readiness' => ['path' => '/compliance-readiness', 'label' => 'Continuity & Control Review'],
        'monthly-it-support-plans' => ['path' => '/monthly-it-support-plans', 'label' => 'Monthly Backup and Recovery Verification'],
    ];

    return $pages;
}

function page_href(string $slug): string
{
    $pages = page_catalog();
    $path = $pages[$slug]['path'] ?? '/';

    if ($path === '/' || !site_config()['prefer_php_paths']) {
        return $path;
    }

    if (preg_match('/\.php$/i', $path)) {
        return $path;
    }

    $candidate = dirname(__DIR__) . $path . '.php';
    if (is_file($candidate)) {
        return $path . '.php';
    }

    return $path;
}

function page_url(string $slug): string
{
    return absolute_url(page_href($slug));
}

function recovery_assessment_href(string $service = 'risk-assessment', string $anchor = ''): string
{
    $href = page_href('security-risk-assessments');
    $query = $service !== '' ? '?service=' . urlencode($service) : '';
    $fragment = $anchor !== '' ? '#' . ltrim($anchor, '#') : '';

    return $href . $query . $fragment;
}

function landing_page_href(string $slug): string
{
    $basePath = '/landing/' . trim($slug, '/');

    if (!site_config()['prefer_php_paths']) {
        return $basePath;
    }

    $candidate = dirname(__DIR__) . $basePath . '.php';
    if (is_file($candidate)) {
        return $basePath . '.php';
    }

    return $basePath;
}

function landing_page_url(string $slug): string
{
    return absolute_url(landing_page_href($slug));
}

function page_definition(string $slug): array
{
    $definitions = site_page_definitions();

    if (!isset($definitions[$slug])) {
        throw new InvalidArgumentException('Unknown page definition: ' . $slug);
    }

    return $definitions[$slug];
}

function current_year(): string
{
    return date('Y');
}

function set_flash(array $payload): void
{
    ensure_session_started();
    $_SESSION['_flash'] = $payload;
}

function pull_flash(): ?array
{
    ensure_session_started();

    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }

    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);

    return $flash;
}

function remember_form_input(array $input): void
{
    ensure_session_started();
    $_SESSION['_old_input'] = $input;
}

function old_input(string $key, string $default = ''): string
{
    ensure_session_started();
    return isset($_SESSION['_old_input'][$key]) && is_string($_SESSION['_old_input'][$key])
        ? $_SESSION['_old_input'][$key]
        : $default;
}

function clear_old_input(): void
{
    ensure_session_started();
    unset($_SESSION['_old_input']);
}

function normalize_page(array $page): array
{
    $page['path'] = $page['path'] ?? '/';
    $page['slug'] = $page['slug'] ?? trim($page['path'], '/');
    $page['nav_key'] = $page['nav_key'] ?? '';
    $page['title'] = $page['title'] ?? site_config()['site_name'];
    $page['description'] = $page['description'] ?? site_config()['tagline'];
    $page['canonical'] = absolute_url($page['path']);
    $page['og_type'] = $page['og_type'] ?? 'website';
    $page['body_class'] = $page['body_class'] ?? 'page-' . preg_replace('/[^a-z0-9\-]+/i', '-', $page['slug']);
    $page['contact_service_type'] = $page['contact_service_type'] ?? 'other';

    return $page;
}

function page_backing_file_path(string $slug, string $path): ?string
{
    if ($slug === 'home' || $path === '/') {
        $candidate = dirname(__DIR__) . '/index.php';
        return is_file($candidate) ? $candidate : null;
    }

    $cleanPath = '/' . trim($path, '/');
    $candidates = [];

    if (preg_match('/\.php$/i', $cleanPath)) {
        $candidates[] = dirname(__DIR__) . $cleanPath;
    } elseif (preg_match('/^\/[a-z0-9\-]+$/i', $cleanPath)) {
        $candidates[] = dirname(__DIR__) . $cleanPath . '.php';
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function page_lastmod_iso8601(string $slug, string $path): string
{
    $fallback = 0;
    foreach ([
        dirname(__DIR__) . '/includes/page-definitions.php',
        dirname(__DIR__) . '/includes/functions.php',
        dirname(__DIR__) . '/includes/header.php',
        dirname(__DIR__) . '/includes/footer.php',
        dirname(__DIR__) . '/sitemap.xml',
    ] as $sharedFile) {
        $fallback = max($fallback, (int) @filemtime($sharedFile));
    }

    $backingFile = page_backing_file_path($slug, $path);
    if ($backingFile !== null) {
        $fallback = max($fallback, (int) @filemtime($backingFile));
    }

    return gmdate('c', $fallback);
}

function base_schemas(array $page): array
{
    $config = site_config();
    $schemas = [];

    $localBusiness = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $config['site_name'],
        'url' => $config['site_url'],
        'areaServed' => [
            '@type' => 'City',
            'name' => $config['city'] . ', ' . $config['region'],
        ],
        'serviceArea' => [
            '@type' => 'AdministrativeArea',
            'name' => $config['service_area'],
        ],
        'description' => $config['tagline'],
    ];

    if ($config['business_email'] !== '') {
        $localBusiness['email'] = $config['business_email'];
    }

    if ($config['business_phone'] !== '') {
        $localBusiness['telephone'] = $config['business_phone'];
    }

    $schemas[] = $localBusiness;
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $config['site_name'],
        'url' => $config['site_url'],
    ];
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $page['title'],
        'url' => $page['canonical'],
        'description' => $page['description'],
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $config['site_name'],
            'url' => $config['site_url'],
        ],
    ];

    if (!empty($page['schema_person']) && is_array($page['schema_person'])) {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => (string) ($page['schema_person']['name'] ?? ''),
            'jobTitle' => (string) ($page['schema_person']['jobTitle'] ?? ''),
            'description' => (string) ($page['schema_person']['description'] ?? ''),
            'worksFor' => [
                '@type' => 'LocalBusiness',
                'name' => $config['site_name'],
                'url' => $config['site_url'],
            ],
            'url' => $page['canonical'],
        ];
    }

    if (($page['slug'] ?? '') !== 'home') {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => absolute_url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $page['title'],
                    'item' => $page['canonical'],
                ],
            ],
        ];
    }

    if (!empty($page['faq_items'])) {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ],
                $page['faq_items']
            ),
        ];
    }

    if (in_array($page['template'] ?? '', ['service', 'local', 'commercial'], true)) {
        $serviceName = $page['schema_name'] ?? preg_replace('/ \| .+$/', '', $page['title']);
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $serviceName,
            'serviceType' => $page['schema_service_type'] ?? $serviceName,
            'provider' => [
                '@type' => 'LocalBusiness',
                'name' => $config['site_name'],
                'url' => $config['site_url'],
            ],
            'areaServed' => $config['city'] . ', ' . $config['region'],
            'url' => $page['canonical'],
            'description' => $page['description'],
        ];
    }

    return $schemas;
}

function render_schemas(array $page): void
{
    $nonce = function_exists('ben_csp_nonce') ? ben_csp_nonce() : '';
    foreach (base_schemas($page) as $schema) {
        $nonceAttr = $nonce !== '' ? ' nonce="' . e($nonce) . '"' : '';
        echo '<script type="application/ld+json"' . $nonceAttr . '>' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . PHP_EOL;
    }
}

function navigation_groups(): array
{
    return [
        'Core Services' => [
            'backup-disaster-recovery',
            'security-risk-assessments',
            'monthly-it-support-plans',
            'network-security',
            'case-study-backup-recovery-failure',
        ],
        'Company' => [
            'about',
            'service-area',
            'faq',
            'contact',
        ],
    ];
}

function contact_service_options(): array
{
    $allowed = site_config()['allowed_service_types'];
    $preferredKeys = [
        'risk-assessment',
        'backup-recovery-failure',
        'security-recovery-risk',
        'backup-dr',
        'monthly-backup-verification',
        'network-security',
        'compliance',
        'other',
    ];

    $options = [];
    foreach ($preferredKeys as $key) {
        if (isset($allowed[$key])) {
            $options[$key] = $allowed[$key];
        }
    }

    return $options;
}

function service_cards(array $slugs): array
{
    $content = [];
    foreach ($slugs as $slug) {
        $page = page_definition($slug);
        $title = $page['card_title'] ?? $page['hero_title'] ?? preg_replace('/ \| .+$/', '', $page['title']);
        $content[] = [
            'title' => $title,
            'copy' => $page['card_copy'] ?? $page['description'],
            'href' => page_href($slug),
            'link_label' => $page['card_link_label'] ?? 'See ' . $title,
        ];
    }

    return $content;
}

function render_contact_form(string $context = 'general', string $heading = 'Start a Recovery Assessment', string $copy = 'A Recovery Assessment checks whether your backups, restore process, and failure response are actually usable.'): void
{
    $flash = pull_flash();
    $statusClass = 'form-response';
    $statusMessage = '';

    if ($flash !== null) {
        $statusClass .= !empty($flash['success']) ? ' is-success' : ' is-error';
        $statusMessage = (string) ($flash['message'] ?? '');
    }

    $serviceType = old_input('service_type', old_input('prefill_service_type', ''));
    if ($serviceType === '' && isset($_GET['service'])) {
        $candidate = trim((string) $_GET['service']);
        if (array_key_exists($candidate, site_config()['allowed_service_types'])) {
            $serviceType = $candidate;
        }
    }
    ?>
    <section class="contact-panel" id="contact-form">
      <div class="contact-panel__intro">
        <span class="eyebrow">Recovery Assessment</span>
        <h2><?= e($heading) ?></h2>
        <p><?= e($copy) ?></p>
      </div>
      <form class="site-form" action="<?= e(asset_url('contact-handler.php')) ?>" method="post" data-async-form novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form_context" value="<?= e($context) ?>">
        <div class="form-honeypot" aria-hidden="true">
          <label for="website">Leave this field blank</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" autocomplete="name" value="<?= e(old_input('name')) ?>" required aria-describedby="name-error">
            <p class="field-error" id="name-error" data-field-error="name"></p>
          </div>
          <div class="field">
            <label for="company">Business Name</label>
            <input id="company" name="company" type="text" autocomplete="organization" value="<?= e(old_input('company')) ?>" required aria-describedby="company-error">
            <p class="field-error" id="company-error" data-field-error="company"></p>
          </div>
          <div class="field">
            <label for="email">Work Email</label>
            <input id="email" name="email" type="email" autocomplete="email" inputmode="email" value="<?= e(old_input('email')) ?>" required aria-describedby="email-error">
            <p class="field-error" id="email-error" data-field-error="email"></p>
          </div>
          <div class="field">
            <label for="phone">Phone <span class="field-optional">(optional)</span></label>
            <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" value="<?= e(old_input('phone')) ?>" aria-describedby="phone-error">
            <p class="field-error" id="phone-error" data-field-error="phone"></p>
          </div>
          <div class="field field--full">
            <label for="service_type">What Should We Review?</label>
            <select id="service_type" name="service_type" required aria-describedby="service-type-error">
              <option value="">Select a review type</option>
              <?php foreach (contact_service_options() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $serviceType === $value ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field-error" id="service-type-error" data-field-error="service_type"></p>
          </div>
          <div class="field">
            <label for="main_concern">Primary Concern</label>
            <select id="main_concern" name="main_concern" required aria-describedby="main-concern-error">
              <option value="">Select primary concern</option>
              <option value="Backup recovery"<?= old_input('main_concern') === 'Backup recovery' ? ' selected' : '' ?>>Backup recovery</option>
              <option value="Account access"<?= old_input('main_concern') === 'Account access' ? ' selected' : '' ?>>Account access</option>
              <option value="Ransomware"<?= old_input('main_concern') === 'Ransomware' ? ' selected' : '' ?>>Ransomware</option>
              <option value="Downtime"<?= old_input('main_concern') === 'Downtime' ? ' selected' : '' ?>>Downtime</option>
              <option value="Not sure"<?= old_input('main_concern') === 'Not sure' ? ' selected' : '' ?>>Not sure</option>
            </select>
            <p class="field-error" id="main-concern-error" data-field-error="main_concern"></p>
          </div>
          <div class="field field--full">
            <label for="message">What Happens If Your Main System Is Unavailable Tomorrow?</label>
            <textarea id="message" name="message" rows="5" placeholder="Share the system you cannot afford to lose, when you last tested a restore, and what worries you most about downtime or ransomware." required aria-describedby="message-error"><?= e(old_input('message')) ?></textarea>
            <p class="field-error" id="message-error" data-field-error="message"></p>
          </div>
        </div>
        <div class="form-actions">
          <button class="button button--primary" type="submit" data-submit-button>Request a Recovery Assessment</button>
          <p class="form-meta">Salt Lake metro only. Best fit: small offices that need tested backups, recovery planning, or ransomware resilience.</p>
        </div>
        <p class="<?= e($statusClass) ?>" aria-live="polite" role="status" tabindex="-1" data-form-status><?= e($statusMessage) ?></p>
      </form>
    </section>
    <?php
    clear_old_input();
}

function render_card_grid(array $items, string $modifier = ''): void
{
    $className = 'card-grid' . ($modifier !== '' ? ' ' . $modifier : '');
    echo '<div class="' . e($className) . '">';
    foreach ($items as $item) {
        echo '<article class="card">';
        echo '<h3>' . e($item['title']) . '</h3>';
        echo '<p>' . e($item['copy']) . '</p>';
        if (!empty($item['href']) && !empty($item['link_label'])) {
            echo '<a class="text-link" href="' . e($item['href']) . '">' . e($item['link_label']) . '</a>';
        } elseif (!empty($item['href'])) {
            echo '<a class="text-link" href="' . e($item['href']) . '">Review ' . e($item['title']) . '</a>';
        }
        echo '</article>';
    }
    echo '</div>';
}

function render_feature_list(array $items, string $className = 'check-list'): void
{
    echo '<ul class="' . e($className) . '">';
    foreach ($items as $item) {
        echo '<li>' . e($item) . '</li>';
    }
    echo '</ul>';
}

function render_home(array $page): void
{
    ?>
    <section class="hero hero--home">
      <div class="hero__mesh" aria-hidden="true"></div>
      <div class="container hero__layout">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e(recovery_assessment_href('risk-assessment', 'contact-form')) ?>">Request a Recovery Assessment</a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
        <aside class="hero__panel">
          <p class="hero__panel-label"><?= e($page['hero_panel_label'] ?? 'Who We Help') ?></p>
          <ul class="hero__panel-list">
            <?php foreach ($page['trust_points'] as $point): ?>
              <li><?= e($point) ?></li>
            <?php endforeach; ?>
          </ul>
        </aside>
      </div>
    </section>

    <section class="trust-strip">
      <div class="container trust-strip__inner">
        <?php foreach ($page['trust_strip'] as $item): ?>
          <span><?= e($item) ?></span>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Who We Help</span>
          <h2>Small Salt Lake metro offices with 5-20 users that cannot afford prolonged downtime.</h2>
        </div>
        <?php render_card_grid($page['core_services']); ?>
        <p><a class="text-link" href="<?= e(page_href('services')) ?>">See what happens after the assessment</a></p>
      </div>
    </section>

    <?php if (!empty($page['founder_section'])): ?>
      <section class="section">
        <div class="container split">
          <article>
            <span class="eyebrow"><?= e($page['founder_section']['eyebrow']) ?></span>
            <h2><?= e($page['founder_section']['title']) ?></h2>
            <?php foreach ($page['founder_section']['paragraphs'] as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
            <a class="button button--ghost button--small" href="<?= e($page['founder_section']['cta_href']) ?>"><?= e($page['founder_section']['cta_label']) ?></a>
          </article>
          <article class="card card--tall">
            <span class="card__eyebrow"><?= e($page['founder_section']['card_title']) ?></span>
            <?php render_feature_list($page['founder_section']['card_points']); ?>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Before Clients Call</span>
          <h2>What usually goes wrong before downtime becomes expensive.</h2>
        </div>
        <div class="card-grid">
          <?php foreach ($page['warning_signs'] as $item): ?>
            <article class="card">
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Core Outcomes</span>
          <h2>Verified recovery, restore-tested backups, and measurable downtime prevention.</h2>
        </div>
        <div class="card-grid">
          <?php foreach ($page['proof_strip'] as $item): ?>
            <article class="card">
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <?php if (!empty($page['case_study_block'])): ?>
      <section class="section">
        <div class="container split">
          <article>
            <span class="eyebrow"><?= e($page['case_study_block']['eyebrow']) ?></span>
            <h2><?= e($page['case_study_block']['title']) ?></h2>
            <?php foreach ($page['case_study_block']['paragraphs'] as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
            <div class="cta-box__actions">
              <a class="button button--ghost button--small" href="<?= e($page['case_study_block']['href']) ?>"><?= e($page['case_study_block']['label']) ?></a>
              <?php if (!empty($page['case_study_block']['secondary_href']) && !empty($page['case_study_block']['secondary_label'])): ?>
                <a class="button button--ghost button--small" href="<?= e($page['case_study_block']['secondary_href']) ?>"><?= e($page['case_study_block']['secondary_label']) ?></a>
              <?php endif; ?>
            </div>
          </article>
          <article class="card card--tall">
            <span class="card__eyebrow"><?= e($page['case_study_block']['card_title']) ?></span>
            <?php if (!empty($page['case_study_block']['card_copy'])): ?>
              <p><?= e($page['case_study_block']['card_copy']) ?></p>
            <?php endif; ?>
            <?php render_feature_list($page['case_study_block']['points']); ?>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <section class="section section--alt">
      <div class="container split">
        <div>
          <span class="eyebrow">How We Work</span>
          <h2>Defined scope, local accountability, and decisions tied to operational risk.</h2>
        </div>
        <div class="stack">
          <?php foreach ($page['why_cards'] as $item): ?>
            <article class="statement">
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Engagement Models</span>
          <h2>Start by finding out what fails. Then fix it, enforce it, or monitor it.</h2>
        </div>
        <div class="comparison">
          <?php foreach ($page['engagements'] as $item): ?>
            <article class="comparison__card">
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
              <?php render_feature_list($item['points'], 'mini-list'); ?>
              <a class="button button--ghost button--small" href="<?= e($item['href']) ?>"><?= e($item['link_label']) ?></a>
            </article>
          <?php endforeach; ?>
        </div>
        <article class="cta-box">
          <span class="eyebrow">Recommended Action</span>
          <h2>Start with a Recovery Assessment.</h2>
          <p>If recovery has not been tested, failure is only delayed. A Recovery Assessment shows what can be restored, what fails, and what has to be fixed first.</p>
          <div class="cta-box__actions">
            <a class="button button--primary" href="<?= e(recovery_assessment_href('risk-assessment', 'contact-form')) ?>">Request a Recovery Assessment</a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost button--small" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </article>
      </div>
    </section>

    <section class="section section--accent">
      <div class="container split">
        <div>
          <span class="eyebrow">Recovery Dependencies</span>
          <h2><?= e($page['access_control']['title']) ?></h2>
          <p><?= e($page['access_control']['copy']) ?></p>
        </div>
        <div class="card card--tall">
          <?php render_feature_list($page['access_control']['points']); ?>
          <a class="button button--primary button--small" href="<?= e(page_href('security-risk-assessments')) ?>">Identify Recovery Dependencies</a>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <div>
          <span class="eyebrow">Salt Lake Focus</span>
          <h2><?= e($page['service_area']['title']) ?></h2>
          <p><?= e($page['service_area']['copy']) ?></p>
        </div>
        <div class="card card--tall">
          <?php render_feature_list($page['service_area']['points']); ?>
          <a class="text-link" href="<?= e(page_href('service-area')) ?>">Review service area details</a>
        </div>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Process</span>
          <h2>How the work moves from findings to verified recovery confidence.</h2>
        </div>
        <div class="process-grid">
          <?php foreach ($page['process'] as $item): ?>
            <article class="process-step">
              <span class="process-step__index"><?= e($item['step']) ?></span>
              <h3><?= e($item['title']) ?></h3>
              <p><?= e($item['copy']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <div>
          <span class="eyebrow">FAQ</span>
          <h2>Questions owners ask when downtime, ransomware, or backup uncertainty is on the table.</h2>
        </div>
        <div class="faq-list">
          <?php foreach ($page['faq_items'] as $item): ?>
            <details class="faq-item">
              <summary><?= e($item['question']) ?></summary>
              <p><?= e($item['answer']) ?></p>
            </details>
          <?php endforeach; ?>
          <a class="text-link" href="<?= e(page_href('faq')) ?>">Read the full FAQ</a>
        </div>
      </div>
    </section>

    <section class="section section--accent">
      <div class="container">
        <?php render_contact_form('homepage', $page['final_cta']['title'], $page['final_cta']['copy']); ?>
      </div>
    </section>
    <?php
}

function render_service_like_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e(recovery_assessment_href($page['contact_service_type'], 'contact-form')) ?>"><?= e($page['primary_cta_label'] ?? 'Request a Recovery Assessment') ?></a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container two-up">
        <article class="card card--tall">
          <span class="card__eyebrow">Pain Points</span>
          <h2>Where businesses usually feel the strain.</h2>
          <?php render_feature_list($page['pain_points']); ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Included</span>
          <h2>What the work actually covers.</h2>
          <?php render_feature_list($page['included']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container split">
        <article>
          <span class="eyebrow">Who It Is For</span>
          <h2><?= e($page['who_for_title']) ?></h2>
          <p><?= e($page['who_for_copy']) ?></p>
        </article>
        <article>
          <span class="eyebrow">Why It Matters</span>
          <h2><?= e($page['why_title']) ?></h2>
          <?php foreach ($page['why_paragraphs'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
      </div>
    </section>

    <?php if (!empty($page['process_steps'])): ?>
      <section class="section">
        <div class="container">
          <div class="section-heading">
            <span class="eyebrow">Simple Process</span>
            <h2><?= e($page['process_heading'] ?? 'How the engagement moves from concern to action.') ?></h2>
          </div>
          <div class="process-grid">
            <?php foreach ($page['process_steps'] as $item): ?>
              <article class="process-step">
                <span class="process-step__index"><?= e($item['step']) ?></span>
                <h3><?= e($item['title']) ?></h3>
                <p><?= e($item['copy']) ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <section class="section">
      <div class="container split">
        <article class="card card--tall">
          <span class="card__eyebrow">Deliverables</span>
          <h2>Clear outputs, not vague promises.</h2>
          <?php render_feature_list($page['deliverables']); ?>
        </article>
        <article class="cta-box">
          <span class="eyebrow">Next Step</span>
          <h2><?= e($page['cta_title']) ?></h2>
          <p><?= e($page['cta_copy']) ?></p>
          <div class="cta-box__actions">
            <a class="button button--primary" href="<?= e(recovery_assessment_href($page['contact_service_type'], 'contact-form')) ?>"><?= e($page['primary_cta_label'] ?? 'Request a Recovery Assessment') ?></a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost button--small" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </article>
      </div>
    </section>

    <?php if (!empty($page['assessment_checks']) && !empty($page['assessment_outputs'])): ?>
      <section class="section section--alt">
        <div class="container two-up">
          <article class="card card--tall">
            <span class="card__eyebrow">What We Check</span>
            <h2>Controls linked directly to downtime and recovery risk.</h2>
            <?php render_feature_list($page['assessment_checks']); ?>
          </article>
          <article class="card card--tall">
            <span class="card__eyebrow">What You Receive</span>
            <h2>Clear findings, priorities, and recovery actions.</h2>
            <?php render_feature_list($page['assessment_outputs']); ?>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($page['assessment_findings'])): ?>
      <section class="section">
        <div class="container">
          <div class="section-heading">
            <span class="eyebrow">Sample Assessment Findings</span>
            <h2>Common breakdowns found before an outage or ransomware event.</h2>
          </div>
          <?php render_card_grid($page['assessment_findings']); ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($page['assessment_explanation'])): ?>
      <section class="section section--alt">
        <div class="container split">
          <article>
            <span class="eyebrow">Backup Validation</span>
            <?php foreach ($page['assessment_explanation'] as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
          </article>
          <?php if (!empty($page['assessment_pricing'])): ?>
            <article class="cta-box">
              <span class="eyebrow">Assessment Offer</span>
              <h2><?= e($page['assessment_pricing']['title']) ?></h2>
              <p><?= e($page['assessment_pricing']['copy']) ?></p>
              <a class="button button--primary" href="<?= e(recovery_assessment_href('risk-assessment', 'contact-form')) ?>">Start a Recovery Assessment</a>
            </article>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($page['show_inline_form'])): ?>
      <section class="section section--accent">
        <div class="container">
          <?php render_contact_form('assessment-page', 'Start a Recovery Assessment', 'A Recovery Assessment checks what can be restored, what fails during an outage, and what has to be fixed before it matters.'); ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Internal Links</span>
          <h2>Related pages for the next decision.</h2>
        </div>
        <?php render_card_grid(service_cards($page['related_links']), 'card-grid--compact'); ?>
        <p>Also review <a class="text-link" href="<?= e(page_href('faq')) ?>">the FAQ</a>, <a class="text-link" href="<?= e(page_href('about')) ?>">about Bald Eagle</a>, and <a class="text-link" href="<?= e(page_href('service-area')) ?>">the Salt Lake service area</a>.</p>
      </div>
    </section>
    <?php
}

function render_services_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Service Stack</span>
          <h2>What happens after the Recovery Assessment.</h2>
        </div>
        <?php render_card_grid($page['service_cards']); ?>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container split">
        <div>
          <span class="eyebrow">How Engagements Start</span>
          <h2>Every engagement starts with test findings, then moves into the specific fix path your business actually needs.</h2>
        </div>
        <div class="card card--tall">
          <?php render_feature_list($page['approach']); ?>
          <div class="cta-box__actions">
            <a class="button button--primary button--small" href="<?= e(recovery_assessment_href('risk-assessment', 'contact-form')) ?>">Start a Recovery Assessment</a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost button--small" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <?php if (!empty($page['final_cta'])): ?>
      <section class="section">
        <div class="container">
          <article class="cta-box">
            <span class="eyebrow">Best Next Step</span>
            <h2><?= e($page['final_cta']['title']) ?></h2>
            <p><?= e($page['final_cta']['copy']) ?></p>
            <div class="cta-box__actions">
              <a class="button button--primary" href="<?= e($page['final_cta']['href']) ?>"><?= e($page['final_cta']['label']) ?></a>
            </div>
          </article>
        </div>
      </section>
    <?php endif; ?>
    <?php
}

function render_about_page(array $page): void
{
    $sections = $page['about_sections'] ?? [];
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e($page['final_cta']['href']) ?>"><?= e($page['final_cta']['label']) ?></a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">About</span>
          <h2><?= e($sections[0]['title'] ?? '') ?></h2>
          <?php foreach (($sections[0]['paragraphs'] ?? []) as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Direct Accountability</span>
          <h2><?= e($sections[1]['title'] ?? '') ?></h2>
          <?php foreach (($sections[1]['paragraphs'] ?? []) as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Background</span>
          <h2>Experience and security priorities shaped by direct hands-on support work.</h2>
        </div>
        <div class="two-up">
          <article class="card card--tall">
            <span class="card__eyebrow">Experience</span>
            <h2><?= e($sections[2]['title'] ?? '') ?></h2>
            <?php foreach (($sections[2]['paragraphs'] ?? []) as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
          </article>
          <article class="card card--tall">
            <span class="card__eyebrow">Security Standard</span>
            <h2><?= e($sections[3]['title'] ?? '') ?></h2>
            <?php foreach (($sections[3]['paragraphs'] ?? []) as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">Best Fit</span>
          <h2><?= e($sections[4]['title'] ?? '') ?></h2>
          <?php foreach (($sections[4]['paragraphs'] ?? []) as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">How Work Moves</span>
          <h2><?= e($sections[5]['title'] ?? '') ?></h2>
          <?php foreach (($sections[5]['paragraphs'] ?? []) as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container two-up">
        <article class="card card--tall">
          <span class="card__eyebrow">Credentials</span>
          <h2><?= e($sections[6]['title'] ?? '') ?></h2>
          <?php foreach (($sections[6]['paragraphs'] ?? []) as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="cta-box">
          <span class="eyebrow">Ownership</span>
          <h2><?= e($sections[7]['title'] ?? '') ?></h2>
          <?php foreach (($sections[7]['paragraphs'] ?? []) as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
          <div class="cta-box__actions">
            <a class="button button--primary button--small" href="<?= e($page['final_cta']['href']) ?>"><?= e($page['final_cta']['label']) ?></a>
          </div>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">At A Glance</span>
          <h2>Direct handling, security-first priorities, and clear fit for small businesses.</h2>
        </div>
        <?php render_card_grid($page['fit_cards']); ?>
        <?php if (!empty($page['related_links'])): ?>
          <p>See also <a class="text-link" href="<?= e(page_href('security-risk-assessments')) ?>">the Recovery Assessment</a>, <a class="text-link" href="<?= e(page_href('monthly-it-support-plans')) ?>">monthly backup verification</a>, and <a class="text-link" href="<?= e(page_href('backup-disaster-recovery')) ?>">backup readiness</a>.</p>
        <?php endif; ?>
      </div>
    </section>

    <?php if (!empty($page['final_cta'])): ?>
      <section class="section">
        <div class="container">
          <article class="cta-box">
            <span class="eyebrow">Assessment First</span>
            <h2><?= e($page['final_cta']['title']) ?></h2>
            <p><?= e($page['final_cta']['copy']) ?></p>
            <div class="cta-box__actions">
              <a class="button button--primary" href="<?= e($page['final_cta']['href']) ?>"><?= e($page['final_cta']['label']) ?></a>
            </div>
          </article>
        </div>
      </section>
    <?php endif; ?>
    <?php
}

function render_service_area_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container two-up">
        <article class="card card--tall">
          <span class="card__eyebrow">Coverage</span>
          <h2>Salt Lake metro only.</h2>
          <?php render_feature_list($page['coverage_points']); ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Why This Matters</span>
          <h2>Local travel discipline keeps project windows predictable.</h2>
          <?php render_feature_list($page['why_local']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Typical Work</span>
          <h2>Most local engagements fall into these categories.</h2>
        </div>
        <?php render_card_grid($page['local_cards']); ?>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <?php render_contact_form('service-area', 'Request a Recovery Assessment', 'Tell us where your office is, what system cannot stay down, and whether backup, ransomware, or access risk is the main concern.'); ?>
      </div>
    </section>
    <?php
}

function render_contact_page(array $page): void
{
    $config = site_config();
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
        <aside class="hero__panel">
          <p class="hero__panel-label">Service Limits</p>
          <ul class="hero__panel-list">
            <li><?= e($config['service_area']) ?></li>
            <li>Assessment-first engagement model</li>
            <li>Verified recovery and backup restore testing priorities</li>
            <li>Scoped remediation, not support-queue sprawl</li>
          </ul>
        </aside>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">What To Send</span>
          <h2>Enough detail to understand recovery risk, business impact, and urgency.</h2>
          <?php render_feature_list($page['contact_points']); ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Response Standard</span>
          <?php render_feature_list($page['response_points']); ?>
        </article>
      </div>
    </section>

    <section class="section section--accent">
      <div class="container">
        <?php render_contact_form('contact-page', $page['form_title'], $page['form_copy']); ?>
      </div>
    </section>
    <?php
}

function render_faq_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Common Questions</span>
          <h2>Answers for owners reviewing backup readiness, ransomware resilience, and continuity risk.</h2>
        </div>
      </div>
      <div class="container faq-list faq-list--full">
        <?php foreach ($page['faq_items'] as $item): ?>
          <details class="faq-item">
            <summary><?= e($item['question']) ?></summary>
            <p><?= e($item['answer']) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if (!empty($page['final_cta'])): ?>
      <section class="section section--accent">
        <div class="container">
          <article class="cta-box">
            <span class="eyebrow">Next Step</span>
            <h2><?= e($page['final_cta']['title']) ?></h2>
            <p><?= e($page['final_cta']['copy']) ?></p>
            <div class="cta-box__actions">
              <a class="button button--primary" href="<?= e($page['final_cta']['href']) ?>"><?= e($page['final_cta']['label']) ?></a>
            </div>
          </article>
        </div>
      </section>
    <?php endif; ?>
    <?php
}

function render_legal_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container legal-copy">
        <?php foreach ($page['sections'] as $section): ?>
          <section class="legal-copy__section">
            <h2><?= e($section['title']) ?></h2>
            <?php foreach ($section['paragraphs'] as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
}

function render_recovery_assessment_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <?php foreach ($page['hero_paragraphs'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
          <div class="hero__actions">
            <a class="button button--primary" href="#contact-form"><?= e($page['primary_cta_label']) ?></a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">Assessment Scope</span>
          <h2>What This Is</h2>
          <?php foreach ($page['what_this_is'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Recovery Standard</span>
          <h2>If recovery has not been tested, it is not considered reliable.</h2>
          <?php render_feature_list($page['assessment_standard']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Review Areas</span>
          <h2>What Is Checked</h2>
        </div>
        <?php render_card_grid($page['what_is_checked_cards']); ?>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article class="card card--tall">
          <span class="card__eyebrow">Assessment Output</span>
          <h2>What You Get</h2>
          <?php render_feature_list($page['what_you_get']); ?>
        </article>
        <article>
          <span class="eyebrow">Plain-Language Review</span>
          <?php foreach ($page['what_you_get_copy'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container split">
        <article>
          <span class="eyebrow">Next Actions</span>
          <h2>What Happens After</h2>
          <?php foreach ($page['what_happens_after'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Follow-Through</span>
          <?php render_feature_list($page['after_steps']); ?>
        </article>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">Limits</span>
          <h2>What This Is Not</h2>
          <?php foreach ($page['what_this_is_not_copy'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Not Included</span>
          <?php render_feature_list($page['what_this_is_not']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Before It Fails</span>
          <h2>Why This Matters Before Failure</h2>
        </div>
        <?php render_card_grid($page['why_this_matters_cards']); ?>
        <?php foreach ($page['why_this_matters_copy'] as $paragraph): ?>
          <p><?= e($paragraph) ?></p>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if (!empty($page['proof_block'])): ?>
      <section class="section">
        <div class="container split">
          <article>
            <span class="eyebrow"><?= e($page['proof_block']['eyebrow']) ?></span>
            <h2><?= e($page['proof_block']['title']) ?></h2>
            <?php foreach ($page['proof_block']['paragraphs'] as $paragraph): ?>
              <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>
          </article>
          <article class="cta-box">
            <span class="eyebrow"><?= e($page['proof_block']['card_eyebrow']) ?></span>
            <h2><?= e($page['proof_block']['card_title']) ?></h2>
            <p><?= e($page['proof_block']['card_copy']) ?></p>
            <div class="cta-box__actions">
              <a class="button button--ghost button--small" href="<?= e($page['proof_block']['href']) ?>"><?= e($page['proof_block']['label']) ?></a>
              <?php if (!empty($page['proof_block']['secondary_href']) && !empty($page['proof_block']['secondary_label'])): ?>
                <a class="button button--ghost button--small" href="<?= e($page['proof_block']['secondary_href']) ?>"><?= e($page['proof_block']['secondary_label']) ?></a>
              <?php endif; ?>
            </div>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <section class="section section--accent">
      <div class="container">
        <article class="cta-box">
          <span class="eyebrow">Primary Conversion</span>
          <h2>Start a Recovery Assessment</h2>
          <p><?= e($page['cta_supporting_line']) ?></p>
          <div class="cta-box__actions">
            <a class="button button--primary" href="#contact-form">Start a Recovery Assessment</a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost button--small" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </article>
      </div>
    </section>

    <?php if (!empty($page['submission_clarity'])): ?>
      <section class="section">
        <div class="container split">
          <article>
            <span class="eyebrow">Clarity</span>
            <h2><?= e($page['submission_clarity']['title']) ?></h2>
            <?php foreach ($page['submission_clarity']['points'] as $point): ?>
              <p><?= e($point) ?></p>
            <?php endforeach; ?>
            <p><?= e($page['submission_clarity']['trust_line']) ?></p>
          </article>
          <article class="card card--tall">
            <span class="card__eyebrow">Next Output</span>
            <h2><?= e($page['submission_clarity']['what_you_get_title']) ?></h2>
            <?php render_feature_list($page['submission_clarity']['what_you_get']); ?>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <section class="section section--accent">
      <div class="container">
        <?php render_contact_form('recovery-assessment', 'Start a Recovery Assessment', 'Find out what actually happens when your systems fail.'); ?>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Related Pages</span>
          <h2>Where recovery work goes next.</h2>
        </div>
        <?php render_card_grid(service_cards($page['related_links']), 'card-grid--compact'); ?>
      </div>
    </section>
    <?php
}

function render_not_found_page(array $page): void
{
    ?>
    <section class="hero hero--interior hero--not-found">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e(page_href('home')) ?>">Return Home</a>
            <a class="button button--ghost" href="<?= e(page_href('security-risk-assessments')) ?>">Request a Recovery Assessment</a>
          </div>
        </div>
      </div>
    </section>
    <?php
}

function render_case_study_page(array $page): void
{
    ?>
    <section class="hero hero--interior">
      <div class="container hero__layout hero__layout--single">
        <div class="hero__content">
          <span class="eyebrow"><?= e($page['hero_kicker']) ?></span>
          <h1><?= e($page['hero_title']) ?></h1>
          <p class="hero__lede"><?= e($page['hero_intro']) ?></p>
          <div class="hero__actions">
            <a class="button button--primary" href="<?= e($page['cta']['href']) ?>"><?= e($page['cta']['label']) ?></a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">Context</span>
          <h2>The Situation</h2>
          <?php foreach ($page['situation'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">What Was Wrong</span>
          <?php render_feature_list($page['what_was_wrong']); ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container two-up">
        <article class="card card--tall">
          <span class="card__eyebrow"><?= e($page['what_failed_eyebrow'] ?? 'Recovery Test') ?></span>
          <h2><?= e($page['what_failed_title'] ?? 'What Failed') ?></h2>
          <?php render_feature_list($page['what_failed']); ?>
        </article>
        <article class="card card--tall">
          <span class="card__eyebrow">Corrective Work</span>
          <h2>What Was Fixed</h2>
          <?php render_feature_list($page['what_was_fixed']); ?>
        </article>
      </div>
    </section>

    <?php if (!empty($page['mid_cta'])): ?>
      <section class="section">
        <div class="container">
          <article class="cta-box">
            <span class="eyebrow"><?= e($page['mid_cta']['eyebrow']) ?></span>
            <h2><?= e($page['mid_cta']['title']) ?></h2>
            <p><?= e($page['mid_cta']['copy']) ?></p>
            <div class="cta-box__actions">
              <a class="button button--primary" href="<?= e($page['mid_cta']['href']) ?>"><?= e($page['mid_cta']['label']) ?></a>
            </div>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <section class="section">
      <div class="container split">
        <article>
          <span class="eyebrow">Business Impact</span>
          <h2>Why It Mattered</h2>
          <?php foreach ($page['why_it_mattered'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
        <article class="cta-box">
          <span class="eyebrow">Outcome</span>
          <h2><?= e($page['outcome_title']) ?></h2>
          <?php foreach ($page['outcome'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
          <?php endforeach; ?>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">What To Check</span>
          <h2>What Other Small Businesses Should Check</h2>
        </div>
        <?php render_card_grid($page['check_cards']); ?>
      </div>
    </section>

    <section class="section section--accent">
      <div class="container">
        <article class="cta-box">
          <span class="eyebrow">Next Step</span>
          <h2><?= e($page['cta']['title']) ?></h2>
          <p><?= e($page['cta']['copy']) ?></p>
          <div class="cta-box__actions">
            <a class="button button--primary" href="<?= e($page['cta']['href']) ?>"><?= e($page['cta']['label']) ?></a>
            <?php if (!empty($page['secondary_cta']['href']) && !empty($page['secondary_cta']['label'])): ?>
              <a class="button button--ghost button--small" href="<?= e($page['secondary_cta']['href']) ?>"><?= e($page['secondary_cta']['label']) ?></a>
            <?php endif; ?>
          </div>
        </article>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container">
        <div class="section-heading">
          <span class="eyebrow">Related Pages</span>
          <h2>Keep the next step tied to recovery.</h2>
        </div>
        <?php render_card_grid(service_cards($page['related_links']), 'card-grid--compact'); ?>
      </div>
    </section>
    <?php
}

function render_site_page(array $page): void
{
    $page = normalize_page($page);
    if (($page['template'] ?? '') === '404') {
        http_response_code(404);
    }
    include __DIR__ . '/header.php';

    echo '<main id="main-content" class="site-main">';
    switch ($page['template']) {
        case 'home':
            render_home($page);
            break;
        case 'service':
        case 'commercial':
        case 'local':
            render_service_like_page($page);
            break;
        case 'services':
            render_services_page($page);
            break;
        case 'about':
            render_about_page($page);
            break;
        case 'service-area':
            render_service_area_page($page);
            break;
        case 'contact':
            render_contact_page($page);
            break;
        case 'faq':
            render_faq_page($page);
            break;
        case 'recovery-assessment':
            render_recovery_assessment_page($page);
            break;
        case 'case-study':
            render_case_study_page($page);
            break;
        case 'legal':
            render_legal_page($page);
            break;
        case '404':
            render_not_found_page($page);
            break;
        default:
            render_service_like_page($page);
            break;
    }
    echo '</main>';

    include __DIR__ . '/footer.php';
}
