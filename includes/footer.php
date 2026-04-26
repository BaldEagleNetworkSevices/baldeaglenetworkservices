<?php
declare(strict_types=1);

$config = site_config();
?>
    <footer class="site-footer">
      <div class="container footer-grid">
        <div>
          <p class="footer-title"><?= e($config['site_name']) ?></p>
          <p class="footer-copy">Recovery readiness testing, verified backup restore validation, and ransomware resilience support for Salt Lake metro offices.</p>
        </div>
        <div>
          <p class="footer-title">Core Pages</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('services')) ?>">Services</a></li>
            <li><a href="<?= e(page_href('backup-disaster-recovery')) ?>">Backup &amp; Recovery</a></li>
            <li><a href="<?= e(page_href('managed-it-services')) ?>">Recovery Planning</a></li>
            <li><a href="<?= e(page_href('service-area')) ?>">Service Area</a></li>
          </ul>
        </div>
        <div>
          <p class="footer-title">Continuity Focus</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('network-security')) ?>">Security Hardening</a></li>
            <li><a href="<?= e(page_href('monthly-it-support-plans')) ?>">Stability &amp; Monitoring</a></li>
            <li><a href="<?= e(page_href('security-risk-assessments')) ?>">Risk Assessment</a></li>
            <li><a href="<?= e(page_href('backup-disaster-recovery')) ?>">Recovery Verification</a></li>
          </ul>
        </div>
        <div>
          <p class="footer-title">Company</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('about')) ?>">About</a></li>
            <li><a href="<?= e(page_href('faq')) ?>">FAQ</a></li>
            <li><a href="<?= e(page_href('privacy-policy')) ?>">Privacy Policy</a></li>
            <li><a href="<?= e(page_href('terms')) ?>">Terms</a></li>
          </ul>
        </div>
      </div>
      <div class="container footer-meta">
        <p>&copy; <?= e(current_year()) ?> <?= e($config['site_name']) ?>. Salt Lake metro only.</p>
      </div>
    </footer>
  </div>
  <script src="<?= e(asset_url('js/site.js')) ?>" defer></script>
</body>
</html>
