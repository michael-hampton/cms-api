<section id="subscription-delivery-section" aria-labelledby="subscription-delivery-heading" hidden>
    <h3 id="subscription-delivery-heading">Print delivery</h3>
    <p id="subscription-delivery-status">Manage temporary delivery pauses for this print subscription.</p>

    <form id="subscription-delivery-form">
        <label for="subscription-delivery-start">Pause from</label>
        <input type="date" id="subscription-delivery-start" name="pause_start" required>

        <label for="subscription-delivery-end">Resume after</label>
        <input type="date" id="subscription-delivery-end" name="pause_end" required>

        <label for="subscription-delivery-reason">Reason (optional)</label>
        <input type="text" id="subscription-delivery-reason" name="reason">

        <div class="sub-card-full__actions">
            <button type="submit" class="btn btn--gold btn--sm">Pause print delivery</button>
            <button type="button" class="btn btn--ghost btn--sm" id="subscription-delivery-resume" hidden>Resume print delivery</button>
        </div>

        <div class="account-message" id="subscription-delivery-message" role="alert" aria-live="polite"></div>
    </form>
</section>
