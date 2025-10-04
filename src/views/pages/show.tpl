<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premier Properties - Your Dream Home Awaits</title>
    @css('app.css')
</head>
<body>
<!-- Header -->
<header class="header">
    <nav class="nav">
        <a href="#home" class="logo">Premier Properties</a>
        <ul class="nav-menu">
            <li><a href="#home">Home</a></li>
            <li><a href="#properties">Properties</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <a href="#contact" class="cta-button">Get Started</a>
        <button class="mobile-menu-toggle">☰</button>
    </nav>
</header>

<!-- Hero Section -->
<section id="home" class="hero">
    <div class="hero-content">
        <h1>Find Your Perfect Home</h1>
        <p>Discover exceptional properties with Premier Properties. From luxury estates to cozy starter homes, we help you find the perfect place to call home.</p>

        <div class="hero-buttons">
            <a href="#properties" class="cta-button">Browse Properties</a>
            <a href="#services" class="btn-secondary">Our Services</a>
        </div>

        <!-- Property Search -->
        <div class="search-section">
            <form class="search-form">
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" placeholder="Enter city or postcode" />
                </div>
                <div class="form-group">
                    <label for="property-type">Property Type</label>
                    <select id="property-type">
                        <option value="">Any Type</option>
                        <option value="house">House</option>
                        <option value="apartment">Apartment</option>
                        <option value="townhouse">Townhouse</option>
                        <option value="villa">Villa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price-range">Price Range</label>
                    <select id="price-range">
                        <option value="">Any Price</option>
                        <option value="0-300000">Up to £300,000</option>
                        <option value="300000-600000">£300,000 - £600,000</option>
                        <option value="600000-1000000">£600,000 - £1,000,000</option>
                        <option value="1000000+">£1,000,000+</option>
                    </select>
                </div>
                <button type="submit" class="cta-button">Search</button>
            </form>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats">
    <div class="stats-container">
        <div class="stat-item">
            <div class="stat-number">500+</div>
            <div class="stat-label">Properties Sold</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">15+</div>
            <div class="stat-label">Years Experience</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">98%</div>
            <div class="stat-label">Client Satisfaction</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">50+</div>
            <div class="stat-label">Expert Agents</div>
        </div>
    </div>
</section>

