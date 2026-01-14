<div id="renewalModal"
     style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); padding: 20px; overflow-y: auto;">
    <div style="max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding: 24px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 24px;">Renew Your Subscription</h2>
                <button onclick="closeRenewalModal()"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">×
                </button>
            </div>
        </div>

        <div style="padding: 24px;">
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <div style="font-weight: 600; margin-bottom: 4px;">Current Plan</div>
                <div style="color: #64748b; font-size: 14px;" id="currentPlanName"></div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    Choose Renewal Type
                </label>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 16px; cursor: pointer; transition: all 0.2s;"
                           class="renewal-option" data-type="fixed">
                        <input type="radio" name="renewal_type" value="fixed" checked style="margin-right: 12px;">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 4px;">Fixed Term</div>
                            <div style="font-size: 14px; color: #64748b;">Choose 1 or 2 year subscription</div>
                        </div>
                    </label>
                    <label style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 16px; cursor: pointer; transition: all 0.2s;"
                           class="renewal-option" data-type="auto">
                        <input type="radio" name="renewal_type" value="auto" style="margin-right: 12px;">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 4px;">Auto-Renewing</div>
                            <div style="font-size: 14px; color: #64748b;">Automatically renews, cancel anytime</div>
                        </div>
                    </label>
                </div>
            </div>

            <div id="addressSection" style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    Delivery Address
                </label>
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 12px;"
                     id="currentAddress">
                    Loading address...
                </div>
                <button onclick="updateAddress()" class="btn btn-secondary" style="font-size: 14px; padding: 8px 16px;">
                    Update Address
                </button>
            </div>
        </div>

        <div style="padding: 24px; border-top: 1px solid #e2e8f0; display: flex; gap: 12px; justify-content: flex-end;">
            <button onclick="closeRenewalModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="processRenewal()" class="btn btn-primary" id="renewalSubmitBtn">
                Continue to Payment
            </button>
        </div>
    </div>
</div>