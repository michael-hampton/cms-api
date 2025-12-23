<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($author->name) ?> - Author</title>
    <meta name="description" content="<?= htmlspecialchars($author->bio ?? '') ?>">
    <?php if ($author->avatar): ?>
        <meta property="og:image" content="<?= htmlspecialchars($author->avatar) ?>">
    <?php endif; ?>
    @css('landing-page.css')
    @js('base.js')

</head>
<body>

@include('header', ['menu' => $menu, 'title' => $author->name])

<!-- Header -->
<header class="header">
    <div class="header-content">
        <?php if ($author->avatar): ?>
            <img src="<?= htmlspecialchars($author->avatar) ?>"
                 alt="<?= htmlspecialchars($author->name) ?>"
                 class="author-avatar-header">
        <?php else: ?>
            <div class="author-avatar-placeholder-header">
                <?= strtoupper(substr($author->name, 0, 2)) ?>
            </div>
        <?php endif; ?>

        <h1><?= htmlspecialchars($author->name) ?></h1>

        <?php if ($author->bio): ?>
            <p><?= htmlspecialchars($author->bio) ?></p>
        <?php endif; ?>

        <div class="author-stats-header">
            <div class="stat-item-header">
                <span class="stat-value-header"><?= $pagination['total'] ?? count($pages) ?></span>
                <span class="stat-label-header"><?= ($pagination['total'] ?? count($pages)) === 1 ? 'Article' : 'Articles' ?></span>
            </div>
            <?php if ($author->created_at): ?>
                <div class="stat-item-header">
                    <span class="stat-value-header"><?= $author->created_at->format('Y') ?></span>
                    <span class="stat-label-header">Joined</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="container">
    <?php if ($pages && count($pages) > 0): ?>

        <!-- Author Details Section -->
        <?php if ($author->expertise || $author->location || $author->education || $author->awards || $author->email || $author->website || $author->twitter || $author->linkedin || $author->facebook): ?>
            <div class="author-info-section">
                <div class="author-info-grid">

                    <?php if ($author->expertise): ?>
                        <div class="info-card" style="grid-column: 1 / -1;">
                            <div class="info-card-header">
                                <svg class="info-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                                <h3 class="info-card-title">Expertise</h3>
                            </div>
                            <div class="expertise-text"><?= htmlspecialchars($author->expertise) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($author->location && is_array($author->location) && count($author->location) > 0): ?>
                        <div class="info-card">
                            <div class="info-card-header">
                                <svg class="info-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <h3 class="info-card-title">Location</h3>
                            </div>
                            <ul class="info-list">
                                <?php foreach ($author->location as $location): ?>
                                    <li><?= htmlspecialchars($location) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($author->education && is_array($author->education) && count($author->education) > 0): ?>
                        <div class="info-card">
                            <div class="info-card-header">
                                <svg class="info-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 14l9-5-9-5-9 5 9 5z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                                </svg>
                                <h3 class="info-card-title">Education</h3>
                            </div>
                            <ul class="info-list">
                                <?php foreach ($author->education as $edu): ?>
                                    <li><?= htmlspecialchars($edu) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($author->awards && is_array($author->awards) && count($author->awards) > 0): ?>
                        <div class="info-card">
                            <div class="info-card-header">
                                <svg class="info-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                </svg>
                                <h3 class="info-card-title">Awards & Recognition</h3>
                            </div>
                            <ul class="info-list">
                                <?php foreach ($author->awards as $award): ?>
                                    <li><?= htmlspecialchars($award) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($author->seniority_date): ?>
                        <div class="info-card">
                            <div class="info-card-header">
                                <svg class="info-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <h3 class="info-card-title">Experience</h3>
                            </div>
                            <div class="info-card-content">
                                <p><strong>Started:</strong> <?= $author->seniority_date->format('F Y') ?></p>
                                <?php if ($author->years_of_experience): ?>
                                    <p><strong>Years of Experience:</strong> <?= $author->years_of_experience ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($author->email || $author->website || $author->twitter || $author->linkedin || $author->facebook): ?>
                    <div class="info-card" style="grid-column: 1 / -1;">
                        <div class="info-card-header">
                            <svg class="info-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            <h3 class="info-card-title">Connect</h3>
                        </div>
                        <div class="social-links">
                            <?php if ($author->email): ?>
                                <a href="mailto:<?= htmlspecialchars($author->email) ?>" class="social-link">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    Email
                                </a>
                            <?php endif; ?>

                            <?php if ($author->website): ?>
                                <a href="<?= htmlspecialchars($author->website) ?>" target="_blank" rel="noopener"
                                   class="social-link">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                    </svg>
                                    Website
                                </a>
                            <?php endif; ?>

                            <?php if ($author->twitter): ?>
                                <a href="https://twitter.com/<?= htmlspecialchars($author->twitter) ?>" target="_blank"
                                   rel="noopener" class="social-link">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/>
                                    </svg>
                                    Twitter
                                </a>
                            <?php endif; ?>

                            <?php if ($author->linkedin): ?>
                                <a href="<?= htmlspecialchars($author->linkedin) ?>" target="_blank" rel="noopener"
                                   class="social-link">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/>
                                        <circle cx="4" cy="4" r="2" stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2"/>
                                    </svg>
                                    LinkedIn
                                </a>
                            <?php endif; ?>

                            <?php if ($author->facebook): ?>
                                <a href="<?= htmlspecialchars($author->facebook) ?>" target="_blank" rel="noopener"
                                   class="social-link">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                                    </svg>
                                    Facebook
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        <?php endif; ?>

        <hr class="section-divider">

        <h2 class="section-title">
            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Published Articles
        </h2>

        <!-- Filters -->
        @include('partials.filters', ['pages' => $pages])


        <!-- Pages Grid -->
        <div class="pages-grid">
            <?php foreach ($pages as $page): ?>
                @include('components/page-card', ['page' => $page, 'showToolbar' => true])
            <?php endforeach; ?>
        </div>

        @include('partials.pagination', ['pages' => $pages])
    <?php else: ?>
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h2>No Articles Yet</h2>
            <p><?= htmlspecialchars($author->name) ?> hasn't published any articles yet.</p>
        </div>
    <?php endif; ?>
</div>

@include('components/newsletter-modal')
@include('components/comment-modal')

</body>
</html>