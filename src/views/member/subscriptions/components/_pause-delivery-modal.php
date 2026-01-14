<div id="pauseDeliveryModal"
     style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); padding: 20px; overflow-y: auto;">
    <div style="max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding: 24px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 24px;">Pause Delivery</h2>
                <button onclick="closePauseDeliveryModal()"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">×
                </button>
            </div>
        </div>

        <div style="padding: 24px;">
            <p style="color: #64748b; margin-bottom: 24px;">
                Pause your magazine deliveries temporarily. Your subscription will remain active and unused issues will
                be available when you resume.
            </p>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Pause Start Date
                </label>
                <input type="date" id="pauseStartDate"
                       min="<?= (new \DateTime())->format('Y-m-d') ?>"
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Resume Date (Pause End)
                </label>
                <input type="date" id="pauseEndDate"
                       min="<?= (new \DateTime('+1 day'))->format('Y-m-d') ?>"
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
                <p style="font-size: 13px; color: #64748b; margin-top: 8px;">
                    Maximum pause period: 90 days
                </p>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Reason (Optional)
                </label>
                <textarea id="pauseReason" rows="3"
                          placeholder="e.g., Holiday, Moving house..."
                          style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 12px;">
                <button onclick="closePauseDeliveryModal()"
                        style="flex: 1; padding: 12px; background: #e2e8f0; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
                <button onclick="confirmPauseDelivery()" id="confirmPauseBtn"
                        style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Pause Delivery
                </button>
            </div>
        </div>

        <input type="hidden" id="pauseSubscriptionId">
    </div>
</div>