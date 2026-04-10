<h2>Payment Details</h2>
<p>We use Stripe to process your earnings. Please provide your payout details.</p>
<form onsubmit="event.preventDefault(); window.Onboarding.submitPayment(this)">
    @csrf
    <div class="form-group">
        <label>Payment Method Type</label>
        <select name="payment_method_type" id="p_type">
            <option value="stripe">Stripe Connect</option>
            <option value="bank">Direct Bank Transfer (Manual)</option>
        </select>
    </div>
    <div class="form-group">
        <label>Account Reference / Email</label>
        <input type="text" name="payment_details" required>
    </div>
    <button type="submit" class="btn btn-primary">Setup Payment</button>
</form>