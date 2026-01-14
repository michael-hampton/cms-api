<div id="billingDateModal"
     style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); padding: 20px; overflow-y: auto;">
    <div style="max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding: 24px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 24px;">Change Billing Date</h2>
                <button onclick="closeBillingDateModal()"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">×
                </button>
            </div>
        </div>

        <div style="padding: 24px;">
            <p style="color: #64748b; margin-bottom: 24px;">
                Select the day of the month you'd like to be charged. Your payment will be adjusted accordingly.
            </p>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    Current Billing Date
                </label>
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px;">
                    <span id="currentBillingDay" style="font-weight: 700; font-size: 18px;"></span> of each month
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    New Billing Day
                </label>
                <select id="billingDaySelect"
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
                    <?php for ($day = 1; $day <= 28; $day++): ?>
                        <option value="<?= $day ?>"><?= $day ?><?= $day == 1 ? 'st' : ($day == 2 ? 'nd' : ($day == 3 ? 'rd' : 'th')) ?>
                            of each month
                        </option>
                    <?php endfor; ?>
                </select>
                <p style="font-size: 13px; color: #64748b; margin-top: 8px;">
                    Note: We recommend choosing a day between 1-28 to avoid issues in shorter months
                </p>
            </div>

            <div id="prorationPreview"
                 style="display: none; background: #f0f4ff; padding: 16px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #667eea;">
                <div style="font-weight: 600; margin-bottom: 8px;">Billing Adjustment</div>
                <div id="prorationDetails" style="font-size: 14px; color: #334155;"></div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button onclick="closeBillingDateModal()"
                        style="flex: 1; padding: 12px; background: #e2e8f0; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
                <button onclick="confirmBillingDateChange()" id="confirmBillingBtn"
                        style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Update Billing Date
                </button>
            </div>
        </div>

        <input type="hidden" id="billingSubscriptionId">
    </div>
</div>