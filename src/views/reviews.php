<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - Expert Recommendations & Analysis</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #ffffff;
            color: #111827;
            line-height: 1.6;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        .header-content h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .header-content p {
            font-size: 1.25rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .review-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .review-card:hover {
            border-color: #667eea;
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.15);
            transform: translateY(-4px);
        }

        .review-card-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            overflow: hidden;
        }

        .review-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .review-card-content {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .review-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .review-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 0.75rem;
        }

        .review-card-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #111827;
            line-height: 1.4;
        }

        .review-card-title a {
            text-decoration: none;
            color: inherit;
            transition: color 0.3s ease;
        }

        .review-card-title a:hover {
            color: #667eea;
        }

        .review-excerpt {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 16px;
            flex-grow: 1;
            line-height: 1.6;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 2px solid #f3f4f6;
        }

        .review-author-date {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .review-author {
            font-size: 0.85rem;
            color: #4b5563;
            font-weight: 500;
        }

        .review-date {
            font-size: 0.85rem;
            color: #9ca3af;
        }

        .review-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .review-link:hover {
            background: #5568d3;
            transform: translateX(2px);
        }

        .review-link svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        .review-link:hover svg {
            transform: translateX(4px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: 12px;
            color: #6b7280;
        }

        .empty-state p {
            color: #9ca3af;
        }

        @media (max-width: 768px) {
            .header-content h1 {
                font-size: 2rem;
            }

            .header-content p {
                font-size: 1rem;
            }

            .reviews-grid {
                grid-template-columns: 1fr;
            }

            .review-card-title {
                font-size: 1.1rem;
            }

            .container {
                padding: 20px;
            }
        }

        .page-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 12px;
        }

        .tag-mini {
            display: inline-block;
            background: #f3f4f6;
            color: #6b7280;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .tag-mini:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <h1>Expert Reviews</h1>
        <p>In-depth analysis and honest recommendations from our team of experts</p>
    </div>
</header>

<div class="container">
    <?php if ($pages && count($pages) > 0): ?>
        @include('partials.filters', ['pages' => $pages])

        <div class="reviews-grid">
            <?php foreach ($pages as $page): ?>
                <article class="review-card">
                    <?php if ($page->featured_image): ?>
                        <div class="review-card-image">
                            <img src="<?= htmlspecialchars($page->featured_image) ?>"
                                 alt="<?= htmlspecialchars($page->title) ?>">
                        </div>
                    <?php else: ?>
                        <div class="review-card-image">📋</div>
                    <?php endif; ?>

                    <div class="review-card-content">
                        <div class="review-meta">
                            <?php if ($page->categories && count($page->categories) > 0): ?>
                                <?php foreach ($page->categories->take(2) as $category): ?>
                                    <span class="review-badge"><?= htmlspecialchars($category->name) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <h2 class="review-card-title">
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/<?= htmlspecialchars($page->slug) ?>">
                                <?= htmlspecialchars($page->title) ?>
                            </a>
                        </h2>

                        <?php if ($page->excerpt): ?>
                            <p class="review-excerpt">
                                <?= htmlspecialchars(substr($page->excerpt, 0, 150)) ?>
                                <?= strlen($page->excerpt) > 150 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>

                        <div class="review-footer">
                            <div class="review-author-date">
                                <?php if ($page->authors && count($page->authors) > 0): ?>
                                    <span class="review-author">
                                        By <?= htmlspecialchars($page->authors->first()->name) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($page->published_at): ?>
                                    <span class="review-date">
                                        <?= date('M j, Y', strtotime($page->published_at)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/<?= htmlspecialchars($page->slug) ?>"
                               class="review-link">
                                Read Review
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        @include('partials.pagination', ['pages' => $pages])
    <?php else: ?>
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h2>No Reviews Yet</h2>
            <p>Check back soon for expert reviews and recommendations.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>