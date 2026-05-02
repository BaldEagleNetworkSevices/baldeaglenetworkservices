<?php
declare(strict_types=1);

$config = site_config();
?>
    <footer class="site-footer">
      <div class="container footer-grid">
        <div>
          <p class="footer-title"><?= e($config['site_name']) ?></p>
          <p class="footer-copy">Recovery-first IT for small businesses that need tested backups, verified restores, and direct accountability when systems fail.</p>
        </div>
        <div>
          <p class="footer-title">Recovery Services</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('security-risk-assessments')) ?>">Recovery Assessment</a></li>
            <li><a href="<?= e(page_href('backup-disaster-recovery')) ?>">Backup &amp; Recovery</a></li>
            <li><a href="<?= e(page_href('monthly-it-support-plans')) ?>">Monthly Verification</a></li>
            <li><a href="<?= e(page_href('backup-disaster-recovery')) ?>">Recovery Verification</a></li>
            <li><a href="<?= e(page_href('network-security')) ?>">Security Hardening</a></li>
          </ul>
        </div>
        <div>
          <p class="footer-title">Recovery Example</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('case-study-backup-recovery-failure')) ?>">Backup Existed. Recovery Failed.</a></li>
            <li><a href="<?= e(page_href('about')) ?>">About</a></li>
            <li><a href="<?= e(page_href('faq')) ?>">FAQ</a></li>
            <li><a href="<?= e(page_href('contact')) ?>">Contact</a></li>
          </ul>
        </div>
        <div>
          <p class="footer-title">Company</p>
          <ul class="footer-list">
            <li><a href="<?= e(page_href('service-area')) ?>">Service Area</a></li>
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
