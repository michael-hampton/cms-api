@extends('open-collab/layouts/admin')

@section('content')

<div class="oc-page__header">
    <div>
        <h1 class="oc-page__title">Site Settings</h1>
        <p class="oc-page__subtitle">Manage contributor workflow requirements for
            <strong><?= htmlspecialchars($currentSite->name) ?></strong>.
        </p>
    </div>
</div>

<div class="oc-card" style="max-width:640px;">

    <form
            method="POST"
            action="/<?= htmlspecialchars($site) ?>/open-collab/admin/sites/settings"
            id="site-settings-form"
            novalidate>

        @csrf

        <div class="oc-form-group">
            <label class="oc-label" for="guidelines_version">
                Guidelines Version
                <span class="oc-label__hint">
                    Bump this when guidelines change to force re-acknowledgement.
                </span>
            </label>

            <input
                    type="text"
                    id="guidelines_version"
                    name="guidelines_version"
                    class="oc-input <?= $errors['guidelines_version'] ?? false ? 'oc-input--error' : '' ?>"
                    value="<?= htmlspecialchars($currentSite->guidelines_version ?? '') ?>"
                    placeholder="e.g. 2024-06"
                    maxlength="50">

            <?php if (!empty($errors['guidelines_version'])): ?>
                <span class="oc-field-error"><?= htmlspecialchars($errors['guidelines_version']) ?></span>
            <?php endif; ?>
        </div>

        <fieldset class="oc-fieldset" style="margin-top:28px;">
            <legend class="oc-fieldset__legend">Contributor Requirements</legend>
            <p class="oc-fieldset__description">
                Enabling a requirement blocks contributors from submitting content
                until the condition is met.
            </p>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require payment setup</span>
                    <span class="oc-toggle-row__description">
                        Contributors must add a payout method before submitting articles.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require payment setup">
                    <input
                            type="checkbox"
                            name="require_payment_setup"
                            value="1"
                            <?= $currentSite->require_payment_setup ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require signed contract</span>
                    <span class="oc-toggle-row__description">
                        Contributors must sign the active contract before submitting.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require signed contract">
                    <input
                            type="checkbox"
                            name="require_contracts"
                            value="1"
                            <?= $currentSite->require_contracts ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require guidelines acknowledgement</span>
                    <span class="oc-toggle-row__description">
                        Contributors must acknowledge the current guidelines version.
                        Tied to <em>Guidelines Version</em> above.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require guidelines acknowledgement">
                    <input
                            type="checkbox"
                            name="require_guidelines_ack"
                            value="1"
                            <?= $currentSite->require_guidelines_ack ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

        </fieldset>

        <div class="oc-form-actions" style="margin-top:32px;">
            <button
                    type="submit"
                    class="oc-btn oc-btn--primary"
                    id="save-btn">
                Save settings
            </button>
        </div>

    </form>

</div>

@endsection

@section('scripts')
<script>
    /**
     * SiteSettingsForm
     * ─────────────────
     * Serialises the form and PATCHes the API, then shows inline feedback.
     * Falls back to native POST if JS is unavailable.
     */
    class SiteSettingsForm {

        constructor(formId) {
            this.form = document.getElementById(formId);
            this.saveBtn = document.getElementById('save-btn');
            this.siteSlug = <?= json_encode($site) ?>;
            this.apiUrl = `/${this.siteSlug}/open-collab/admin/sites/settings`;
            this.token = localStorage.getItem('oc_token') || '';

            if (this.form) {
                this._bindEvents();
            }
        }

        _bindEvents() {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this._submit();
            });
        }

        async _submit() {
            if (this._saving) return;
            this._saving = true;
            this._setLoading(true);

            const payload = this._buildPayload();

            try {
                const res = await fetch(this.apiUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.token ? {Authorization: `Bearer ${this.token}`} : {}),
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(data.message || `Server error (${res.status})`);
                }

                this._showBanner('success', 'Site settings saved.');

            } catch (err) {
                this._showBanner('danger', err.message || 'Could not save settings.');
            } finally {
                this._setLoading(false);
                this._saving = false;
            }
        }

        /**
         * Checkboxes that are unchecked are not included in FormData,
         * so we build the payload explicitly to ensure false is sent.
         */
        _buildPayload() {
            const fd = new FormData(this.form);

            return {
                guidelines_version: fd.get('guidelines_version') || null,
                require_payment_setup: fd.has('require_payment_setup'),
                require_contracts: fd.has('require_contracts'),
                require_guidelines_ack: fd.has('require_guidelines_ack'),
            };
        }

        _setLoading(on) {
            if (!this.saveBtn) return;
            this.saveBtn.disabled = on;
            this.saveBtn.textContent = on ? 'Saving…' : 'Save settings';
        }

        _showBanner(type, message) {
            // Remove any existing banner
            document.querySelector('.oc-settings-banner')?.remove();

            const iconPath = type === 'success'
                ? 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z'
                : 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z';

            const banner = document.createElement('div');
            banner.className = `oc-alert oc-alert--${type} oc-settings-banner`;
            banner.style.cssText = 'margin:0 0 20px;';
            banner.innerHTML = `
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="${this._escape(iconPath)}" clip-rule="evenodd"/>
                </svg>
                ${this._escape(message)}
            `;

            this.form.prepend(banner);

            setTimeout(() => {
                banner.style.transition = 'opacity .4s';
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 400);
            }, 4500);
        }

        _escape(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    }

    new SiteSettingsForm('site-settings-form');
</script>
@endsection

