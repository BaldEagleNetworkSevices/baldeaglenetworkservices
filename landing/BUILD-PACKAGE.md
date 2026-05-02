# 1. Executive Summary

This build creates a reusable `/landing` workspace for Bald Eagle Network Services and seeds it with a production-oriented landing page for the `External Security Scan` product. The system is deliberately opinionated: centralized intake controls, centralized security policy, centralized CRM mapping, centralized report-token policy, and explicit per-page config. Nothing in this package treats security as a page-level concern.

Priority delivery is not considered launch-ready without server-enforced Turnstile validation before any staging-table write or queue insertion. Standard delivery can operate without Turnstile, but Turnstile remains strongly recommended.

# 2. Landing Page Strategy

The page sells a narrow product with a clear business outcome: show the buyer what the public internet can already see about their business domain. The copy avoids generic provider language and keeps the offer bounded.

Conversion strategy:

- Lead with threat visibility, not vague “peace of mind”
- Sell a product, not a consultation
- Keep intake to the minimum required fields
- Separate `Standard` from `Priority` using urgency and control requirements
- Frame the scan as low-friction because it requires no internal access
- Use scope boundaries to increase trust and reduce bad-fit submissions

# 3. Full Landing Page Copy

Hero:

- Eyebrow: `External Security Scan`
- Headline: `Find what the internet can already see about your business.`
- Lede: `Buy a focused external security scan for your domain. Bald Eagle reviews your DNS posture, website security headers, TLS, email authentication records, and basic external exposure indicators without touching internal systems.`
- Primary CTA: `Buy External Security Scan`
- Secondary CTA: `See What Is Included`

Secure intake panel:

- Title: `Secure Intake`
- Support copy: `Step 1 collects only the minimum required information. All validation and abuse checks must pass before any staging-table write or queue insertion.`
- Sensitive-data warning: `Do not enter passwords, MFA codes, API keys, payment data, internal network details, or other sensitive material.`

Scope section headline:

- `Actionable exposure findings without internal access or security theater.`

Included block:

- `Domain and DNS posture review`
- `Website TLS certificate and header review`
- `SPF, DKIM, and DMARC presence check`
- `Basic public exposure indicators and prioritised findings`

Excluded block:

- `Penetration testing`
- `Internal vulnerability scanning`
- `Authenticated assessments`
- `Incident response or compliance certification`

Pricing section headline:

- `Choose the turnaround that matches your urgency.`

Data-boundaries section headline:

- `The first step is intentionally narrow.`
- Body: `The intake form should only collect business name, business domain, work email, delivery tier, and explicit authorization. Optional enrichment fields belong later in the workflow, not in the first step.`

FAQ headline:

- `Clear scope. Clear delivery. Clear security boundaries.`

# 4. Section-by-Section Layout

1. Hero
- Headline, threat-oriented subhead, primary CTA, secondary CTA
- Sticky secure-intake card on desktop

2. Trust strip
- `No internal access required`
- `No exploit attempts`
- `Priority delivery requires Turnstile`
- `Signed report links with server-side expiration`

3. Scope block
- Two-column included vs excluded structure

4. Pricing
- Standard and Priority cards with Turnstile status shown on-card

5. Data boundaries
- Explicit statement of what Step 1 does and does not collect

6. FAQ
- Scope, product boundaries, intake expectations, report security

# 5. Pricing Tier Copy

Standard:

- Price: `$295`
- Delivery: `Report emailed within 12 to 24 hours`
- Positioning: `Best fit for normal review cycles where you need a business-ready report without urgent turnaround.`
- Points:
- `Queued after gateway validation`
- `Signed report link valid for 5 to 7 days`
- `Email delivery to verified work address`
- Turnstile status: `RECOMMENDED`

Priority:

- Price: `$595`
- Delivery: `Near-instant summary plus full report by email`
- Positioning: `Fastest path for time-sensitive reviews, but only launch-ready when Turnstile verification is enforced before queue insertion.`
- Points:
- `Turnstile required before acceptance`
- `Stricter abuse controls and domain cooldowns`
- `Signed report link valid for 24 hours`
- Turnstile status: `REQUIRED`

# 6. Secure Intake Form Specification

Step 1 allowed fields:

- `business_name` required
- `business_domain` required
- `work_email` required
- `delivery_tier` required
- `authorization_checkbox` required

Optional later only:

- `first_name`
- `phone`
- `company_size`

Mandatory checkbox:

- `I confirm I am authorized to request a scan for this business/domain.`

Prohibited in Step 1:

- `passwords`
- `MFA codes`
- `API keys`
- `secrets`
- `payment data`
- `SSNs`
- `medical data`
- `internal network details`

Hard rule:

- No freeform sensitive text area in Step 1

# 7. Validation Rules (Strict)

Request filter:

- Reject non-POST
- Reject unsupported content types
- Reject missing user-agent

Field validation:

- `business_name` 1 to 120 chars
- `business_domain` must be registrable domain only, no scheme, no path, no `@`
- `work_email` must be valid email
- `work_email` domain must match or fall under requested business domain unless later allowlisted
- `delivery_tier` must be `standard` or `priority`
- `authorization_checkbox` must equal `yes`

Abuse gating before staging or queue:

- Honeypot hit: reject
- Validation errors: reject
- Rate-limit violation: reject
- Duplicate-payload hit within 60 minutes: reject
- High abuse score: reject
- Priority without valid Turnstile: reject

# 8. SuiteCRM Field Mapping

Required mapping:

