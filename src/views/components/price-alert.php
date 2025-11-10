<div class="price-alert-widget">
    <button class="price-alert-trigger" onclick="openPriceAlert(<?= $product->id ?>, null, null, <?= $product->sale_price < $product->price ? $product->sale_price : $product->price ?>)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        Set Price Alert
    </button>
</div>

<div id="price-alert-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Set Price Alert</h3>
            <button class="modal-close" onclick="closePriceAlert()">&times;</button>
        </div>

        <div class="modal-body">
            <p>Get notified when the price drops below your target</p>

            <div class="price-alert-form">
                <div class="form-group">
                    <label>Current Price</label>
                    <input type="text" value="£<?= number_format($product->sale_price ?? $product->price, 2) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Target Price *</label>
                    <input type="number" id="target-price" placeholder="£0.00" step="0.01" min="0">
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" id="alert-email" placeholder="your@email.com">
                </div>

                <button class="btn btn-primary btn-block" onclick="submitPriceAlert()">Create Alert</button>
            </div>
        </div>
    </div>
</div>