<!-- Featured Properties -->
<section id="properties" class="featured">
    <div class="section-header">
        <h2 class="section-title">Featured Properties</h2>
        <p class="section-subtitle">Discover our handpicked selection of premium properties, carefully curated to match your lifestyle and investment goals.</p>
    </div>

    <div class="properties-grid">
        <!-- Property 1 -->
        <div class="property-card">
            <div class="property-image">
                <div class="property-badge">For Sale</div>
                <div class="property-price">£750,000</div>
            </div>
            <div class="property-content">
                <h3 class="property-title">Modern Victorian Townhouse</h3>
                <div class="property-location">📍 Kensington, London</div>
                <div class="property-features">
                    <div class="feature">🛏️ 4 Bedrooms</div>
                    <div class="feature">🚿 3 Bathrooms</div>
                    <div class="feature">📏 2,400 sq ft</div>
                </div>
                <div class="property-actions">
                    <a href="#" class="btn-outline">View Details</a>
                    <a href="#" class="btn-outline">Schedule Tour</a>
                </div>
            </div>
        </div>

        <!-- Property 3 -->
        <div class="property-card">
            <div class="property-image">
                <div class="property-badge">New Listing</div>
                <div class="property-price">£1,200,000</div>
            </div>
            <div class="property-content">
                <h3 class="property-title">Contemporary Family Home</h3>
                <div class="property-location">📍 Richmond, London</div>
                <div class="property-features">
                    <div class="feature">🛏️ 5 Bedrooms</div>
                    <div class="feature">🚿 4 Bathrooms</div>
                    <div class="feature">📏 3,200 sq ft</div>
                </div>
                <div class="property-actions">
                    <a href="#" class="btn-outline">View Details</a>
                    <a href="#" class="btn-outline">Schedule Tour</a>
                </div>
            </div>
        </div>

        <!-- Property 4 -->
        <div class="property-card">
            <div class="property-image">
                <div class="property-badge">Price Reduced</div>
                <div class="property-price">£450,000</div>
            </div>
            <div class="property-content">
                <h3 class="property-title">Charming Garden Flat</h3>
                <div class="property-location">📍 Hampstead, London</div>
                <div class="property-features">
                    <div class="feature">🛏️ 2 Bedrooms</div>
                    <div class="feature">🚿 1 Bathroom</div>
                    <div class="feature">📏 950 sq ft</div>
                </div>
                <div class="property-actions">
                    <a href="#" class="btn-outline">View Details</a>
                    <a href="#" class="btn-outline">Schedule Tour</a>
                </div>
            </div>
        </div>

        <!-- Property 5 -->
        <div class="property-card">
            <div class="property-image">
                <div class="property-badge">Featured</div>
                <div class="property-price">£2,800,000</div>
            </div>
            <div class="property-content">
                <h3 class="property-title">Executive Penthouse Suite</h3>
                <div class="property-location">📍 Mayfair, London</div>
                <div class="property-features">
                    <div class="feature">🛏️ 3 Bedrooms</div>
                    <div class="feature">🚿 3 Bathrooms</div>
                    <div class="feature">📏 2,800 sq ft</div>
                </div>
                <div class="property-actions">
                    <a href="#" class="btn-outline">View Details</a>
                    <a href="#" class="btn-outline">Schedule Tour</a>
                </div>
            </div>
        </div>

        <!-- Property 6 -->
        <div class="property-card">
            <div class="property-image">
                <div class="property-badge">Coming Soon</div>
                <div class="property-price">£680,000</div>
            </div>
            <div class="property-content">
                <h3 class="property-title">Riverside Conversion</h3>
                <div class="property-location">📍 Greenwich, London</div>
                <div class="property-features">
                    <div class="feature">🛏️ 3 Bedrooms</div>
                    <div class="feature">🚿 2 Bathrooms</div>
                    <div class="feature">📏 1,800 sq ft</div>
                </div>
                <div class="property-actions">
                    <a href="#" class="btn-outline">View Details</a>
                    <a href="#" class="btn-outline">Register Interest</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section id="services" class="services">
    <div class="section-header">
        <h2 class="section-title">Our Services</h2>
        <p class="section-subtitle">From property search to closing day and beyond, we provide comprehensive real estate services tailored to your needs.</p>
    </div>

    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon">🏡</div>
            <h3 class="service-title">Property Sales</h3>
            <p class="service-description">Expert guidance through the entire selling process, from market valuation to completion. We ensure maximum value and minimal stress.</p>
        </div>

        <div class="service-card">
            <div class="service-icon">🔍</div>
            <h3 class="service-title">Property Search</h3>
            <p class="service-description">Our dedicated team helps you find the perfect property that matches your criteria, budget, and lifestyle requirements.</p>
        </div>

        <div class="service-card">
            <div class="service-icon">💰</div>
            <h3 class="service-title">Investment Advisory</h3>
            <p class="service-description">Strategic property investment advice to help you build a profitable portfolio and secure your financial future.</p>
        </div>

        <div class="service-card">
            <div class="service-icon">📊</div>
            <h3 class="service-title">Market Analysis</h3>
            <p class="service-description">Comprehensive market reports and trend analysis to help you make informed decisions in today's dynamic property market.</p>
        </div>

        <div class="service-card">
            <div class="service-icon">🔧</div>
            <h3 class="service-title">Property Management</h3>
            <p class="service-description">Full-service property management for landlords, handling everything from tenant screening to maintenance coordination.</p>
        </div>

        <div class="service-card">
            <div class="service-icon">⚖️</div>
            <h3 class="service-title">Legal Support</h3>
            <p class="service-description">Professional legal assistance throughout your property transaction, ensuring all documentation and contracts are properly handled.</p>
        </div>
    </div>
</section>

