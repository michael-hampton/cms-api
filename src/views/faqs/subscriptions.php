<?php
/**
 * @var \App\Models\Site $site
 * @var string $pageTitle
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #2c3e50;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 60px;
            color: white;
        }

        .header h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .header p {
            font-size: 20px;
            opacity: 0.9;
        }

        .search-box {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .search-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .faq-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .category-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .category-card.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .category-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .category-title {
            font-size: 18px;
            font-weight: 700;
        }

        .faq-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 3px solid #667eea;
        }

        .faq-item {
            margin-bottom: 24px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: #cbd5e1;
        }

        .faq-item.open {
            border-color: #667eea;
        }

        .faq-question {
            padding: 24px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 18px;
            color: #1e293b;
            user-select: none;
        }

        .faq-question:hover {
            background: #f8fafc;
        }

        .faq-icon {
            font-size: 24px;
            transition: transform 0.3s ease;
        }

        .faq-item.open .faq-icon {
            transform: rotate(45deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .faq-item.open .faq-answer {
            max-height: 1000px;
        }

        .faq-answer-content {
            padding: 0 24px 24px 24px;
            color: #475569;
            font-size: 16px;
            line-height: 1.7;
        }

        .faq-answer-content ul,
        .faq-answer-content ol {
            margin: 16px 0 16px 24px;
        }

        .faq-answer-content li {
            margin-bottom: 8px;
        }

        .faq-answer-content strong {
            color: #1e293b;
        }

        .faq-answer-content a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .faq-answer-content a:hover {
            text-decoration: underline;
        }

        .contact-box {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 32px;
            text-align: center;
        }

        .contact-box h3 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .contact-box p {
            color: #64748b;
            margin-bottom: 24px;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 36px;
            }

            .faq-section {
                padding: 24px;
            }

            .section-title {
                font-size: 24px;
            }

            .faq-question {
                font-size: 16px;
                padding: 16px;
            }

            .faq-answer-content {
                padding: 0 16px 16px 16px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>❓ Frequently Asked Questions</h1>
        <p>Everything you need to know about subscriptions and newsletters</p>
    </div>

    <div class="search-box">
        <input type="text"
               class="search-input"
               placeholder="🔍 Search for answers..."
               id="searchInput"
               onkeyup="searchFAQs()">
    </div>

    <div class="faq-categories">
        <div class="category-card active" onclick="filterCategory('all')" data-category="all">
            <div class="category-icon">📚</div>
            <div class="category-title">All Topics</div>
        </div>
        <div class="category-card" onclick="filterCategory('subscriptions')" data-category="subscriptions">
            <div class="category-icon">💳</div>
            <div class="category-title">Subscriptions</div>
        </div>
        <div class="category-card" onclick="filterCategory('newsletters')" data-category="newsletters">
            <div class="category-icon">📧</div>
            <div class="category-title">Newsletters</div>
        </div>
        <div class="category-card" onclick="filterCategory('billing')" data-category="billing">
            <div class="category-icon">💰</div>
            <div class="category-title">Billing</div>
        </div>
        <div class="category-card" onclick="filterCategory('account')" data-category="account">
            <div class="category-icon">⚙️</div>
            <div class="category-title">Account</div>
        </div>
    </div>

    <!-- Subscriptions FAQs -->
    <div class="faq-section" data-category="subscriptions">
        <h2 class="section-title">💳 Subscriptions</h2>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What subscription plans do you offer?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>We offer several subscription tiers to meet your needs:</p>
                    <ul>
                        <li><strong>Digital Subscriptions:</strong> Access all content online instantly</li>
                        <li><strong>Print Subscriptions:</strong> Receive physical copies delivered to your door</li>
                        <li><strong>Print + Digital:</strong> Get the best of both worlds</li>
                    </ul>
                    <p>Visit our <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans">subscription
                            plans page</a> to compare features and pricing.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How do I subscribe?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Subscribing is easy:</p>
                    <ol>
                        <li>Visit our <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans">subscription
                                plans page</a></li>
                        <li>Choose the plan that's right for you</li>
                        <li>Create an account or log in</li>
                        <li>Complete the checkout process</li>
                        <li>Start enjoying your subscription immediately!</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I cancel my subscription anytime?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes! You can cancel your subscription at any time with no penalties. You have two options:</p>
                    <ul>
                        <li><strong>Cancel at period end:</strong> Keep access until your current billing period ends
                            (recommended)
                        </li>
                        <li><strong>Cancel immediately:</strong> Stop access right away</li>
                    </ul>
                    <p>To cancel, go to your <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions">subscriptions
                            page</a> and click the "Cancel Subscription" button.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I reactivate a cancelled subscription?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Absolutely! If you cancelled your subscription but it hasn't ended yet, you can reactivate it
                        anytime before the end date. Simply visit your <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions">subscriptions
                            page</a> and click "Reactivate Subscription".</p>
                    <p>If your subscription has already ended, you can start a new subscription at any time.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What's the difference between digital and print subscriptions?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p><strong>Digital Subscriptions (💻):</strong></p>
                    <ul>
                        <li>Instant access to all content online</li>
                        <li>Read on any device</li>
                        <li>Environmentally friendly</li>
                        <li>Usually more affordable</li>
                    </ul>
                    <p><strong>Print Subscriptions (📦):</strong></p>
                    <ul>
                        <li>Physical copies delivered to your address</li>
                        <li>Traditional reading experience</li>
                        <li>Collectible issues</li>
                        <li>No screen time required</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How do I change my shipping address for print subscriptions?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>To update your shipping address:</p>
                    <ol>
                        <li>Go to your <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account-details">account
                                details page</a></li>
                        <li>Navigate to the "Addresses" section</li>
                        <li>Update your shipping address</li>
                        <li>Save your changes</li>
                    </ol>
                    <p>Changes will take effect for the next scheduled delivery.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Will my subscription auto-renew?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes, by default all subscriptions are set to auto-renew so you don't lose access. This means:</p>
                    <ul>
                        <li>Your payment method will be charged automatically on your renewal date</li>
                        <li>You'll receive a reminder email before each billing</li>
                        <li>Your subscription continues without interruption</li>
                    </ul>
                    <p>You can disable auto-renewal anytime from your <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions">subscriptions
                            page</a>. If you disable it, you can still manually renew before your subscription expires.
                    </p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I pause my subscription?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>While we don't offer a formal "pause" feature, you can:</p>
                    <ul>
                        <li>Cancel your subscription and resubscribe later (you won't lose any saved preferences)</li>
                        <li>Disable auto-renewal to let it expire, then reactivate when ready</li>
                    </ul>
                    <p>If you need to pause for a specific reason (medical, travel, etc.), please contact our support
                        team and we'll work with you to find a solution.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I switch between subscription plans?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes! You can upgrade or downgrade your subscription plan:</p>
                    <ul>
                        <li><strong>Upgrading:</strong> You'll be charged a prorated amount for the remainder of your
                            billing period
                        </li>
                        <li><strong>Downgrading:</strong> The change takes effect at the end of your current billing
                            period
                        </li>
                    </ul>
                    <p>To change plans, visit your <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions">subscriptions
                            page</a> or <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans">browse
                            available plans</a>.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What happens to my content if I cancel?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p><strong>Digital Subscriptions:</strong></p>
                    <ul>
                        <li>If you cancel at period end: Full access until your billing period ends</li>
                        <li>If you cancel immediately: Access ends right away</li>
                        <li>Bookmarks and preferences are saved if you resubscribe</li>
                    </ul>
                    <p><strong>Print Subscriptions:</strong></p>
                    <ul>
                        <li>You'll receive all issues scheduled before your end date</li>
                        <li>No more deliveries after the subscription ends</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Do you offer gift subscriptions?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes! Gift subscriptions are a perfect present for friends and family. To purchase a gift
                        subscription:</p>
                    <ol>
                        <li>Visit our <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans">subscription
                                plans page</a></li>
                        <li>Select "Gift This Subscription" on your chosen plan</li>
                        <li>Enter the recipient's email and a personal message</li>
                        <li>Complete checkout</li>
                    </ol>
                    <p>The recipient will receive an email with instructions to activate their gift subscription.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I share my subscription with family members?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Subscription sharing policies vary by plan:</p>
                    <ul>
                        <li><strong>Individual plans:</strong> For one person only</li>
                        <li><strong>Family plans:</strong> Can be shared with household members (if available)</li>
                        <li><strong>Print subscriptions:</strong> Physical copies can be shared with anyone in your
                            household
                        </li>
                    </ul>
                    <p>Account sharing outside your household violates our terms of service. Consider gifting a
                        subscription instead!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Newsletters FAQs -->
    <div class="faq-section" data-category="newsletters">
        <h2 class="section-title">📧 Newsletters</h2>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What newsletters are available?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>We offer several newsletters covering different topics and frequencies. You can browse all
                        available newsletters and their descriptions on your <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters">newsletters
                            management page</a>.</p>
                    <p>Most newsletters are free to subscribe to, even if you don't have a paid subscription!</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How do I subscribe to newsletters?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>To subscribe to newsletters:</p>
                    <ol>
                        <li>Visit your <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters">newsletters
                                page</a></li>
                        <li>Click "Subscribe to Newsletters" button</li>
                        <li>Select the newsletters you're interested in</li>
                        <li>Click "Subscribe to Selected"</li>
                    </ol>
                    <p>You can also subscribe to individual newsletters by clicking the "Subscribe" button on each
                        newsletter card.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How do I unsubscribe from newsletters?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>You can unsubscribe from any newsletter in several ways:</p>
                    <ul>
                        <li>Click the "Unsubscribe" link at the bottom of any newsletter email</li>
                        <li>Visit your <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters">newsletters
                                page</a> and click "Unsubscribe" on specific newsletters
                        </li>
                        <li>Adjust your preferences on your <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/preferences">subscription
                                preferences page</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How often will I receive newsletters?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Newsletter frequency varies by type:</p>
                    <ul>
                        <li><strong>Daily:</strong> Sent every day with the latest updates</li>
                        <li><strong>Weekly:</strong> Sent once per week with a roundup of content</li>
                        <li><strong>Monthly:</strong> Sent once per month with highlights</li>
                    </ul>
                    <p>Each newsletter displays its frequency on the subscription page so you know what to expect.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I download newsletters as PDFs?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes! You can download any newsletter as a PDF:</p>
                    <ul>
                        <li>Visit the <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters">newsletters
                                archive</a></li>
                        <li>Click on any newsletter to view it</li>
                        <li>Click the "Download PDF" button at the top</li>
                    </ul>
                    <p>This is great for offline reading or archiving!</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I view past newsletters?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes! All past newsletters are available in our <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/archive">newsletter
                            archive</a>. You can browse, read, and download previous issues anytime.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Are newsletters free?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Most newsletters are completely free! You don't need a paid subscription to receive them.
                        However:</p>
                    <ul>
                        <li>Some premium newsletters are available only to paid subscribers</li>
                        <li>Paid subscribers may get early access to certain newsletters</li>
                        <li>Special edition newsletters may require a subscription</li>
                    </ul>
                    <p>Newsletter cards on your <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters">newsletters
                            page</a> will clearly indicate if they require a subscription.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I customize which topics I receive in newsletters?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Absolutely! You have granular control over your newsletter content:</p>
                    <ol>
                        <li>Go to your <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/preferences">subscription
                                preferences</a></li>
                        <li>Select specific content types (News, Blog Posts, Articles, etc.)</li>
                        <li>Choose categories you're interested in</li>
                        <li>Set your preferred frequency (daily, weekly, monthly)</li>
                    </ol>
                    <p>This ensures you only receive content relevant to your interests!</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Why am I not receiving newsletters?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>If you're not receiving newsletters, check these common issues:</p>
                    <ul>
                        <li><strong>Spam folder:</strong> Check your spam/junk folder and mark our emails as "Not Spam"
                        </li>
                        <li><strong>Email filters:</strong> Add our sender address to your contacts</li>
                        <li><strong>Unsubscribed:</strong> Verify you're subscribed on your <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters">newsletters
                                page</a></li>
                        <li><strong>Email address:</strong> Confirm your email is correct in <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account-details">account
                                settings</a></li>
                        <li><strong>Preferences:</strong> Check your <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/preferences">email
                                preferences</a> are enabled
                        </li>
                    </ul>
                    <p>Still having issues? Contact our support team for help.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I forward newsletters to others?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes, you're welcome to forward newsletters to friends and colleagues! However:</p>
                    <ul>
                        <li>Some links may be personalized to your account</li>
                        <li>Premium content may require a subscription to access</li>
                        <li>We encourage them to subscribe directly for the best experience</li>
                    </ul>
                    <p>Think they'd enjoy it? Share the <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters">newsletter
                            subscription page</a> with them!</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What's the difference between newsletters and email subscriptions?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p><strong>Newsletters:</strong> Curated content sent at specific intervals (daily, weekly,
                        monthly). You can subscribe to multiple newsletters about different topics.</p>
                    <p><strong>Email Subscriptions:</strong> Your general email notification preferences that control
                        things like account updates, renewal reminders, and content notifications.</p>
                    <p>You can manage both separately:</p>
                    <ul>
                        <li>Newsletters: <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters">Newsletter
                                Management</a></li>
                        <li>Email Preferences: <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/preferences">Subscription
                                Preferences</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Billing FAQs -->
    <div class="faq-section" data-category="billing">
        <h2 class="section-title">💰 Billing & Payments</h2>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>When will I be billed?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Your billing date depends on your subscription plan:</p>
                    <ul>
                        <li><strong>Monthly plans:</strong> Billed every month on the same date you subscribed</li>
                        <li><strong>Quarterly plans:</strong> Billed every 3 months</li>
                        <li><strong>Annual plans:</strong> Billed once per year</li>
                    </ul>
                    <p>You can see your exact next billing date on your <a
                                href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions">subscriptions
                            page</a>.</p>
                    <p>We'll send you a reminder email before each billing date!</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What payment methods do you accept?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>We accept all major payment methods through Stripe:</p>
                    <ul>
                        <li>Credit cards (Visa, Mastercard, American Express, Discover)</li>
                        <li>Debit cards</li>
                        <li>Digital wallets (Apple Pay, Google Pay)</li>
                    </ul>
                    <p>All payments are processed securely through Stripe, and we never store your full card
                        details.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How do I update my payment method?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>To update your payment method:</p>
                    <ol>
                        <li>Go to your <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions">subscriptions
                                page</a></li>
                        <li>Click on "Payment Methods"</li>
                        <li>Add a new payment method or remove old ones</li>
                        <li>Set your preferred default payment method</li>
                    </ol>
                    <p>Changes will apply to your next billing cycle.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What if my payment fails?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>If your payment fails:</p>
                    <ol>
                        <li>We'll send you an email notification immediately</li>
                        <li>We'll automatically retry the payment after 3 days</li>
                        <li>If it fails again, we'll try once more after 7 days</li>
                        <li>Your subscription will be suspended if all attempts fail</li>
                    </ol>
                    <p>To avoid interruption:</p>
                    <ul>
                        <li>Make sure your card hasn't expired</li>
                        <li>Ensure you have sufficient funds</li>
                        <li>Update your payment method if needed</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I get a refund?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Our refund policy varies by subscription type:</p>
                    <p><strong>Digital Subscriptions:</strong> Refunds are available within 14 days of purchase if you
                        haven't accessed the content.</p>
                    <p><strong>Print Subscriptions:</strong> Refunds are available for unshipped issues only. Once an
                        issue has been shipped, that portion is non-refundable.</p>
                    <p><strong>Annual Subscriptions:</strong> Prorated refunds may be available in the first 30 days.
                    </p>
                    <p>To request a refund, please contact our support team with your order number and reason for the
                        refund request.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Where can I find my receipts?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>You can access all your payment receipts in two ways:</p>
                    <ol>
                        <li><strong>Email:</strong> We automatically send a receipt to your email after each payment
                        </li>
                        <li><strong>Account:</strong> View and download all receipts from your <a
                                    href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/orders">order
                                history page</a></li>
                    </ol>
                    <p>Each receipt includes your payment details, subscription information, and tax breakdown.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Do you charge sales tax?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Sales tax is calculated based on your location and may be applied to your subscription:</p>
                    <ul>
                        <li>Tax rates vary by state/country</li>
                        <li>Digital subscriptions may be taxed differently than print</li>
                        <li>Tax amount is shown before you complete checkout</li>
                        <li>Receipts include full tax breakdown</li>
                    </ul>
                    <p>The final price including any applicable taxes is always displayed at checkout before you confirm
                        your purchase.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What currency will I be charged in?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Prices are displayed and charged in USD by default. If you're paying with a card in a different
                        currency:</p>
                    <ul>
                        <li>Your card issuer will convert the amount</li>
                        <li>Your bank's exchange rate will apply</li>
                        <li>Foreign transaction fees may apply (check with your bank)</li>
                    </ul>
                    <p>The exact amount in your local currency will appear on your card statement.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I get a discount or use a coupon code?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes! We occasionally offer discount codes and promotions:</p>
                    <ul>
                        <li>Enter coupon codes at checkout in the "Voucher Code" field</li>
                        <li>Some codes are for new subscribers only</li>
                        <li>Annual plans often have better value than monthly</li>
                        <li>Sign up for our newsletter to receive exclusive offers</li>
                    </ul>
                    <p>Discounts are applied before payment and clearly shown on your receipt.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Is my payment information secure?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Absolutely! Your payment security is our top priority:</p>
                    <ul>
                        <li>All payments processed through Stripe (PCI-DSS Level 1 certified)</li>
                        <li>We never store your full credit card numbers</li>
                        <li>All transactions use 256-bit SSL encryption</li>
                        <li>Your payment data is tokenized and encrypted</li>
                        <li>Industry-standard fraud protection</li>
                    </ul>
                    <p>We use the same security measures as major banks and companies worldwide.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Account & Settings FAQs -->
    <div class="faq-section" data-category="account">
        <h2 class="section-title">⚙️ Account & Settings</h2>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How do I update my email address?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>To change your email address:</p>
                    <ol>
                        <li>Log in to your account</li>
                        <li>Go to <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account-details">Account
                                Details</a></li>
                        <li>Update your email address</li>
                        <li>Click "Save Changes"</li>
                        <li>Verify your new email address via the confirmation email</li>
                    </ol>
                    <p><strong>Important:</strong> All future newsletters and notifications will be sent to your new
                        email address.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>How do I reset my password?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>To reset your password:</p>
                    <ol>
                        <li>Go to the <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/login">login
                                page</a></li>
                        <li>Click "Forgot Password?"</li>
                        <li>Enter your email address</li>
                        <li>Check your email for the reset link</li>
                        <li>Create your new password</li>
                    </ol>
                    <p>The reset link expires after 24 hours for security reasons.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>Can I delete my account?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>Yes, you can delete your account at any time. Before doing so:</p>
                    <ul>
                        <li>Cancel any active subscriptions</li>
                        <li>Download any content you want to keep</li>
                        <li>Save your order history/receipts if needed</li>
                    </ul>
                    <p>To delete your account:</p>
                    <ol>
                        <li>Go to <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account-details">Account
                                Details</a></li>
                        <li>Scroll to "Delete Account" section</li>
                        <li>Confirm deletion</li>
                    </ol>
                    <p><strong>Warning:</strong> Account deletion is permanent and cannot be undone.</p>
                </div>
            </div>
        </div>

        <div class="faq-item" onclick="toggleFAQ(this)">
            <div class="faq-question">
                <span>What data do you collect about me?</span>
                <span class="faq-icon">+</span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-content">
                    <p>We collect only the data necessary to provide our services:</p>
                    <ul>
                        <li>Account information (name, email, password)</li>
                        <li>Subscription and payment history</li>
                        <li>Shipping address (for print subscriptions)</li>
                        <li>Email preferences and newsletter subscriptions</li>
                        <li>Usage data to improve our services</li>
                    </ul>
                    <p>We never sell your personal data. Read our full <a href="/privacy">Privacy Policy</a> for
                        details.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle individual FAQ items
    function toggleFAQ(element) {
        element.classList.toggle('open');
    }

    // Filter FAQs by category
    function filterCategory(category) {
        // Update active category card
        document.querySelectorAll('.category-card').forEach(card => {
            card.classList.remove('active');
        });
        document.querySelector(`.category-card[data-category="${category}"]`).classList.add('active');

        // Show/hide FAQ sections
        const sections = document.querySelectorAll('.faq-section');

        if (category === 'all') {
            sections.forEach(section => {
                section.style.display = 'block';
            });
        } else {
            sections.forEach(section => {
                if (section.dataset.category === category) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
        }

        // Clear search when filtering by category
        document.getElementById('searchInput').value = '';
    }

    // Search FAQs
    function searchFAQs() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const faqItems = document.querySelectorAll('.faq-item');
        const sections = document.querySelectorAll('.faq-section');
        let hasVisibleItems = {};

        // If search is empty, show all based on current category filter
        if (searchTerm === '') {
            const activeCategory = document.querySelector('.category-card.active').dataset.category;
            filterCategory(activeCategory);
            return;
        }

        // Reset all sections to hidden
        sections.forEach(section => {
            hasVisibleItems[section.dataset.category] = false;
        });

        // Search through FAQ items
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question span:first-child').textContent.toLowerCase();
            const answer = item.querySelector('.faq-answer-content').textContent.toLowerCase();
            const section = item.closest('.faq-section');

            if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                item.style.display = 'block';
                hasVisibleItems[section.dataset.category] = true;

                // Highlight search term in question
                highlightSearchTerm(item, searchTerm);
            } else {
                item.style.display = 'none';
            }
        });

        // Show/hide sections based on whether they have visible items
        sections.forEach(section => {
            if (hasVisibleItems[section.dataset.category]) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        });

        // Set all categories card to active during search
        document.querySelectorAll('.category-card').forEach(card => {
            card.classList.remove('active');
        });
        document.querySelector('.category-card[data-category="all"]').classList.add('active');
    }

    // Highlight search term in FAQ items
    function highlightSearchTerm(item, searchTerm) {
        const questionSpan = item.querySelector('.faq-question span:first-child');
        const originalText = questionSpan.textContent;

        // Remove any existing highlights
        questionSpan.innerHTML = originalText;

        // Add highlight if search term is in question
        if (originalText.toLowerCase().includes(searchTerm)) {
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            questionSpan.innerHTML = originalText.replace(regex, '<mark style="background: #fef3c7; padding: 2px 4px; border-radius: 3px;">$1</mark>');
        }
    }

    // Auto-expand FAQ if URL has hash
    document.addEventListener('DOMContentLoaded', function () {
        const hash = window.location.hash;
        if (hash) {
            const element = document.querySelector(hash);
            if (element && element.classList.contains('faq-item')) {
                element.classList.add('open');
                element.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        }
    });

    // Add keyboard accessibility for FAQ items
    document.querySelectorAll('.faq-question').forEach(question => {
        question.setAttribute('tabindex', '0');
        question.setAttribute('role', 'button');
        question.setAttribute('aria-expanded', 'false');

        question.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleFAQ(this.parentElement);
                this.setAttribute('aria-expanded', this.parentElement.classList.contains('open'));
            }
        });
    });

    // Add analytics tracking for FAQ interactions (optional)
    document.querySelectorAll('.faq-item').forEach(item => {
        item.addEventListener('click', function () {
            const question = this.querySelector('.faq-question span:first-child').textContent;
            const isOpening = !this.classList.contains('open');

            // You can integrate with your analytics here
            console.log(`FAQ ${isOpening ? 'opened' : 'closed'}: ${question}`);
        });
    });

    // Debounce search for better performance
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchFAQs();
        }, 300);
    });
</script>