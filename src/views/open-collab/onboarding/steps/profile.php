<h2>Contributor Profile</h2>
<form onsubmit="event.preventDefault(); window.Onboarding.submitProfile(this)">
    @csrf
    <div class="form-group">
        <label>Bio (Visible to Readers)</label>
        <textarea name="bio" rows="4" required placeholder="Tell us about yourself..."></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save & Continue</button>
</form>