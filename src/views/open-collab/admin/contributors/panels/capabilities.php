<?php $canManageCapabilities = (bool) ($canManageCapabilities ?? false); ?>

<div id="capability-drawer-backdrop" style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:400;"></div>

<div id="capability-drawer" style="display:none;position:fixed;top:0;right:0;bottom:0;width:440px;max-width:100%;background:#fff;z-index:500;box-shadow:-10px 0 40px rgba(0,0,0,.12);transform:translateX(100%);transition:transform .3s ease-in-out;flex-direction:column;">
    <div style="padding:24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h3 id="capability-drawer-title" style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin:0;">Capability</h3>
            <p id="capability-drawer-subtitle" style="font-size:.82rem;color:var(--slate);margin:4px 0 0 0;"></p>
        </div>
        <button data-action="close-capability-drawer" style="border:none;background:none;font-size:1.4rem;color:var(--slate);cursor:pointer;line-height:1;">&times;</button>
    </div>

    <div style="padding:24px;flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:18px;">
        <div id="capability-drawer-banner" style="display:none;"></div>

        <div class="oc-form-group" style="display:none;">
            <select id="capability-drawer-select" class="oc-select"></select>
        </div>

        <div>
            <span style="font-size:.75rem;text-transform:uppercase;font-weight:700;letter-spacing:.05em;color:var(--slate);display:block;margin-bottom:6px;">Rule Description</span>
            <p id="capability-drawer-desc" style="font-size:.875rem;color:var(--navy);line-height:1.5;margin:0;"></p>
        </div>

        <div class="oc-form-group">
            <span style="font-size:.75rem;text-transform:uppercase;font-weight:700;letter-spacing:.05em;color:var(--slate);display:block;margin-bottom:8px;">Explicit Override Action</span>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;color:var(--navy);cursor:pointer;">
                    <input type="radio" name="cap_action" value="grant" checked>
                    <span>Explicitly Allow (Grant permission)</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;color:var(--navy);cursor:pointer;">
                    <input type="radio" name="cap_action" value="revoke">
                    <span>Explicitly Block (Deny permission)</span>
                </label>
            </div>
        </div>
    </div>

    <div style="padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;gap:12px;">
        <button data-action="close-capability-drawer" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
        <?php if ($canManageCapabilities): ?>
            <button id="capability-drawer-save" class="oc-btn oc-btn--primary" style="flex:1;">Save Override</button>
        <?php endif; ?>
    </div>
</div>
