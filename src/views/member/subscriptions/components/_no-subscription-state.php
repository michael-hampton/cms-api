<div class="subscription-status">
    <div class="status-icon inactive">✗</div>
    <div>
        <div style="font-weight: 700; font-size: 20px; color: #1e293b;">
            No Active Subscription
        </div>
        <div style="color: #64748b; font-size: 15px; font-weight: 500;">Choose a plan to get started
        </div>
    </div>
</div>

<?php if (isset($plans) && $plans->count() > 0): ?>
    <div style="margin-top: 24px;">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #1e293b;">Available
            Plans</h3>

        <div style="display: grid; gap: 16px;">
            <?php foreach ($plans as $plan): ?>
                <div data-plan-id="<?= $plan->id ?>"
                     style="padding: 20px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 12px; border: 2px solid #e2e8f0; transition: all 0.3s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                        <div>
                            <div style="font-weight: 700; font-size: 18px; color: #1e293b; margin-bottom: 4px;">
                                <?= htmlspecialchars($plan->name) ?>
                            </div>
                            <?php if ($plan->description): ?>
                                <div style="font-size: 14px; color: #64748b;">
                                    <?= htmlspecialchars($plan->description) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 800; font-size: 24px; color: #667eea;">
                                <?= htmlspecialchars($plan->currency) ?><?= number_format($plan->price, 2) ?>
                            </div>
                            <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                                per <?= htmlspecialchars($plan->getBillingPeriodLabel()) ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($plan->features && is_array($plan->features) && count($plan->features) > 0): ?>
                        <ul style="list-style: none; margin: 16px 0; padding: 0;">
                            <?php foreach (array_slice($plan->features, 0, 3) as $feature): ?>
                                <li style="padding: 6px 0; color: #334155; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                    <span style="color: #10b981; font-weight: bold;">✓</span>
                                    <?= htmlspecialchars($feature) ?>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($plan->features) > 3): ?>
                                <li style="padding: 6px 0; color: #64748b; font-size: 13px; font-style: italic;">
                                    And <?= count($plan->features) - 3 ?> more features...
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>

                    <button class="btn btn-primary" style="width: 100%; margin-top: 12px;"
                            onclick="quickSubscribe('<?= htmlspecialchars($plan->slug) ?>', this)">
                        Subscribe to <?= htmlspecialchars($plan->name) ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans"
               style="color: #667eea; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-flex; align-items: center; gap: 6px;">
                View All Plans & Compare Features
                <span style="font-size: 18px;">→</span>
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <h3>No Plans Available</h3>
        <p>Please check back later for subscription options</p>
    </div>
<?php endif; ?>