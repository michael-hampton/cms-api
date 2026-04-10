<div class="paywall-overlay"
     style="text-align: center; background: #fff; border: 2px solid #000; padding: 3rem; margin-top: -2rem; position: relative; z-index: 10;">
    <h3>Unlock the Full Story</h3>
    <p>Support the author and get instant access to this article.</p>
    <div style="font-size: 1.5rem; font-weight: bold; margin: 1.5rem 0;">
        Price: £<?= number_format($article->price, 2) ?>
    </div>

    <button class="unlock-btn btn-primary" data-article-id="<?= $article->id ?>"
            style="padding: 1rem 2rem; font-size: 1.1rem;">
        Pay Now via Stripe
    </button>
</div>

<script>
    // Logic as per Frontend Ticket 6
    document.querySelector('.unlock-btn')?.addEventListener('click', async (e) => {
        const btn = e.target;
        const articleId = btn.dataset.articleId;

        try {
            const res = await fetch(`/api/site/open-collab/pages/${articleId}/purchase`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: 'user@example.com'}) // Collect via small modal or auth context
            });
            const data = await res.json();
            if (data.client_secret) {
                // Forward to Stripe Checkout or handle PaymentIntent
                window.location.href = data.checkout_url;
            }
        } catch (err) {
            alert('Payment failed to initiate.');
        }
    });
</script>