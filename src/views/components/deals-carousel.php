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

    .deals-carousel-header h2 {
        font-size: 1.75rem;
        color: #232f3e;
        margin: 0;
    }

    .refresh-deals-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: #ff9900;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s;
    }

    .refresh-deals-btn:hover {
        background: #fa8900;
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
        flex: 0 0 250px;
        background: #fff;
        border: 1px solid #e7e7e7;
        border-radius: 4px;
        padding: 1rem;
        position: relative;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .deal-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .deal-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #c45500;
        color: #fff;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-weight: 700;
        font-size: 0.875rem;
        z-index: 5;
    }

    .deal-image-link {
        display: block;
        margin-bottom: 1rem;
    }

    .deal-image {
        width: 100%;
        height: 200px;
        object-fit: contain;
    }

    .deal-content {
        text-align: left;
    }

    .deal-title {
        font-size: 1rem;
        margin: 0 0 0.5rem;
        height: 2.5em;
        overflow: hidden;
        display: -webkit-box;
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
        margin: 0.75rem 0;
    }

    .was-price {
        display: block;
        color: #565959;
        text-decoration: line-through;
        font-size: 0.875rem;
    }

    .now-price {
        display: block;
        color: #b12704;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .deal-cta {
        width: 100%;
        padding: 0.5rem;
        background: #ff9900;
        color: #0f1111;
        border: none;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .deal-cta:hover {
        background: #fa8900;
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
        background: #ff9900;
    }
</style>

<div class="deals-carousel-wrapper">
    <div class="deals-carousel-header">
        <h2>Today's Best Deals & Offers</h2>
        <button class="refresh-deals-btn" onclick="refreshDeals()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Refresh Deals
        </button>
    </div>

    <div class="deals-carousel" id="deals-carousel">
        <button class="carousel-arrow carousel-arrow-left" onclick="scrollCarousel(-1)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <div class="deals-carousel-track">
            <?php foreach ($todaysDeals ?? [] as $deal): ?>
                <?php include __DIR__ . '/../../views/components/deal-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <button class="carousel-arrow carousel-arrow-right" onclick="scrollCarousel(1)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
    </div>

    <div class="carousel-dots" id="carousel-dots"></div>
</div>