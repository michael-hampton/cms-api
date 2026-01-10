<style>
    .activities-section {
        background: white;
        border-radius: 1rem;
        padding: 2rem;
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
    }

    .activity-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
        margin-top: 1.5rem;
    }

    .activity-stat {
        text-align: center;
        padding: 1.5rem;
        background: var(--bg-light);
        border-radius: 0.75rem;
    }

    .activity-icon {
        font-size: 2rem;
        margin-bottom: 0.75rem;
    }

    .activity-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        display: block;
        margin-bottom: 0.25rem;
    }

    .activity-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

</style>

<div class="activities-section">
    <h2 class="section-title">Activities</h2>
    <p style="color: var(--text-secondary); margin-bottom: 1rem;">Summary of your recent activities</p>

    <div class="activity-stats">
        <div class="activity-stat">
            <div class="activity-icon">📰</div>
            <span class="activity-number">12</span>
            <span class="activity-label">This Month's Reading</span>
        </div>

        <div class="activity-stat">
            <div class="activity-icon">👍</div>
            <span class="activity-number">20.7K</span>
            <span class="activity-label">Likes Given</span>
        </div>

        <div class="activity-stat">
            <div class="activity-icon">💬</div>
            <span class="activity-number">33</span>
            <span class="activity-label">Comments Posted</span>
        </div>
    </div>
</div>