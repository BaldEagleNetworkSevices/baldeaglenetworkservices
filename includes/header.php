<?php
declare(strict_types=1);

ensure_session_started();

$page = normalize_page($page ?? []);
$config = site_config();
$ogImage = $page['og_image'] ?? absolute_url(asset_path('img/og-default.jpg'));
$linkedinProfileUrl = 'https://www.linkedin.com/in/baldeaglenetworkservices?trk=profile-badge';
$navItems = [
    'services' => 'Services',
    'case-study-backup-recovery-failure' => 'Case Study',
    'about' => 'About',
    'service-area' => 'Service Area',
    'contact' => 'Contact',
];

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page['title']) ?></title>
  <meta name="description" content="<?= e($page['description']) ?>">
<meta name="google-site-verification" content="zRe35B9XcMCBqUIbQzKvDgZpZY2A7PvMiWrzArBo03w" />
  <meta name="robots" content="<?= e($page['robots'] ?? 'index,follow') ?>">
  <link rel="canonical" href="<?= e($page['canonical']) ?>">
  <meta name="theme-color" content="#0c1524">
  <meta property="og:site_name" content="<?= e($config['site_name']) ?>">
  <meta property="og:type" content="<?= e($page['og_type']) ?>">
  <meta property="og:title" content="<?= e($page['title']) ?>">
  <meta property="og:description" content="<?= e($page['description']) ?>">
  <meta property="og:url" content="<?= e($page['canonical']) ?>">
  <meta property="og:locale" content="en_US">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($page['title']) ?>">
  <meta name="twitter:description" content="<?= e($page['description']) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
  <link rel="icon" href="<?= e('/favicon.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('css/baldeagle.css')) ?>">
  <?php if (!empty($page['extra_head']) && is_string($page['extra_head'])): ?>
    <?= $page['extra_head'] . PHP_EOL ?>
  <?php endif; ?>
  <?php render_schemas($page); ?>
</head>
<body class="<?= e($page['body_class']) ?>">
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div class="site-shell">
    <header class="site-header" data-site-header>
      <div class="container container--header header-bar">
        <a class="brand" href="<?= e(page_href('home')) ?>" aria-label="<?= e($config['site_name']) ?> home">
          <picture class="brand__mark">
            <source srcset="<?= e(asset_url('images/logo-primary.webp')) ?>" type="image/webp">
            <img class="brand__logo" src="<?= e(asset_url('images/logo-primary.png')) ?>" width="288" height="192" alt="Bald Eagle Network Services logo" fetchpriority="high">
          </picture>
          <span class="brand__text">
            <strong><?= e($config['site_name']) ?></strong>
          </span>
        </a>

        <nav class="desktop-nav" aria-label="Primary">
          <ul>
            <?php foreach ($navItems as $slug => $label): ?>
              <li><a href="<?= e(page_href($slug)) ?>"<?= $page['nav_key'] === $slug ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>

        <div class="header-actions">
          <a class="button button--primary button--small desktop-cta" href="<?= e(recovery_assessment_href('risk-assessment', 'contact-form')) ?>">Recovery Assessment</a>
          <a class="linkedin-icon" href="<?= e($linkedinProfileUrl) ?>" aria-label="Steve Carlsen on LinkedIn" title="Steve Carlsen on LinkedIn" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M6.94 8.98H3.75v10.27h3.19V8.98ZM5.35 7.58c1.02 0 1.65-.68 1.65-1.52-.02-.86-.63-1.52-1.63-1.52s-1.65.66-1.65 1.52c0 .84.63 1.52 1.61 1.52h.02Zm4.08 11.67h3.19v-5.74c0-.31.02-.61.11-.83.23-.61.75-1.24 1.63-1.24 1.15 0 1.61.88 1.61 2.17v5.64h3.19v-6.01c0-3.22-1.72-4.72-4.01-4.72-1.85 0-2.68 1.02-3.14 1.74h.02V8.98h-3.2c.04.96 0 10.27 0 10.27h3.2Z" />
            </svg>
          </a>
          <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" data-nav-toggle>
            <span class="nav-toggle__label">Menu</span>
            <span class="nav-toggle__bars" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </button>
        </div>
      </div>
    </header>

    <div class="mobile-menu-backdrop" hidden data-mobile-backdrop></div>
    <aside class="mobile-menu" id="mobile-menu" aria-label="Mobile navigation" aria-hidden="true" data-mobile-menu inert>
      <div class="mobile-menu__inner">
        <div class="mobile-menu__top">
          <p class="mobile-menu__title"><?= e($config['site_name']) ?></p>
          <button class="mobile-menu__close" type="button" data-nav-close aria-label="Close navigation">Close</button>
        </div>
        <?php foreach (navigation_groups() as $group => $slugs): ?>
          <div class="mobile-menu__group">
            <p><?= e($group) ?></p>
            <ul>
              <?php foreach ($slugs as $slug): ?>
                <li><a href="<?= e(page_href($slug)) ?>"<?= ($page['slug'] === $slug || $page['nav_key'] === $slug) ? ' aria-current="page"' : '' ?>><?= e(page_catalog()[$slug]['label']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
        <a class="button button--primary mobile-menu__cta" href="<?= e(recovery_assessment_href('risk-assessment', 'contact-form')) ?>">Request a Recovery Assessment</a>
      </div>
    </aside>
