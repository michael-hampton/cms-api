<div id='newsletter-account-modal' class='newsletter-modal' style='display: none;'>
    <div class='newsletter-modal-overlay' id='modal-overlay'></div>
    <div class='newsletter-modal-content'>
        <div class='newsletter-modal-header'>
            <h3>Create Your Account</h3>
            <button type='button' class='modal-close' id='modal-close-btn' aria-label='Close'>&times;</button>
        </div>
        <div class='newsletter-modal-body'>
            <p class='modal-description'>Get access to exclusive features, saved preferences, and personalized
                content.</p>
            <form id='footer-account-creation-form'>
                <div class='form-group'>
                    <label for='modal-email-display'>Email Address</label>
                    <input type='email' id='modal-email-display' name='email' class='readonly-input'>
                    <small class='form-hint'>This is the email you used for newsletter signup</small>
                </div>

                <div class='form-row'>
                    <div class='form-group'>
                        <label for='modal-first-name'>First Name *</label>
                        <input type='text' id='modal-first-name' name='first_name' required>
                    </div>
                    <div class='form-group'>
                        <label for='modal-last-name'>Last Name *</label>
                        <input type='text' id='modal-last-name' name='last_name' required>
                    </div>
                </div>

                <div class='form-group'>
                    <label for='modal-password'>Password *</label>
                    <input type='password' id='modal-password' name='password' minlength='8' required>
                    <small class='form-hint'>Minimum 8 characters</small>
                </div>

                <div class='form-group'>
                    <label for='modal-password-confirm'>Confirm Password *</label>
                    <input type='password' id='modal-password-confirm' name='password_confirm' required>
                </div>

                <div class='form-group checkbox-group'>
                    <label>
                        <input type='checkbox' name='terms_accepted' required>
                        <span>I agree to the <a href='/terms' target='_blank'>Terms of Service</a> and <a
                                    href='/privacy' target='_blank'>Privacy Policy</a></span>
                    </label>
                </div>

                <div class='modal-actions'>
                    <button type='button' class='btn-secondary' id='modal-cancel-btn'>Skip for now</button>
                    <button type='submit' class='btn-primary' id='modal-submit-btn'>Create Account</button>
                </div>

                <div class='modal-message' id='modal-message'></div>
            </form>
        </div>
    </div>
</div>