<!-- About Section -->
<section id="about" class="about">
    <div class="about-container">
        <div class="about-content">
            <h2>About Premier Properties</h2>
            <p>For over 15 years, Premier Properties has been the trusted name in luxury real estate across London and the Home Counties. Our team of experienced professionals combines deep market knowledge with personalized service to deliver exceptional results for our clients.</p>

            <p>We understand that buying or selling a property is one of life's most significant decisions. That's why we're committed to providing expert guidance, innovative marketing strategies, and unwavering support throughout your real estate journey.</p>

            <div class="about-stats">
                <div class="about-stat">
                    <div class="about-stat-number">£2.5B</div>
                    <div class="stat-label">Properties Sold</div>
                </div>
                <div class="about-stat">
                    <div class="about-stat-number">4.9★</div>
                    <div class="stat-label">Client Rating</div>
                </div>
            </div>

            <a href="#contact" class="cta-button" style="display: inline-block; margin-top: 2rem;">Meet Our Team</a>
        </div>

        <div class="about-image">
            🏢
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials">
    <div class="testimonials-container">
        <div class="section-header">
            <h2 class="section-title">What Our Clients Say</h2>
            <p class="section-subtitle">Don't just take our word for it - hear from satisfied clients who have experienced our exceptional service firsthand.</p>
        </div>

        <div class="testimonials-grid">
            <div class="testimonial-card">
                <p class="testimonial-text">"Premier Properties made our home buying experience seamless and stress-free. Their attention to detail and market expertise helped us find our dream home at the perfect price."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">SJ</div>
                    <div class="author-info">
                        <h4>Sarah Johnson</h4>
                        <p>First-time Buyer</p>
                    </div>
                </div>
            </div>

            <div class="testimonial-card">
                <p class="testimonial-text">"The team's professionalism and dedication exceeded our expectations. They sold our property 20% above asking price and handled every detail with care."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">MC</div>
                    <div class="author-info">
                        <h4>Michael Chen</h4>
                        <p>Property Investor</p>
                    </div>
                </div>
            </div>

            <div class="testimonial-card">
                <p class="testimonial-text">"Outstanding service from start to finish. The market analysis was thorough and the marketing strategy was brilliant. Highly recommended!"</p>
                <div class="testimonial-author">
                    <div class="author-avatar">EP</div>
                    <div class="author-info">
                        <h4>Emma Phillips</h4>
                        <p>Property Seller</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="contact">
    <div class="section-header">
        <h2 class="section-title">Get In Touch</h2>
        <p class="section-subtitle">Ready to start your property journey? Contact our expert team today for personalized advice and exceptional service.</p>
    </div>

    <div class="contact-container">
        <div class="contact-info">
            <h3>Contact Information</h3>
            <p style="margin-bottom: 2rem;">We're here to help with all your property needs. Reach out to us through any of the following channels.</p>

            <div class="contact-item">
                <div class="contact-icon">📍</div>
                <div>
                    <h4>Visit Our Office</h4>
                    <p>123 Premium Street<br>London, SW1A 1AA</p>
                </div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">📞</div>
                <div>
                    <h4>Call Us</h4>
                    <p>+44 20 7123 4567<br>Mon-Fri: 9AM-6PM</p>
                </div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">✉️</div>
                <div>
                    <h4>Email Us</h4>
                    <p>info@premierproperties.co.uk<br>sales@premierproperties.co.uk</p>
                </div>
            </div>

            <div class="social-links">
                <a href="#" class="social-link">📘</a>
                <a href="#" class="social-link">📷</a>
                <a href="#" class="social-link">🐦</a>
                <a href="#" class="social-link">💼</a>
            </div>
        </div>

        <div class="contact-form">
            <h3>Send Us a Message</h3>
            <form>
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" placeholder="First Name" required />
                    </div>
                    <div class="form-group">
                        <input type="text" placeholder="Last Name" required />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <input type="email" placeholder="Email Address" required />
                    </div>
                    <div class="form-group">
                        <input type="tel" placeholder="Phone Number" />
                    </div>
                </div>

                <div class="form-group">
                    <select required>
                        <option value="">Select Service</option>
                        <option value="buying">Property Buying</option>
                        <option value="selling">Property Selling</option>
                        <option value="renting">Property Renting</option>
                        <option value="investment">Investment Advisory</option>
                        <option value="valuation">Property Valuation</option>
                        <option value="other">Other Inquiry</option>
                    </select>
                </div>

                <div class="form-group">
                    <textarea placeholder="Tell us about your property needs..." required></textarea>
                </div>

                <button type="submit" class="cta-button" style="width: 100%;">Send Message</button>
            </form>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-section">
            <h3>Premier Properties</h3>
            <p>Your trusted partner in luxury real estate across London and the Home Counties. We're committed to delivering exceptional service and outstanding results.</p>
            <div class="social-links">
                <a href="#" class="social-link">📘</a>
                <a href="#" class="social-link">📷</a>
                <a href="#" class="social-link">🐦</a>
                <a href="#" class="social-link">💼</a>
            </div>
        </div>

        <div class="footer-section">
            <h3>Quick Links</h3>
            <a href="#properties">Properties</a>
            <a href="#services">Services</a>
            <a href="#about">About Us</a>
            <a href="#contact">Contact</a>
            <a href="#">Careers</a>
            <a href="#">Blog</a>
        </div>

        <div class="footer-section">
            <h3>Services</h3>
            <a href="#">Property Sales</a>
            <a href="#">Property Rentals</a>
            <a href="#">Investment Advisory</a>
            <a href="#">Property Management</a>
            <a href="#">Market Analysis</a>
            <a href="#">Valuations</a>
        </div>

        <div class="footer-section">
            <h3>Contact Info</h3>
            <p>📍 123 Premium Street<br>London, SW1A 1AA</p>
            <p>📞 +44 20 7123 4567</p>
            <p>✉️ info@premierproperties.co.uk</p>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; 2025 Premier Properties. All rights reserved. | Privacy Policy | Terms of Service | Cookie Policy</p>
    </div>
</footer>

@foreach($html as $block)
{!! $block !!}
@endforeach