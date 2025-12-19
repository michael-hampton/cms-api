<div class="modal-overlay" id="commentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Leave a Comment</h3>
            <button class="modal-close" onclick="closeCommentModal()">
                <svg viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-message" id="commentMessage"></div>
            <form id="commentForm">
                <input type="hidden" id="commentPageUrl" name="pageUrl">
                <input type="hidden" id="commentPageId" name="pageId">

                <div class="comment-form-group">
                    <label class="comment-label">Name</label>
                    <input type="text" class="comment-input" id="commentName" required placeholder="Your name">
                </div>

                <div class="comment-form-group">
                    <label class="comment-label">Email</label>
                    <input type="email" class="comment-input" id="commentEmail" required placeholder="your@email.com">
                </div>

                <div class="comment-form-group">
                    <label class="comment-label">Comment</label>
                    <textarea class="comment-textarea" id="commentText" required maxlength="1000"
                              placeholder="Share your thoughts..."></textarea>
                    <div class="comment-char-count">
                        <span id="commentCharCount">0</span> / 1000 characters
                    </div>
                </div>

                <button type="submit" class="comment-submit">Post Comment</button>
            </form>
        </div>
    </div>
</div>

<!-- Newsletter Modal -->
<div class="modal-overlay" id="newsletterModal">
    <div class="modal-content newsletter-modal-content">
        <button class="modal-close" onclick="closeNewsletterModal()">
            <svg viewBox="0 0 24 24">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <!-- Step 1: Main Newsletter Form -->
        <div id="newsletterStep1">
            <div class="newsletter-logo">
                <img src="https://via.placeholder.com/150x50/6366f1/ffffff?text=FourFourTwo" alt="Logo">
            </div>

            <div class="modal-header">
                <h2 class="modal-title newsletter-title">Get the FourFourTwo Newsletter</h2>
                <p class="newsletter-subtitle">The best features, fun and footballing quizzes, straight to your inbox
                    every week.</p>
            </div>

            <div class="modal-body">
                <div class="form-message" id="newsletterMessage"></div>

                <form id="newsletterForm">
                    <div class="newsletter-input-group">
                        <input type="email"
                               class="newsletter-input"
                               id="newsletterEmail"
                               required
                               placeholder="Your Email Address">
                        <button type="submit" class="newsletter-submit" id="newsletterSubmitBtn">SIGN ME UP</button>
                    </div>

                    <div class="newsletter-checkboxes">
                        <label class="newsletter-checkbox">
                            <input type="checkbox" id="createAccount" name="createAccount">
                            <span>Create an account for me<br>
                                <small>Get instant access to exclusive member features and save your preferences</small>
                            </span>
                        </label>

                        <!-- Account fields - shown when create account is checked -->
                        <div id="accountFields" class="account-fields-container">
                            <div class="account-fields-grid">
                                <input type="text"
                                       class="newsletter-input account-field"
                                       id="firstName"
                                       name="firstName"
                                       placeholder="First Name">
                                <input type="text"
                                       class="newsletter-input account-field"
                                       id="lastName"
                                       name="lastName"
                                       placeholder="Last Name">
                            </div>
                            <input type="password"
                                   class="newsletter-input account-field"
                                   id="accountPassword"
                                   name="password"
                                   placeholder="Password (min 8 characters)"
                                   minlength="8">
                        </div>

                        <label class="newsletter-checkbox">
                            <input type="checkbox" id="otherBrands" name="otherBrands">
                            <span>Contact me with news and offers from other Future brands<br>
                                <small>Receive email from us on behalf of our trusted partners or sponsors</small>
                            </span>
                        </label>

                        <label class="newsletter-checkbox">
                            <input type="checkbox" id="trustedPartners" name="trustedPartners">
                            <span>Receive email from us on behalf of our trusted partners or sponsors</span>
                        </label>
                    </div>

                    <p class="newsletter-terms">
                        By submitting your information you agree to the
                        <a href="/terms" target="_blank">Terms & Conditions</a> and
                        <a href="/privacy" target="_blank">Privacy Policy</a> and are aged 16 or over.
                    </p>
                </form>
            </div>
        </div>

        <!-- Step 2: Success + Other Newsletters -->
        <div id="newsletterStep2" style="display: none;">
            <div class="newsletter-success-header">
                <div class="success-checkmark">
                    <svg viewBox="0 0 52 52" width="80" height="80">
                        <circle cx="26" cy="26" r="25" fill="none" stroke="#10b981" stroke-width="2"/>
                        <path fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round"
                              d="M14 27l7.5 7.5L38 18"/>
                    </svg>
                </div>
                <h2 id="successTitle">You are now subscribed.</h2>
                <p id="successMessage">Your newsletter sign-up was successful.</p>
            </div>

            <div class="more-newsletters-section">
                <h3>Want to add more newsletters?</h3>

                <div class="newsletters-grid">
                    <!-- First card is static - the one they just subscribed to -->
                    <div class="newsletter-card">
                        <div class="newsletter-card-image"
                             style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="newsletter-card-badge">FIVE TIMES A WEEK</div>
                        </div>
                        <div class="newsletter-card-content">
                            <h4>FourFourTwo Daily</h4>
                            <p>Fantastic football content straight to your inbox daily: breaking news, quizzes, videos,
                                features and interviews with the biggest names in the game.</p>
                            <button class="newsletter-card-btn selected">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                     stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                SELECTED
                            </button>
                        </div>
                    </div>
                    <!-- Additional newsletters will be dynamically added here by JavaScript -->
                </div>

                <div class="newsletter-footer-section">
                    <button class="explore-btn" onclick="closeNewsletterModal()">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <path d="M12 2v20M2 12h20"/>
                        </svg>
                        EXPLORE
                    </button>
                    <p>Keep this banner visible even if the user has already opted out via the link but not closed the
                        window/tab.</p>
                </div>
            </div>
        </div>
    </div>
</div>