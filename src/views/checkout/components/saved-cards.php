<?php
/**
 * Saved payment methods (cards) list.
 * Rendered hidden initially; JS populates and reveals it when cards are found.
 *
 * @var string|null $useDifferentCardOnClick JS onclick for "Use Different Card" button.
 */
$useDifferentCardOnClick = $useDifferentCardOnClick ?? 'showNewCardForm()';
?>
<style>
    .saved-card {
        border: 2px solid var(--border-color);
        border-radius: 0.5rem;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .saved-card:hover {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }

    .saved-card.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
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
        font-size: 0.875rem;
    }
</style>

<div class="form-section" id="<?= htmlspecialchars($sectionId ?? 'saved-cards-section') ?>"
     style="display: none;">
    <h2 class="section-title">Saved Payment Methods</h2>
    <div id="saved-cards-list"></div>
    <button type="button"
            onclick="<?= htmlspecialchars($useDifferentCardOnClick) ?>"
            class="btn btn-secondary">
        Use Different Card
    </button>
</div>