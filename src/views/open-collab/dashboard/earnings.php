@css('open-collab.css')

<div class="container">
    <header style="margin-bottom: 2rem;">
        <h1>Earnings Overview</h1>
        <p class="text-muted">Track your revenue and performance across all paid articles.</p>
    </header>

    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-label">Lifetime Earnings</span>
            <span class="stat-value">£<?= number_format($earnings['total'] / 100, 2) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Pending Payout</span>
            <span class="stat-value"
                  style="color: #2563eb;">£<?= number_format(($earnings['pending'] ?? $earnings['total']) / 100, 2) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Articles Sold</span>
            <span class="stat-value"><?= count($earnings['breakdown']) ?></span>
        </div>
    </div>

    <div class="dashboard-grid">
        <section class="content-card">
            <div class="card-header">
                <h3 style="margin: 0;">Revenue by Article</h3>
            </div>

            <div class="article-list">
                <?php if (!empty($earnings['breakdown'])): ?>
                    <?php foreach ($earnings['breakdown'] as $item): ?>
                        <div class="table-row">
                            <div style="flex: 1;">
                                <div style="font-weight: 600;"><?= htmlspecialchars($item['title']) ?></div>
                                <div class="text-muted" style="font-size: 0.8rem;">
                                    <?= (int)$item['sales_count'] ?> sales @
                                    £<?= number_format($item['unit_price'] / 100, 2) ?>
                                </div>
                            </div>

                            <div style="text-align: right;">
                                <div style="font-weight: 700; color: #0f172a;">
                                    £<?= number_format($item['total_revenue'] / 100, 2) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">Gross Revenue</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 3rem; text-align: center;" class="text-muted">
                        No sales data available yet.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside>
            <div class="content-card" style="padding: 1.5rem;">
                <h3 style="margin-top: 0; font-size: 1.1rem;">Payout Method</h3>

                <?php if ($payment_details): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase;">
                            Linked Account
                        </div>
                        <div style="font-weight: 500;"><?= htmlspecialchars($payment_details['email'] ?? 'Account Connected') ?></div>
                        <div style="font-size: 0.75rem; color: #22c55e;">● Connected via Stripe</div>
                    </div>
                    <button class="btn btn-block"
                            style="background: #f1f5f9; font-size: 0.875rem; width: 100%; cursor: pointer; border: 1px solid #cbd5e1; padding: 0.5rem;">
                        Update Payout Details
                    </button>
                <?php else: ?>
                    <div style="padding: 1rem; border: 1px dashed #cbd5e1; border-radius: 8px; text-align: center; margin-bottom: 1rem;">
                        <p style="font-size: 0.875rem;" class="text-muted">You haven't set up a payout method yet.</p>
                        <a href="/onboarding" class="text-primary" style="font-weight: 600; text-decoration: none;">Complete
                            Onboarding</a>
                    </div>
                <?php endif; ?>

                <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #f1f5f9;">

                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem;">Information</h4>
                <p class="text-muted" style="font-size: 0.8rem;">Payouts are processed automatically when your balance
                    exceeds £50.00.</p>
            </div>
        </aside>
    </div>
</div>