<?php
/**
 * Saved payment methods (cards) list.
 * Rendered hidden initially; JS populates and reveals it when cards are found.
 *
 * @var string|null $sectionId DOM id for the wrapper. Defaults to 'saved-cards-section'.
 * @var string|null $useDifferentCardOnClick JS onclick for "Use Different Card" button.
 */
$sectionId = $sectionId ?? 'saved-cards-section';
$useDifferentCardOnClick = $useDifferentCardOnClick ?? 'showNewCardForm()';
?>
<style>
    .saved-cards-list {
        display: grid;
        gap: .75rem;
    }

    .saved-card {
        border: 1px solid var(--border-color);
        border-radius: .875rem;
        padding: .875rem 1rem;
        cursor: pointer;
        transition: border-color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
        display: flex;
        align-items: center;
        gap: .875rem;
        margin-bottom: 0;
        background: #fff;
        position: relative;
    }

    .saved-card:hover {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, .04);
        box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
        transform: translateY(-1px);
    }

    .saved-card.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, .06);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    }

    .saved-card input[type="radio"] {
        flex-shrink: 0;
        accent-color: var(--primary-color);
    }

    .saved-card-icon {
        width: 52px;
        height: 36px;
        border-radius: .5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .saved-card-icon svg {
        width: 38px;
        height: 24px;
        display: block;
    }

    .saved-card .card-details {
        flex: 1;
        min-width: 0;
    }

    .saved-card .card-brand {
        font-weight: 700;
        text-transform: capitalize;
        color: var(--text-color);
        line-height: 1.2;
    }

    .saved-card .card-number,
    .saved-card .card-expiry {
        color: var(--text-secondary);
        font-size: .875rem;
        line-height: 1.35;
    }

    .saved-card .card-number {
        letter-spacing: .03em;
        margin-top: .15rem;
    }

    .saved-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .375rem .75rem;
        margin-top: .2rem;
    }

    @media (max-width: 480px) {
        .saved-card {
            align-items: flex-start;
            padding: .875rem;
        }

        .saved-card-icon {
            width: 46px;
            height: 32px;
        }

        .saved-card-icon svg {
            width: 34px;
            height: 22px;
        }
    }
</style>

<div class="form-section" id="<?= htmlspecialchars($sectionId) ?>" style="display: none;">
    <h2 class="section-title">Saved Payment Methods</h2>
    <div id="saved-cards-list" class="saved-cards-list"></div>
    <?= $this->partial('checkout/components/form/button', [
            'label' => 'Use Different Card',
            'variant' => 'secondary',
            'type' => 'button',
            'onclick' => $useDifferentCardOnClick,
            'style' => 'width: auto; padding: .5rem 1rem; margin-top: .5rem;',
    ]) ?>
</div>