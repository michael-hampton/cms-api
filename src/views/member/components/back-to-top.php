<style>
    .back-to-top {
        padding: 0.875rem 2.5rem;
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
        border-radius: 2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .back-to-top:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-2px);
    }
</style>

<!-- Back to Top Button -->
<div style="text-align: center; margin: 3rem 0;">
    <button class="back-to-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">Back to Top</button>
</div>