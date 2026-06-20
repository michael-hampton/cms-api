<link rel="stylesheet" href="/public/css/subscription-account-drawer.css">

<div class="modal-overlay subscription-drawer-overlay"
     id="subscription-manage-drawer"
     role="dialog"
     aria-modal="true"
     aria-labelledby="subscription-manage-title"
     hidden>
    <div class="subscription-drawer">
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

            <section id="subscription-billing-date-section"
                     aria-labelledby="subscription-billing-date-heading"
                     hidden>
                <h3 id="subscription-billing-date-heading">Billing date</h3>
                <p>Choose the day of the month on which this subscription renews.</p>

                <form id="subscription-billing-date-form">
                    <label for="subscription-billing-day">Billing day</label>

                    <select id="subscription-billing-day" name="day_of_month">
                        <?php for ($day = 1; $day <= 31; $day++): ?>
                            <option value="<?= $day ?>"><?= $day ?></option>
                        <?php endfor; ?>
                    </select>

                    <div class="sub-card-full__actions">
                        <button type="button"
                                class="btn btn--ghost btn--sm"
                                id="subscription-billing-preview">
                            Preview change
                        </button>

                        <button type="submit" class="btn btn--gold btn--sm">
                            Update billing date
                        </button>
                    </div>

                    <div class="account-message"
                         id="subscription-billing-date-message"
                         role="alert"
                         aria-live="polite"></div>
                </form>
            </section>

            @include('subscriptions/account/_subscription_delivery')
            @include('subscriptions/account/_subscription_history')
        </div>
    </div>
</div>
