<?php


$basePath = '';
$currentPage = 'home';
$pageTitle = 'Bald Eagle Network Services | Small Business IT & Network Support';
$metaDescription = 'Reliable small business IT, network troubleshooting, endpoint support, and security-minded service from Bald Eagle Network Services.';
require __DIR__ . '/includes/header.php';
?>


<main id="top">
  <!-- Hero Section -->
  <section class="hero">
    <div class="container hero__content">
      <span class="kicker">Trusted Small Business IT Partner</span>
      <h1>Security-minded IT and network support that keeps your business moving</h1>
      <p>Bald Eagle Network Services helps small businesses resolve outages, stabilize networks, protect endpoints, and maintain dependable day-to-day operations with remote and on-site support options.</p>
      <div class="hero__actions">
        <a class="btn btn--primary" href="#request-service">Request Service</a>
        <a class="btn btn--secondary" href="#packages">View Packages</a>
      </div>
    </div>
  </section>

  <!-- Why Choose Us -->
  <section class="section section--tight">
    <div class="container">
      <div class="section__head">
        <span class="kicker">Why Choose Us</span>
        <h2>Practical support, clear communication, reliable follow-through</h2>
        <p>We focus on fast diagnosis, lasting fixes, and secure configurations so your team can work without constant technology disruptions.</p>
      </div>
      <div class="grid grid--3">
        <article class="card">
          <h3>Small Business Focus</h3>
          <p>Service workflows designed for growing offices that need responsive support without enterprise complexity.</p>
        </article>
        <article class="card">
          <h3>Security-First Mindset</h3>
          <p>Every support action considers access control, patch hygiene, backup readiness, and reduced risk exposure.</p>
        </article>
        <article class="card">
          <h3>Remote + On-Site Coverage</h3>
          <p>Quick remote remediation when possible, with on-site assistance available for infrastructure and hardware needs.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- Services Overview -->
  <section class="section" id="services">
    <div class="container">
      <div class="section__head">
        <span class="kicker">Core Services</span>
        <h2>End-to-end support for networks, users, and devices</h2>
      </div>
      <div class="grid grid--3">
        <article class="card">
          <h3>Network Troubleshooting</h3>
          <p>Resolve latency, dropouts, switching issues, and configuration conflicts before they impact productivity.</p>
          <a class="btn btn--secondary" href="#services">Learn More</a>
        </article>
        <article class="card">
          <h3>Endpoint &amp; Workstation Support</h3>
          <p>Keep devices healthy, secured, and updated across your office or remote workforce.</p>
          <a class="btn btn--secondary" href="#services">Learn More</a>
        </article>
        <article class="card">
          <h3>Wi-Fi &amp; Connectivity Setup</h3>
          <p>Plan and optimize wireless coverage, internet failover, and office network reliability.</p>
          <a class="btn btn--secondary" href="#services">Learn More</a>
        </article>
      </div>
    </div>
  </section>

  <!-- Packages Placeholder -->
  <section class="section section--tight" id="packages">
    <div class="container">
      <div class="section__head">
        <span class="kicker">Packages</span>
        <h2>Service package details are being finalized</h2>
        <p>We are preparing clear support tiers for small businesses that want predictable coverage, responsive help, and practical security guidance.</p>
      </div>
      <article class="card">
        <h3>Placeholder</h3>
        <p>Package pricing, scope, and response options will be published here. For now, use the request-service section below to start a conversation and get a tailored recommendation.</p>
      </article>
    </div>
  </section>

  <!-- Process Section -->
  <section class="section section--tight">
    <div class="container grid grid--2">
      <div>
        <span class="kicker">Simple Process</span>
        <h2>Support in three clear steps</h2>
        <p>From first contact to full resolution, we keep communication direct and timelines realistic.</p>
      </div>
      <ol class="number-steps">
        <li><strong>Tell us what is wrong.</strong> Share your issue, scope, and urgency through our intake workflow.</li>
        <li><strong>We assess and respond.</strong> We review symptoms, prioritize risk, and confirm a response plan.</li>
        <li><strong>We fix, secure, and support.</strong> We restore service, harden weak points, and provide follow-up guidance.</li>
      </ol>
    </div>
  </section>

  <!-- Trust / Reliability Section -->
  <section class="section section--tight" id="about">
    <div class="container grid grid--2">
      <article class="card">
        <h3>Reliability You Can Depend On</h3>
        <p>Your team needs stable systems and predictable support. We document environments, standardize maintenance, and reduce recurring incidents over time.</p>
      </article>
      <article class="card">
        <h3>Built for Business Continuity</h3>
        <p>We help protect core operations through sensible hardening, proactive checks, and escalation paths that match your business needs.</p>
      </article>
    </div>
  </section>

  <!-- Contact Placeholder -->
  <section class="section section--tight" id="contact">
    <div class="container">
      <div class="section__head">
        <span class="kicker">Contact</span>
        <h2>Direct contact details will be added here</h2>
        <p>This section is reserved for phone, email, and service-area details. Until then, use the request-service placeholder below as the primary next step.</p>
      </div>
      <article class="card">
        <h3>Placeholder</h3>
        <p>Contact information and a lightweight inquiry form are planned for this section.</p>
      </article>
    </div>
  </section>

  <!-- Final CTA -->
  <section class="section section--tight" id="request-service">
    <div class="container">
      <div class="cta-banner">
        <div>
          <h2>Need reliable IT support for your business?</h2>
          <p class="small-note">Start with a short intake and we will follow up with next steps.</p>
        </div>
        <form
          id="intake-form"
          class="intake-form"
          action="/assets/contact-handler.php"
          method="post"
          novalidate
        >
          <div class="form-grid">
            <div class="form-field">
              <label for="name">Full Name *</label>
              <input type="text" id="name" name="name" autocomplete="name" required>
            </div>

            <div class="form-field">
              <label for="company">Company *</label>
              <input type="text" id="company" name="company" autocomplete="organization" required>
            </div>

            <div class="form-field">
              <label for="email">Email *</label>
              <input type="email" id="email" name="email" autocomplete="email" required>
            </div>

            <div class="form-field">
              <label for="phone">Phone</label>
              <input type="tel" id="phone" name="phone" autocomplete="tel">
            </div>

            <div class="form-field">
              <label for="service_type">Service Needed *</label>
              <select id="service_type" name="service_type" required>
                <option value="">Select a service</option>
                <option value="managed-it">Managed IT</option>
                <option value="network-setup">Network Setup</option>
                <option value="firewall">Firewall</option>
                <option value="voip">VoIP</option>
                <option value="structured-cabling">Structured Cabling</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div class="form-field form-field--full">
              <label for="message">Tell us about the project *</label>
              <textarea id="message" name="message" rows="4" required></textarea>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Submit Request</button>
          </div>

          <p id="form-status" class="form-status" aria-live="polite"></p>
        </form>

      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
