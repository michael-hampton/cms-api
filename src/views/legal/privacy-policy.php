@extends('legal.layouts.legal')

@section('title', 'Privacy Policy')
@section('meta_description', 'How {{ config(\'app.name\') }} collects, uses, and protects your personal data — your rights under UK GDPR.')
@section('last_updated', config('legal.privacy_policy_updated', 'January 2025'))

@section('content')

<div class="legal-callout legal-callout--info">
    <p><strong>Summary:</strong> We collect the data needed to run your account and deliver your subscriptions. We use
        Stripe for payments — we never see or store your card details. You can download your data, update your
        preferences, or delete your account at any time from your <a href="{{ url('/account') }}">account dashboard</a>.
    </p>
</div>

<section>
    <h2>1. Who we are</h2>
    <p>
        <strong>{{ config('app.name') }}</strong>
        (<span class="legal-placeholder">[COMPANY NUMBER — TO BE COMPLETED]</span>,
        registered in England and Wales) is the data controller for personal data collected through this platform.
    </p>
    <p>
        Registered address: <span class="legal-placeholder">[REGISTERED ADDRESS — TO BE COMPLETED]</span><br>
        Contact for data matters: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email')
            }}</a>
    </p>
    <p>
        We are subject to the <strong>UK General Data Protection Regulation (UK GDPR)</strong> and the
        <strong>Data Protection Act 2018</strong>.
        <span class="legal-placeholder">[IF APPLICABLE: We are registered with the Information Commissioner's Office (ICO), registration number: TO BE COMPLETED]</span>
    </p>
</section>

<section>
    <h2>2. What data we collect and why</h2>
    <p>The table below sets out each category of personal data we process, the purpose, and the lawful basis we rely on
        under UK GDPR Article 6.</p>

    <div class="legal-table-wrap">
        <table class="legal-table">
            <thead>
            <tr>
                <th>Data category</th>
                <th>What we collect</th>
                <th>Purpose</th>
                <th>Lawful basis</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><strong>Account data</strong></td>
                <td>Name, email address, password (hashed)</td>
                <td>Creating and maintaining your account</td>
                <td>Contract (Art. 6(1)(b))</td>
            </tr>
            <tr>
                <td><strong>Subscription &amp; order data</strong></td>
                <td>Plan selected, billing period, subscription status, order history, voucher codes used</td>
                <td>Fulfilling your subscription; managing renewals; processing cancellations</td>
                <td>Contract (Art. 6(1)(b))</td>
            </tr>
            <tr>
                <td><strong>Delivery data</strong></td>
                <td>Postal address, delivery preferences, delivery pause/resume dates</td>
                <td>Shipping physical editions to you</td>
                <td>Contract (Art. 6(1)(b))</td>
            </tr>
            <tr>
                <td><strong>Payment data</strong></td>
                <td>A Stripe customer ID and payment method reference (token). <strong>We do not store card numbers,
                        CVVs, or full payment card data</strong> — these are handled entirely by Stripe.
                </td>
                <td>Processing subscription payments and refunds</td>
                <td>Contract (Art. 6(1)(b))</td>
            </tr>
            <tr>
                <td><strong>Reading &amp; browsing behaviour</strong></td>
                <td>Articles read, time spent on pages, content categories browsed, newsletter open/click events</td>
                <td>Personalising content recommendations; measuring engagement; improving the platform</td>
                <td>Legitimate interests (Art. 6(1)(f)) — see Section 5</td>
            </tr>
            <tr>
                <td><strong>Device &amp; technical data</strong></td>
                <td>IP address, browser type and version, operating system, device type, referring URL, session
                    identifiers
                </td>
                <td>Security, fraud prevention, debugging, abuse detection</td>
                <td>Legitimate interests (Art. 6(1)(f)) — see Section 5</td>
            </tr>
            <tr>
                <td><strong>Marketing &amp; communication preferences</strong></td>
                <td>Whether you have consented to marketing communications; which newsletters you subscribe to;
                    preference history
                </td>
                <td>Sending promotional emails, offers, and newsletters you have opted into</td>
                <td>Consent (Art. 6(1)(a)) — see Section 6</td>
            </tr>
            <tr>
                <td><strong>Support communications</strong></td>
                <td>Content of support tickets, emails, and chat messages you send us</td>
                <td>Responding to enquiries; resolving disputes; improving support quality</td>
                <td>Contract / legitimate interests (Art. 6(1)(b)/(f))</td>
            </tr>
            <tr>
                <td><strong>Financial &amp; compliance records</strong></td>
                <td>Transaction amounts, dates, refund records, VAT data where applicable</td>
                <td>Accounting obligations; tax compliance; legal claims</td>
                <td>Legal obligation (Art. 6(1)(c))</td>
            </tr>
            </tbody>
        </table>
    </div>
</section>

<section>
    <h2>3. How we use your data</h2>

    <h3>Delivering your subscription</h3>
    <p>We use your account, subscription, delivery, and payment data to fulfil your contract with us — activating
        access, processing payments, shipping physical editions, managing renewals, and handling cancellations and
        refunds.</p>

    <h3>Transactional communications</h3>
    <p>We will send you emails that are necessary to your subscription — order confirmations, payment receipts, renewal
        reminders, price change notices, and support responses. These are not marketing emails and are sent on the basis
        of contract; you cannot opt out of them while your subscription is active.</p>

    <h3>Newsletter delivery</h3>
    <p>Where you subscribe to a free or paid newsletter, we use your email address and preferences to deliver that
        newsletter. You can manage your newsletter subscriptions at any time from <a
                href="{{ url('/account/newsletters') }}">Account &gt; Newsletters</a>.</p>

    <h3>Platform improvement</h3>
    <p>We use aggregated and anonymised behavioural data to understand how the platform is used and to improve it. Where
        we use identifiable behavioural data (for example, to personalise article recommendations), we rely on
        legitimate interests (see Section 5).</p>

    <h3>Security and fraud prevention</h3>
    <p>We use device and technical data to detect and prevent fraud, abuse, and unauthorised access. This is a
        legitimate interest that we consider necessary to protect both the platform and our users.</p>
</section>

<section>
    <h2>4. Third-party processors</h2>
    <p>
        We use one third-party service that processes personal data on our behalf:
    </p>

    <div class="legal-table-wrap">
        <table class="legal-table">
            <thead>
            <tr>
                <th>Processor</th>
                <th>Purpose</th>
                <th>Data transferred</th>
                <th>Location</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><strong>Stripe, Inc.</strong></td>
                <td>Payment processing, subscription billing, refunds</td>
                <td>Name, email address, billing address, payment card data (handled directly by Stripe — we do not
                    receive or store card details)
                </td>
                <td>USA (Stripe maintains UK GDPR compliance via standard contractual clauses and its Privacy Shield
                    successor commitments)
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <p>
        Stripe's privacy policy is available at <a href="https://stripe.com/gb/privacy" target="_blank"
                                                   rel="noopener noreferrer">stripe.com/gb/privacy</a>.
        We have a Data Processing Agreement in place with Stripe as required by UK GDPR Article 28.
    </p>
    <p>
        We do not sell, rent, or share your personal data with any other third party for their own marketing purposes.
        We do not use any third-party analytics or advertising platforms that process your personal data.
    </p>
</section>

<section>
    <h2>5. Legitimate interests</h2>
    <p>
        Where we rely on legitimate interests (Art. 6(1)(f)) as our lawful basis, we have carried out a
        balancing test to confirm that our interests do not override your rights and freedoms. The activities
        we conduct on this basis are:
    </p>
    <ul>
        <li><strong>Behavioural data for platform personalisation and improvement</strong> — we have a genuine
            commercial interest in understanding how content is consumed. The data is used internally and not shared.
            You can object to this processing at any time (see Section 9).
        </li>
        <li><strong>Device and technical data for security</strong> — necessary to protect the platform and all users
            from fraud and abuse. The privacy impact is low relative to the security benefit.
        </li>
        <li><strong>Support communications</strong> — retaining support correspondence to resolve disputes and improve
            service quality.
        </li>
    </ul>
</section>

<section>
    <h2>6. Marketing and consent</h2>
    <p>
        We only send marketing emails to you if you have given explicit consent. When you first visit a
        publication on our platform, we present a consent modal that clearly explains what types of
        communications we may send. You can accept or decline each category independently.
    </p>
    <p>
        Your consent choices are recorded with a timestamp. You can review and update your marketing
        preferences at any time from <a href="{{ url('/account/preferences') }}">Account &gt; Communication
            Preferences</a>.
    </p>
    <p>
        <strong>Withdrawing consent:</strong> you can withdraw consent for marketing communications at any time,
        either from your account dashboard or by clicking the unsubscribe link in any marketing email.
        Withdrawing consent does not affect the lawfulness of processing based on consent before withdrawal,
        and does not affect transactional emails necessary to your subscription.
    </p>
    <p>
        We do not use pre-ticked boxes or infer consent from inaction.
    </p>
</section>

<section>
    <h2>7. Cookies</h2>
    <p>
        We use only strictly necessary and functional cookies. We do not use analytics cookies, advertising
        cookies, or any third-party tracking cookies. Full details are in our
        <a href="{{ url('/legal/cookie-policy') }}">Cookie Policy</a>.
    </p>
</section>

<section>
    <h2>8. Data retention</h2>
    <p>
        We retain personal data only for as long as necessary for the purpose it was collected, or as required
        by law. Our full retention schedule is in our <a href="{{ url('/legal/data-retention') }}">Data Retention
            Policy</a>.
        Key periods:
    </p>
    <ul>
        <li><strong>Order and financial records:</strong> <span class="legal-placeholder">[DECISION REQUIRED: 6 or 7 years]</span>
            from the date of the transaction, to comply with HMRC requirements.
        </li>
        <li><strong>Account data for inactive accounts:</strong> <span class="legal-placeholder">[DECISION REQUIRED: e.g. 2 years after last login]</span>,
            after which you will receive notice and data will be deleted.
        </li>
        <li><strong>Newsletter subscriber data:</strong> <span class="legal-placeholder">[DECISION REQUIRED: e.g. 1 year after unsubscribe or last engagement]</span>.
        </li>
        <li><strong>Support communications:</strong> <span
                    class="legal-placeholder">[DECISION REQUIRED: e.g. 3 years]</span> after the ticket is closed.
        </li>
    </ul>
</section>

<section>
    <h2>9. Your rights</h2>
    <p>Under UK GDPR you have the following rights in relation to your personal data:</p>
    <ul>
        <li><strong>Right of access</strong> — to receive a copy of the personal data we hold about you;</li>
        <li><strong>Right to rectification</strong> — to have inaccurate data corrected;</li>
        <li><strong>Right to erasure</strong> — to have your data deleted in certain circumstances;</li>
        <li><strong>Right to restrict processing</strong> — to limit how we use your data;</li>
        <li><strong>Right to data portability</strong> — to receive your data in a structured, machine-readable format;
        </li>
        <li><strong>Right to object</strong> — to processing based on legitimate interests;</li>
        <li><strong>Rights related to automated decision-making</strong> — we do not make solely automated decisions
            that produce legal or similarly significant effects.
        </li>
    </ul>
    <p>
        You can exercise the access and erasure rights directly from your account:
        <a href="{{ url('/account/data') }}">Account &gt; Your Data</a> (download) and
        <a href="{{ url('/account/delete') }}">Account &gt; Delete Account</a>.
        For all other rights, or if you prefer to make a formal request, see our
        <a href="{{ url('/legal/data-subject-rights') }}">Data Subject Rights</a> page.
    </p>

    <h3>Right to complain</h3>
    <p>
        If you are unhappy with how we have handled your personal data, you have the right to lodge a
        complaint with the <strong>Information Commissioner's Office (ICO)</strong>:
        <a href="https://ico.org.uk/make-a-complaint" target="_blank" rel="noopener noreferrer">ico.org.uk/make-a-complaint</a>,
        telephone 0303 123 1113. We would appreciate the opportunity to address your concerns before you
        contact the ICO.
    </p>
</section>

<section>
    <h2>10. Changes to this policy</h2>
    <p>
        We will notify you of any material changes to this policy by email before they take effect.
        The date at the top of this page shows when it was last updated.
    </p>
</section>

@endsection