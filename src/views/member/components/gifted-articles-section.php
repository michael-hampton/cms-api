<?php
/**
 * @var array $giftedArticles
 */

if (empty($giftedArticles) || (empty($giftedArticles['received']) && empty($giftedArticles['given']))) {
    return;
}
?>

<style>
    .gifted-section {
        background: white;
        border-radius: 1rem;
        padding: 2rem;
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
    }

    .gifted-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid var(--border-color);
    }

    .gifted-tab {
        padding: 1rem;
        background: none;
        border: none;
        font-weight: 600;
        cursor: pointer;
        color: var(--text-secondary);
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s ease;
    }

    .gifted-tab.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }

    .gifted-tab-content {
        display: none;
    }

    .gifted-tab-content.active {
        display: block;
    }

    .gift-card {
        padding: 1.25rem;
        background: var(--bg-light);
        border-radius: 0.75rem;
        margin-bottom: 1rem;
        transition: all 0.2s ease;
    }

    .gift-card:hover {
        background: #e5e7eb;
        transform: translateX(4px);
    }

    .gift-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
    }

    .gift-title {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9375rem;
        flex: 1;
    }

    .gift-status {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .gift-status.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .gift-status.claimed {
        background: #d1fae5;
        color: #065f46;
    }

    .gift-status.expired {
        background: #fee2e2;
        color: #991b1b;
    }

    .gift-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-bottom: 0.75rem;
    }

    .gift-message {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-style: italic;
        margin-bottom: 0.75rem;
        padding-left: 1rem;
        border-left: 3px solid var(--border-color);
    }

    .gift-actions {
        display: flex;
        gap: 0.75rem;
    }

    .gift-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .gift-btn-primary {
        background: var(--primary-color);
        color: white;
    }

    .gift-btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .gift-btn-secondary {
        background: white;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
    }

    .gift-btn-secondary:hover {
        background: var(--primary-color);
        color: white;
    }

    .empty-gifts {
        text-align: center;
        padding: 2rem;
        color: var(--text-secondary);
    }

    .view-all-link {
        text-align: center;
        margin-top: 1rem;
    }

    .view-all-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
    }

    .view-all-link a:hover {
        text-decoration: underline;
    }
</style>

<div class="gifted-section">
    <h2 class="section-title">🎁 Gifted Articles</h2>

    <div class="gifted-tabs">
        <button class="gifted-tab active" onclick="switchGiftTab('received')">
            Received (<?= $giftedArticles['received_count'] ?>)
        </button>
        <button class="gifted-tab" onclick="switchGiftTab('given')">
            Given (<?= $giftedArticles['given_count'] ?>)
        </button>
    </div>

    <div id="receivedGifts" class="gifted-tab-content active">
        <?php if ($giftedArticles['received']->count() > 0): ?>
            <?php foreach ($giftedArticles['received'] as $gift): ?>
                <div class="gift-card">
                    <div class="gift-header">
                        <div class="gift-title"><?= htmlspecialchars($gift->page->title) ?></div>
                        <span class="gift-status <?= strtolower($gift->status) ?>">
                            <?= htmlspecialchars($gift->status) ?>
                        </span>
                    </div>

                    <div class="gift-meta">
                        <span>👤 From: <?= htmlspecialchars($gift->giftedBy->full_name ?? $gift->giftedBy->email) ?></span>
                        <span>📅 <?= $gift->gifted_at->format('M j, Y') ?></span>
                    </div>

                    <?php if ($gift->personal_message): ?>
                        <div class="gift-message">
                            "<?= htmlspecialchars($gift->personal_message) ?>"
                        </div>
                    <?php endif; ?>

                    <div class="gift-actions">
                        <?php if ($gift->status === 'pending'): ?>
                            <a href="/gift/<?= htmlspecialchars($gift->gift_token) ?>"
                               class="gift-btn gift-btn-primary">
                                Claim & Read
                            </a>
                        <?php else: ?>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/<?= htmlspecialchars($gift->page->slug) ?>"
                               class="gift-btn gift-btn-primary">
                                Read Article
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($giftedArticles['received_count'] > 5): ?>
                <div class="view-all-link">
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/gifted-articles">
                        View all <?= $giftedArticles['received_count'] ?> received gifts →
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-gifts">
                <p>You haven't received any gifted articles yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="givenGifts" class="gifted-tab-content">
        <?php if ($giftedArticles['given']->count() > 0): ?>
            <?php foreach ($giftedArticles['given'] as $gift): ?>
                <div class="gift-card">
                    <div class="gift-header">
                        <div class="gift-title"><?= htmlspecialchars($gift->page->title) ?></div>
                        <span class="gift-status <?= strtolower($gift->status) ?>">
                            <?= htmlspecialchars($gift->status) ?>
                        </span>
                    </div>

                    <div class="gift-meta">
                        <span>📧 To: <?= htmlspecialchars($gift->recipient_email) ?></span>
                        <span>📅 <?= $gift->gifted_at->format('M j, Y') ?></span>
                    </div>

                    <?php if ($gift->status === 'claimed' && $gift->claimed_at): ?>
                        <div style="font-size: 0.875rem; color: var(--success-color); margin-bottom: 0.75rem;">
                            ✓ Claimed on <?= $gift->claimed_at->format('M j, Y') ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($giftedArticles['given_count'] > 5): ?>
                <div class="view-all-link">
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/gifted-articles">
                        View all <?= $giftedArticles['given_count'] ?> given gifts →
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-gifts">
                <p>You haven't gifted any articles yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function switchGiftTab(tab) {
        document.querySelectorAll('.gifted-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.gifted-tab-content').forEach(c => c.classList.remove('active'));

        event.target.classList.add('active');
        document.getElementById(tab + 'Gifts').classList.add('active');
    }
</script>