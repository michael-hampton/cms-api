@extends('legal.layouts.legal')

@section('title', 'Your Data Rights')
@section('meta_description', 'How to exercise your rights under UK GDPR — access, erasure, portability, objection, and more.')
@section('last_updated', config('legal.data_subject_rights_updated', 'January 2025'))

@section('content')

<div class="legal-callout legal-callout--info">
    <p>Many rights can be exercised directly from your account without contacting us. See <a
                href="{{ url('/account/data') }}">Account&nbsp;&gt;&nbsp;Your Data</a> to download your data, and <a
                href="{{ url('/account/delete') }}">Account&nbsp;&gt;&nbsp;Delete Account</a> to request erasure.</p>
</div>

<section>
    <h2>1. Your rights at a glance</h2>
    <p>
        Under the <strong>UK General Data Protection Regulation (UK GDPR)</strong> and the
        <strong>Data Protection Act 2018</strong>, you have the following rights in relation to personal
        data we hold about you. Not all rights apply in every situation — we explain the limits below.
    </p>

    <div class="legal-table-wrap">
        <table class="legal-table">
            <thead>
            <tr>
                <th>Right</th>
                <th>What it means</th>
                <th>Self-service available?</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><strong>Access (Subject Access Request)</strong></td>
                <td>Receive a copy of the personal data we hold about you and information about how it is used.</td>
                <td><a href="{{ url('/account/data') }}">Download from account</a></td>
            </tr>
            <tr>
                <td><strong>Rectification</strong></td>
                <td>Have inaccurate personal data corrected, or incomplete data completed.</td>
                <td>Update most data in <a href="{{ url('/account/profile') }}">Account&nbsp;&gt;&nbsp;Profile</a>;
                    contact us for the rest.
                </td>
            </tr>
            <tr>
                <td><strong>Erasure ("right to be forgotten")</strong></td>
                <td>Have your personal data deleted where there is no longer a lawful reason to keep it.</td>
                <td><a href="{{ url('/account/delete') }}">Delete account</a> (see Section 4 for limits)</td>
            </tr>
            <tr>
                <td><strong>Restriction of processing</strong></td>
                <td>Ask us to pause processing of your data — for example while you contest its accuracy — without
                    asking us to delete it.
                </td>
                <td>Contact us (see Section 5)</td>
            </tr>
            <tr>
                <td><strong>Data portability</strong></td>
                <td>Receive your data in a structured, commonly-used, machine-readable format (JSON or CSV) for transfer
                    to another service.
                </td>
                <td><a href="{{ url('/account/data') }}">Download from account</a></td>
            </tr>
            <tr>
                <td><strong>Object to processing</strong></td>
                <td>Object to processing based on legitimate interests. We must stop unless we can demonstrate
                    compelling legitimate grounds that override your interests.
                </td>
                <td>Contact us (see Section 5)</td>
            </tr>
            <tr>
                <td><strong>Withdraw consent</strong></td>
                <td>Withdraw consent for marketing communications at any time. This does not affect the lawfulness of
                    processing before withdrawal.
                </td>
                <td><a href="{{ url('/account/preferences') }}">Communication Preferences</a> or unsubscribe link in any
                    marketing email
                </td>
            </tr>
            <tr>
                <td><strong>Automated decision-making</strong></td>
                <td>Not to be subject to solely automated decisions that produce legal or similarly significant effects.
                    We do not carry out such processing.
                </td>
                <td>Not applicable</td>
            </tr>
            </tbody>
        </table>
    </div>
</section>

<section>
    <h2>2. Accessing your data (Subject Access Request)</h2>
    <p>
        You can download a copy of your personal data at any time from
        <a href="{{ url('/account/data') }}">Account&nbsp;&gt;&nbsp;Your Data</a>. The download includes:
    </p>
    <ul>
        <li>Your account and profile information;</li>
        <li>Your subscription and order history;</li>
        <li>Your communication and marketing preferences;</li>
        <li>Your newsletter subscriptions;</li>
        <li>Delivery addresses associated with your account.</li>
    </ul>
    <p>
        If you need data not included in the download — for example, support correspondence or
        behavioural data — you can submit a formal Subject Access Request (see Section 5). We will
        respond within <strong>one calendar month</strong>. In complex cases we may extend this by a
        further two months, in which case we will notify you within the first month.
    </p>
    <p>There is no charge for a Subject Access Request unless it is manifestly unfounded or excessive.</p>
</section>

