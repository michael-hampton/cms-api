<?php if (!empty($item['trial_days'])): ?>
    <div style="
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        background: #f0fdf4;
        border: 1px solid #6ee7b7;
        border-radius: 100px;
        padding: .2rem .75rem;
        font-size: .75rem;
        font-weight: 600;
        color: #065f46;
        margin-top: .4rem;
        line-height: 1.6;
    ">
        <span aria-hidden="true">🎁</span>
        <?= (int)$item['trial_days'] ?>-day free trial included
    </div>
<?php endif; ?>