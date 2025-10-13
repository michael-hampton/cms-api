<footer class="footer">
    <div class="footer-content">
        <div class="footer-section footer-about">
            <h4>The Wine Chronicle</h4>
            <p>Expert wine criticism and education since 1975. Independent reviews from Master Sommeliers and Masters of Wine.</p>
            <div class="social-links">
                <a href="#">📘</a>
                <a href="#">🐦</a>
                <a href="#">📷</a>
                <a href="#">▶️</a>
            </div>
        </div>

        <div class="footer-section">
            <h4>Wine Reviews</h4>
            <ul>
                <li><a href="#">Bordeaux</a></li>
                <li><a href="#">Burgundy</a></li>
                <li><a href="#">Champagne</a></li>
                <li><a href="#">Tuscany</a></li>
                <li><a href="#">Napa Valley</a></li>
                <li><a href="#">All Regions</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>Wine Knowledge</h4>
            <ul>
                <li><a href="#">Beginner's Guide</a></li>
                <li><a href="#">Tasting Techniques</a></li>
                <li><a href="#">Food Pairing</a></li>
                <li><a href="#">Wine Storage</a></li>
                <li><a href="#">Grape Varieties</a></li>
                <li><a href="#">Masterclasses</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>About Us</h4>
            <ul>
                <li><a href="#">Our Story</a></li>
                <li><a href="#">Expert Team</a></li>
                <li><a href="#">Rating System</a></li>
                <li><a href="#">Editorial Policy</a></li>
                <li><a href="#">Events & Tastings</a></li>
                <li><a href="#">Contact Us</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; 2025 The Wine Chronicle. All rights reserved.</p>
        <p>
            <a href="#">Privacy Policy</a> |
            <a href="#">Terms of Service</a> |
            <a href="#">Cookie Policy</a> |
            <a href="#">Advertise</a>
        </p>
        <p style="margin-top: 1rem; font-size: 0.9rem;">
            Drink responsibly. Must be 18+ years old.
        </p>
    </div>
</footer>

