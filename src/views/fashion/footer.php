<footer class="fashion-footer">
    <div class="footer-container">
        <div class="footer-main">
            <div class="footer-section footer-brand">
                <div class="footer-logo">
                    <span class="logo-main">VOGUE</span>
                    <span class="logo-sub">NOIR</span>
                </div>
                <p class="footer-tagline">Fashion Forward. Always.</p>
                <div class="footer-social">
                    <a href="#" class="footer-social-link" aria-label="Instagram">📷</a>
                    <a href="#" class="footer-social-link" aria-label="Twitter">🐦</a>
                    <a href="#" class="footer-social-link" aria-label="Pinterest">📌</a>
                    <a href="#" class="footer-social-link" aria-label="Facebook">📘</a>
                </div>
            </div>

            <div class="footer-section">
                <h4 class="footer-title">Fashion</h4>
                <ul class="footer-links">
                    <li><a href="/runway">Runway</a></li>
                    <li><a href="/street-style">Street Style</a></li>
                    <li><a href="/trends">Trends</a></li>
                    <li><a href="/designers">Designers</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4 class="footer-title">Beauty</h4>
                <ul class="footer-links">
                    <li><a href="/makeup">Makeup</a></li>
                    <li><a href="/skincare">Skincare</a></li>
                    <li><a href="/hair">Hair</a></li>
                    <li><a href="/reviews">Reviews</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4 class="footer-title">About</h4>
                <ul class="footer-links">
                    <li><a href="/about">About Us</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/advertise">Advertise</a></li>
                    <li><a href="/careers">Careers</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4 class="footer-title">Newsletter</h4>
                <p class="footer-newsletter-text">Get the latest fashion news delivered to your inbox.</p>
                <form class="footer-newsletter-form" action="/newsletter/subscribe" method="POST">
                    <input type="email" name="email" placeholder="Your email" required class="newsletter-input">
                    <button type="submit" class="newsletter-button">Subscribe</button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-copyright">&copy; <?= date('Y') ?> VOGUE NOIR. All rights reserved.</p>
            <div class="footer-legal">
                <a href="/privacy">Privacy Policy</a>
                <span class="separator">|</span>
                <a href="/terms">Terms of Service</a>
                <span class="separator">|</span>
                <a href="/cookies">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>
</body>
</html>