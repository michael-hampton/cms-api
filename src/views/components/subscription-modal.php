<?php
// views/components/subscription-modal.php
/**
 * @var array $subscriptionModalData
 */

if (!isset($subscriptionModalData) || !$subscriptionModalData['show_modal']) {
    return;
}

$plans = $subscriptionModalData['plans'];
$member = $subscriptionModalData['member'];
?>

<div id="subscriptionModal" class="subscription-modal">
    <div class="subscription-modal-overlay"></div>
    <div class="subscription-modal-content">
        <button class="subscription-modal-close" onclick="closeSubscriptionModal()">&times;</button>

        <div class="subscription-modal-header">
            <h2>🚀 Unlock Premium Access</h2>
            <p>Join our community and get instant access to exclusive content</p>
        </div>

        <div class="subscription-modal-plans">
            <?php foreach ($plans as $plan): ?>
                <div class="subscription-modal-plan <?= $plan->is_featured ? 'featured' : '' ?>">
                    <?php if ($plan->is_featured): ?>
                        <div class="plan-badge">⭐ Best Value</div>
                    <?php endif; ?>

                    <div class="plan-name"><?= htmlspecialchars($plan->name) ?></div>

                    <?php if ($plan->description): ?>
                        <div class="plan-description"><?= htmlspecialchars($plan->description) ?></div>
                    <?php endif; ?>

                    <div class="plan-price">
                        <span class="currency"><?= htmlspecialchars($plan->currency) ?></span>
                        <span class="amount"><?= number_format($plan->price, 2) ?></span>
                        <span class="period">/ <?= htmlspecialchars($plan->getBillingPeriodLabel()) ?></span>
                    </div>

                    <?php if ($plan->trial_days > 0): ?>
                        <div class="plan-trial">
                            🎉 <?= $plan->trial_days ?> Day Free Trial
                        </div>
                    <?php endif; ?>

                    <?php if ($plan->features && count($plan->features) > 0): ?>
                        <ul class="plan-features">
                            <?php foreach (array_slice($plan->features, 0, 4) as $feature): ?>
                                <li>✓ <?= htmlspecialchars($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <button
                            class="plan-button <?= $plan->is_featured ? 'featured' : '' ?>"
                            onclick="subscribeToModalPlan('<?= htmlspecialchars($plan->slug) ?>', this)">
                        Subscribe Now
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="subscription-modal-footer">
            <button onclick="closeSubscriptionModal()" class="modal-dismiss-button">
                Maybe Later
            </button>
            <p class="trust-badge">🔒 Secure payment • Cancel anytime</p>
        </div>
    </div>
</div>


<style>
    .subscription-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        animation: fadeIn 0.3s ease-in-out;
    }

    .subscription-modal.show {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .subscription-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(8px);
    }

    .subscription-modal-content {
        position: relative;
        max-width: 1100px;
        max-height: 90vh;
        margin: 5vh auto;
        background: linear-gradient(to bottom, #ffffff, #f8fafc);
        border-radius: 24px;
        padding: 48px 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow-y: auto;
        animation: slideUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .subscription-modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        background: #f1f5f9;
        border: none;
        font-size: 28px;
        color: #64748b;
        cursor: pointer;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
        font-weight: 300;
    }

    .subscription-modal-close:hover {
        background: #e2e8f0;
        color: #1e293b;
        transform: rotate(90deg);
    }

    .subscription-modal-header {
        text-align: center;
        margin-bottom: 48px;
    }

    .subscription-modal-header h2 {
        font-size: 42px;
        font-weight: 800;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 12px;
        letter-spacing: -0.5px;
    }

    .subscription-modal-header p {
        font-size: 18px;
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
        font-weight: 400;
    }

    .subscription-modal-plans {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .subscription-modal-plan {
        background: white;
        border-radius: 20px;
        padding: 36px 28px;
        position: relative;
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 2px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .subscription-modal-plan:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #667eea;
    }

    .subscription-modal-plan.featured {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
        transform: scale(1.05);
        box-shadow: 0 20px 25px -5px rgba(102, 126, 234, 0.3);
    }

    .subscription-modal-plan.featured:hover {
        transform: translateY(-8px) scale(1.08);
        box-shadow: 0 25px 30px -5px rgba(102, 126, 234, 0.4);
    }

    .plan-badge {
        position: absolute;
        top: -14px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: white;
        padding: 6px 18px;
        border-radius: 24px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.5);
    }

    .plan-name {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 12px;
        color: inherit;
        letter-spacing: -0.5px;
    }

    .plan-description {
        font-size: 15px;
        color: #64748b;
        margin-bottom: 24px;
        min-height: 44px;
        line-height: 1.5;
    }

    .subscription-modal-plan.featured .plan-description {
        color: rgba(255, 255, 255, 0.9);
    }

    .plan-price {
        margin-bottom: 24px;
        padding-bottom: 24px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 4px;
    }

    .subscription-modal-plan.featured .plan-price {
        border-bottom-color: rgba(255, 255, 255, 0.2);
    }

    .plan-price .currency {
        font-size: 20px;
        font-weight: 700;
        opacity: 0.7;
    }

    .plan-price .amount {
        font-size: 52px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -2px;
    }

    .plan-price .period {
        font-size: 16px;
        color: #64748b;
        font-weight: 500;
    }

    .subscription-modal-plan.featured .plan-price .period {
        color: rgba(255, 255, 255, 0.8);
    }

    .plan-trial {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        padding: 12px 16px;
        border-radius: 12px;
        text-align: center;
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(251, 191, 36, 0.2);
    }

    .subscription-modal-plan.featured .plan-trial {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        backdrop-filter: blur(10px);
    }

    .plan-features {
        list-style: none;
        padding: 0;
        margin: 0 0 28px 0;
    }

    .plan-features li {
        padding: 12px 0;
        font-size: 15px;
        color: #334155;
        font-weight: 500;
        line-height: 1.5;
    }

    .subscription-modal-plan.featured .plan-features li {
        color: rgba(255, 255, 255, 0.95);
    }

    .plan-button {
        display: block;
        width: 100%;
        padding: 16px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-align: center;
        text-decoration: none;
        border: none;
        border-radius: 12px;
        font-size: 17px;
        font-weight: 700;
        transition: all 0.3s ease;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        letter-spacing: 0.3px;
    }

    .plan-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .plan-button:active {
        transform: translateY(0);
    }

    .plan-button.featured {
        background: white;
        color: #667eea;
    }

    .plan-button.featured:hover {
        background: #f8fafc;
        box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
    }

    .plan-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    .subscription-modal-footer {
        text-align: center;
        padding-top: 24px;
        border-top: 2px solid #f1f5f9;
    }

    .modal-dismiss-button {
        background: transparent;
        border: none;
        color: #64748b;
        font-size: 16px;
        cursor: pointer;
        padding: 12px 28px;
        transition: all 0.3s ease;
        border-radius: 10px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .modal-dismiss-button:hover {
        background: #f1f5f9;
        color: #334155;
    }

    .trust-badge {
        font-size: 14px;
        color: #94a3b8;
        margin: 0;
        font-weight: 500;
    }

    /* Loading state */
    .plan-button.loading {
        position: relative;
        color: transparent;
    }

    .plan-button.loading::after {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin-left: -10px;
        margin-top: -10px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .subscription-modal-content {
            margin: 20px;
            padding: 32px 24px;
            max-height: calc(100vh - 40px);
            border-radius: 20px;
        }

        .subscription-modal-header h2 {
            font-size: 32px;
        }

        .subscription-modal-header p {
            font-size: 16px;
        }

        .subscription-modal-plans {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .subscription-modal-plan.featured {
            transform: scale(1);
        }

        .plan-price .amount {
            font-size: 44px;
        }
    }

    @media (max-width: 480px) {
        .subscription-modal-close {
            top: 12px;
            right: 12px;
            font-size: 24px;
            width: 40px;
            height: 40px;
        }

        .subscription-modal-header h2 {
            font-size: 28px;
        }

        .subscription-modal-content {
            padding: 28px 20px;
        }
    }
</style>

<script>
    let subscriptionModalShown = false;

    function showSubscriptionModal() {
        const modal = document.getElementById('subscriptionModal');
        if (modal && !subscriptionModalShown) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            subscriptionModalShown = true;

            // Mark as shown in backend
            fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/api/subscription-modal/mark-shown', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).catch(err => console.error('Failed to mark modal as shown:', err));
        }
    }

    function closeSubscriptionModal() {
        const modal = document.getElementById('subscriptionModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    function subscribeToModalPlan(slug, button) {
        window.location.href = '/<?= \App\Framework\Support\SiteContext::slug() ?>/checkout?plan_slug=' + slug;
        //// Disable button and show loading state
        //button.disabled = true;
        //button.classList.add('loading');
        //const originalText = button.textContent;
        //
        //fetch('/<?php //= \App\Framework\Support\SiteContext::slug() ?>///member/subscription-plans/' + slug + '/subscribe', {
        //    method: 'POST',
        //    headers: {
        //        'Content-Type': 'application/json',
        //        'X-Requested-With': 'XMLHttpRequest'
        //    }
        //})
        //    .then(response => response.json())
        //    .then(data => {
        //        if (data.success) {
        //            button.textContent = '✓ Subscribed!';
        //            button.classList.remove('loading');
        //            button.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        //
        //            setTimeout(() => {
        //                closeSubscriptionModal();
        //                // Optionally reload or redirect
        //                //window.location.href = '/<?php //= \App\Framework\Support\SiteContext::slug() ?>///member/subscriptions';
        //            }, 1500);
        //        } else {
        //            alert(data.message || 'Failed to subscribe. Please try again.');
        //            button.disabled = false;
        //            button.classList.remove('loading');
        //            button.textContent = originalText;
        //        }
        //    })
        //    .catch(error => {
        //        console.error('Subscription error:', error);
        //        alert('An error occurred. Please try again.');
        //        button.disabled = false;
        //        button.classList.remove('loading');
        //        button.textContent = originalText;
        //    });
    }

    // Close modal when clicking overlay
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('subscriptionModal');
        if (modal) {
            const overlay = modal.querySelector('.subscription-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', closeSubscriptionModal);
            }

            // Show modal after 3 seconds delay
            <?php if ($subscriptionModalData['show_modal'] ?? false): ?>
            setTimeout(showSubscriptionModal, 3000);
            <?php endif; ?>
        }
    });

    // Close on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSubscriptionModal();
        }
    });
</script>