<script>
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Search functionality
    const searchInput = document.querySelector('.search-bar input');
    const searchButton = document.querySelector('.search-bar button');

    searchButton.addEventListener('click', () => {
        const query = searchInput.value.trim();
        if (query) {
            console.log('Searching for:', query);
            // Add your search logic here
        }
    });

    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            searchButton.click();
        }
    });

    // Newsletter form submission
    const newsletterForm = document.querySelector('.newsletter-form');
    newsletterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(newsletterForm);
        console.log('Newsletter subscription:', Object.fromEntries(formData));
        alert('Thank you for subscribing to The Wine Chronicle!');
        newsletterForm.reset();
    });

    // Add animation on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe all cards and stat items
    document.querySelectorAll('.wine-card, .stat-item, .gallery-item').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // Sticky header shadow on scroll
    let lastScroll = 0;
    window.addEventListener('scroll', () => {
        const header = document.querySelector('.header');
        const currentScroll = window.pageYOffset;

        if (currentScroll > 100) {
            header.style.boxShadow = '0 4px 20px rgba(0,0,0,0.15)';
        } else {
            header.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        }

        lastScroll = currentScroll;
    });

    // Dynamic wine rating display (if you have ratings)
    function displayRating(score) {
        const rating = document.createElement('div');
        rating.className = 'wine-rating';
        rating.style.cssText = `
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: linear-gradient(135deg, #6B1F3D, #8B2F4D);
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-weight: 700;
                font-size: 1.1rem;
            `;

        const stars = Math.round(score / 20);
        rating.innerHTML = `
                <span>${'⭐'.repeat(stars)}</span>
                <span>${score}/100</span>
            `;

        return rating;
    }

    // Card hover effects enhancement
    document.querySelectorAll('.wine-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.borderLeft = '4px solid var(--gold)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.borderLeft = 'none';
        });
    });

    // Gallery item click handler
    document.querySelectorAll('.gallery-item').forEach(item => {
        item.addEventListener('click', function() {
            const title = this.querySelector('h4').textContent;
            console.log('Gallery item clicked:', title);
            // Add your navigation logic here
        });
    });

    // Mobile menu toggle (for responsive design)
    const createMobileMenu = () => {
        if (window.innerWidth <= 768) {
            const nav = document.querySelector('.main-nav ul');
            nav.style.display = 'none';

            const menuButton = document.createElement('button');
            menuButton.innerHTML = '☰';
            menuButton.style.cssText = `
                    background: none;
                    border: none;
                    color: white;
                    font-size: 2rem;
                    cursor: pointer;
                    padding: 0.5rem;
                `;

            const headerTop = document.querySelector('.header-top');
            headerTop.insertBefore(menuButton, headerTop.firstChild);

            menuButton.addEventListener('click', () => {
                if (nav.style.display === 'none') {
                    nav.style.display = 'flex';
                    menuButton.innerHTML = '✕';
                } else {
                    nav.style.display = 'none';
                    menuButton.innerHTML = '☰';
                }
            });
        }
    };

    // Call on load and resize
    createMobileMenu();
    window.addEventListener('resize', createMobileMenu);

    // Add loading animation
    window.addEventListener('load', () => {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
    });

    // Wine card read more animation
    document.querySelectorAll('.read-more').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const card = link.closest('.wine-card');
            card.style.transform = 'scale(0.98)';
            setTimeout(() => {
                card.style.transform = 'scale(1)';
                // Navigate to article
                window.location.href = link.href;
            }, 200);
        });
    });

    // Stats counter animation
    const animateCounter = (element, target, duration = 2000) => {
        let current = 0;
        const increment = target / (duration / 16);
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target + (element.dataset.suffix || '');
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current) + (element.dataset.suffix || '');
            }
        }, 16);
    };

    // Trigger counter animation when stats come into view
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const number = entry.target.querySelector('.stat-number');
                const targetValue = parseInt(number.textContent.replace(/\D/g, ''));
                number.dataset.suffix = number.textContent.replace(/\d/g, '');
                animateCounter(number, targetValue);
                statsObserver.unobserve(entry.target);
            }
        });
    });

    document.querySelectorAll('.stat-item').forEach(stat => {
        statsObserver.observe(stat);
    });

    // Add parallax effect to hero
    window.addEventListener('scroll', () => {
        const hero = document.querySelector('.hero');
        const scrolled = window.pageYOffset;
        if (hero && scrolled < hero.offsetHeight) {
            hero.style.backgroundPositionY = scrolled * 0.5 + 'px';
        }
    });

    // Wine badge color variations
    const badges = document.querySelectorAll('.wine-badge');
    badges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.transition = 'transform 0.3s ease';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });

    // Social links hover effect
    document.querySelectorAll('.social-links a').forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) rotate(5deg)';
        });
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) rotate(0)';
        });
    });

    // Info banner dismiss functionality
    const infoBanner = document.querySelector('.info-banner');
    if (infoBanner) {
        const dismissBtn = document.createElement('button');
        dismissBtn.innerHTML = '✕';
        dismissBtn.style.cssText = `
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--dark-burgundy);
                font-size: 1.5rem;
                cursor: pointer;
                opacity: 0.7;
                transition: opacity 0.3s ease;
            `;
        dismissBtn.addEventListener('mouseenter', () => dismissBtn.style.opacity = '1');
        dismissBtn.addEventListener('mouseleave', () => dismissBtn.style.opacity = '0.7');
        dismissBtn.addEventListener('click', () => {
            infoBanner.style.opacity = '0';
            infoBanner.style.transform = 'translateY(-20px)';
            setTimeout(() => infoBanner.remove(), 300);
        });
        infoBanner.style.position = 'relative';
        infoBanner.appendChild(dismissBtn);
    }

    // Form validation for newsletter
    const emailInput = document.querySelector('.newsletter-form input[type="email"]');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.style.borderColor = '#e74c3c';
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.style.borderColor = 'rgba(255,255,255,0.3)';
                this.setCustomValidity('');
            }
        });
    }

    // Add keyboard navigation for cards
    document.querySelectorAll('.wine-card').forEach((card, index) => {
        card.setAttribute('tabindex', '0');
        card.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const link = card.querySelector('.read-more');
                if (link) link.click();
            }
        });
    });

    // Console log for developers
    console.log('%c🍷 The Wine Chronicle', 'color: #6B1F3D; font-size: 24px; font-weight: bold;');
    console.log('%cExpert wine reviews since 1975', 'color: #D4AF37; font-size: 14px;');
</script>
</body>
</html>