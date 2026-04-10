<div class="container" style="max-width: 500px; margin: 60px auto;">
    <div class="card"
         style="padding: 2.5rem; background: #fff; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
        <h1 style="margin-bottom: 0.5rem; text-align: center;">Join as a Contributor</h1>
        <p class="text-muted" style="text-align: center; margin-bottom: 2rem;">
            Complete your profile to start writing for <strong><?= $site ?></strong>.
        </p>

        <form id="accept-invitation-form">
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="John Doe" required
                       style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required
                       style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>

            <button type="button" onclick="submitAcceptance()" class="btn btn-primary" id="submit-btn"
                    style="width: 100%; padding: 0.8rem;">
                Create Account & Get Started
            </button>
        </form>
    </div>
</div>

<script>
    async function submitAcceptance() {
        const form = document.getElementById('accept-invitation-form');
        const btn = document.getElementById('submit-btn');

        // The token is passed in the URL: /invitations/{token}/accept
        const pathParts = window.location.pathname.split('/');
        const inviteToken = pathParts[pathParts.length - 2];

        const payload = {
            name: form.name.value,
            password: form.password.value
        };

        btn.disabled = true;
        btn.innerText = 'Setting up your account...';

        try {
            const res = await fetch(`/api/<?= $site ?>/open-collab/invitations/${inviteToken}/accept`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '<?= csrf_token() ?>'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (res.ok) {
                // IMPORTANT: Save the Bearer token returned by InvitationController@accept
                localStorage.setItem('api_token', data.token);

                // Redirect to onboarding (Epic 2)
                window.location.href = '/onboarding';
            } else {
                alert(data.message || 'Validation failed. Please check your details.');
            }
        } catch (err) {
            console.error(err);
            alert('An error occurred during registration.');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Create Account & Get Started';
        }
    }
</script>