@extends('legal.layouts.legal')

@section('title', 'Cancellation Rights')
@section('meta_description', 'Your right to cancel digital subscriptions and one-time digital purchases, including the 14-day cooling-off period under the Consumer Contracts Regulations 2013.')

@section('content')
<article class="legal-page">

    <header class="legal-page__header">
        <p class="legal-page__section">Legal</p>
        <h1 class="legal-page__title">Cancellation Rights</h1>
        <p class="legal-page__meta">Last updated: {{ config('legal.cancellation_rights_updated', 'January 2025') }}</p>
    </header>

    <div class="legal-page__body">

        <section>
            <h2>1. Overview</h2>
            <p>
                This page explains your right to cancel purchases made on our platform. The rights described here
                apply to consumers (individuals purchasing for personal, not business, use) who contract with us
                through our website or mobile application.
            </p>
            <p>
                Your cancellation rights are governed by the
                <strong>Consumer Contracts (Information, Cancellation and Additional Charges) Regulations 2013</strong>
                and, where applicable, the
                <strong>Consumer Rights Act 2015</strong>.
            </p>
        </section>

        <section>
            <h2>2. One-time digital purchases</h2>

            <h3>Your 14-day right to cancel</h3>
            <p>
                When you purchase a one-time digital product (for example, a single digital edition of a
                publication), you have a <strong>14-day right to cancel</strong> from the date the contract
                is concluded (i.e. the date your order is confirmed), without giving any reason.
            </p>

            <h3>Exception: immediate access to digital content</h3>
            <p>
                Under Regulation 37 of the Consumer Contracts Regulations 2013, your right to cancel may be
                lost if, before the 14-day period expires, you:
            </p>
            <ol>
                <li>explicitly consent to the supply of the digital content beginning immediately; and</li>
                <li>acknowledge that your right to cancel will be lost once the content is supplied.</li>
            </ol>
            <p>
                Where we offer you the option to access or download digital content immediately at checkout,
                we will ask you to confirm both of these points. If you provide that confirmation, your
                right to cancel is extinguished once access or download begins.
            </p>
            <p>
                If you do <strong>not</strong> request immediate access at checkout, the 14-day cancellation
                window applies in full and you may cancel before the content is supplied.
            </p>
        </section>

        <section>
            <h2>3. Recurring digital subscriptions</h2>

            <h3>14-day right to cancel on sign-up</h3>
            <p>
                When you take out a recurring digital subscription, you have a <strong>14-day right to cancel</strong>
                from the date your subscription contract is concluded (i.e. the date you sign up), without giving
                any reason.
            </p>
            <p>
                As with one-time purchases, if you explicitly consent to your subscription beginning immediately
                and acknowledge the loss of your cancellation right, access to content before the 14-day period
                ends will extinguish the right to cancel under the Consumer Contracts Regulations 2013.
            </p>

            <h3>Cancelling after the 14-day period</h3>
            <p>
                You may cancel your recurring subscription at any time after the 14-day cooling-off period.
                Your access will continue until the end of your current billing period; we do not offer
                pro-rata refunds for part-periods unless required by law.
            </p>
            <p>
                To cancel, log in to your account and navigate to <strong>Account &gt; Subscriptions</strong>,
                or contact us at
                <a href="mailto:{{ config('legal.contact_email', 'support@' . parse_url(config('app.url'), PHP_URL_HOST)) }}">{{
                    config('legal.contact_email') }}</a>.
            </p>

            <h3>Price changes</h3>
            <p>
                We will give you at least <strong>30 days' written notice</strong> before any price increase
                takes effect on your subscription. You have the right to cancel at any time before the new
                price takes effect and will not be charged the increased rate. See our
                <a href="{{ url('/legal/cancellation-rights#price-changes') }}">price change section below</a>
                for full details.
            </p>
        </section>

        <section id="price-changes">
            <h2>4. Price changes to recurring subscriptions</h2>
            <p>
                If we increase the price of a recurring subscription:
            </p>
            <ul>
                <li>We will notify you by email at least <strong>30 days</strong> before the change takes effect;</li>
                <li>The notice will clearly state your current price, the new price, and the date the change applies;
                </li>
                <li>You may cancel your subscription at any time before the effective date and will not be charged the
                    new rate;
                </li>
                <li>If you do not cancel, your continued use of the subscription after the effective date constitutes
                    acceptance of the new price.
                </li>
            </ul>
            <p>
                We will never increase your subscription price without notice. Automated renewals before the
                effective date will continue at your existing price.
            </p>
        </section>

        <section>
            <h2>5. Physical goods</h2>
            <p>
                If your subscription or order includes physical goods (for example, a print edition),
                your rights in relation to those goods are set out in our separate
                <a href="{{ url('/returns-policy') }}">Returns Policy</a>.
            </p>
        </section>

        <section>
            <h2>6. How to exercise your right to cancel</h2>
            <p>You can cancel in any of the following ways:</p>
            <ul>
                <li><strong>Online:</strong> Log in and go to <a href="{{ url('/account/subscriptions') }}">Account &gt;
                        Subscriptions</a>.
                </li>
                <li><strong>Email:</strong> Write to us at <a href="mailto:{{ config('legal.contact_email') }}">{{
                        config('legal.contact_email') }}</a>. You may use the model cancellation form below, but you are
                    not required to.
                </li>
            </ul>
            <p>We will acknowledge receipt of your cancellation without undue delay.</p>
        </section>

        <section>
            <h2>7. Refunds on cancellation</h2>
            <p>
                Where you cancel within the 14-day cooling-off period and your right to cancel has not been
                extinguished, we will issue a full refund within <strong>14 days</strong> of receiving your
                cancellation notice, using the same payment method you used to purchase.
            </p>
            <p>
                Where you cancel after the 14-day period, no refund is due for the remaining time in
                your current billing period unless your subscription was cancelled due to a fault on our part.
            </p>
        </section>

        <section>
            <h2>8. Model cancellation form</h2>
            <p>
                You are not required to use this form, but you may if you wish:
            </p>
            <blockquote class="legal-page__model-form">
                <p><strong>To:</strong> {{ config('app.name') }}, {{ config('legal.registered_address', '[REGISTERED
                    ADDRESS — TO BE COMPLETED]') }}<br>
                    <strong>Email:</strong> {{ config('legal.contact_email') }}</p>
                <p>
                    I/We [*] hereby give notice that I/We [*] cancel my/our [*] contract for the supply of
                    the following digital service [*]: <em>[describe the subscription or product]</em>
                </p>
                <p>Ordered on [*]: <em>[date of order]</em><br>
                    Name of consumer(s): <em>[your name]</em><br>
                    Address of consumer(s): <em>[your address]</em><br>
                    Signature (if notified on paper): <em>[signature]</em><br>
                    Date: <em>[date]</em></p>
                <p><small>[*] Delete as appropriate.</small></p>
            </blockquote>
        </section>

        <section>
            <h2>9. Contact and complaints</h2>
            <p>
                If you have a question or complaint about your cancellation rights, contact us at:
            </p>
            <p>
                <strong>{{ config('app.name') }}</strong><br>
                {{ config('legal.registered_address', '[REGISTERED ADDRESS — TO BE COMPLETED]') }}<br>
                Email: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>
            </p>
            <p>
                If we are unable to resolve your complaint, you may refer it to an approved Alternative
                Dispute Resolution (ADR) scheme or, for disputes under £10,000, to the
                <a href="https://www.gov.uk/small-claims-court" rel="noopener noreferrer" target="_blank">small claims
                    court</a>.
            </p>
        </section>

    </div>
</article>
@endsection