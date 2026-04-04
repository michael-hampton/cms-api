<style>
    /* Security Badge */
    .security-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        background: var(--bg-light);
        border-radius: 0.5rem;
        margin-top: 1rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
</style>

<div class="security-badge">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
    </svg>
    <span><?= htmlspecialchars($label ?? 'Secure SSL encrypted checkout') ?></span>
</div>