<div class="card">
    <h2>
        <span class="icon">📋</span>
        Current Plan
    </h2>

    <?php if ($activeSubscription): ?>
        @include('member/subscriptions/components/_warning-banners', [
        'activeSubscription' => $activeSubscription,
        'member' => $member
        ])

        <div class="subscription-status">
            <div class="status-icon active">✓</div>
            <div>
                <div style="font-weight: 700; font-size: 20px; color: #1e293b;">
                    <?= htmlspecialchars($activeSubscription->plan_name) ?>
                </div>
                <div style="color: #64748b; font-size: 15px; font-weight: 500;">Active subscription</div>
            </div>
        </div>

        @include('member/subscriptions/components/_upgrade-section', [
        'activeSubscription' => $activeSubscription
        ])

        @include('member/subscriptions/components/_subscription-info-rows', [
        'activeSubscription' => $activeSubscription
        ])

        <?php if ($activeSubscription->isPrint()): ?>
            @include('member/subscriptions/components/_delivery-management', [
            'activeSubscription' => $activeSubscription,
            'member' => $member
            ])
        <?php endif; ?>

        <?php if ($activeSubscription->isDigital() && $activeSubscription->hasValidDownload()): ?>
            @include('member/subscriptions/components/_digital-access', [
            'activeSubscription' => $activeSubscription
            ])
        <?php endif; ?>

        @include('member/subscriptions/components/_action-buttons', [
        'activeSubscription' => $activeSubscription
        ])

    <?php else: ?>
        @include('member/subscriptions/components/_no-subscription-state', [
        'plans' => $plans
        ])
    <?php endif; ?>
</div>