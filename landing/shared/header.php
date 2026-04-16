<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= landing_e($page['page_title']) ?></title>
  <meta name="description" content="<?= landing_e($page['meta_description']) ?>">
  <meta name="robots" content="index,follow">
  <link rel="canonical" href="<?= landing_e(landing_url('landing/' . $page['slug'] . '.php')) ?>">
  <link rel="stylesheet" href="<?= landing_e(landing_asset_path('shared/landing.css')) ?>">
  <?php if (landing_turnstile_site_key() !== ''): ?>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <?php endif; ?>
</head>
<body>
  <main class="lp-shell">
