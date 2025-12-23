<style>
    .subscription-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        background-color: #1D4ED8; /* Blue */
        color: #fff;
        border: none;
        border-radius: 9999px; /* fully rounded */
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        transition: background-color 0.2s, transform 0.1s;
    }

    .subscription-toggle svg {
        width: 20px;
        height: 20px;
        stroke: currentColor;
    }

    .subscription-toggle span {
        margin-left: 8px;
    }

    /* Hover effect */
    .subscription-toggle:hover {
        background-color: #2563EB; /* slightly darker blue */
        transform: translateY(-1px);
    }

    /* Active effect */
    .subscription-toggle:active {
        transform: translateY(1px);
    }
</style>

<button class="subscription-toggle" aria-label="Subscribe" onclick="showSubscriptionModal()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
         stroke-linejoin="round">
        <!-- Bell -->
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        <!-- Plus -->
        <line x1="19" y1="8" x2="19" y2="14"></line>
        <line x1="16" y1="11" x2="22" y2="11"></line>
    </svg>
    <span>Subscribe</span>
</button>
