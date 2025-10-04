<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Premier Properties') ?></title>
    <meta name="description" content="<?= htmlspecialchars($description ?? 'Premier Properties - Luxury Real Estate in London') ?>">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #f59e0b;
            --accent: #10b981;
            --text: #1f2937;
            --text-light: #6b7280;
            --bg: #ffffff;
            --bg-light: #f9fafb;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text);
            background: var(--bg);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-menu a {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-menu a:hover {
            color: var(--primary);
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }

        /* Hero Block */
        .hero-block {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 8rem 2rem 4rem;
            margin-top: 80px;
            position: relative;
            background-size: cover;
            background-position: center;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 3rem;
        }

        .hero-search {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            max-width: 800px;
            margin: 0 auto;
        }

        .property-search-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
        }

        .form-input, .form-select {
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            background: white;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Stats Block */
        .stats-block {
            background: var(--bg-light);
            padding: 4rem 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stat-item {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 1.125rem;
            color: var(--text-light);
            font-weight: 600;
        }

        /* Services Block */
        .services-block {
            padding: 6rem 2rem;
        }

        .services-block .section-header {
            text-align: center;
            margin-bottom: 4rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .services-block .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text);
        }

        .services-block .section-header p {
            font-size: 1.125rem;
            color: var(--text-light);
            line-height: 1.7;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 1rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .service-card {
            background: white;
            padding: 3rem 2rem;
            border-radius: var(--radius-xl);
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
            border: 1px solid var(--border);
        }

        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
        }

        .service-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Testimonials Block */
        .testimonials-block {
            background: var(--primary);
            color: white;
            padding: 6rem 2rem;
        }

        .testimonials-block::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 2px, transparent 2px);
            background-size: 40px 40px;
            opacity: 0.3;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .testimonial-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .testimonial-rating {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }

        .award-block {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
            margin-bottom: 2rem;
        }

        .award-winner {
            border: 2px solid var(--accent);
        }

        .award-content {
            color: black;
        }

        .star-filled {
            color: var(--secondary);
        }

        .star-empty {
            color: rgba(255, 255, 255, 0.3);
        }

        .testimonial-text {
            font-size: 1.125rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .author-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .author-name {
            font-weight: 600;
            font-style: normal;
        }

        .author-role {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        /* Person Block */
        .person-block {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin: 2rem 0;
        }

        .map-location-block {
            margin: 2rem 0;
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .map-container {
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin: 1rem 0;
        }

        .map-container iframe {
            width: 100%;
            border: none;
        }

        .person-display-profile {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .person-display-profile .person-image {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .person-display-profile .person-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .person-display-profile .person-info {
            flex: 1;
        }

        .person-display-profile .person-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .person-display-profile .person-role {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .person-display-profile .person-bio {
            line-height: 1.7;
            margin-bottom: 1.5rem;
            color: var(--text);
        }

        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .contact-info {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .contact-form {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .cta-button {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .cta-button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .testimonials-container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 4rem;
        }

        .testimonial-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .testimonial-text {
            font-size: 1.125rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .testimonials-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .services-header {
            text-align: center;
            margin-bottom: 4rem;
        }


        /* Search Bar */
        .search-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            max-width: 800px;
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        /* Add to layout.php styles */
        .sidebar-agent-block {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .sidebar-agent-block h3 {
            margin-bottom: 1.5rem;
            color: var(--text);
            font-size: 1.25rem;
        }

        .agent-profile-sidebar {
            text-align: center;
        }

        .sidebar-agent-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 1rem;
        }

        .sidebar-agent-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-agent-name {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .sidebar-agent-title {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-agent-experience {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .sidebar-agent-contact {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-contact-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: var(--bg-light);
            color: var(--primary);
            text-decoration: none;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .sidebar-contact-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .property-enquiry-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .property-enquiry-card h3 {
            margin-bottom: 1.5rem;
            color: var(--text);
            font-size: 1.25rem;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .form-group input,
        .form-group select {
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .services-header h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .testimonials-header h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .person-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .person-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .person-info {
            flex: 1;
        }

        /* Add to layout.php styles */
        .hero-search-results {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            margin-top: 2rem;
            max-height: 600px;
            overflow-y: auto;
        }

        .search-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
        }

        .search-results-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close-results {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--text-light);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-results:hover {
            background: var(--bg-light);
            color: var(--text);
        }

        .search-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            padding: 1.5rem 2rem;
        }

        .search-result-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .search-result-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .result-image {
            position: relative;
            height: 150px;
            overflow: hidden;
        }

        .result-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .result-price {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .result-content {
            padding: 1rem;
        }

        .result-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .result-location {
            color: var(--text-light);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .result-features {
            color: var(--text-light);
            font-size: 0.8rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .result-link {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-block;
            transition: background 0.3s ease;
        }

        .result-link:hover {
            background: var(--primary-dark);
        }

        .search-results-footer {
            padding: 1rem 2rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .search-loading, .search-error, .no-results {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-light);
        }

        .search-error {
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .search-results-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .search-results-header {
                padding: 1rem;
            }
        }

        .gallery-carousel {
            position: relative;
            max-width: 100%;
            margin: 2rem 0;
        }

        .carousel-container {
            position: relative;
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .carousel-slides {
            position: relative;
            width: 100%;
            height: 400px;
        }

        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
            display: flex;
        }

        .carousel-slide.active {
            opacity: 1;
        }

        .carousel-slide .slide-image {
            flex: 1;
            height: 100%;
        }

        .carousel-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 10;
            transition: background 0.3s ease;
        }

        .carousel-btn:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        .carousel-prev {
            left: 1rem;
        }

        .carousel-next {
            right: 1rem;
        }

        .carousel-indicators {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 0.5rem;
            z-index: 10;
        }

        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .indicator.active {
            background: white;
        }

        .person-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .person-role {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .person-bio {
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .person-contact {
            display: flex;
            gap: 1rem;
        }

        .contact-link {
            background: var(--bg-light);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .contact-link:hover {
            background: var(--primary);
            color: white;
        }

        .person-display-contact .contact-info h3 {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.75rem;
        }

        .contact-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--bg-light);
            border-radius: var(--radius-lg);
        }

        .contact-icon {
            font-size: 1.5rem;
            min-width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .contact-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .contact-item strong {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        /* Property Cards */
        .properties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .property-card {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
            border: 1px solid var(--border);
        }

        .property-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .property-image {
            width: 100%;
            height: 250px;
            position: relative;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--accent);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .property-price {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.125rem;
        }

        .property-content {
            padding: 1.5rem;
        }

        .property-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .property-location {
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .property-features {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .feature {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .property-actions {
            display: flex;
            gap: 1rem;
        }

        .property-actions .btn {
            flex: 1;
            font-size: 0.875rem;
        }

        /* CMS Blocks */
        .text-block p {
            font-size: 1.125rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            color: var(--text);
        }

        .heading-block {
            margin: 2rem 0 1.5rem;
        }

        .heading-text {
            color: var(--text);
            font-weight: 700;
            line-height: 1.3;
        }

        .gallery-block {
            margin: 2rem 0;
        }

        .gallery-slides {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .gallery-slide {
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .gallery-slide img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #ef4444;
        }

        .text-center { text-align: center; }
        .mt-20 { margin-top: 80px; }

        .page-grid-block {
            margin: 2rem 0;
        }

        .page-grid-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-grid-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .page-grid-subtitle {
            font-size: 1.25rem;
            color: #666;
        }

        .page-grid-container {
            display: grid;
            gap: 2rem;
        }

        .page-grid-grid.columns-3 .page-grid-container {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .page-grid-list .page-grid-container {
            grid-template-columns: 1fr;
        }

        .page-card {
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .page-card:hover {
            transform: translateY(-2px);
        }

        .page-image {
            position: relative;
        }

        .page-image img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .page-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: bold;
        }

        .page-price {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: bold;
        }

        .page-content {
            padding: 1.5rem;
        }

        .page-features {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .page-feature {
            font-size: 0.875rem;
            color: #666;
        }

        .page-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .sidebar {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            height: fit-content;
        }

        @media (max-width: 768px) {
            .page-layout.has-sidebar {
                grid-template-columns: 1fr;
            }

            .sidebar {
                order: -1;
                margin-bottom: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 2rem;
            }
        }

        .page-layout {
            display: grid;
            gap: 2rem;
        }

        .page-layout.has-sidebar {
            grid-template-columns: 1fr 300px;
        }

        .page-layout.full-width .main-content {
            max-width: 100%;
        }

        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1a1a1a;
        }

        .page-categories {
            margin: 1rem 0;
        }

        .categories-label {
            font-weight: 600;
            color: #666;
            margin-right: 0.5rem;
        }

        .category-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }

        .category-link:hover {
            text-decoration: underline;
        }

        .page-tags {
            margin: 1rem 0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tag-badge {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .comments-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #eee;
        }

        .comments-section h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #1a1a1a;
        }

        .comment {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .comment-author {
            color: #1a1a1a;
            font-size: 1rem;
        }

        .comment-date {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .comment-content {
            color: #333;
            line-height: 1.6;
        }

        .no-comments {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 2rem;
        }

        .comment-form-container {
            margin-top: 2rem;
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
        }

        .comment-form h4 {
            margin-bottom: 1rem;
            color: #1a1a1a;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group input,
        .form-group textarea {
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .social-sharing {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .social-sharing h4 {
            margin-bottom: 1rem;
            color: #1a1a1a;
        }

        .social-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .social-btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-weight: 500;
            font-size: 0.875rem;
            transition: opacity 0.2s;
        }

        .social-btn:hover {
            opacity: 0.8;
        }

        .social-btn.facebook { background: #1877f2; }
        .social-btn.twitter { background: #1da1f2; }
        .social-btn.linkedin { background: #0077b5; }
        .social-btn.email { background: #6c757d; }

        .sidebar {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            height: fit-content;
        }

        @media (max-width: 768px) {
            .page-layout.has-sidebar {
                grid-template-columns: 1fr;
            }

            .sidebar {
                order: -1;
                margin-bottom: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 2rem;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-menu { display: none; }
            .property-search-form { grid-template-columns: 1fr; }
            .hero-actions { flex-direction: column; align-items: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .services-grid { grid-template-columns: 1fr; }
            .testimonials-grid { grid-template-columns: 1fr; }
            .properties-grid { grid-template-columns: 1fr; }
            .container { padding: 0 1rem; }
            .form-row { grid-template-columns: 1fr; }
            .property-actions { flex-direction: column; }
            .person-display-profile { flex-direction: column; text-align: center; }
        }

        /* Event Block Styles */
        .event-block {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .event-image {
            width: 100%;
            height: 300px;
            overflow: hidden;
        }

        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-content {
            padding: 2rem;
        }

        .event-header {
            margin-bottom: 2rem;
        }

        .event-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .event-category {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .event-details {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .event-detail-item {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .detail-icon {
            font-size: 1.5rem;
            min-width: 2rem;
        }

        .detail-content {
            flex: 1;
        }

        .detail-content strong {
            color: #1a1a1a;
            font-weight: 600;
        }

        .map-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }

        .map-link:hover {
            text-decoration: underline;
        }

        .free-event {
            color: #28a745;
            font-weight: 700;
            font-size: 1.125rem;
        }

        .ticket-price {
            color: #007bff;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .event-description h3 {
            color: #1a1a1a;
            margin-bottom: 1rem;
        }

        .description-content {
            color: #6c757d;
            line-height: 1.6;
        }

        .event-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .event-ticket-btn {
            background: #007bff;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .event-ticket-btn:hover {
            background: #0056b3;
        }

        /* Event Sidebar Styles */
        .event-sidebar {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .sidebar-event-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1a1a1a;
        }

        .sidebar-event-details {
            margin-bottom: 1.5rem;
        }

        .sidebar-detail {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .sidebar-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        /* Event Modal Styles */
        .event-modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .event-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .event-modal-close {
            color: #aaa;
            float: right;
            font-size: 2rem;
            font-weight: bold;
            position: absolute;
            right: 1rem;
            top: 1rem;
            cursor: pointer;
        }

        .event-modal-close:hover {
            color: #000;
        }

        /* Event Signup Form Styles */
        .event-signup-section {
            background: #f8f9fa;
            padding: 3rem 0;
            margin: 2rem 0;
        }

        .signup-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .signup-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .signup-subtitle {
            color: #6c757d;
            font-size: 1.125rem;
            margin-top: 0.5rem;
        }

        .event-signup-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6c757d;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            margin: 0;
        }

        .signup-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.125rem;
            font-weight: 600;
        }

        /* Event Signup Sidebar */
        .event-signup-sidebar {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .sidebar-signup-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
        }

        .sidebar-signup-subtitle {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        /* Contact Form Sidebar Styles */
        .contact-form-sidebar {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .sidebar-form-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
        }

        .sidebar-form-subtitle {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .sidebar-contact-form .form-group {
            margin-bottom: 1rem;
        }

        .sidebar-contact-form input,
        .sidebar-contact-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .sidebar-contact-form textarea {
            min-height: 80px;
            resize: vertical;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .event-actions {
                flex-direction: column;
            }

            .event-signup-form .form-row {
                grid-template-columns: 1fr;
            }

            .event-modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 1.5rem;
            }

            .event-title {
                font-size: 1.5rem;
            }

            .event-content {
                padding: 1.5rem;
            }
        }

    </style>
</head>
<body>
<header class="header">
    <?php echo (new \App\Services\MenuRenderer())->render($menu, ['layout' => 'vertical', 'logo' => true, 'title' => $title ?? $page->title]) ?>
</header>
