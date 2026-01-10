<style>
    .benefits-section {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 1.5rem;
        padding: 3rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .benefits-header {
        margin-bottom: 2rem;
    }

    .benefits-header h2 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
    }

    .benefits-header p {
        color: var(--text-secondary);
        font-size: 1.125rem;
        line-height: 1.6;
    }

    .benefits-grid {
        display: grid;
        gap: 1.5rem;
    }

    .benefit-item {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        padding: 1.25rem;
        background: var(--bg-light);
        border-radius: 1rem;
        transition: all 0.3s ease;
    }

    .benefit-item:hover {
        background: white;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transform: translateX(4px);
    }

    .benefit-icon {
        width: 3rem;
        height: 3rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .benefit-content h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.375rem;
    }

    .benefit-content p {
        font-size: 0.9375rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .stats-banner {
        display: flex;
        justify-content: space-around;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        padding: 1.5rem;
        border-radius: 1rem;
        margin-top: 2rem;
    }

    .stat-item {
        text-align: center;
        color: white;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        display: block;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        opacity: 0.9;
    }

    @media (max-width: 480px) {
        body {
            padding: 1rem;
        }

        .login-card,
        .benefits-section {
            padding: 2rem 1.5rem;
        }

        .login-header h1 {
            font-size: 1.5rem;
        }

        .benefits-header h2 {
            font-size: 1.5rem;
        }

        .login-links {
            flex-direction: column;
            align-items: center;
        }

        .stats-banner {
            flex-direction: column;
            gap: 1rem;
        }
    }
</style>

<div class="benefits-section">
    <div class="benefits-header">
        <h2>Join The Club</h2>
        <p>Become part of our community and unlock exclusive features</p>
    </div>

    <div class="benefits-grid">
        <div class="benefit-item">
            <div class="benefit-icon">📊</div>
            <div class="benefit-content">
                <h3>Polls</h3>
                <p>Voice your opinion on the latest topics and see what the community thinks</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon">🔮</div>
            <div class="benefit-content">
                <h3>Predictors</h3>
                <p>Make predictions and track your accuracy against other members</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon">⚔️</div>
            <div class="benefit-content">
                <h3>Challenge a Friend</h3>
                <p>Send challenges to friends and compete for bragging rights</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon">🏆</div>
            <div class="benefit-content">
                <h3>Member Competitions</h3>
                <p>Exclusive competitions and prizes only available to members</p>
            </div>
        </div>
    </div>

    <div class="stats-banner">
        <div class="stat-item">
            <span class="stat-number">17</span>
            <span class="stat-label">MEMBER FEATURES</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">24/7</span>
            <span class="stat-label">ACCESS AVAILABLE</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">5K+</span>
            <span class="stat-label">ACTIVE MEMBERS</span>
        </div>
    </div>
</div>