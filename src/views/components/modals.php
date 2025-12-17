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
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Subscribe to Newsletter</h3>
            <button class="modal-close" onclick="closeNewsletterModal()">
                <svg viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-message" id="newsletterMessage"></div>
            <p class="newsletter-description">
                Stay updated with our latest articles, exclusive content, and special offers delivered directly to your
                inbox.
            </p>

            <div class="newsletter-benefits">
                <div class="newsletter-benefit">
                    <svg class="newsletter-benefit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span class="newsletter-benefit-text">Weekly curated content</span>
                </div>
                <div class="newsletter-benefit">
                    <svg class="newsletter-benefit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span class="newsletter-benefit-text">Exclusive member deals</span>
                </div>
                <div class="newsletter-benefit">
                    <svg class="newsletter-benefit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span class="newsletter-benefit-text">No spam, unsubscribe anytime</span>
                </div>
            </div>

            <form id="newsletterForm">
                <div class="comment-form-group">
                    <label class="comment-label">Email Address</label>
                    <input type="email" class="comment-input" id="newsletterEmail" required
                           placeholder="your@email.com">
                </div>

                <button type="submit" class="comment-submit">Subscribe Now</button>
            </form>
        </div>
    </div>
</div>