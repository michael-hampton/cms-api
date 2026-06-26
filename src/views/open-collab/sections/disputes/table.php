<!-- Raise dispute modal + disputes table section -->
<div id="dispute-modal" style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;" onclick="if(event.target===this)closeDisputeModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Raise an earnings dispute</h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Tell us what's wrong with this earnings entry. Our team will review it within 2–3 business days.</p>
        <input type="hidden" id="dispute-ledger-id">

        <div class="oc-form-group">
            <label class="oc-label" for="dispute-ledger-select">Earnings entry</label>
            <select class="oc-select" id="dispute-ledger-select" onchange="selectLedgerEntry(this)">
                <option value="">Select an earnings entry…</option>
                <?php foreach ($disputableLedgerEntries as $entry):
                    $amount = number_format(abs((int)$entry->amount) / 100, 2);
                    $currency = strtoupper($entry->currency ?? 'GBP');
                    $symbol = $currency === 'GBP' ? '£' : '$';
                    $type = ucfirst($entry->type ?? 'sale');
                    $date = $entry->earned_at?->format('d M Y') ?? '';
                    ?>
                    <option value="<?= (int)$entry->id ?>" data-amount="<?= htmlspecialchars($symbol . $amount) ?>" data-type="<?= htmlspecialchars($type) ?>" data-date="<?= htmlspecialchars($date) ?>">
                        #<?= (int)$entry->id ?> · <?= htmlspecialchars($type) ?> · <?= htmlspecialchars($symbol . $amount) ?> · <?= htmlspecialchars($date) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($disputableLedgerEntries->isEmpty()): ?>
                <div class="oc-help" style="color:var(--amber-dark,#b45309);">No eligible earnings entries found. Entries become disputable once they appear in your earnings ledger.</div>
            <?php endif; ?>
        </div>

        <div id="selected-entry-summary" style="display:none;background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:.82rem;"></div>

        <div class="oc-form-group">
            <label class="oc-label" for="dispute-reason">Reason <span style="color:var(--red);">*</span></label>
            <textarea class="oc-textarea" id="dispute-reason" rows="4" style="min-height:100px;" placeholder="Describe the issue clearly — e.g. incorrect amount, missing payment, duplicate entry…" required></textarea>
            <div class="oc-help">Minimum 10 characters. Be specific so we can investigate quickly.</div>
        </div>

        <div id="dispute-modal-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

        <div style="display:flex;gap:10px;">
            <button onclick="closeDisputeModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitDispute()" class="oc-btn oc-btn--primary" style="flex:1;" id="dispute-submit-btn" <?= $disputableLedgerEntries->isEmpty() ? 'disabled' : '' ?>>Submit dispute</button>
        </div>
    </div>
</div>

<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <button class="filter-pill filter-pill--active" onclick="setFilter('all', this)">All</button>
    <button class="filter-pill" onclick="setFilter('open', this)">Open</button>
    <button class="filter-pill" onclick="setFilter('resolved', this)">Resolved</button>
    <button class="filter-pill" onclick="setFilter('rejected', this)">Rejected</button>
    <?php if (!$disputableLedgerEntries->isEmpty()): ?>
        <button onclick="openDisputeModal()" class="oc-btn oc-btn--primary oc-btn--sm" style="margin-left:auto;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" style="margin-right:4px;"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            Raise a dispute
        </button>
    <?php endif; ?>
</div>

<style>
    .filter-pill { padding:5px 14px;border-radius:20px;border:1.5px solid var(--border);background:#fff;font-size:.78rem;font-weight:500;color:var(--slate);cursor:pointer;transition:background .15s,color .15s,border-color .15s; }
    .filter-pill:hover { border-color:var(--navy);color:var(--navy); }
    .filter-pill--active { background:var(--navy);color:#fff;border-color:var(--navy); }
</style>

<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title" id="list-title">My Disputes</span>
        <span id="list-count" style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
    </div>

    <div id="disputes-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
        <div class="oc-spinner" style="margin:0 auto 12px;"></div>
        Loading disputes…
    </div>

    <div id="disputes-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
        <svg viewBox="0 0 20 20" fill="currentColor" width="32" style="opacity:.2;display:block;margin:0 auto 12px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <div style="font-weight:500;margin-bottom:6px;">No disputes</div>
        <div style="font-size:.85rem;">If you believe there's an error in your earnings, you can raise a dispute above.</div>
    </div>

    <div id="disputes-error" style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
        Failed to load disputes.
        <button onclick="loadDisputes()" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:8px;">Retry</button>
    </div>

    <div id="disputes-list" style="display:none;flex-direction:column;"></div>
</div>