- `lead_source` -> `leads.lead_source`
- `campaign` -> `leads.campaign_name_c`
- `product_code` -> `leads.product_code_c`
- `delivery_tier` -> `leads.delivery_tier_c`
- `authorization_checkbox` -> `leads.authorization_checkbox_c`
- `client_ip` -> `leads.client_ip_c`
- `user_agent` -> `leads.user_agent_c`
- `honeypot_triggered` -> `leads.honeypot_triggered_c`
- `abuse_score` -> `leads.abuse_score_c`
- `submitted_at` -> `leads.submitted_at_c`
- `validation_status` -> `leads.validation_status_c`
- `scan_status` -> `leads.scan_status_c`
- `follow_up_task` -> `leads.follow_up_task_c`
- `risk_score` -> `leads.risk_score_c`

# 9. Hidden/System Fields

System-generated fields:

- `page_slug`
- `campaign`
- `product_code`
- `client_ip`
- `user_agent`
- `submitted_at`
- `turnstile_verified`
- `honeypot_triggered`
- `abuse_score`
- `validation_status`
- `scan_status`
- `follow_up_task`
- `risk_score`

Operational guidance:

- Never trust client-provided versions of these values
- Populate server-side only
- Do not expose worker-routing details in frontend markup

# 10. Automation Flow (Standard + Priority)

Standard:

1. Request hits live server
2. Request filter runs
3. Honeypot check runs
4. Strict validation runs
5. Rate-limit and duplicate checks run
6. Abuse scoring runs
7. If accepted, staging write occurs
8. Worker queue insertion occurs
9. Worker enforces one active scan per domain plus cooldown
10. Report generated
11. Signed URL issued with 5 to 7 day TTL
12. Email sent

Priority:

1. Request hits live server
2. Request filter runs
3. Honeypot check runs
4. Strict validation runs
5. Turnstile verification runs and must pass
6. Stricter rate-limit and abuse checks run
7. If accepted, staging write occurs
8. Worker queue insertion occurs
9. Worker enforces one active scan per domain plus cooldown
10. Near-instant summary delivered
11. Full report generated
12. Signed URL issued with 24 hour TTL
13. Email sent

# 11. Abuse Control Blueprint

Mandatory edge / endpoint controls:

- 5 submissions per IP per 15 minutes
- Burst max 10, then throttle or reject

Mandatory application controls:

- 3 submissions per email per 24 hours
- 2 submissions per domain per 24 hours
- Duplicate payload rejection for 60 minutes

Mandatory worker controls:

- 1 active scan per domain
- Enforce per-domain cooldown
- Cap queued jobs per identifier
- Quarantine invalid jobs

Abuse scoring inputs:

- Honeypot triggered
- Invalid field patterns
- Missing or suspicious user-agent
- Secret-like strings in request payload
- Repeated submissions

# 12. Report Token Security Design

Requirements:

- Opaque unguessable token IDs
- Server-side signature binding
- No predictable URLs
- Revocation support required
- Expiration enforced server-side on every access
- Single-use or limited-use support recommended

TTL policy:

- Priority: `24 hours`
- Standard: `5 to 7 days`

Hard rule:

- Do not encode sensitive report metadata directly in the URL token

# 13. Turnstile Integration (REQUIRED for Priority)

Policy:

- Priority / instant tier: `REQUIRED_FOR_PRIORITY`
- Standard tier: `RECOMMENDED`

Enforcement point:

- Server-side before staging write
- Server-side before queue insertion

Launch rule:

- Do not launch Priority as production-ready unless Turnstile verification is fully enforced server-side

# 14. /landing Workspace File Structure

```text
/landing
  external-security-scan.php
  /shared
    header.php
    footer.php
    landing.css
    /components
      checklist-card.php
      faq.php
      intake-form.php
      pricing-grid.php
      trust-strip.php
  /forms
    intake-handler.php
    honeypot.php
    turnstile.php
    validation.php
  /security
    rate-limit.php
    abuse-checks.php
    domain-validation.php
  /crm
    field-mapping.php
    suitecrm-handler.php
  /reports
    token.php
    report-access.php
  /config
    global.php
    external-security-scan.php
  BUILD-PACKAGE.md
```

# 15. Implementation Notes

- Security is centralised in `/landing/security` and `/landing/forms`
- CRM mapping is centralised in `/landing/crm`
- Report token policy is centralised in `/landing/reports`
- Per-page behavior lives in `/landing/config/pages.php`
- The secure form handler currently returns the pre-queue accepted payload and explicitly documents where storage-backed enforcement must occur
- Storage-backed rate-limit counters, duplicate-payload hashing, Turnstile verification API calls, staging writes, worker queue insertion, and report issuance still need to be wired to production systems

Implementation status labels:

- `IMPLEMENTED_FOUNDATION`: workspace structure, page rendering, centralized policies, payload schema
- `REQUIRED_BEFORE_PRODUCTION`: actual Turnstile verification, durable rate-limit counters, duplicate-payload blocking, staging-table writer, queue gate release, token issuer, worker cooldown enforcement
- `RECOMMENDED`: limited-use report tokens for standard tier, allowlist support for legitimate non-matching work email domains, additional bot fingerprinting signals

# 16. Final Risk Warnings

- `REQUIRED`: Priority tier must not launch without real Turnstile verification before staging and queue
- `REQUIRED`: Rate limiting shown in policy form is not enough by itself; it must be backed by durable server-side counters
- `REQUIRED`: Duplicate-payload rejection must be implemented against normalized payload hashes
- `REQUIRED`: Worker-side domain cooldown and active-scan locks must be enforced in shared state, not memory only
- `REQUIRED`: Signed report URLs must be backed by revocable server records and server-side expiry checks
- `RECOMMENDED`: Standard tier should also use Turnstile when operationally possible
- `RECOMMENDED`: Add alerting on rejected abuse spikes and repeated domain targeting
