<footer class="site-footer">

    <div class="footer-inner">

        <div class="footer-brand">
            <a href="{{ url('/') }}" class="footer-logo">
                <span class="logo-mark">◆</span>
                <span class="logo-name">YourCompany</span>
            </a>
            <p class="footer-tagline">
                Quality you can trust, service you'll remember.
            </p>
            <div class="footer-social">
                <a href="https://instagram.com/yourcompany" target="_blank" rel="noopener noreferrer"
                   aria-label="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                        <circle cx="12" cy="12" r="4"/>
                        <circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/>
                    </svg>
                </a>
                <a href="https://facebook.com/yourcompany" target="_blank" rel="noopener noreferrer"
                   aria-label="Facebook">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                    </svg>
                </a>
                <a href="https://twitter.com/yourcompany" target="_blank" rel="noopener noreferrer"
                   aria-label="X / Twitter">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>
                <a href="https://linkedin.com/company/yourcompany" target="_blank" rel="noopener noreferrer"
                   aria-label="LinkedIn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                        <rect x="2" y="9" width="4" height="12"/>
                        <circle cx="4" cy="4" r="2"/>
                    </svg>
                </a>
                <a href="https://tiktok.com/@yourcompany" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.3 6.3 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.22 8.22 0 0 0 4.8 1.53V6.77a4.85 4.85 0 0 1-1.03-.08z"/>
                    </svg>
                </a>
            </div>
        </div>

        <div class="footer-newsletter">
            <h3 class="footer-heading">Stay in the loop</h3>
            <p class="footer-newsletter-desc">Get our latest articles and offers delivered straight to your inbox. No
                spam, ever.</p>
            <form class="footer-newsletter-form" id="footer-newsletter-form">
                <div class="footer-newsletter-input-wrap">
                    <input
                            type="email"
                            name="email"
                            class="footer-newsletter-input"
                            placeholder="your@email.com"
                            required
                            autocomplete="email"
                    />
                    <button type="submit" class="footer-newsletter-btn" aria-label="Subscribe">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </button>
                </div>
                <p class="footer-newsletter-status" id="footer-newsletter-status" aria-live="polite"></p>
            </form>
        </div>

        <div class="footer-col">
            <h3 class="footer-heading">Company</h3>
            <ul class="footer-links">
                <li><a href="{{ url('/about') }}">About Us</a></li>
                <li><a href="{{ url('/contact') }}">Contact</a></li>
                <li><a href="{{ url('/blog') }}">Blog</a></li>
                <li><a href="{{ url('/careers') }}">Careers</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3 class="footer-heading">Help</h3>
            <ul class="footer-links">
                <li><a href="{{ url('/faq') }}">FAQ</a></li>
                <li><a href="{{ route('returns') }}">Returns</a></li>
                <li><a href="{{ route('cancellation') }}">Cancellation Rights</a></li>
                <li><a href="{{ route('reviews') }}">Reviews Policy</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3 class="footer-heading">Legal</h3>
            <ul class="footer-links">
                <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
                <li><a href="{{ route('cookies') }}">Cookie Policy</a></li>
                <li><a href="{{ route('data-rights') }}">Data Subject Rights</a></li>
                <li><a href="{{ route('data-retention') }}">Data Retention</a></li>
            </ul>
        </div>

    </div>

    <div class="footer-bottom">
        <p class="footer-copy">
            &copy; {{ date('Y') }} YourCompany Ltd. All rights reserved.
            Registered in England &amp; Wales · Company No. 12345678
        </p>
        <div class="footer-bottom-links">
            <a href="{{ route('privacy') }}">Privacy</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('cookies') }}">Cookies</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('cancellation') }}">Cancellation</a>
        </div>
    </div>

</footer>

