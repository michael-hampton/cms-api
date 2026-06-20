<div class="modal-overlay"
     id="subscription-manage-drawer"
     role="dialog"
     aria-modal="true"
     aria-labelledby="subscription-manage-title"
     hidden>
    <div class="modal">
        <div class="modal__header">
            <div>
                <div class="page-heading__eyebrow">Manage subscription</div>
                <h2 class="modal__title" id="subscription-manage-title">Subscription</h2>
                <p class="page-heading__sub" id="subscription-manage-status"></p>
            </div>
            <button class="modal__close"
                    type="button"
                    data-close-subscription-manage
                    aria-label="Close">×</button>
        </div>

        <div class="modal__body">
            <div class="sub-card-full__body" id="subscription-manage-facts"></div>

            <section id="subscription-auto-renew-section" aria-labelledby="subscription-auto-renew-heading">
                <h3 id="subscription-auto-renew-heading">Automatic renewal</h3>
                <p>Automatically renew this subscription at the end of the current billing period.</p>

                <form id="subscription-auto-renew-form">
                    <label>
                        <input type="checkbox" id="subscription-auto-renew-toggle">
                        Automatically renew this subscription
                    </label>

                    <div id="subscription-auto-renew-consent" hidden>
                        <label>
                            <input type="checkbox" id="subscription-auto-renew-consent-checkbox">
                            I agree to automatic renewal and future renewal charges.
                        </label>
                    </div>

                    <div class="sub-card-full__actions">
                        <button type="submit" class="btn btn--gold btn--sm">
                            Save renewal preference
                        </button>
                    </div>

                    <div class="account-message"
                         id="subscription-auto-renew-message"
                         role="alert"
                         aria-live="polite"></div>
                </form>
            </section>
        </div>
    </div>
</div>
