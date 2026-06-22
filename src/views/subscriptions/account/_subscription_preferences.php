<section id="subscription-preference-section"
         aria-labelledby="subscription-preference-heading">
    <h3 id="subscription-preference-heading">Email preferences</h3>
    <p>Choose how often this publication contacts you.</p>

    <form id="subscription-preference-form">
        <label>
            <input type="checkbox" id="subscription-preference-active">
            Keep this publication subscription active
        </label>

        <label>
            <input type="checkbox" id="subscription-preference-email">
            Send email notifications
        </label>

        <label for="subscription-preference-frequency">Newsletter frequency</label>
        <select id="subscription-preference-frequency">
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
        </select>

        <div class="sub-card-full__actions">
            <button type="submit" class="btn btn--gold btn--sm">Save email preferences</button>
        </div>

        <div class="account-message"
             id="subscription-preference-message"
             role="alert"
             aria-live="polite"></div>
    </form>
</section>
