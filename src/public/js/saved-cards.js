(function () {

    window.savedCards = [];
    window.selectedCardId = null;

    window.loadSavedCards = async function () {
        if (!window.isLoggedIn || !window.currentMember) return;
        alert('mike8')
        try {
            const res = await fetch(`${API_BASE}/member/payment-methods`);
            const data = await res.json();
            console.log('data')
            if (data.success && data.data.payment_methods?.length > 0) {
                window.savedCards = data.data.payment_methods;
                displaySavedCards();
            }
        } catch (err) {
            console.error('loadSavedCards error:', err);
        }
    };

    window.displaySavedCards = function () {
        alert('here67')
        const container = document.getElementById('saved-cards-list');
        const section = document.getElementById('saved-cards-section');

        alert(container + ' ' + section)

        if (!container || !section) return;

        alert('here10')

        container.innerHTML = window.savedCards.map(function (card) {
            const selected = card.id === window.selectedCardId ? ' selected' : '';
            return `
                <label class="saved-card${selected}" for="card-${card.id}">
                    <input type="radio" name="saved_card"
                           value="${card.id}" id="card-${card.id}"
                           onchange="selectSavedCard('${card.id}')">
                    <div class="card-details">
                        <div class="card-brand">${card.card.brand}</div>
                        <div class="card-number">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; ${card.card.last4}</div>
                        <div class="card-expiry">Expires ${card.card.exp_month}/${card.card.exp_year}</div>
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