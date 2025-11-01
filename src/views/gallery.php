<style>
    /* Gallery Carousel Styles */
    .gallery-carousel {
        position: relative;
        width: 100%;
        max-width: 1200px;
        margin: 2rem auto;
        overflow: hidden;
        background: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .gallery-slides-container {
        position: relative;
        width: 100%;
        min-height: 400px;
    }

    .gallery-slide {
        display: none;
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
        animation: fadeOut 0.5s ease-in-out;
    }

    .gallery-slide.active {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        padding: 2rem;
        opacity: 1;
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(20px); }
        to { opacity: 1; transform: translateX(0); }
    }

    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(-20px); }
    }

    .gallery-slide-image {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .gallery-slide-image img {
        width: 100%;
        height: auto;
        max-height: 500px;
        object-fit: cover;
        border-radius: 4px;
    }

    .gallery-slide-caption {
        margin-top: 1rem;
        padding: 0.5rem;
        font-size: 0.9rem;
        color: #666;
        text-align: center;
        font-style: italic;
    }

    .gallery-slide-content {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 1.5rem;
        overflow-y: auto;
        max-height: 500px;
    }

    /* Block type adaptations for slides */
    .gallery-slide-content .heading-block {
        margin: 0;
    }

    .gallery-slide-content .heading-text {
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }

    .gallery-slide-content .heading-subtitle {
        font-size: 1.1rem;
        color: #7f8c8d;
        margin-bottom: 1rem;
    }

    .gallery-slide-content .text-block p {
        margin-bottom: 1rem;
        line-height: 1.6;
        color: #34495e;
    }

    .gallery-slide-content .list-block {
        margin: 0;
    }

    .gallery-slide-content .list-items {
        padding-left: 1.5rem;
    }

    .gallery-slide-content .list-item {
        margin-bottom: 0.5rem;
        line-height: 1.5;
    }

    .gallery-slide-content .quote-block {
        border-left: 4px solid #3498db;
        padding-left: 1rem;
        margin: 1rem 0;
    }

    .gallery-slide-content .info-block {
        padding: 1rem;
        border-radius: 4px;
        margin: 1rem 0;
    }

    .gallery-slide-content .table-block {
        overflow-x: auto;
    }

    .gallery-slide-content .data-table {
        width: 100%;
        font-size: 0.9rem;
    }

    /* Navigation Controls */
    .gallery-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .gallery-nav:hover {
        background: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        transform: translateY(-50%) scale(1.1);
    }

    .gallery-nav:active {
        transform: translateY(-50%) scale(0.95);
    }

    .gallery-nav-prev {
        left: 1rem;
    }

    .gallery-nav-next {
        right: 1rem;
    }

    .gallery-nav svg {
        color: #2c3e50;
    }

    /* Slide Indicators */
    .gallery-indicators {
        position: absolute;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 0.5rem;
        z-index: 10;
    }

    .gallery-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid white;
        background: rgba(255, 255, 255, 0.5);
        cursor: pointer;
        transition: all 0.3s ease;
        padding: 0;
    }

    .gallery-indicator:hover {
        background: rgba(255, 255, 255, 0.8);
        transform: scale(1.2);
    }

    .gallery-indicator.active {
        background: white;
        transform: scale(1.3);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .gallery-slide.active {
            grid-template-columns: 1fr;
            padding: 1rem;
        }

        .gallery-slide-image img {
            max-height: 300px;
        }

        .gallery-slide-content {
            max-height: none;
        }

        .gallery-nav {
            width: 40px;
            height: 40px;
        }

        .gallery-nav-prev {
            left: 0.5rem;
        }

        .gallery-nav-next {
            right: 0.5rem;
        }

        .gallery-slide-content .heading-text {
            font-size: 1.5rem;
        }
    }

    @media (max-width: 480px) {
        .gallery-carousel {
            margin: 1rem 0;
            border-radius: 0;
        }

        .gallery-slide.active {
            padding: 0.5rem;
        }

        .gallery-nav {
            width: 36px;
            height: 36px;
        }

        .gallery-indicator {
            width: 10px;
            height: 10px;
        }
    }
</style>