<section>
    <h2>3. Updating or correcting your data</h2>
    <p>
        You can update your name, email address, and postal address from
        <a href="{{ url('/account/profile') }}">Account&nbsp;&gt;&nbsp;Profile</a> at any time.
    </p>
    <p>
        If you believe other data we hold is inaccurate — for example, an incorrect order record or
        preference logged in error — contact us and we will investigate and correct it within
        <strong>one calendar month</strong>.
    </p>
</section>

<section id="erasure">
    <h2>4. Deleting your account and data (erasure)</h2>
    <p>
        You can request deletion of your account and personal data at any time from
        <a href="{{ url('/account/delete') }}">Account&nbsp;&gt;&nbsp;Delete Account</a>.
    </p>
    <p>
        On confirming deletion, we will:
    </p>
    <ul>
        <li>Deactivate your account immediately;</li>
        <li>Cancel any active subscriptions (no further charges will be made);</li>
        <li>Delete or anonymise personal data that we are not required to retain;</li>
        <li>Confirm deletion by email to your registered address.</li>
    </ul>

    <h3>Data we may retain after deletion</h3>
    <p>
        We are not always able to delete all data immediately. We may retain certain data after your
        account is deleted for the following reasons:
    </p>
    <ul>
        <li><strong>Financial records:</strong> order and payment records are retained for
            <span class="legal-placeholder">[DECISION REQUIRED: 6 or 7 years]</span>
            to comply with HMRC requirements. This data is held securely and used only for tax and legal purposes.
        </li>
        <li><strong>Consent records:</strong> records of marketing consent given or withdrawn are retained for
            <span class="legal-placeholder">[DECISION REQUIRED: e.g. 3 years]</span>
            after account deletion to demonstrate compliance with UK GDPR.
        </li>
        <li><strong>Fraud prevention:</strong> where there is a legitimate security reason (for example, a confirmed
            fraudulent account), limited data may be retained to prevent re-registration.
        </li>
        <li><strong>Legal hold:</strong> where data is relevant to a dispute, investigation, or legal proceedings.</li>
    </ul>
    <p>
        Where retention is based on a legal obligation, the right to erasure does not apply to that data.
        We will tell you specifically what data we are retaining and why when you make an erasure request.
    </p>
</section>

<section>
    <h2>5. How to make a formal request</h2>
    <p>
        For rights not available through self-service (restriction, objection, formal SAR, or erasure of
        data not covered by the account deletion flow), contact us at:
    </p>
    <p style="margin-left: 16px;">
        <strong>Email:</strong> <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email')
            }}</a><br>
        <strong>Subject line:</strong> Data Subject Rights Request<br>
        <strong>Post:</strong> {{ config('app.name') }},
        <span class="legal-placeholder">[REGISTERED ADDRESS — TO BE COMPLETED]</span>
    </p>
    <p>Please include:</p>
    <ul>
        <li>Your full name and the email address registered to your account;</li>
        <li>The right you wish to exercise (access, erasure, restriction, objection, etc.);</li>
        <li>Enough detail for us to identify the data in question.</li>
    </ul>
    <p>
        We may need to verify your identity before processing a request. We will not charge you for
        identity verification. If we ask for verification it is only to protect you — preventing someone
        else from requesting your data.
    </p>

    <h3>Response timelines</h3>
    <p>
        We will acknowledge your request within <strong>72 hours</strong> and respond substantively
        within <strong>one calendar month</strong> of the date we receive it (or, if later, the date
        we are satisfied we have verified your identity). We may extend this by a further two months for
        complex or multiple requests — if so, we will notify you within the first month with the reason
        for the extension.
    </p>
    <p>
        If we decide not to comply with a request, we will tell you why and inform you of your right
        to complain to the ICO.
    </p>
</section>

<section>
    <h2>6. Complaining to the ICO</h2>
    <p>
        If you are not satisfied with our response to a rights request, or with how we handle your
        personal data more generally, you have the right to lodge a complaint with the
        <strong>Information Commissioner's Office (ICO)</strong>:
    </p>
    <ul>
        <li>Website: <a href="https://ico.org.uk/make-a-complaint" target="_blank" rel="noopener noreferrer">ico.org.uk/make-a-complaint</a>
        </li>
        <li>Telephone: 0303 123 1113</li>
        <li>Post: Information Commissioner's Office, Wycliffe House, Water Lane, Wilmslow, Cheshire, SK9 5AF</li>
    </ul>
    <p>
        You also have the right to seek a judicial remedy against us or against the ICO. We would
        always prefer the opportunity to address your concerns directly before you escalate — please
        contact us first.
    </p>
</section>

@endsection