(function () {

    window.savedCards = [];
    window.selectedCardId = null;

    function normaliseSavedCard(card) {
        const details = card.card || card;

        return {
            id: card.id,
            brand: details.brand || 'card',
            last4: details.last4 || '••••',
            exp_month: details.exp_month || '',
            exp_year: details.exp_year || '',
        };
    }

    function normaliseBrand(brand) {
        return String(brand || 'card').toLowerCase().replace(/\s+/g, '-');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function escapeJsString(value) {
        return String(value ?? '')
            .replaceAll('\\', '\\\\')
            .replaceAll("'", "\\'");
    }

    function cardIconSvg(brand) {
        const normalisedBrand = normaliseBrand(brand);

        const icons = {
            visa: `
                <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                    <rect width="48" height="32" rx="5" fill="#fff"/>
                    <path fill="#1A1F71" d="M19.2 21.4h-3.1l1.9-10.8h3.1l-1.9 10.8Zm10.9-10.5a7.7 7.7 0 0 0-2.8-.5c-3.1 0-5.2 1.5-5.2 3.7 0 1.6 1.5 2.5 2.7 3.1 1.2.5 1.6.9 1.6 1.4 0 .7-1 1.1-1.8 1.1-1.2 0-1.9-.2-2.9-.6l-.4-.2-.4 2.4c.7.3 2.1.6 3.5.6 3.3 0 5.4-1.5 5.4-3.9 0-1.3-.8-2.3-2.6-3.1-1.1-.5-1.8-.8-1.8-1.3 0-.4.6-.9 1.7-.9.9 0 1.6.2 2.2.4l.3.1.5-2.3Zm8 10.5h2.8l-2.5-10.8h-2.6c-.6 0-1.1.2-1.3.8l-4.9 10h3.3l.7-1.8h4l.5 1.8Zm-3.6-4 1.7-4.1 1 4.1h-2.7ZM13.4 10.6l-3 7.4-.3-1.5c-.6-1.8-2.3-3.8-4.2-4.8l2.7 9.7h3.3l4.8-10.8h-3.3Z"/>
                </svg>`,
            mastercard: `
                <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                    <rect width="48" height="32" rx="5" fill="#fff"/>
                    <circle cx="20" cy="16" r="8" fill="#EB001B"/>
                    <circle cx="28" cy="16" r="8" fill="#F79E1B" fill-opacity=".9"/>
                    <path fill="#FF5F00" d="M24 9.7a8 8 0 0 1 0 12.6 8 8 0 0 1 0-12.6Z"/>
                </svg>`,
            amex: `
                <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                    <rect width="48" height="32" rx="5" fill="#2E77BC"/>
                    <text x="24" y="20" text-anchor="middle" font-size="9" font-weight="700" fill="#fff" font-family="Arial, sans-serif">AMEX</text>
                </svg>`,
            discover: `
                <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                    <rect width="48" height="32" rx="5" fill="#fff"/>
                    <text x="23" y="18" text-anchor="middle" font-size="7" font-weight="700" fill="#111827" font-family="Arial, sans-serif">DISCOVER</text>
                    <circle cx="37" cy="16" r="6" fill="#F97316" opacity=".85"/>
                </svg>`,
            default: `
                <svg viewBox="0 0 48 32" aria-hidden="true" focusable="false">
                    <rect width="48" height="32" rx="5" fill="#0f172a"/>
                    <rect x="6" y="9" width="36" height="4" rx="1" fill="#64748b"/>
                    <rect x="7" y="20" width="12" height="2" rx="1" fill="#cbd5e1"/>
                    <rect x="23" y="20" width="8" height="2" rx="1" fill="#cbd5e1"/>
                </svg>`,
        };

        return icons[normalisedBrand] || icons.default;
    }

    window.loadSavedCards = async function () {
        if (!window.isLoggedIn || !window.currentMember) return;
        try {
            const res = await fetch(`${API_BASE}/member/payment-methods`);
            const data = await res.json();
            const methods = data.data?.payment_methods || data.payment_methods || [];

            if (data.success && methods.length > 0) {
                window.savedCards = methods.map(normaliseSavedCard);
                displaySavedCards();
            }
        } catch (err) {
            console.error('loadSavedCards error:', err);
        }
    };

    window.displaySavedCards = function () {
        const container = document.getElementById('saved-cards-list');
        const section = document.getElementById('saved-cards-section');

        if (!container || !section) return;

        container.innerHTML = window.savedCards.map(function (card) {
            const savedCard = normaliseSavedCard(card);
            const selected = savedCard.id === window.selectedCardId ? ' selected' : '';
            const cardId = escapeHtml(savedCard.id);
            const brand = escapeHtml(savedCard.brand);
            const last4 = escapeHtml(savedCard.last4);
            const expMonth = escapeHtml(savedCard.exp_month);
            const expYear = escapeHtml(savedCard.exp_year);

            return `
                <label class="saved-card${selected}" for="card-${cardId}">
                    <input type="radio" name="saved_card"
                           value="${cardId}" id="card-${cardId}"
                           onchange="selectSavedCard('${escapeJsString(savedCard.id)}')">
                    <span class="saved-card-icon saved-card-icon-${normaliseBrand(savedCard.brand)}">
                        ${cardIconSvg(savedCard.brand)}
                    </span>
                    <div class="card-details">
                        <div class="card-brand">${brand}</div>
                        <div class="saved-card-meta">
                            <div class="card-number">&bull;&bull;&bull;&bull; ${last4}</div>
                            <div class="card-expiry">Expires ${expMonth}/${expYear}</div>
                        </div>
                    </div>
                </label>`;
        }).join('');

        section.style.display = 'block';

        const newCardSection = document.getElementById('new-card-section');
        if (newCardSection) newCardSection.style.display = 'none';
    };

    window.selectSavedCard = function (cardId) {
        window.selectedCardId = cardId;
        document.querySelectorAll('.saved-card')
            .forEach(function (c) {
                c.classList.remove('selected');
            });
        const el = document.getElementById(`card-${cardId}`);
        if (el) el.closest('.saved-card').classList.add('selected');
    };

    window.showNewCardForm = function () {
        window.selectedCardId = null;

        const savedSection = document.getElementById('saved-cards-section');
        const newSection = document.getElementById('new-card-section');
        const backBtn = document.getElementById('back-to-saved-cards-btn');

        if (savedSection) savedSection.style.display = 'none';
        if (newSection) newSection.style.display = 'block';
        if (backBtn) backBtn.style.display = 'block';

        document.querySelectorAll('[name="saved_card"]')
            .forEach(function (r) {
                r.checked = false;
            });
    };

    window.showSavedCards = function () {
        window.selectedCardId = null;

        const savedSection = document.getElementById('saved-cards-section');
        const newSection = document.getElementById('new-card-section');
        const backBtn = document.getElementById('back-to-saved-cards-btn');

        if (savedSection) savedSection.style.display = 'block';
        if (newSection) newSection.style.display = 'none';
        if (backBtn) backBtn.style.display = 'none';
    };
})();