<!-- Gallery Slides Section -->
    <?php
    $gallerySlides = is_string($page->gallery_slides)
        ? json_decode($page->gallery_slides, true)
        : $page->gallery_slides;
    ?>

    <?php if (!empty($gallerySlides) && is_array($gallerySlides)): ?>
        <div class="gallery-carousel" data-autoplay-delay="5000">
            <div class="gallery-slides-container">
                <?php foreach ($gallerySlides as $index => $slide): ?>
                    <div class="gallery-slide <?= $index === 0 ? 'active' : '' ?>" data-slide-index="<?= $index ?>">
                        <div class="gallery-slide-image">
                            <img
                                src="<?= htmlspecialchars($slide['image_url']) ?>"
                                alt="<?= htmlspecialchars($slide['alt'] ?? '') ?>"
                                loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                            >
                            <?php if (!empty($slide['caption'])): ?>
                                <div class="gallery-slide-caption">
                                    <?= htmlspecialchars($slide['caption']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($slide['blocks']) && is_array($slide['blocks'])): ?>
                            <div class="gallery-slide-content">
                                <?php foreach ($slide['blocks'] as $blockIndex => $block): ?>
                                    <?php
                                    try {
                                        // Build block in preview mode to skip validation if needed
                                        echo $blockParserService->buildBlock(
                                            $page->id,
                                            $block,
                                            $blockIndex,
                                            true // Preview mode
                                        );
                                    } catch (Exception $e) {
                                        // Silently fail or log error
                                        error_log("Gallery slide block error: " . $e->getMessage());
                                    }
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Navigation Controls -->
            <button class="gallery-nav gallery-nav-prev" aria-label="Previous slide">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button class="gallery-nav gallery-nav-next" aria-label="Next slide">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            <!-- Slide Indicators -->
            <div class="gallery-indicators">
                <?php foreach ($gallerySlides as $index => $slide): ?>
                    <button
                        class="gallery-indicator <?= $index === 0 ? 'active' : '' ?>"
                        data-slide-index="<?= $index ?>"
                        aria-label="Go to slide <?= $index + 1 ?>"
                    ></button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const carousels = document.querySelectorAll('.gallery-carousel');

        carousels.forEach(carousel => {
            const slides = carousel.querySelectorAll('.gallery-slide');
            const prevBtn = carousel.querySelector('.gallery-nav-prev');
            const nextBtn = carousel.querySelector('.gallery-nav-next');
            const indicators = carousel.querySelectorAll('.gallery-indicator');
            const autoplayDelay = parseInt(carousel.dataset.autoplayDelay) || 5000;

            let currentSlide = 0;
            let autoplayTimer = null;
            let isTransitioning = false;

            function showSlide(index) {
                if (isTransitioning) return;

                isTransitioning = true;

                // Wrap around
                if (index >= slides.length) index = 0;
                if (index < 0) index = slides.length - 1;

                // Update slides
                slides.forEach(slide => slide.classList.remove('active'));
                slides[index].classList.add('active');

                // Update indicators
                indicators.forEach(indicator => indicator.classList.remove('active'));
                indicators[index].classList.add('active');

                currentSlide = index;

                setTimeout(() => {
                    isTransitioning = false;
                }, 500);

                resetAutoplay();
            }

            function nextSlide() {
                showSlide(currentSlide + 1);
            }

            function prevSlide() {
                showSlide(currentSlide - 1);
            }

            function startAutoplay() {
                autoplayTimer = setInterval(nextSlide, autoplayDelay);
            }

            function stopAutoplay() {
                if (autoplayTimer) {
                    clearInterval(autoplayTimer);
                    autoplayTimer = null;
                }
            }

            function resetAutoplay() {
                stopAutoplay();
                startAutoplay();
            }

            // Event listeners
            prevBtn.addEventListener('click', prevSlide);
            nextBtn.addEventListener('click', nextSlide);

            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => showSlide(index));
            });

            // Pause on hover
            carousel.addEventListener('mouseenter', stopAutoplay);
            carousel.addEventListener('mouseleave', startAutoplay);

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') prevSlide();
                if (e.key === 'ArrowRight') nextSlide();
            });

            // Touch support
            let touchStartX = 0;
            let touchEndX = 0;

            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            });

            carousel.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });

            function handleSwipe() {
                if (touchEndX < touchStartX - 50) nextSlide();
                if (touchEndX > touchStartX + 50) prevSlide();
            }

            // Start autoplay if more than one slide
            if (slides.length > 1) {
                startAutoplay();
            }
        });
    });
</script>
