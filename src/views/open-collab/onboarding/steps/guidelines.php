<form onsubmit="event.preventDefault(); window.Onboarding.submitGuidelines(this)">
    <h2>Brand Guidelines</h2>
    <p>Please review our latest writing and brand guidelines before publishing.</p>
    <div class="card" style="margin-bottom: 1rem;">
        <a href="/guidelines" target="_blank">Read Guidelines (Version {{ $site_obj->guidelines_version ?? 1 }})</a>
    </div>
    <input type="hidden" name="version" value="{{ $site_obj->guidelines_version ?? 1 }}">
    <button type="submit" class="btn-primary">I Have Read the Guidelines</button>
</form>