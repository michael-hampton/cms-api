@extends('legal.layouts.legal')

@section('title', 'Reviews Policy')
@section('meta_description', 'How we collect, moderate and display customer reviews — in compliance with the Digital Markets, Competition and Consumers Act 2024.')

@section('content')
<article class="legal-page">

    <header class="legal-page__header">
        <p class="legal-page__section">Legal</p>
        <h1 class="legal-page__title">Reviews Policy</h1>
        <p class="legal-page__meta">Last updated: {{ config('legal.reviews_policy_updated', 'January 2025') }}</p>
    </header>

    <div class="legal-page__body">

        <section>
            <h2>1. Our commitment to genuine reviews</h2>
            <p>
                We publish customer reviews to help people make informed purchasing decisions. We are committed to
                ensuring that every review we publish is genuine, was written by a real customer who has experienced
                the product or service being reviewed, and has not been manipulated or fabricated.
            </p>
            <p>
                These commitments are required by the
                <strong>Digital Markets, Competition and Consumers Act 2024 (DMCC Act)</strong>,
                which prohibits businesses from publishing fake reviews, commissioning fake reviews, or
                misrepresenting consumer reviews in any way.
            </p>
        </section>

        <section>
            <h2>2. Who can leave a review</h2>
            <p>
                Reviews may only be submitted by customers who have purchased the product or service in question
                through our platform. We verify purchase history before a review is accepted for moderation.
            </p>
            <p>
                We do not accept reviews from:
            </p>
            <ul>
                <li>Individuals who have not purchased or directly experienced the product or service;</li>
                <li>Employees, directors, agents, or close associates of {{ config('app.name') }} or the relevant
                    merchant;
                </li>
                <li>Individuals who have been incentivised (paid, gifted, or otherwise rewarded) to leave a review,
                    unless this is clearly disclosed and the incentive is not conditional on a positive outcome;
                </li>
                <li>Automated systems, bots, or AI-generated content.</li>
            </ul>
        </section>

        <section>
            <h2>3. How reviews are moderated</h2>
            <p>
                All reviews are submitted for <strong>pre-publication moderation</strong>. This means a review
                is held in a pending state and is not visible to other users until it has been reviewed and
                approved by a member of our team.
            </p>
            <p>During moderation, we check that the review:</p>
            <ul>
                <li>Was submitted by a verified purchaser of the product or service;</li>
                <li>Does not contain unlawful content (including defamatory, discriminatory, or harassing content);</li>
                <li>Does not contain personal data belonging to third parties;</li>
                <li>Is relevant to the product or service being reviewed;</li>
                <li>Has not been previously published elsewhere in identical or substantially similar form by the same
                    reviewer.
                </li>
            </ul>
            <p>
                We do <strong>not</strong> reject reviews solely on the basis that they are negative or
                unflattering. Genuine negative feedback is as valuable to consumers as positive feedback.
                Selectively suppressing negative reviews would violate the DMCC Act and our own editorial
                standards.
            </p>
        </section>

        <section>
            <h2>4. What we will not publish</h2>
            <p>We will decline to publish a review if it:</p>
            <ul>
                <li>Cannot be attributed to a verified purchase on our platform;</li>
                <li>Contains content that is unlawful, abusive, threatening, discriminatory, or in breach of any
                    applicable law;
                </li>
                <li>Contains personal data about a third party (for example, a full name, address, or contact
                    details);
                </li>
                <li>Is clearly spam, promotional in nature, or unrelated to the product;</li>
                <li>Has been flagged as fabricated or submitted by someone acting in bad faith.</li>
            </ul>
            <p>
                Where we decline to publish a review, we will notify the submitter where it is reasonably
                practicable to do so and explain the reason.
            </p>
        </section>

        <section>
            <h2>5. Ratings and aggregated scores</h2>
            <p>
                Aggregate star ratings shown on product or service pages are calculated solely from
                approved published reviews. We do not import, purchase, or fabricate ratings from
                any external source.
            </p>
            <p>
                Where a product has fewer than five approved reviews, we may display individual reviews
                but withhold an aggregate score to avoid a statistically misleading figure.
            </p>
        </section>

        <section>
            <h2>6. Third-party review platforms</h2>
            <p>
                Where we display ratings or reviews sourced from a third-party platform (for example, Trustpilot),
                we will clearly identify the source and link to the original platform so that consumers can
                verify the reviews independently.
            </p>
            <p>
                We do not cherry-pick positive reviews from third-party platforms for display on our site
                while suppressing negative ones.
            </p>
        </section>

        <section>
            <h2>7. Reporting a suspected fake review</h2>
            <p>
                If you believe a review on our platform is fake, fraudulent, or otherwise in breach of this
                policy, please report it to us at
                <a href="mailto:{{ config('legal.contact_email', 'legal@' . parse_url(config('app.url'), PHP_URL_HOST)) }}">{{
                    config('legal.contact_email', 'legal@' . parse_url(config('app.url'), PHP_URL_HOST)) }}</a>.
                We will investigate all credible reports and take appropriate action, which may include
                removing the review and investigating the reviewer's account.
            </p>
        </section>

        <section>
            <h2>8. Review of this policy</h2>
            <p>
                We review this policy periodically and when required by changes in law or regulation.
                The DMCC Act fake reviews provisions are in force from April 2025.
            </p>
        </section>

        <section>
            <h2>9. Contact</h2>
            <p>
                Questions about this policy should be directed to:
                <a href="mailto:{{ config('legal.contact_email', 'legal@' . parse_url(config('app.url'), PHP_URL_HOST)) }}">{{
                    config('legal.contact_email') }}</a>
            </p>
            <p>
                Our company details, including registered address and company number, are set out in our
                <a href="{{ url('/legal/company-details') }}">legal notices page</a>.
            </p>
        </section>

    </div>
</article>
@endsection