<style>
    /* ─── Google Fonts ───────────────────────────────────────────── */
    @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap');

    /* ─── Tokens ─────────────────────────────────────────────────── */
    :root {
        --footer-bg: #F7F6F2; /* warm off-white */
        --footer-border: #E2DFD7;
        --footer-text: #3A3730;
        --footer-muted: #8A8478;
        --footer-accent: #1A1916;
        --footer-hover: #6B5B45; /* warm brown */
        --footer-icon-size: 18px;
    }

    /* ─── Shell ──────────────────────────────────────────────────── */
    .site-footer {
        background-color: var(--footer-bg);
        border-top: 1px solid var(--footer-border);
        font-family: 'DM Sans', sans-serif;
        color: var(--footer-text);
    }

    .footer-inner {
        max-width: 1200px;
        margin: 0 auto;
        padding: 72px 32px 48px;
        display: grid;
        grid-template-columns: 1.4fr 1fr 1fr 1fr 1fr;
        gap: 48px;
    }

    /* ─── Brand ──────────────────────────────────────────────────── */
    .footer-logo {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        margin-bottom: 16px;
    }

    .logo-mark {
        font-size: 20px;
        color: var(--footer-hover);
        line-height: 1;
    }

    .logo-name {
        font-family: 'DM Serif Display', serif;
        font-size: 22px;
        color: var(--footer-accent);
        letter-spacing: -0.02em;
    }

    .footer-tagline {
        font-size: 13.5px;
        font-weight: 300;
        color: var(--footer-muted);
        line-height: 1.6;
        margin: 0 0 28px;
        max-width: 240px;
    }

    /* ─── Social Icons ───────────────────────────────────────────── */
    .footer-social {
        display: flex;
        gap: 14px;
        align-items: center;
    }

    .footer-social a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: 1px solid var(--footer-border);
        border-radius: 8px;
        color: var(--footer-muted);
        text-decoration: none;
        transition: color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        background: transparent;
    }

    .footer-social a svg {
        width: var(--footer-icon-size);
        height: var(--footer-icon-size);
    }

    .footer-social a:hover {
        color: var(--footer-hover);
        border-color: var(--footer-hover);
        transform: translateY(-2px);
    }

    /* ─── Nav Columns ────────────────────────────────────────────── */
    .footer-col {
        padding-top: 4px;
    }

    .footer-heading {
        font-family: 'DM Serif Display', serif;
        font-size: 13px;
        font-weight: 400;
        font-style: italic;
        color: var(--footer-muted);
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin: 0 0 20px;
    }

    .footer-links {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 11px;
    }

    .footer-links a {
        font-size: 14px;
        font-weight: 400;
        color: var(--footer-text);
        text-decoration: none;
        transition: color 0.15s ease;
        position: relative;
    }

    .footer-links a::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 0;
        height: 1px;
        background: var(--footer-hover);
        transition: width 0.2s ease;
    }

    .footer-links a:hover {
        color: var(--footer-hover);
    }

    .footer-links a:hover::after {
        width: 100%;
    }

    /* ─── Bottom Bar ─────────────────────────────────────────────── */
    .footer-bottom {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px 32px 28px;
        border-top: 1px solid var(--footer-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .footer-copy {
        font-size: 12.5px;
        color: var(--footer-muted);
        font-weight: 300;
        margin: 0;
        line-height: 1.5;
    }

    .footer-bottom-links {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .footer-bottom-links a {
        font-size: 12.5px;
        color: var(--footer-muted);
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .footer-bottom-links a:hover {
        color: var(--footer-hover);
    }

    .footer-bottom-links span {
        color: var(--footer-border);
        font-size: 12px;
    }

    /* ─── Responsive ─────────────────────────────────────────────── */
    @media (max-width: 900px) {
        .footer-inner {
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .footer-brand {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 560px) {
        .footer-inner {
            grid-template-columns: 1fr;
            padding: 48px 24px 36px;
            gap: 32px;
        }

        .footer-brand {
            grid-column: auto;
        }

        .footer-bottom {
            flex-direction: column;
            align-items: flex-start;
            padding: 20px 24px 24px;
        }
    }

    /* ─── Newsletter ─────────────────────────────────────────────── */
    .footer-newsletter {
        padding-top: 4px;
    }

    .footer-newsletter-desc {
        font-size: 13.5px;
        font-weight: 300;
        color: var(--footer-muted);
        line-height: 1.6;
        margin: 0 0 20px;
    }

    .footer-newsletter-input-wrap {
        display: flex;
        align-items: center;
        border: 1px solid var(--footer-border);
        border-radius: 10px;
        overflow: hidden;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background: #fff;
    }

    .footer-newsletter-input-wrap:focus-within {
        border-color: var(--footer-hover);
        box-shadow: 0 0 0 3px rgba(107, 91, 69, 0.1);
    }

    .footer-newsletter-input {
        flex: 1;
        border: none;
        outline: none;
        padding: 11px 14px;
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        color: var(--footer-text);
        background: transparent;
    }

    .footer-newsletter-input::placeholder {
        color: var(--footer-muted);
        opacity: 0.7;
    }

    .footer-newsletter-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        flex-shrink: 0;
        background: var(--footer-hover);
        border: none;
        cursor: pointer;
        color: #fff;
        transition: background 0.2s ease;
    }

    .footer-newsletter-btn:hover {
        background: var(--footer-accent);
    }

    .footer-newsletter-btn svg {
        width: 16px;
        height: 16px;
    }

    .footer-newsletter-status {
        margin: 10px 0 0;
        font-size: 12.5px;
        min-height: 18px;
        font-weight: 400;
    }

    .footer-newsletter-status.success {
        color: #4a7c59;
    }

    .footer-newsletter-status.error {
        color: #b94a48;
    }

    @media (max-width: 900px) {
        .footer-inner {
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .footer-brand,
        .footer-newsletter {
            grid-column: 1 / -1;
        }
    }
</style>

<script>
    (function () {
        const form = document.getElementById('footer-newsletter-form');
        const status = document.getElementById('footer-newsletter-status');

        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const email = form.querySelector('input[name="email"]').value.trim();
            const btn = form.querySelector('.footer-newsletter-btn');

            btn.disabled = true;
            status.textContent = '';
            status.className = 'footer-newsletter-status';

            try {
                const res = await fetch('/api/newsletter/signup', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({email})
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    status.textContent = 'You\'re in! Check your inbox to confirm.';
                    status.classList.add('success');
                    form.reset();
                } else {
                    status.textContent = data.error || data.message || 'Something went wrong. Please try again.';
                    status.classList.add('error');
                }
            } catch (_) {
                status.textContent = 'Network error. Please try again.';
                status.classList.add('error');
            } finally {
                btn.disabled = false;
            }
        });
    })();
</script>