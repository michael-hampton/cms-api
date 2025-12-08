<style>
    .account-cta-button {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px -1px rgba(102, 126, 234, 0.3);
        position: relative;
        overflow: hidden;
    }

    .account-cta-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .account-cta-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.4);
    }

    .account-cta-button:hover::before {
        opacity: 1;
    }

    .account-cta-button svg {
        width: 20px;
        height: 20px;
        transition: transform 0.3s ease;
    }

    .account-cta-button:hover svg {
        transform: scale(1.1);
    }

    .account-cta-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.125rem;
    }

    .account-cta-main {
        font-size: 0.9375rem;
        font-weight: 600;
        line-height: 1;
    }

    .account-cta-sub {
        font-size: 0.75rem;
        opacity: 0.9;
        font-weight: 400;
    }

    @media (max-width: 768px) {
        .account-cta-button {
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
        }

        .account-cta-text {
            display: none;
        }
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.625rem 1.25rem;
        background: var(--bg-light);
        border-radius: 50px;
        transition: all 0.3s ease;
        margin-left: auto;
    }

    .user-profile:hover {
        background: #e5e7eb;
    }

    .user-avatar {
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: black;
        font-weight: 700;
        font-size: 1.125rem;
        box-shadow: var(--shadow-sm);
        flex-shrink: 0;
    }

    .user-details {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .user-name {
        font-weight: 600;
        font-size: 0.9375rem;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .user-role {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        line-height: 1;
    }

    .btn-logout {
        padding: 0.625rem 1.25rem;
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }

    .btn-logout:hover {
        border-color: var(--danger-color);
        color: var(--danger-color);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }
</style>

<?php if (!\App\Framework\Authorization\MemberAuth::check()): ?>
    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="account-cta-button">

        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2"
             stroke-linecap="round"
             stroke-linejoin="round">
            <circle cx="12" cy="8" r="3.2"/>
            <path d="M4.5 20c0-3.2 2.8-5.8 7.5-5.8s7.5 2.6 7.5 5.8"/>
        </svg>

        <div class="account-cta-text">
            <span class="account-cta-main">My Account</span>
            <span class="account-cta-sub">Login or Sign Up</span>
        </div>
    </a>
<?php else: ?>
    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard">
        <div class="header-actions">
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($member->first_name ?? $member->email ?? 'M', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($member->displayName ?? 'Member') ?></span>
                    <span class="user-role">Member</span>
                </div>
            </div>

            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>

            <form method="POST" action="/member/logout" style="display: inline;">
                <button type="submit" class="btn-logout">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </a>
<?php endif; ?>