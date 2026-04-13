<style>
    .deals-carousel-wrapper {
        margin: 2rem 0;
        padding: 2rem;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .deals-carousel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .view-all-deals {
        color: #007185;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
        margin-left: auto; /* Push it to the left of the refresh button */
        padding-right: 1rem;
    }

    .view-all-deals:hover {
        color: #c45500;
        text-decoration: underline;
    }

    .deals-carousel-header h2 {
        font-size: 1.75rem;
        color: #0f1111;
        margin: 0;
    }

    .refresh-deals-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: #ffa41c;
        color: #0f1111;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s;
    }

    .refresh-deals-btn:hover {
        background: #ff8f00;
    }

    .deals-carousel {
        position: relative;
        overflow: hidden;
    }

    .deals-carousel-track {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .deals-carousel-track::-webkit-scrollbar {
        display: none;
    }

    .carousel-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255,255,255,0.9);
        border: 1px solid #ddd;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        transition: all 0.2s;
    }

    .carousel-arrow:hover {
        background: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .carousel-arrow-left {
        left: 10px;
    }

    .carousel-arrow-right {
        right: 10px;
    }

    .deal-card {
        flex: 0 0 280px; /* Make card slightly narrower */
        background: #fff;
        border: 1px solid #e7e7e7;
        border-radius: 4px;
        position: relative;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex; /* Flexbox for better content control */
        flex-direction: column;
        justify-content: space-between;
    }

    .deal-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .deal-header-actions {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 200px; /* Same height as image */
        display: flex;
        justify-content: space-between;
        padding: 10px;
        z-index: 10; /* Ensure they are above the image */
    }

    .deal-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #cc0c39;
        color: #fff;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-weight: 700;
        font-size: 0.875rem;
        z-index: 5;
    }

    .deal-wishlist-btn {
        position: static; /* No longer absolute within the card */
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #ddd;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        color: #565959;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        align-self: flex-start; /* Aligns the button at the top right */
    }

    .deal-wishlist-btn:hover {
        color: #cc0c39;
        border-color: #cc0c39;
    }

    .deal-image-link {
        display: block;
        margin-bottom: 0; /* Remove space above content */
        position: relative;
    }

    .deal-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        margin: 0 !important;
        /*min-width: 100%;*/
    }

    .deal-content {
        text-align: left;
        padding: 1rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .deal-title {
        font-size: 1rem;
        margin: 0 0 0.5rem !important; /* Reduced space after title */
        height: 2.5em !important;
        overflow: hidden;
        display: -webkit-box !important;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .deal-title a {
        color: #007185;
        text-decoration: none;
    }

    .deal-title a:hover {
        color: #c45500;
        text-decoration: underline;
    }

    .deal-rating {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .stars {
        display: inline-block;
        width: 100px;
        height: 20px;
        background: linear-gradient(90deg,
        #ffa41c 0%,
        #ffa41c calc(var(--rating) * 20%),
        #ddd calc(var(--rating) * 20%),
        #ddd 100%);
        -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 20'%3E%3Cpath d='M10,1 L12,7 L18,7 L13,11 L15,17 L10,13 L5,17 L7,11 L2,7 L8,7 Z M30,1 L32,7 L38,7 L33,11 L35,17 L30,13 L25,17 L27,11 L22,7 L28,7 Z M50,1 L52,7 L58,7 L53,11 L55,17 L50,13 L45,17 L47,11 L42,7 L48,7 Z M70,1 L72,7 L78,7 L73,11 L75,17 L70,13 L65,17 L67,11 L62,7 L68,7 Z M90,1 L92,7 L98,7 L93,11 L95,17 L90,13 L85,17 L87,11 L82,7 L88,7 Z'/%3E%3C/svg%3E") repeat-x;
        mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 20'%3E%3Cpath d='M10,1 L12,7 L18,7 L13,11 L15,17 L10,13 L5,17 L7,11 L2,7 L8,7 Z M30,1 L32,7 L38,7 L33,11 L35,17 L30,13 L25,17 L27,11 L22,7 L28,7 Z M50,1 L52,7 L58,7 L53,11 L55,17 L50,13 L45,17 L47,11 L42,7 L48,7 Z M70,1 L72,7 L78,7 L73,11 L75,17 L70,13 L65,17 L67,11 L62,7 L68,7 Z M90,1 L92,7 L98,7 L93,11 L95,17 L90,13 L85,17 L87,11 L82,7 L88,7 Z'/%3E%3C/svg%3E") repeat-x;
    }

    .review-count {
        color: #007185;
        font-size: 0.875rem;
    }

    .deal-prices {
        margin: 0.5rem 0 1rem; /* Reduced space above prices, more space below */
    }

    .was-price {
        display: inline; /* Display inline with now-price (will wrap if necessary) */
        color: #565959;
        text-decoration: line-through;
        font-size: 0.875rem;
        margin-right: 0.5rem;
    }

    .now-price {
        display: inline; /* Display inline with was-price */
        color: #cc0c39;
        font-size: 1.25rem; /* Slightly smaller now-price */
        font-weight: 700;
    }

    .deal-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: auto; /* Push actions to the bottom */
    }

    .deal-cta {
        flex: 1; /* Take half the space */
        padding: 0.5rem;
        background: #ffa41c;
        color: #0f1111;
        border: none;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .deal-cta:hover {
        background: #ff8f00;
    }

    .deal-add-cart {
        flex: 1; /* Take half the space */
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.5rem;
        background: #f0f2f2;
        color: #0f1111;
        border: 1px solid #d5d9d9;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, box-shadow 0.2s;
    }

    .deal-add-cart:hover {
        background: #e9ecec;
        border-color: #adb1b8 #a2a6ac #8d9096;
        box-shadow: 0 1px 2px rgba(15, 17, 17, .15);
    }

    .deal-cta:hover {
        background: #ff8f00;
    }

    .carousel-dots {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .carousel-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #ddd;
        cursor: pointer;
        transition: background 0.2s;
    }

    .carousel-dot.active {
        background: #ffa41c;
    }
</style>

<div class="deals-carousel-wrapper">
    <div class="deals-carousel-header">
        <h2>Today's Best Deals & Offers</h2>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/deals" class="view-all-deals">View All Deals</a>
        <button class="refresh-deals-btn" onclick="refreshDeals()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Refresh Deals
        </button>
    </div>

    <div class="deals-carousel" id="deals-carousel">
        <button class="carousel-arrow carousel-arrow-left" onclick="event.stopPropagation(); scrollCarousel(-1)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <div class="deals-carousel-track">
            <?php foreach ($todaysDeals ?? [] as $deal): ?>
                <?php include __DIR__ . '/../../views/components/deal-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <button class="carousel-arrow carousel-arrow-right" onclick="event.stopPropagation(); scrollCarousel(1)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
    </div>

    <div class="carousel-dots" id="carousel-dots"></div>
</div>