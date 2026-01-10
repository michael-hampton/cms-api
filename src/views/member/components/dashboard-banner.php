<style>
    .promo-banner {
        background: linear-gradient(135deg, #e91e63 0%, #c62641 50%, #2d3142 100%);
        border-radius: 1rem;
        padding: 0;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        position: relative;
        min-height: 280px;
        display: flex;
        align-items: center;
    }

    .banner-content {
        padding: 2.5rem 3rem;
        max-width: 50%;
        position: relative;
        z-index: 2;
    }

    .banner-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        transition: all 0.2s ease;
        z-index: 3;
    }

    .banner-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .banner-content h2 {
        font-size: 2.5rem;
        font-weight: 800;
        color: white;
        margin-bottom: 0.75rem;
        line-height: 1.2;
    }

    .banner-content p {
        font-size: 1.125rem;
        color: rgba(255, 255, 255, 0.95);
        margin-bottom: 2rem;
        line-height: 1.6;
    }

    .banner-btn {
        background: white;
        color: var(--primary-color);
        padding: 0.875rem 2rem;
        border: none;
        border-radius: 2rem;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-block;
        text-decoration: none;
    }

    .banner-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .banner-image {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        width: 50%;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect fill="%23e91e63" opacity="0.1" width="400" height="300"/></svg>');
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .banner-image::before {
        content: '⚽';
        font-size: 12rem;
        opacity: 0.3;
        position: absolute;
        right: 2rem;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% {
            transform: translateY(0px);
        }
        50% {
            transform: translateY(-20px);
        }
    }

    .banner-dots {
        position: absolute;
        bottom: 1rem;
        right: 1rem;
        display: flex;
        gap: 0.5rem;
        z-index: 2;
    }

    .banner-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .banner-dot.active {
        background: white;
        width: 24px;
        border-radius: 4px;
    }
</style>

<div class="promo-banner">
    <button class="banner-close" onclick="this.parentElement.style.display='none'">×</button>
    <div class="banner-content">
        <h2>Prove Your<br>Prediction Power</h2>
        <p>Predict football scores, beat your friends and climb the leaderboard!</p>
        <a href="/predictions" class="banner-btn">Play Now</a>
    </div>
    <div class="banner-image"></div>
    <div class="banner-dots">
        <div class="banner-dot active"></div>
        <div class="banner-dot"></div>
        <div class="banner-dot"></div>
    </div>
</div>