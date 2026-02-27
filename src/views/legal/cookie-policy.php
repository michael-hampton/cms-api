@extends('legal.layouts.legal')

@section('title', 'Cookie Policy')
@section('meta_description', 'What cookies {{ config(\'app.name\') }} uses, why, and how to control them.')
@section('last_updated', config('legal.cookie_policy_updated', 'January 2025'))

@section('content')

<div class="legal-callout legal-callout--info">
    <p><strong>Short version:</strong> We only use cookies that are strictly necessary to make the platform work —
        session management, security, and your saved preferences. We do not use analytics cookies, advertising cookies,
        or any third-party tracking cookies. No consent banner is required for the cookies we use.</p>
</div>

<section>
    <h2>1. What cookies are</h2>
    <p>
        Cookies are small text files that a website places on your device when you visit. They are widely used
        to make websites work, remember your preferences, and provide a consistent experience across pages.
    </p>
    <p>
        The UK rules on cookies are set out in the <strong>Privacy and Electronic Communications Regulations 2003
            (PECR)</strong>, as amended. PECR requires websites to obtain consent before placing cookies that are
        not strictly necessary. Because we only use strictly necessary and functional cookies, we do not require
        your consent before placing them.
    </p>
</section>

<section>
    <h2>2. The cookies we use</h2>

    <div class="legal-table-wrap">
        <table class="legal-table">
            <thead>
            <tr>
                <th>Cookie name / type</th>
                <th>Category</th>
                <th>Purpose</th>
                <th>Duration</th>
                <th>First or third party</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code>session</code> / <code>laravel_session</code></td>
                <td>Strictly necessary</td>
                <td>Maintains your login session between page loads. Without this cookie you would be logged out every
                    time you navigate to a new page.
                </td>
                <td>Session (deleted when you close your browser, or after a configurable idle timeout)</td>
                <td>First party</td>
            </tr>
            <tr>
                <td><code>XSRF-TOKEN</code></td>
                <td>Strictly necessary</td>
                <td>Cross-site request forgery (CSRF) protection. Ensures form submissions and API calls originate from
                    our platform and not a malicious third-party site.
                </td>
                <td>Session</td>
                <td>First party</td>
            </tr>
            <tr>
                <td>Consent preferences cookie</td>
                <td>Functional</td>
                <td>Remembers the marketing consent choices you made in the consent modal, so we do not show it again on
                    your next visit to the same publication.
                </td>
                <td>12 months</td>
                <td>First party</td>
            </tr>
            <tr>
                <td>Cart / checkout state</td>
                <td>Functional</td>
                <td>Preserves the contents of your shopping cart and checkout progress if you navigate away or your
                    session expires mid-purchase.
                </td>
                <td>24 hours</td>
                <td>First party</td>
            </tr>
            </tbody>
        </table>
    </div>

    <p>
        All cookies listed above are set by our platform directly. We do not load any third-party scripts
        that set their own cookies, with the exception of the Stripe payment form which operates in an
        isolated iframe on its own domain (<code>js.stripe.com</code>) and is governed by
        <a href="https://stripe.com/gb/privacy" target="_blank" rel="noopener noreferrer">Stripe's own privacy and
            cookie practices</a>.
    </p>
</section>

<section>
    <h2>3. Cookies we do not use</h2>
    <p>We do not use any of the following:</p>
    <ul>
        <li><strong>Analytics cookies</strong> — we do not use Google Analytics, Mixpanel, Hotjar, or any equivalent
            tool that tracks your behaviour across sessions via cookies;
        </li>
        <li><strong>Advertising or retargeting cookies</strong> — we do not run Meta Pixel, Google Ads tags, or any
            other advertising technology that profiles you for ad targeting;
        </li>
        <li><strong>Social media tracking cookies</strong> — we do not embed third-party social share buttons or feeds
            that set tracking cookies;
        </li>
        <li><strong>Fingerprinting</strong> — we do not use browser fingerprinting as an alternative to cookies.</li>
    </ul>
</section>

<section>
    <h2>4. How to control or delete cookies</h2>
    <p>
        Because the cookies we use are strictly necessary or functional, disabling them will affect your
        ability to use the platform — you will not be able to stay logged in, and your preferences will
        not be remembered.
    </p>
    <p>You can manage cookies through your browser settings:</p>
    <ul>
        <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">Google
                Chrome</a></li>
        <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer"
               target="_blank" rel="noopener noreferrer">Mozilla Firefox</a></li>
        <li><a href="https://support.apple.com/en-gb/guide/safari/sfri11471/mac" target="_blank"
               rel="noopener noreferrer">Apple Safari</a></li>
        <li>
            <a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09"
               target="_blank" rel="noopener noreferrer">Microsoft Edge</a></li>
    </ul>
    <p>
        Note that clearing cookies will log you out of your account and reset any saved preferences.
    </p>
</section>

<section>
    <h2>5. Changes to this policy</h2>
    <p>
        If we introduce new cookies — for example if we adopt an analytics platform in the future — we will
        update this policy before doing so and, if the new cookies require consent, we will request it
        through our consent modal. The date at the top of this page shows when it was last updated.
    </p>
</section>

<section>
    <h2>6. Contact</h2>
    <p>
        Questions about cookies or this policy:
        <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.
    </p>
</section>

@endsection