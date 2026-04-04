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
    .saved-card {
        border: 2px solid var(--border-color);
        border-radius: .5rem;
        padding: 1rem;
        cursor: pointer;
        transition: all .3s;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .saved-card:hover,
    .saved-card.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, .05);
    }

    .saved-card input[type="radio"] {
        flex-shrink: 0;
    }

    .saved-card .card-details {
        flex: 1;
    }

    .saved-card .card-brand {
        font-weight: 600;
        text-transform: capitalize;
    }
    .saved-card .card-number,
    .saved-card .card-expiry {
        color: var(--text-secondary);
        font-size: .875rem;
    }
</style>

<div class="form-section" id="<?= htmlspecialchars($sectionId) ?>" style="display: none;">
    <h2 class="section-title">Saved Payment Methods</h2>
    <div id="saved-cards-list"></div>
    <button type="button"
            onclick="<?= htmlspecialchars($useDifferentCardOnClick) ?>"
            class="btn btn-secondary"
            style="width: auto; padding: .5rem 1rem; margin-top: .5rem;">
        Use Different Card
    </button>
</div>