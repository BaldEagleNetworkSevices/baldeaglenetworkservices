<?php
declare(strict_types=1);

return [
    'slug' => 'external-security-scan',
    'campaign' => 'external_security_scan_q2_2026',
    'product_code' => 'EXT_SCAN',
    'page_title' => 'External Security Scan | Bald Eagle Network Services',
    'meta_description' => 'Buy an external security scan for your business domain. Get DNS, TLS, email posture, and exposure findings without penetration testing or internal access.',
    'hero_kicker' => 'External Security Scan',
    'hero_title' => 'Find what the internet can already see about your business.',
    'hero_intro' => 'Get a focused external security scan for your business domain. Bald Eagle reviews DNS posture, website TLS and headers, email authentication, and visible exposure signals without touching internal systems.',
    'primary_cta' => 'Buy External Security Scan',
    'secondary_cta' => 'See What Is Included',
    'turnstile' => [
        'priority_required' => true,
        'standard_required' => false,
    ],
    'trust_points' => [
        'No internal access required',
        'No exploit attempts',
        'Priority delivery includes a quick verification step',
        'Signed report links with server-side expiration',
    ],
    'included' => [
        'Domain and DNS posture review',
        'Website TLS certificate and header review',
        'SPF, DKIM, and DMARC presence check',
        'Basic public exposure indicators and prioritised findings',
    ],
    'excluded' => [
        'Penetration testing',
        'Internal vulnerability scanning',
        'Authenticated assessments',
        'Incident response or compliance certification',
    ],
    'pricing' => [
        [
            'tier' => 'standard',
            'name' => 'Standard',
            'price' => '$295',
            'delivery' => 'Report emailed within 12 to 24 hours',
            'turnstile' => 'recommended',
            'copy' => 'Best fit when you want a clear external exposure review delivered on a normal business timeline.',
            'points' => [
                'Business-ready summary with prioritized findings',
                'Signed report link valid for 5 to 7 days',
                'Delivered to your verified work email',
            ],
        ],
        [
            'tier' => 'priority',
            'name' => 'Priority',
            'price' => '$595',
            'delivery' => 'Near-instant summary plus full report by email',
            'turnstile' => 'required',
            'copy' => 'Best fit when timing matters and you need faster visibility into what an external party can already see.',
            'points' => [
                'Fast initial summary with full report to follow',
                'Quick verification step on the priority path',
                'Signed report link valid for 24 hours',
            ],
        ],
    ],
    'faq' => [
        [
            'question' => 'What does this scan actually cover?',
            'answer' => 'It checks only externally visible controls: DNS posture, website TLS and response headers, email authentication record presence, and basic public exposure indicators.',
        ],
        [
            'question' => 'Does this include penetration testing?',
            'answer' => 'No. This product does not include exploit attempts, authenticated testing, internal scanning, or incident response work.',
        ],
        [
            'question' => 'What information do I need to provide?',
            'answer' => 'The intake form asks for your business name, business domain, work email, and confirmation that you are authorized to request the scan.',
        ],
        [
            'question' => 'How is report access protected?',
            'answer' => 'Reports must be delivered through opaque signed tokens with server-side expiration, revocation support, and optional limited-use enforcement.',
        ],
        [
            'question' => 'Do I need to be authorized to request this scan?',
            'answer' => 'Yes. You must confirm that you are authorized to request a scan for the business and domain you submit.',
        ],
    ],
    'authorized_checkbox' => 'I confirm I am authorized to request a scan for this business/domain.',
    'prohibited_inputs' => [
        'Passwords',
        'MFA codes',
        'API keys',
        'Secrets',
        'Payment data',
        'SSNs',
        'Medical data',
        'Internal network details',
    ],
];
