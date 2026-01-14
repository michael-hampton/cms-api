<?php if ($activeSubscription && $activeSubscription->isActive()): ?>
    <?php
    $availableUpgrades = $activeSubscription->getAvailableUpgrades();
    if (!empty($availableUpgrades)):
        ?>
        <div style="background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 24px;
            border-radius: 16px;
            margin: 24px 0;">
            <h3 style="font-size: 22px; font-weight: 700; margin-bottom: 16px;">
                🎁 Available Upgrades
            </h3>

            <?php foreach (array_slice($availableUpgrades, 0, 3) as $upgrade): ?>
                <?php $plan = $upgrade['plan']; ?>
                <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 12px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <div style="font-weight: 700; font-size: 18px; margin-bottom: 4px;">
                                <?= htmlspecialchars($plan->name) ?>
                            </div>
                            <div style="font-size: 14px; opacity: 0.9;">
                                Unlock:
                                <?php
                                $accessNames = array_map(function ($a) {
                                    return ucwords(str_replace('-', ' ', $a['identifier']));
                                }, $upgrade['new_access']);
                                echo htmlspecialchars(implode(', ', $accessNames));
                                ?>
                            </div>
                        </div>
                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/<?= $activeSubscription->id ?>/upgrade?plan_id=<?= $plan->id ?>"
                           class="btn btn-primary"
                           style="background: white; color: #667eea;">
                            Upgrade
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>