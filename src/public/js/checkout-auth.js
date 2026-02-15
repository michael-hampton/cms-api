// checkout-auth.js
// Frontend implementation for OTP authentication flow

const CheckoutAuth = {
    otpModalOpen: false,
    currentEmail: null,
    otpExpiresAt: null,
    countdownInterval: null,
    emailSaved: null,

    /**
     * Initialize checkout authentication
     * Call this when checkout page loads
     */
    init() {
        this.attachEmailValidation();
        this.createOTPModal();
        this.checkForPendingOTP(); // Check for interrupted flow
    },

    /**
     * Check if there's a pending OTP from previous session
     * Handles browser close/reopen scenario
     */
    async checkForPendingOTP() {
        try {
            const response = await fetch(`${API_BASE}/checkout/pending-otp`);
            const result = await response.json();

            if (result.has_pending) {
                // Interrupted flow detected!
                this.currentEmail = result.email;
                this.otpExpiresAt = Date.now() + (result.expires_in * 1000);

                // Show notification banner
                this.showPendingOTPBanner(result);

                // Auto-show modal if expires_in > 0
                if (result.expires_in > 0) {
                    this.showOTPModal();
                    this.startCountdown();
                }
            }
        } catch (error) {
            console.error('Error checking pending OTP:', error);
        }
    },

    /**
     * Show banner for pending OTP verification
     */
    showPendingOTPBanner(data) {
        const banner = document.createElement('div');
        banner.id = 'pending-otp-banner';
        banner.className = 'otp-banner';
        banner.innerHTML = `
            <div class="otp-banner-content">
                <span class="otp-banner-icon">⏱️</span>
                <div class="otp-banner-text">
                    <strong>Verification Pending</strong>
                    <p>Complete verification for ${data.email} to continue</p>
                </div>
                <button onclick="CheckoutAuth.resumeOTPFlow()" class="btn btn-primary">
                    Continue Verification
                </button>
                <button onclick="CheckoutAuth.cancelOTPFlow()" class="btn-link">
                    Cancel
                </button>
            </div>
        `;

        // Insert at top of page
        const container = document.querySelector('.container') || document.body;
        container.insertAdjacentElement('afterbegin', banner);
    },

    /**
     * Resume OTP flow (from banner click)
     */
    resumeOTPFlow() {
        this.showOTPModal();
        this.startCountdown();

        // Remove banner
        const banner = document.getElementById('pending-otp-banner');
        if (banner) banner.remove();
    },

    /**
     * Cancel interrupted OTP flow
     */
    async cancelOTPFlow() {
        if (!confirm('Cancel verification and start over?')) return;

        try {
            await fetch(`${API_BASE}/checkout/cancel-otp`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: this.currentEmail})
            });

            // Remove banner
            const banner = document.getElementById('pending-otp-banner');
            if (banner) banner.remove();

            this.emailSaved = null;

            // Hide modal
            this.hideOTPModal();

            // Clear email field to start fresh
            const emailField = document.querySelector('[name="email"]');
            if (emailField) emailField.value = '';

            this.currentEmail = null;

        } catch (error) {
            console.error('Error cancelling OTP:', error);
        }
    },

    /**
     * Attach email field validation and flow detection
     */
    attachEmailValidation() {
        const emailField = document.querySelector('[name="email"]');
        if (!emailField) return;

        // Validate on blur (when user leaves email field)
        emailField.addEventListener('blur', async (e) => {
            const email = e.target.value.trim();
            if (!email || !this.isValidEmail(email) || email === this.emailSaved) return;

            await this.checkEmailFlow(email);
            this.emailSaved = email;
        });
    },

    /**
     * Check which flow to use (anonymous vs OTP)
     */
    async checkEmailFlow(email) {
        try {
            const response = await fetch(`${API_BASE}/checkout/verify-email`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email})
            });

            const result = await response.json();
            const responseData = result.data

            if (responseData.flow === 'otp') {
                // Existing member - show OTP modal
                this.currentEmail = email;
                this.otpExpiresAt = Date.now() + (responseData.expires_in * 1000);
                this.showOTPModal();
                this.startCountdown();
            } else {
                // New email - continue with anonymous checkout
                this.hideOTPModal();
            }
        } catch (error) {
            console.error('Email verification error:', error);
        }
    },

    /**
     * Create OTP modal HTML
     */
    createOTPModal() {
        const modalHTML = `
            <div id="otp-modal" class="otp-modal" style="display: none;">
                <div class="otp-overlay"></div>
                <div class="otp-content">
                    <div class="otp-header">
                        <h3>Verify Your Email</h3>
                        <button class="otp-close" onclick="CheckoutAuth.hideOTPModal()">&times;</button>
                    </div>
                    
                    <div class="otp-body">
                        <p>We've sent a 6-digit code to:</p>
                        <p class="otp-email"><strong id="otp-email-display"></strong></p>
                        
                        <div class="otp-input-group">
                            <input type="text" 
                                   id="otp-input" 
                                   class="otp-input" 
                                   maxlength="6" 
                                   placeholder="000000"
                                   autocomplete="one-time-code"
                                   inputmode="numeric"
                                   pattern="[0-9]*">
                        </div>
                        
                        <div id="otp-error" class="otp-error" style="display: none;"></div>
                        
                        <button id="otp-verify-btn" 
                                class="btn btn-primary" 
                                onclick="CheckoutAuth.verifyOTP()">
                            Verify Code
                        </button>
                        
                        <div class="otp-footer">
                            <p class="otp-countdown">
                                Code expires in: <span id="otp-countdown">5:00</span>
                            </p>
                            <button id="otp-resend-btn" 
                                    class="btn-link" 
                                    onclick="CheckoutAuth.resendOTP()"
                                    disabled>
                                Resend Code
                            </button>
                        </div>
                        
                        <div class="otp-help">
                            <button class="btn-link" onclick="CheckoutAuth.cancelOTPFlow()">
                                Start Over
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.attachOTPInputHandlers();
    },

    /**
     * Attach handlers for OTP input
     */
    attachOTPInputHandlers() {
        const otpInput = document.getElementById('otp-input');
        if (!otpInput) return;

        // Auto-submit when 6 digits entered
        otpInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');

            if (e.target.value.length === 6) {
                this.verifyOTP();
            }
        });

        // Enter key to submit
        otpInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.verifyOTP();
            }
        });
    },

    /**
     * Show OTP modal
     */
    showOTPModal() {
        const modal = document.getElementById('otp-modal');
        const emailDisplay = document.getElementById('otp-email-display');

        if (modal && emailDisplay) {
            emailDisplay.textContent = this.currentEmail;
            modal.style.display = 'flex';
            this.otpModalOpen = true;

            // Focus on input
            setTimeout(() => {
                document.getElementById('otp-input').focus();
            }, 100);
        }
    },

    /**
     * Hide OTP modal
     */
    hideOTPModal() {
        const modal = document.getElementById('otp-modal');
        if (modal) {
            modal.style.display = 'none';
            this.otpModalOpen = false;
            this.clearCountdown();
            this.emailSaved = null;
        }
    },

    /**
     * Verify OTP code
     */
    async verifyOTP() {
        const otpInput = document.getElementById('otp-input');
        const verifyBtn = document.getElementById('otp-verify-btn');
        const errorDiv = document.getElementById('otp-error');

        const otp = otpInput.value.trim();

        if (otp.length !== 6) {
            this.showError('Please enter a 6-digit code');
            return;
        }

        // Disable button during verification
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verifying...';

        try {
            const response = await fetch(`${API_BASE}/checkout/verify-otp`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    email: this.currentEmail,
                    otp: otp
                })
            });

            const result = await response.json();

            if (result.success) {
                // Success - authenticated
                this.showSuccess('Verified! Continuing checkout...');

                // Wait briefly then hide modal and continue
                setTimeout(() => {
                    this.hideOTPModal();
                    this.onAuthenticationSuccess(result.member);
                }, 1000);
            } else {
                // Failed
                this.showError(result.message);
                otpInput.value = '';
                otpInput.focus();

                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify Code';
            }
        } catch (error) {
            console.error('OTP verification error:', error);
            this.showError('Verification failed. Please try again.');
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Verify Code';
        }
    },

    /**
     * Resend OTP code
     */
    async resendOTP() {
        const resendBtn = document.getElementById('otp-resend-btn');
        const originalText = resendBtn.textContent;

        resendBtn.disabled = true;
        resendBtn.textContent = 'Sending...';

        try {
            const response = await fetch(`${API_BASE}/checkout/resend-otp`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: this.currentEmail})
            });

            const result = await response.json();

            if (result.success) {
                this.otpExpiresAt = Date.now() + (result.expires_in * 1000);
                this.startCountdown();
                this.showSuccess('New code sent!');

                // Re-enable after 5 minutes
                setTimeout(() => {
                    resendBtn.disabled = false;
                    resendBtn.textContent = originalText;
                }, 300000);
            } else {
                this.showError(result.message);
                resendBtn.disabled = false;
                resendBtn.textContent = originalText;
            }
        } catch (error) {
            console.error('Resend OTP error:', error);
            this.showError('Failed to resend code. Please try again.');
            resendBtn.disabled = false;
            resendBtn.textContent = originalText;
        }
    },

    /**
     * Start countdown timer
     */
    startCountdown() {
        this.clearCountdown();

        const countdownEl = document.getElementById('otp-countdown');
        const resendBtn = document.getElementById('otp-resend-btn');

        if (!countdownEl) return;

        this.countdownInterval = setInterval(() => {
            const remaining = Math.max(0, this.otpExpiresAt - Date.now());

            if (remaining <= 0) {
                countdownEl.textContent = '0:00';
                if (resendBtn) resendBtn.disabled = false;
                this.clearCountdown();
                return;
            }

            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);
            countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
    },

    /**
     * Clear countdown timer
     */
    clearCountdown() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    },

    /**
     * Handle successful authentication
     */
    onAuthenticationSuccess(member) {
        // Update UI to show authenticated state
        console.log('Authenticated:', member);

        // Remove pending banner if exists
        const banner = document.getElementById('pending-otp-banner');
        if (banner) banner.remove();

        // You can now:
        // 1. Pre-fill member details
        // 2. Show saved addresses/cards
        // 3. Continue with checkout

        // Reload saved data
        if (typeof loadSavedAddresses === 'function') {
            loadSavedAddresses();
        }
        if (typeof loadSavedCards === 'function') {
            loadSavedCards();
        }
    },

    /**
     * Show error message
     */
    showError(message) {
        const errorDiv = document.getElementById('otp-error');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            errorDiv.style.color = 'var(--danger-color)';
        }
    },

    /**
     * Show success message
     */
    showSuccess(message) {
        const errorDiv = document.getElementById('otp-error');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            errorDiv.style.color = 'var(--success-color)';
        }
    },

    /**
     * Validate email format
     */
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
};

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    CheckoutAuth.init();
});