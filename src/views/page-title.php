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
</style>


<div style="display: flex; align-items: center; gap: 1rem; justify-content: space-between;">
    <h1 class="page-title"><?= htmlspecialchars($page->title) ?></h1>
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
</div>