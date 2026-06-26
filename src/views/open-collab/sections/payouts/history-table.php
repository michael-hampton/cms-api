<!-- Payout history table section -->
<div class="oc-grid-sidebar" style="align-items:start;">
    <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
        <div class="oc-card__header">
            <span class="oc-card__title" id="list-title">Payout History</span>
            <span id="list-count" style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
        </div>

        <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">
            <button class="filter-pill filter-pill--active" onclick="setFilter('all', this)">All</button>
            <button class="filter-pill" onclick="setFilter('pending', this)">Pending</button>
            <button class="filter-pill" onclick="setFilter('approved', this)">Approved</button>
            <button class="filter-pill" onclick="setFilter('paid', this)">Paid</button>
            <button class="filter-pill" onclick="setFilter('rejected', this)">Rejected</button>
        </div>

        <style>
            .filter-pill { padding:4px 12px;border-radius:20px;border:1.5px solid var(--border);background:#fff;font-size:.75rem;font-weight:500;color:var(--slate);cursor:pointer;transition:background .15s,color .15s,border-color .15s; }
            .filter-pill:hover { border-color:var(--navy);color:var(--navy); }
            .filter-pill--active { background:var(--navy);color:#fff;border-color:var(--navy); }
        </style>

        <div id="payouts-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
            <div class="oc-spinner" style="margin:0 auto 12px;"></div>
            Loading payouts…
        </div>

        <div id="payouts-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
            <svg viewBox="0 0 20 20" fill="currentColor" width="32" style="opacity:.2;display:block;margin:0 auto 12px;">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z" clip-rule="evenodd"/>
            </svg>
            <div style="font-weight:500;margin-bottom:6px;" id="empty-message">No payout requests yet</div>
            <div style="font-size:.85rem;" id="empty-sub">Once your balance reaches £50.00, you can request a payout.</div>
        </div>

        <div id="payouts-error" style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
            Failed to load payouts.
            <button onclick="loadAll()" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:8px;">Retry</button>
        </div>

        <div id="payouts-table-wrap" style="display:none;overflow-x:auto;">
            <table class="oc-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="payouts-tbody"></tbody>
            </table>
        </div>
    </div>

    <div style="position:sticky;top:calc(var(--header-h) + 20px);">
        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
            <div class="oc-card__header"><span class="oc-card__title" style="font-size:.95rem;">Request Payout</span></div>
            <div class="oc-card__body">
                <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;" id="balance-card">
                    <div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Available now</div>
                    <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);" id="balance-display">—</div>
                </div>

                <div id="payout-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

                <div id="payout-form-wrap">
                    <div class="oc-form-group">
                        <label class="oc-label" for="payout-method">Payout method</label>
                        <select class="oc-select" id="payout-method">
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="paypal">PayPal</option>
                            <option value="other">Other</option>
                        </select>
                        <div class="oc-help">Your payout details are configured in <a href="/contributor/settings#payment">Settings</a>.</div>
                    </div>
                    <button onclick="requestPayout()" class="oc-btn oc-btn--amber oc-btn--block" id="payout-btn" disabled>Request payout</button>
                </div>

                <div id="below-minimum-note" style="display:none;padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);">
                    <div style="font-size:.85rem;font-weight:500;margin-bottom:4px;color:var(--navy);">Minimum not reached</div>
                    <div style="font-size:.78rem;color:var(--slate);">You need at least <strong>£50.00</strong> to request a payout.</div>
                </div>

                <div style="font-size:.72rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);margin-top:16px;">
                    Payouts are processed manually by our team, typically within 2–5 business days after approval.
                </div>
            </div>
        </div>
    </div>
</div>
