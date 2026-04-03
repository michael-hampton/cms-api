<?php
/**
 * Stripe card element component.
 *
 * @var string|null $backBtnId Id for the "back to saved cards" button. Null hides it.
 * @var string|null $backBtnOnClick JS onclick for the back button.
 */
$backBtnId = $backBtnId ?? null;
$backBtnOnClick = $backBtnOnClick ?? 'showSavedCards()';
?>
<style>
    #card-errors:not(:empty) {
        margin-top: 0.5rem;
        padding: 0.75rem 1rem;
        background: #fee2e2;
        border: 1px solid #ef4444;
        border-radius: 0.375rem;
        color: #991b1b;
        font-size: 0.875rem;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .section-header .section-title {
        margin-bottom: 0;
        padding-bottom: 0;
        border: none;
    }
</style>

<div class="form-section" id="<?= htmlspecialchars($sectionId ?? 'new-card-section') ?>">
    <div class="section-header">
        <h2 class="section-title"><?= htmlspecialchars($sectionTitle ?? 'Card Details') ?></h2>
        <?php if ($backBtnId): ?>
            <button type="button"
                    id="<?= htmlspecialchars($backBtnId) ?>"
                    onclick="<?= htmlspecialchars($backBtnOnClick) ?>"
                    class="btn btn-secondary"
                    style="display: none; width: auto; padding: 0.5rem 1rem;">
                ← Back to Saved Cards
            </button>
        <?php endif; ?>
    </div>
    <div class="form-group full-width">
        <label class="form-label">
            Card Information <span class="required">*</span>
        </label>
        <div id="card-element"
             style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;">
        </div>
        <div id="card-errors" class="form-error" role="alert" aria-live="polite"></div>
    </div>
</div>