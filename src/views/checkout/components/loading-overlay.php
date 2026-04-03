<?php
/**
 * Full-screen loading overlay.
 *
 * @var string|null $id DOM id — defaults to 'loading-overlay'
 * @var string|null $message Loading message — defaults to 'Processing...'
 */
$overlayId = $id ?? 'loading-overlay';
$message = $message ?? 'Processing your order...';
?>
<div id="<?= htmlspecialchars($overlayId) ?>" class="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <p><?= htmlspecialchars($message ?? 'Loading...') ?></p>
    </div>
</div>

<style>
    .loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .loading-overlay.show {
        display: flex;
    }

    .loading-content {
        background: white;
        padding: 2rem;
        border-radius: 0.75rem;
        text-align: center;
    }

    .spinner {
        width: 48px;
        height: 48px;
        border: 4px solid var(--border-color);
        border-top-color: var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>