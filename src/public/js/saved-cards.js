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
            return `
                <label class="saved-card${selected}" for="card-${savedCard.id}">
                    <input type="radio" name="saved_card"
                           value="${savedCard.id}" id="card-${savedCard.id}"
                           onchange="selectSavedCard('${savedCard.id}')">
                    <div class="card-details">
                        <div class="card-brand">${savedCard.brand}</div>
                        <div class="card-number">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; ${savedCard.last4}</div>
                        <div class="card-expiry">Expires ${savedCard.exp_month}/${savedCard.exp_year}</div>
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
