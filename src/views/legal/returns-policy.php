@extends('legal.layouts.legal')

@section('title', 'Returns Policy')
@section('meta_description', 'How to return physical goods purchased through our platform, your statutory rights, and how refunds are processed.')
@section('last_updated', config('legal.returns_policy_updated', 'January 2025'))

@section('content')

<div class="legal-callout legal-callout--info">
    <p>This policy applies to <strong>physical goods only</strong> (for example, print editions shipped to your
        address). For digital subscriptions and one-time digital purchases, see our <a
                href="{{ url('/legal/cancellation-rights') }}">Cancellation Rights</a> page.</p>
</div>

<section>
    <h2>1. Your statutory right to cancel</h2>
    <p>
        Under the <strong>Consumer Contracts (Information, Cancellation and Additional Charges) Regulations
            2013</strong>,
        you have a <strong>14-day right to cancel</strong> any physical goods order from the day you (or a nominated
        third party) receive the goods, without giving any reason.
    </p>
    <p>
        To exercise this right within the 14-day window, notify us before the period expires — you do not need
        to have returned the goods yet. See <a href="#how-to-return">Section 4</a> for how to notify us.
    </p>
</section>

<section>
    <h2>2. Our extended returns window</h2>
    <p>
        In addition to your statutory 14-day right, we accept returns of physical goods within
        <strong>30 days of the date you received them</strong>, provided the conditions in Section 3 are met.
        This is a voluntary extension and does not affect your statutory rights.
    </p>
    <p>
        After 30 days from receipt, we are only obliged to accept returns where the goods are faulty,
        misdescribed, or not fit for purpose under the <strong>Consumer Rights Act 2015</strong>.
    </p>
</section>

<section>
    <h2>3. Condition of returned goods</h2>
    <p>To be eligible for a return and refund, goods must be:</p>
    <ul>
        <li>Returned within 30 days of the date you received them;</li>
        <li>In their original, unused condition — not read, opened beyond inspection, damaged by you, or missing any
            components;
        </li>
        <li>Accompanied by proof of purchase (order number or order confirmation email).</li>
    </ul>
    <p>
        We reserve the right to reduce your refund to reflect any diminishment in value if the goods have
        been handled beyond what is necessary to establish their nature, characteristics, and functioning —
        for example, if a magazine has been read and is no longer in a resaleable condition.
    </p>

    <h3>Items we cannot accept back</h3>
    <ul>
        <li>Goods that have been personalised or made to your specification;</li>
        <li>Goods with a seal that you have broken where the seal is necessary for health protection or hygiene
            reasons;
        </li>
        <li>Goods that have, by their nature, become inseparably mixed with other items after delivery.</li>
    </ul>
</section>

<section id="how-to-return">
    <h2>4. How to return goods</h2>

    <h3>Step 1 — notify us</h3>
    <p>
        Before sending anything back, notify us of your intention to return. You can do this by:
    </p>
    <ul>
        <li><strong>Email:</strong> <a href="mailto:{{ config('legal.contact_email') }}">{{
                config('legal.contact_email') }}</a> — include your order number and the item(s) you wish to return;
        </li>
        <li><strong>Account:</strong> log in and go to <a href="{{ url('/account/orders') }}">Account &gt; Orders</a>
            and select the relevant order.
        </li>
    </ul>
    <p>We will acknowledge your return request within 2 business days and provide a return reference number.</p>

    <h3>Step 2 — package and post</h3>
    <p>
        Pack the item(s) securely and include your order number or return reference inside the package.
        Send the return to:
    </p>
    <address style="font-style:normal; margin: 12px 0 12px 16px; color: #374151; line-height: 1.8;">
        Returns Department<br>
        {{ config('app.name') }}<br>
        <span class="legal-placeholder">[REGISTERED ADDRESS — TO BE COMPLETED]</span>
    </address>

    <h3>Return postage costs</h3>
    <p>
        <strong>Change of mind returns:</strong> you are responsible for the cost of return postage. We recommend
        using a tracked service, as we cannot accept responsibility for items lost in transit on their way back to us.
    </p>
    <p>
        <strong>Faulty, damaged, or misdescribed goods:</strong> if you are returning goods because they arrived
        damaged, are faulty, or were not as described, we will refund your reasonable return postage costs.
        Please retain your postage receipt and include it with your return, or email it to us separately.
    </p>
</section>

<section>
    <h2>5. Faulty or misdescribed goods</h2>
    <p>
        Under the <strong>Consumer Rights Act 2015</strong>, goods must be of satisfactory quality, fit for purpose,
        and as described. If your goods arrive damaged, are faulty, or do not match their description, you are
        entitled to one of the following remedies:
    </p>
    <ul>
        <li><strong>Within 30 days of receipt:</strong> a full refund, or replacement at your choice;</li>
        <li><strong>After 30 days but within 6 months:</strong> one repair or replacement attempt; if that fails, a full
            or partial refund;
        </li>
        <li><strong>After 6 months:</strong> you must demonstrate the fault existed at the time of purchase; remedy at
            our discretion.
        </li>
    </ul>
    <p>
        To report a fault, contact us at <a href="mailto:{{ config('legal.contact_email') }}">{{
            config('legal.contact_email') }}</a>
        with your order number and a description (and photo where possible) of the fault.
    </p>
</section>

<section>
    <h2>6. Refunds</h2>
    <p>
        Once we receive and inspect your return, we will process your refund within
        <strong>14 days</strong> of the day we receive the goods back (or, if earlier, the day you provide
        evidence that you have sent them back).
    </p>
    <p>
        Refunds are issued to your <strong>original payment method</strong>. We do not issue refunds by
        cheque, store credit, or any other method unless your original payment method is no longer available,
        in which case we will contact you.
    </p>
    <p>
        We will refund the full price of the goods. Original outbound delivery charges are refunded only
        where you are returning because the goods were faulty, damaged, or misdescribed.
    </p>
</section>

<section>
    <h2>7. Exchanges</h2>
    <p>
        We do not operate a direct exchange service. If you would like a different item, please return the
        original goods for a refund and place a new order.
    </p>
</section>

<section>
    <h2>8. Subscriptions that include physical goods</h2>
    <p>
        If your subscription includes physical goods (for example, a print edition), the above policy
        applies to each physical issue received. Your right to cancel the subscription itself is set out
        in our <a href="{{ url('/legal/cancellation-rights') }}">Cancellation Rights</a> page.
    </p>
</section>

<section>
    <h2>9. Contact</h2>
    <p>
        For returns queries contact us at
        <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.
        Our company details are in our <a href="{{ url('/legal/company-details') }}">legal notices</a>.
    </p>
    <p>
        If we cannot resolve your complaint, you may refer it to an approved Alternative Dispute Resolution
        scheme or the <a href="https://www.gov.uk/small-claims-court" target="_blank" rel="noopener noreferrer">small
            claims court</a>
        for disputes under £10,000.
    </p>
</section>

@endsection