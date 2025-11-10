// Price Alert Functionality
let currentProductId = null;
let currentVariantId = null;
let currentMerchantId = null;
let currentPrice = 0;

function openPriceAlert(productId, variantId = null, merchantId = null, currentPriceValue = 0) {
    currentProductId = productId;
    currentVariantId = variantId;
    currentMerchantId = merchantId;
    currentPrice = currentPriceValue;

    const modal = document.getElementById('price-alert-modal');
    if (modal) {
        modal.style.display = 'flex';

        // Pre-fill email if user is logged in
        const userEmail = getUserEmail();
        if (userEmail) {
            document.getElementById('alert-email').value = userEmail;
        }
    }
}

function closePriceAlert() {
    const modal = document.getElementById('price-alert-modal');
    if (modal) {
        modal.style.display = 'none';
    }

    // Clear form
    document.getElementById('target-price').value = '';
    document.getElementById('alert-email').value = '';
}

async function submitPriceAlert() {
    const targetPrice = parseFloat(document.getElementById('target-price').value);
    const email = document.getElementById('alert-email').value;

    // Validation
    if (!targetPrice || targetPrice <= 0) {
        showToast('Please enter a valid target price', 'error');
        return;
    }

    if (!email || !isValidEmail(email)) {
        showToast('Please enter a valid email address', 'error');
        return;
    }

    if (targetPrice >= currentPrice) {
        showToast('Target price must be lower than current price', 'error');
        return;
    }

    try {
        const response = await fetch('/api/price-alerts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: currentProductId,
                variant_id: currentVariantId,
                merchant_id: currentMerchantId,
                target_price: targetPrice,
                email: email
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Price alert created! We\'ll notify you when the price drops.', 'success');
            closePriceAlert();
        } else {
            showToast(data.message || 'Failed to create price alert', 'error');
        }
    } catch (error) {
        console.error('Error creating price alert:', error);
        showToast('Failed to create price alert. Please try again.', 'error');
    }
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function getUserEmail() {
    // This would get the logged-in user's email from session/local storage
    // Implementation depends on your auth system
    return null;
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = document.getElementById('price-alert-modal');
    if (modal && e.target === modal) {
        closePriceAlert();
    }
});

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = `toast toast-${type} show`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}