@extends('legal.layouts.legal')

@section('title', 'Data Retention Policy')
@section('meta_description', 'How long {{ config(\'app.name\') }} retains different categories of personal data and the reasons why.')
@section('last_updated', config('legal.data_retention_updated', 'January 2025'))

@section('content')

<div class="legal-callout legal-callout--warn">
    <p><strong>Note:</strong> Items marked <span class="legal-placeholder">[DECISION REQUIRED]</span> are pending an
        internal decision. This page must not be published until all placeholders are resolved.</p>
</div>

<section>
    <h2>1. Purpose of this policy</h2>
    <p>
        UK GDPR's storage limitation principle (Article 5(1)(e)) requires that personal data is kept for
        no longer than is necessary for the purpose for which it was collected. This policy sets out our
        retention schedule — how long we keep each category of data and why — and our deletion process.
    </p>
    <p>
        This policy applies to all personal data processed by {{ config('app.name') }}, whether held in
        our database, email systems, support tools, or any other medium.
    </p>
</section>

<section>
    <h2>2. Retention schedule</h2>

    <div class="legal-table-wrap">
        <table class="legal-table">
            <thead>
            <tr>
                <th>Data category</th>
                <th>Retention period</th>
                <th>Trigger for deletion</th>
                <th>Reason / legal basis</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><strong>Order and payment records</strong><br><small>Order details, subscription history,
                        transaction amounts, refund records</small></td>
                <td><span class="legal-placeholder">[DECISION REQUIRED: 6 or 7 years recommended]</span></td>
                <td>Date of transaction</td>
                <td>HMRC requirements under s.12A of the Taxes Management Act 1970; legal obligation (UK GDPR Art.
                    6(1)(c))
                </td>
            </tr>
            <tr>
                <td><strong>Account and profile data</strong><br><small>Name, email, address, login history, preferences
                        — for active accounts</small></td>
                <td>Duration of the account relationship plus <span class="legal-placeholder">[DECISION REQUIRED: e.g. 30 days grace period]</span>
                    after account deletion request
                </td>
                <td>Account deletion by member or by us</td>
                <td>Contract performance; legitimate interests in fraud prevention</td>
            </tr>
            <tr>
                <td><strong>Inactive account data</strong><br><small>Accounts with no login and no active
                        subscription</small></td>
                <td><span class="legal-placeholder">[DECISION REQUIRED: e.g. 2 years after last login]</span></td>
                <td>Last login date. We will email a notice <span class="legal-placeholder">[e.g. 30 days]</span> before
                    deletion.
                </td>
                <td>Storage limitation principle; no ongoing legitimate purpose after prolonged inactivity</td>
            </tr>
            <tr>
                <td><strong>Newsletter subscriber data</strong><br><small>Email, preferences, open/click history for
                        newsletter subscribers who have not purchased</small></td>
                <td><span class="legal-placeholder">[DECISION REQUIRED: e.g. 1 year after unsubscribe or last engagement (open/click)]</span>
                </td>
                <td>Unsubscribe action or last engagement date</td>
                <td>Consent withdrawn or expired; no ongoing purpose</td>
            </tr>
            <tr>
                <td><strong>Marketing consent records</strong><br><small>Timestamp and detail of consent given or
                        withdrawn</small></td>
                <td>For as long as the account exists, plus <span class="legal-placeholder">[DECISION REQUIRED: e.g. 3 years]</span>
                    after account deletion
                </td>
                <td>Account deletion</td>
                <td>Accountability obligation under UK GDPR Art. 5(2) — we must be able to demonstrate consent was
                    lawfully obtained
                </td>
            </tr>
            <tr>
                <td><strong>Behavioural and reading data</strong><br><small>Articles read, time spent, content
                        preferences</small></td>
                <td><span class="legal-placeholder">[DECISION REQUIRED: e.g. anonymised after 12 months, deleted with account]</span>
                </td>
                <td>Account deletion, or rolling anonymisation schedule</td>
                <td>Legitimate interests (personalisation/improvement); minimised by anonymisation after the useful
                    period
                </td>
            </tr>
            <tr>
                <td><strong>Device and technical data</strong><br><small>IP addresses, session logs, browser/device
                        data</small></td>
                <td>
                    <span class="legal-placeholder">[DECISION REQUIRED: e.g. 90 days for logs; shorter for raw IPs]</span>
                </td>
                <td>Rolling deletion after the retention period</td>
                <td>Legitimate interests (security, fraud prevention); no ongoing purpose beyond the security window
                </td>
            </tr>
            <tr>
                <td><strong>Support communications</strong><br><small>Ticket content, email correspondence, chat
                        logs</small></td>
                <td><span class="legal-placeholder">[DECISION REQUIRED: e.g. 3 years after ticket closed]</span></td>
                <td>Ticket closure date</td>
                <td>Legitimate interests in resolving disputes; limitation period for contract claims is 6 years, but
                    most support matters are resolved much sooner
                </td>
            </tr>
            <tr>
                <td><strong>Delivery addresses</strong><br><small>Postal addresses used for physical edition
                        delivery</small></td>
                <td>Duration of active subscription, then deleted with account data</td>
                <td>Subscription cancellation or account deletion</td>
                <td>No ongoing purpose once delivery ceases</td>
            </tr>
            <tr>
                <td><strong>Pricing change records</strong><br><small>Records of price change notifications sent</small>
                </td>
                <td>Same as order records — <span class="legal-placeholder">[DECISION REQUIRED: 6 or 7 years]</span>
                </td>
                <td>Date notice was sent</td>
                <td>Legal accountability; potential consumer disputes</td>
            </tr>
            </tbody>
        </table>
    </div>
