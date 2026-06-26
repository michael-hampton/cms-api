<?php
$page_title = 'FAQs';
$faqs = $faqs ?? [];
?>

@include('subscriptions/account/_layout')

<main class="page-content">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Subscription help</div>
        <h1 class="page-heading__title">FAQs</h1>
        <p class="page-heading__sub">Subscription-specific help for managing plans, payments, renewals and account details.</p>
    </div>

    <section class="card" aria-labelledby="subscription-faqs-title">
        <div class="card__header">
            <span class="card__title" id="subscription-faqs-title">Subscription FAQs</span>
        </div>
        <div class="card__body">
            <div class="faq-list faq-list--account">
                <?php foreach ($faqs as $faq): ?>
                    <details class="faq-item">
                        <summary><?= htmlspecialchars($faq['question']) ?></summary>
                        <p><?= htmlspecialchars($faq['answer']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
</div>
</body>
</html>
