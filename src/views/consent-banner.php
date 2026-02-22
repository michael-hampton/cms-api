<?php
// Only show if member is logged in and hasn't responded to consent banner
$shouldShowBanner = false;
$memberId = null;

if (\App\Framework\Authorization\MemberAuth::check()) {
    $member = \App\Framework\Authorization\MemberAuth::getMember();
    $memberId = $member->id;

    // Check if member has responded to consent banner
    $session = new \App\Framework\Session\Session();
    $hasResponded = $session->get('consent_banner_shown_' . \App\Framework\Support\SiteContext::slug(), false);

    // Or check in database if they have any consent records
    if (!$hasResponded) {

        $consentCount = \App\Models\MemberConsent::where('member_id', $memberId)->where('site_id', \App\Framework\Support\SiteContext::getId())->count();
        $shouldShowBanner = $consentCount === 0;
    }
}
?>

<?php if ($shouldShowBanner): ?>
    <style>
        .consent-banner-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .consent-banner-overlay.show {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .consent-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
        }

        .consent-banner.show {
            transform: translateY(0);
        }

        .consent-banner-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .consent-banner-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .banner-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .banner-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }

        .banner-description {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .consent-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .consent-option {
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1rem;
            transition: all 0.3s;
        }

        .consent-option:hover {
            border-color: #667eea;
            background: #f9fafb;
        }

        .option-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .option-title {
            font-weight: 600;
            font-size: 0.9375rem;
            color: #1f2937;
        }

        .option-description {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.5;
        }

        .toggle-switch-small {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle-switch-small input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider-small {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 24px;
        }

        .toggle-slider-small:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        .toggle-switch-small input:checked + .toggle-slider-small {
            background-color: #10b981;
        }

        .toggle-switch-small input:checked + .toggle-slider-small:before {
            transform: translateX(20px);
        }

        .toggle-switch-small input:disabled + .toggle-slider-small {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .banner-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-banner {
            padding: 0.875rem 1.75rem;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.9375rem;
        }

        .btn-accept {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-customize {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-customize:hover {
            background: #f9fafb;
        }

        .btn-reject {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-reject:hover {
            background: #e5e7eb;
            color: #1f2937;
        }

        .banner-footer {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .banner-links a {
            color: #667eea;
            text-decoration: none;
            margin-left: 1.5rem;
        }

        .banner-links a:hover {
            text-decoration: underline;
        }

        .customize-panel {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .customize-panel.show {
            max-height: 600px;
        }

        @media (max-width: 768px) {
            .consent-banner-content {
                padding: 1.5rem;
            }

            .consent-options {
                grid-template-columns: 1fr;
            }

            .banner-actions {
                flex-direction: column;
            }

            .btn-banner {
                width: 100%;
            }

            .banner-footer {
                flex-direction: column;
                gap: 1rem;
            }

            .banner-links {
                text-align: center;
            }
        }
    </style>

    <!-- Consent Banner -->
    <div class="consent-banner-overlay" id="consentOverlay"></div>
    <div class="consent-banner" id="consentBanner">
        <div class="consent-banner-content">
            <div class="consent-banner-header">
                <div class="banner-icon">🍪</div>
                <div>
                    <h2 class="banner-title">We Value Your Privacy</h2>
                    <p class="banner-description">
                        We use cookies and similar technologies to enhance your browsing experience,
                        analyze site traffic, and show personalized content. You can customize your
                        preferences or accept all to continue.
                    </p>
                </div>
            </div>

            <!-- Customize Panel -->
            <div class="customize-panel" id="customizePanel">
                <div class="consent-options" id="consentOptions">
                    <!-- Options will be dynamically inserted here -->
                </div>
            </div>

            <div class="banner-actions">
                <button onclick="ConsentBanner.acceptAll()" class="btn-banner btn-accept">
                    ✓ Accept All
                </button>
                <button onclick="ConsentBanner.toggleCustomize()" class="btn-banner btn-customize" id="customizeBtn">
                    ⚙️ Customize Preferences
                </button>
                <button onclick="ConsentBanner.rejectAll()" class="btn-banner btn-reject">
                    ✕ Reject Optional
                </button>
            </div>

            <div class="banner-footer">
            <span>
                By continuing to browse, you agree to our use of essential cookies.
            </span>
                <div class="banner-links">
                    <a href="/privacy-policy" target="_blank">Privacy Policy</a>
                    <a href="/cookie-policy" target="_blank">Cookie Policy</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ConsentBanner = {
            shown: false,
            customizing: false,
            consents: {},
            siteName: '<?= $site->slug ?? '' ?>',

            init() {
                // Show immediately since PHP already checked if needed
                setTimeout(() => this.show(), 500);
            },

            async show() {
                await this.loadConsentTypes();

                const overlay = document.getElementById('consentOverlay');
                const banner = document.getElementById('consentBanner');

                overlay.classList.add('show');
                banner.classList.add('show');
                this.shown = true;
            },

            hide() {
                const overlay = document.getElementById('consentOverlay');
                const banner = document.getElementById('consentBanner');

                overlay.classList.remove('show');
                banner.classList.remove('show');
                this.shown = false;
            },

            async loadConsentTypes() {
                try {
                    const response = await fetch(`/api/${this.siteName}/consent/types/optional`);
                    const data = await response.json();

                    if (data.success) {
                        this.renderOptions(data.consent_types);
                    }
                } catch (error) {
                    console.error('Error loading consent types:', error);
                    // Fallback: render some basic options
                    this.renderFallbackOptions();
                }
            },

            renderOptions(consentTypes) {
                const container = document.getElementById('consentOptions');

                const html = consentTypes.map(type => `
                <div class="consent-option">
                    <div class="option-header">
                        <span class="option-title">${this.escapeHtml(type.name)}</span>
                        <label class="toggle-switch-small">
                            <input
                                type="checkbox"
                                data-consent-code="${this.escapeHtml(type.code)}"
                                onchange="ConsentBanner.updateConsent('${this.escapeHtml(type.code)}', this.checked)"
                            >
                            <span class="toggle-slider-small"></span>
                        </label>
                    </div>
                    <div class="option-description">
                        ${this.escapeHtml(type.description)}
                    </div>
                </div>
            `).join('');

                container.innerHTML = html;
            },

            renderFallbackOptions() {
                const fallbackTypes = [
                    {
                        code: 'analytics',
                        name: 'Analytics & Performance',
                        description: 'Help us understand how visitors use our website.'
                    },
                    {
                        code: 'marketing_email',
                        name: 'Marketing Communications',
                        description: 'Receive promotional emails and newsletters.'
                    }
                ];
                this.renderOptions(fallbackTypes);
            },

            updateConsent(code, granted) {
                this.consents[code] = granted;
            },

            toggleCustomize() {
                this.customizing = !this.customizing;
                const panel = document.getElementById('customizePanel');
                const btn = document.getElementById('customizeBtn');

                if (this.customizing) {
                    panel.classList.add('show');
                    btn.textContent = '▲ Hide Preferences';
                } else {
                    panel.classList.remove('show');
                    btn.textContent = '⚙️ Customize Preferences';
                }
            },

            async acceptAll() {
                const checkboxes = document.querySelectorAll('#consentOptions input[type="checkbox"]');
                const consentsToGrant = Array.from(checkboxes).map(cb => cb.dataset.consentCode);

                await this.saveConsents(consentsToGrant);
            },

            async rejectAll() {
                await this.saveConsents([]);
            },

            async saveCustom() {
                const consentsToGrant = Object.entries(this.consents)
                    .filter(([code, granted]) => granted)
                    .map(([code]) => code);

                await this.saveConsents(consentsToGrant);
            },

            async saveConsents(consentCodes) {
                try {
                    const response = await fetch(`/${SITE}/member/consent/accept-banner`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            consents: consentCodes
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.hide();
                        // Reload page to ensure session is updated
                        setTimeout(() => window.location.reload(), 300);
                    } else {
                        alert('Failed to save consent preferences. Please try again.');
                    }
                } catch (error) {
                    console.error('Error saving consents:', error);
                    alert('Failed to save consent preferences. Please try again.');
                }
            },

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        };

        // Initialize on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => ConsentBanner.init());
        } else {
            ConsentBanner.init();
        }
    </script>
<?php endif; ?>