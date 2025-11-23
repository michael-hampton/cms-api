<?php

namespace App\Database\Seeders;

use App\Models\ConsentNotice;
use App\Models\ConsentType;
use App\Models\DataProcessingActivity;
use App\Models\Site;

class ConsentSeeder
{
    public function run(): void
    {
        $this->seedConsentTypes();
        $this->seedConsentNoticesForAllSites();
        $this->seedDataProcessingActivities();
    }

    private function seedConsentTypes(): void
    {
        $consentTypes = [
            // Essential Consents
            [
                'code' => 'essential_cookies',
                'name' => 'Essential Cookies',
                'description' => 'Necessary for the website to function properly. These cookies cannot be disabled as they are essential for security, navigation, and basic functionality.',
                'category' => 'essential',
                'required' => true,
                'retention_days' => 365,
                'data_purposes' => [
                    'Authentication and session management',
                    'Security and fraud prevention',
                    'Load balancing and performance',
                    'Remember your preferences'
                ]
            ],
            [
                'code' => 'account_management',
                'name' => 'Account Management',
                'description' => 'Process your personal data to create and manage your account, including authentication and profile information.',
                'category' => 'essential',
                'required' => true,
                'retention_days' => null,
                'data_purposes' => [
                    'User authentication',
                    'Account administration',
                    'Service delivery',
                    'Legal compliance'
                ]
            ],

            // Functional Consents
            [
                'code' => 'functional_cookies',
                'name' => 'Functional Cookies',
                'description' => 'Enable enhanced functionality and personalization, such as remembering your preferences and settings.',
                'category' => 'functional',
                'required' => false,
                'retention_days' => 365,
                'data_purposes' => [
                    'Remember user preferences',
                    'Enhance user experience',
                    'Provide personalized content',
                    'Enable social media features'
                ]
            ],
            [
                'code' => 'shopping_cart',
                'name' => 'Shopping Cart & Checkout',
                'description' => 'Remember items in your cart and facilitate the checkout process.',
                'category' => 'functional',
                'required' => false,
                'retention_days' => 30,
                'data_purposes' => [
                    'Maintain shopping cart',
                    'Process orders',
                    'Remember delivery preferences'
                ]
            ],

            // Analytics Consents
            [
                'code' => 'analytics',
                'name' => 'Analytics & Performance',
                'description' => 'Help us understand how visitors use our website so we can improve it. We collect anonymous usage statistics.',
                'category' => 'analytics',
                'required' => false,
                'retention_days' => 730,
                'data_purposes' => [
                    'Website analytics',
                    'Performance monitoring',
                    'User behavior analysis',
                    'A/B testing',
                    'Error tracking'
                ]
            ],
            [
                'code' => 'heatmaps',
                'name' => 'Heatmaps & Session Recording',
                'description' => 'Record anonymous sessions to understand how users interact with our website.',
                'category' => 'analytics',
                'required' => false,
                'retention_days' => 90,
                'data_purposes' => [
                    'User experience research',
                    'Identify usability issues',
                    'Optimize page layouts'
                ]
            ],

            // Marketing Consents
            [
                'code' => 'marketing_email',
                'name' => 'Marketing Emails',
                'description' => 'Receive promotional emails, newsletters, and special offers tailored to your interests.',
                'category' => 'marketing',
                'required' => false,
                'retention_days' => 1095,
                'data_purposes' => [
                    'Send promotional materials',
                    'Product recommendations',
                    'Special offers and discounts',
                    'Newsletter delivery'
                ]
            ],
            [
                'code' => 'targeted_advertising',
                'name' => 'Targeted Advertising',
                'description' => 'Show you personalized advertisements based on your browsing behavior and interests.',
                'category' => 'marketing',
                'required' => false,
                'retention_days' => 365,
                'data_purposes' => [
                    'Personalized advertising',
                    'Retargeting campaigns',
                    'Measure ad effectiveness',
                    'Audience building'
                ]
            ],
            [
                'code' => 'social_media_marketing',
                'name' => 'Social Media Marketing',
                'description' => 'Share your activity with social networks and allow them to show you relevant content.',
                'category' => 'marketing',
                'required' => false,
                'retention_days' => 365,
                'data_purposes' => [
                    'Social media integration',
                    'Share content',
                    'Social advertising',
                    'Social login'
                ]
            ],
            [
                'code' => 'profiling',
                'name' => 'User Profiling',
                'description' => 'Create a profile based on your preferences and behavior to provide personalized experiences.',
                'category' => 'marketing',
                'required' => false,
                'retention_days' => 1095,
                'data_purposes' => [
                    'Build user profiles',
                    'Personalize content',
                    'Product recommendations',
                    'Behavioral targeting'
                ]
            ],

            // Preferences Consents
            [
                'code' => 'communication_preferences',
                'name' => 'Communication Preferences',
                'description' => 'Remember how you prefer to be contacted and what types of communications you want to receive.',
                'category' => 'preferences',
                'required' => false,
                'retention_days' => null,
                'data_purposes' => [
                    'Store communication preferences',
                    'Manage notification settings',
                    'Control frequency of messages'
                ]
            ],
            [
                'code' => 'third_party_sharing',
                'name' => 'Third-Party Data Sharing',
                'description' => 'Share your data with selected partners to provide additional services or offers.',
                'category' => 'marketing',
                'required' => false,
                'retention_days' => 365,
                'data_purposes' => [
                    'Partner integrations',
                    'Third-party services',
                    'Data enrichment',
                    'Joint marketing campaigns'
                ]
            ]
        ];

        foreach ($consentTypes as $type) {
            ConsentType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }

    private function seedConsentNoticesForAllSites(): void
    {
        $sites = Site::all();

        foreach ($sites as $site) {
            $this->seedConsentNoticesForSite($site->id);
        }
    }

    private function seedConsentNoticesForSite(int $siteId): void
    {
        $notices = [
            [
                'site_id' => $siteId,
                'code' => "cookie_banner_{$siteId}",
                'name' => 'Cookie Consent Banner',
                'content' => 'We use cookies and similar technologies to enhance your browsing experience, analyze site traffic, and show personalized content. You can customize your preferences or accept all to continue.',
                'consent_types' => ['functional_cookies', 'analytics', 'marketing_email', 'targeted_advertising'],
                'display_type' => 'banner',
                'display_rules' => [
                    'pages' => ['*'],
                    'trigger' => 'immediate',
                    'frequency' => 'once'
                ],
                'is_active' => true
            ],
            [
                'site_id' => $siteId,
                'code' => "email_signup_consent_{$siteId}",
                'name' => 'Email Newsletter Consent',
                'content' => 'By subscribing, you agree to receive marketing emails. You can unsubscribe at any time.',
                'consent_types' => ['marketing_email'],
                'display_type' => 'inline',
                'display_rules' => [
                    'pages' => ['/newsletter', '/subscribe'],
                    'trigger' => 'form_display'
                ],
                'is_active' => true
            ],
            [
                'site_id' => $siteId,
                'code' => "account_creation_consent_{$siteId}",
                'name' => 'Account Creation Consent',
                'content' => 'By creating an account, you agree to our Terms of Service and Privacy Policy.',
                'consent_types' => ['account_management', 'communication_preferences'],
                'display_type' => 'inline',
                'display_rules' => [
                    'pages' => ['/register', '/signup'],
                    'trigger' => 'form_display'
                ],
                'is_active' => true
            ]
        ];

        foreach ($notices as $notice) {
            ConsentNotice::updateOrCreate(
                ['code' => $notice['code']],
                $notice
            );
        }
    }

    private function seedDataProcessingActivities(): void
    {
        $activities = [
            [
                'name' => 'User Account Management',
                'purpose' => 'Process user registration, authentication, and account management',
                'data_categories' => ['name', 'email', 'password_hash', 'profile_info'],
                'data_subjects' => ['customers', 'website_users'],
                'recipients' => ['internal_staff', 'hosting_provider'],
                'transfers' => null,
                'retention_period_days' => 2555,
                'security_measures' => [
                    'encryption_at_rest',
                    'encryption_in_transit',
                    'access_controls',
                    'regular_backups',
                    'password_hashing'
                ],
                'related_consent_types' => ['account_management']
            ],
            [
                'name' => 'Marketing Communications',
                'purpose' => 'Send marketing emails, newsletters, and promotional content',
                'data_categories' => ['email', 'name', 'preferences', 'interaction_history'],
                'data_subjects' => ['customers', 'subscribers'],
                'recipients' => ['internal_marketing', 'email_service_provider'],
                'transfers' => ['US' => 'Standard Contractual Clauses'],
                'retention_period_days' => 1095,
                'security_measures' => [
                    'encryption_in_transit',
                    'access_controls',
                    'email_authentication'
                ],
                'related_consent_types' => ['marketing_email', 'communication_preferences']
            ],
            [
                'name' => 'Website Analytics',
                'purpose' => 'Analyze website usage and improve user experience',
                'data_categories' => ['ip_address', 'browser_info', 'page_views', 'session_data'],
                'data_subjects' => ['website_visitors'],
                'recipients' => ['internal_analytics', 'analytics_service_provider'],
                'transfers' => ['US' => 'Privacy Shield / SCCs'],
                'retention_period_days' => 730,
                'security_measures' => [
                    'pseudonymization',
                    'ip_anonymization',
                    'encryption_in_transit'
                ],
                'related_consent_types' => ['analytics', 'heatmaps']
            ],
            [
                'name' => 'Targeted Advertising',
                'purpose' => 'Deliver personalized advertisements based on user interests',
                'data_categories' => ['browsing_history', 'interests', 'demographics', 'cookie_id'],
                'data_subjects' => ['website_visitors', 'customers'],
                'recipients' => ['advertising_partners', 'ad_networks'],
                'transfers' => ['US' => 'Standard Contractual Clauses', 'Worldwide' => 'Adequacy Decisions'],
                'retention_period_days' => 365,
                'security_measures' => [
                    'pseudonymization',
                    'encryption_in_transit',
                    'limited_access'
                ],
                'related_consent_types' => ['targeted_advertising', 'profiling', 'third_party_sharing']
            ],
            [
                'name' => 'Order Processing',
                'purpose' => 'Process customer orders and deliver products/services',
                'data_categories' => ['name', 'address', 'payment_info', 'order_history'],
                'data_subjects' => ['customers'],
                'recipients' => ['payment_processor', 'shipping_provider', 'internal_sales'],
                'transfers' => null,
                'retention_period_days' => 2555,
                'security_measures' => [
                    'pci_dss_compliance',
                    'encryption_at_rest',
                    'encryption_in_transit',
                    'tokenization',
                    'access_controls'
                ],
                'related_consent_types' => ['account_management', 'shopping_cart']
            ]
        ];

        foreach ($activities as $activity) {
            DataProcessingActivity::updateOrCreate(
                ['name' => $activity['name']],
                $activity
            );
        }
    }
}