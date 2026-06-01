<div class="oc-card" style="margin-bottom:20px;">
    <div class="oc-card__header">
        <span class="oc-card__title">Invite Contributor</span>
    </div>
    <div class="oc-card__body">
        <div id="invite-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="oc-form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                <label class="oc-label" for="invite-email">Email address</label>
                <input class="oc-input" type="email" id="invite-email" placeholder="contributor@example.com">
            </div>
            <button onclick="sendInvite()" class="oc-btn oc-btn--amber" id="invite-btn">
                Send invitation
            </button>
        </div>
    </div>
</div>
