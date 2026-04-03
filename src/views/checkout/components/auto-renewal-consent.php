<style>
    .auto-renewal-consent {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin-top: 1.5rem;
    }

    .auto-renewal-consent label {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        cursor: pointer;
    }

    .auto-renewal-consent input[type="checkbox"] {
        margin-top: 0.2rem;
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        cursor: pointer;
        accent-color: var(--primary-color);
    }

    .auto-renewal-consent .consent-text {
        font-size: 0.8125rem;
        color: #0c4a6e;
        line-height: 1.6;
    }

    .auto-renewal-consent .consent-text strong {
        display: block;
        margin-bottom: 0.375rem;
        font-size: 0.875rem;
    }

    .auto-renewal-consent.consent-error {
        border-color: var(--danger-color);
        background: #fff1f2;
    }

    .auto-renewal-consent.consent-error .consent-text {
        color: #7f1d1d;
    }
</style>

<?php if ($showGlobal ?? true): ?>
    <!--
        AUTO-RENEWAL CONSENT — ALL USERS
        NOTE: Final wording must be reviewed and approved by Legal prior to release.
    -->
    <div id="global-renewal-consent-block" class="auto-renewal-consent">
        <label>
            <input type="checkbox"
                   id="<?= htmlspecialchars($globalConsentId ?? 'global-renewal-consent') ?>"
                   name="global_renewal_consent"
                   value="1">
            <div class="consent-text">
                <strong>Subscription Terms</strong>
                I understand this is a recurring subscription that will automatically renew
                until cancelled. I can cancel at any time via my account settings before
                the next renewal date.
                <em style="display: block; margin-top: 0.5rem; font-size: 0.75rem; opacity: 0.8;">
                    [Pending Legal review — final wording TBC]
                </em>
            </div>
        </label>
    </div>
<?php endif; ?>

<?php if ($showUs ?? false): ?>
    <!--
        AUTO-RENEWAL CONSENT — US USERS ONLY
        NOTE: Final wording must be reviewed and approved by Legal prior to release.
    -->
    <div id="us-renewal-consent-block"
         class="auto-renewal-consent"
         style="display: none; margin-top: 0.75rem;">
        <label>
            <input type="checkbox"
                   id="<?= htmlspecialchars($usConsentId ?? 'us-renewal-consent') ?>"
                   name="us_renewal_consent"
                   value="1">
            <div class="consent-text">
                <strong>Auto-Renewal Notice (Required for US customers)</strong>
                This is an automatically renewing subscription that will continue until
                you cancel. You may cancel at any time via your account settings. Your
                payment will be charged at the then-current rate on the same date each
                billing period and will continue until cancelled. We may update pricing
                with advance notice; you may cancel before any change takes effect.
                <em style="display: block; margin-top: 0.5rem; font-size: 0.75rem; opacity: 0.8;">
                    [Pending Legal review — final wording TBC]
                </em>
            </div>
        </label>
    </div>
<?php endif; ?>