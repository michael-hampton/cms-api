<div class="container" style="max-width: 800px; margin: 40px auto;">
    <div class="card" style="padding: 2rem; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h1 style="margin-top: 0;">Invite Contributor</h1>
        <p class="text-muted">Send an invitation link to a writer. They will be able to register and start creating
            content for your site.</p>

        <hr style="margin: 2rem 0; border: 0; border-top: 1px solid #f1f5f9;">

        <form id="invite-form">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="writer@example.com" required
                       style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>

            <button type="button" onclick="sendInvitation()" class="btn btn-primary" id="submit-btn">
                Send Invitation Link
            </button>
        </form>

        <div id="success-message"
             style="display: none; margin-top: 1.5rem; padding: 1rem; background: #dcfce7; color: #166534; border-radius: 6px;">
            Invitation sent successfully! A link has been dispatched to the email provided.
        </div>
    </div>
</div>

<script>
    async function sendInvitation() {
        const form = document.getElementById('invite-form');
        const btn = document.getElementById('submit-btn');
        const email = form.email.value;

        if (!email) return alert('Please enter an email.');

        btn.disabled = true;
        btn.innerText = 'Sending...';

        const token = localStorage.getItem('api_token');

        try {
            const res = await fetch(`/api/<?= $site ?>/open-collab/invitations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '<?= csrf_token() ?>',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({email: email})
            });

            const data = await res.json();

            if (res.ok) {
                document.getElementById('success-message').style.display = 'block';
                form.reset();
            } else {
                alert(data.message || 'Failed to send invitation.');
            }
        } catch (err) {
            console.error(err);
            alert('A network error occurred.');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Send Invitation Link';
        }
    }
</script>