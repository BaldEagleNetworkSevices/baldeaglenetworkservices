<?php
declare(strict_types=1);

function landing_suitecrm_field_mapping(): array
{
    return [
        'first_name' => 'leads.first_name',
        'last_name' => 'leads.last_name',
        'service' => 'leads.service_slug_c',
        'lead_source' => 'leads.lead_source',
        'campaign' => 'leads.campaign_name',
        'product_code' => 'leads.product_code_c',
        'delivery_tier' => 'leads.delivery_tier_c',
        'business_name' => 'leads.account_name',
        'business_domain' => 'leads.website',
        'work_email' => 'leads.email1',
        'phone' => 'leads.phone_work',
        'job_title' => 'leads.title',
        'department' => 'leads.department',
        'request_notes' => 'leads.description',
        'authorization_checkbox' => 'leads.authorization_checkbox_c',
        'client_ip' => 'leads.client_ip_c',
        'user_agent' => 'leads.user_agent_c',
        'honeypot_triggered' => 'leads.honeypot_triggered_c',
        'abuse_score' => 'leads.abuse_score_c',
        'submitted_at' => 'leads.submitted_at_c',
        'validation_status' => 'leads.validation_status_c',
        'scan_status' => 'leads.scan_status_c',
        'payment_status' => 'leads.payment_status_c',
        'payment_reference' => 'leads.payment_reference_c',
        'payment_amount' => 'leads.payment_amount_c',
        'payment_currency' => 'leads.payment_currency_c',
        'follow_up_task' => 'leads.follow_up_task_c',
        'risk_score' => 'leads.risk_score_c',
        'request_id' => 'leads.request_id_c',
        'payload_hash' => 'leads.payload_hash_c',
        'turnstile_verified' => 'leads.turnstile_verified_c',
    ];
}
