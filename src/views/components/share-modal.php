<style>
    .share-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .share-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }

    .share-modal-content {
        position: relative;
        background: white;
        border-radius: 16px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .share-modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: #f1f5f9;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        z-index: 1;
    }

    .share-modal-close:hover {
        background: #e2e8f0;
        transform: rotate(90deg);
    }

    .share-modal-header {
        padding: 2rem 2rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .share-modal-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }

    .share-product-info {
        display: flex;
        gap: 1rem;
        padding: 1.5rem 2rem;
        background: #f8f9fa;
    }

    .share-product-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }

    .share-product-details {
        flex: 1;
        min-width: 0;
    }

    .share-product-details h4 {
        margin: 0 0 0.5rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .share-product-price {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .share-price-sale {
        font-size: 1.25rem;
        font-weight: 700;
        color: #ef4444;
    }

    .share-price-original {
        font-size: 1rem;
        color: #94a3b8;
        text-decoration: line-through;
    }

    .share-merchant {
        font-size: 0.875rem;
        color: #64748b;
        margin: 0;
    }

    .share-link-section,
    .share-social-section {
        padding: 1.5rem 2rem;
    }

    .share-link-section label,
    .share-social-section label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.75rem;
        color: #1e293b;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .share-link-input-group {
        display: flex;
        gap: 0.5rem;
    }

    .share-link-input-group input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.875rem;
        background: #f8f9fa;
    }

    .btn-copy-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .btn-copy-link:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .btn-copy-link.copied {
        background: #10b981;
    }

    .share-social-buttons {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

    .share-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.875rem 1rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s;
        color: white;
    }

    .share-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .share-btn-facebook {
        background: #1877f2;
    }

    .share-btn-twitter {
        background: #1da1f2;
    }

    .share-btn-whatsapp {
        background: #25d366;
    }

    .share-btn-linkedin {
        background: #0077b5;
    }

    .share-btn-pinterest {
        background: #e60023;
    }

    @media (max-width: 480px) {
        .share-modal-content {
            width: 95%;
            max-height: 95vh;
        }

        .share-modal-header,
        .share-product-info,
        .share-link-section,
        .share-social-section {
            padding: 1rem;
        }

        .share-social-buttons {
            grid-template-columns: 1fr;
        }
    }
</style>

<div id="share-modal" class="share-modal" style="display: none;">
    <div class="share-modal-overlay"></div>
    <div class="share-modal-content">
        <button class="share-modal-close" onclick="closeShareModal()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <div class="share-modal-header">
            <h3>Share Product</h3>
        </div>

        <div class="share-product-info">
            <img id="share-product-image" src="" alt="" class="share-product-image">
            <div class="share-product-details">
                <h4 id="share-product-name"></h4>
                <div class="share-product-price">
                    <span id="share-product-sale-price" class="share-price-sale"></span>
                    <span id="share-product-price" class="share-price-original"></span>
                </div>
                <p id="share-product-merchant" class="share-merchant"></p>
            </div>
        </div>

        <div class="share-link-section">
            <label>Share Link</label>
            <div class="share-link-input-group">
                <input type="text" id="share-link-input" readonly>
                <button onclick="copyShareLink()" class="btn-copy-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    Copy
                </button>
            </div>
        </div>

        <div class="share-social-section">
            <label>Share On</label>
            <div class="share-social-buttons">
                <button onclick="shareOnFacebook()" class="share-btn share-btn-facebook">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Facebook
                </button>

                <button onclick="shareOnTwitter()" class="share-btn share-btn-twitter">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                    Twitter
                </button>

                <button onclick="shareOnWhatsApp()" class="share-btn share-btn-whatsapp">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    WhatsApp
                </button>

                <button onclick="shareOnLinkedIn()" class="share-btn share-btn-linkedin">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                    LinkedIn
                </button>

                <button onclick="shareOnPinterest()" class="share-btn share-btn-pinterest">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/>
                    </svg>
                    Pinterest
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        let currentShareData = {};

        window.openShareModal = function (product) {
            currentShareData = product;

            // Populate modal with product data
            document.getElementById('share-product-image').src = product.image || '/images/placeholder.jpg';
            document.getElementById('share-product-name').textContent = product.name;

            // Set prices
            const salePriceEl = document.getElementById('share-product-sale-price');
            const priceEl = document.getElementById('share-product-price');

            if (product.sale_price && product.sale_price < product.price) {
                salePriceEl.textContent = `$${parseFloat(product.sale_price).toFixed(2)}`;
                priceEl.textContent = `$${parseFloat(product.price).toFixed(2)}`;
                salePriceEl.style.display = 'inline';
                priceEl.style.display = 'inline';
            } else {
                salePriceEl.textContent = `$${parseFloat(product.price).toFixed(2)}`;
                salePriceEl.style.display = 'inline';
                priceEl.style.display = 'none';
            }

            // Set merchant info
            const merchantEl = document.getElementById('share-product-merchant');
            if (product.merchant_name) {
                merchantEl.textContent = `Available at ${product.merchant_name}`;
                merchantEl.style.display = 'block';
            } else {
                merchantEl.style.display = 'none';
            }

            // Set share link
            const shareUrl = `${window.location.origin}/${SITE}/shop/details/${product.slug}`;
            document.getElementById('share-link-input').value = shareUrl;

            // Show modal
            document.getElementById('share-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        };

        window.closeShareModal = function () {
            document.getElementById('share-modal').style.display = 'none';
            document.body.style.overflow = '';
        };

        window.copyShareLink = function () {
            const input = document.getElementById('share-link-input');
            input.select();
            document.execCommand('copy');

            const btn = document.querySelector('.btn-copy-link');
            const originalText = btn.innerHTML;
            btn.classList.add('copied');
            btn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Copied!
        `;

            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = originalText;
            }, 2000);
        };

        window.shareOnFacebook = function () {
            const url = document.getElementById('share-link-input').value;
            window.open(
                `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
                '_blank',
                'width=600,height=400'
            );
        };

        window.shareOnTwitter = function () {
            const url = document.getElementById('share-link-input').value;
            const text = `Check out ${currentShareData.name}`;
            window.open(
                `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`,
                '_blank',
                'width=600,height=400'
            );
        };

        window.shareOnWhatsApp = function () {
            const url = document.getElementById('share-link-input').value;
            const text = `Check out ${currentShareData.name} - ${url}`;
            window.open(
                `https://wa.me/?text=${encodeURIComponent(text)}`,
                '_blank'
            );
        };

        window.shareOnLinkedIn = function () {
            const url = document.getElementById('share-link-input').value;
            window.open(
                `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`,
                '_blank',
                'width=600,height=400'
            );
        };

        window.shareOnPinterest = function () {
            const url = document.getElementById('share-link-input').value;
            const image = currentShareData.image;
            const description = currentShareData.name;
            window.open(
                `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(url)}&media=${encodeURIComponent(image)}&description=${encodeURIComponent(description)}`,
                '_blank',
                'width=600,height=400'
            );
        };

        // Close modal on overlay click
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('share-modal-overlay')) {
                closeShareModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('share-modal').style.display === 'flex') {
                closeShareModal();
            }
        });
    })();
</script>