</section>

<section>
    <h2>3. Deletion and anonymisation</h2>
    <p>
        At the end of a retention period, data is either <strong>securely deleted</strong> or
        <strong>anonymised</strong> (where aggregate data has ongoing analytical value and the individual
        can no longer be identified from the resulting dataset).
    </p>
    <p>
        Deletion is triggered automatically where possible. For data categories without an automated
        trigger, our team conducts a periodic review
        <span class="legal-placeholder">[DECISION REQUIRED: e.g. quarterly]</span>.
    </p>
    <p>
        Backups: data deleted from live systems is removed from backups within
        <span class="legal-placeholder">[DECISION REQUIRED: e.g. 90 days]</span>, which is the maximum
        period our backup retention policy allows.
    </p>
</section>

<section>
    <h2>4. Exceptions</h2>
    <p>We may retain data beyond the scheduled period in the following circumstances:</p>
    <ul>
        <li><strong>Legal hold:</strong> where data is relevant to ongoing or reasonably anticipated litigation,
            regulatory investigation, or legal proceedings, it is preserved until the matter is resolved;
        </li>
        <li><strong>Regulatory requirement:</strong> where a specific law requires longer retention;</li>
        <li><strong>Disputed transaction:</strong> order data related to a disputed charge or chargeback is retained
            until the dispute is concluded.
        </li>
    </ul>
</section>

<section>
    <h2>5. Your right to erasure</h2>
    <p>
        You have the right to request erasure of your personal data under UK GDPR Article 17. We will
        comply unless we have a lawful reason to retain the data — for example, to comply with a legal
        obligation (such as HMRC record-keeping) or to establish, exercise, or defend a legal claim.
    </p>
    <p>
        You can request deletion directly from
        <a href="{{ url('/account/delete') }}">Account &gt; Delete Account</a>, or make a formal request
        via our <a href="{{ url('/legal/data-subject-rights') }}">Data Subject Rights</a> page.
    </p>
</section>

<section>
    <h2>6. Review of this policy</h2>
    <p>
        This policy is reviewed <span class="legal-placeholder">[DECISION REQUIRED: e.g. annually]</span>
        and updated whenever our data processing activities change materially.
    </p>
</section>

<section>
    <h2>7. Contact</h2>
    <p>
        Questions about data retention:
        <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.
    </p>
</section>

@endsection