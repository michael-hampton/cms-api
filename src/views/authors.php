<?php if ($page->authors && $page->authors->count() > 0): ?>
    <section class="authors-section">
        <div class="authors-header">
            <h3 class="authors-title">
                <svg class="authors-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <?= $page->authors->count() > 1 ? 'Authors' : 'Author' ?>
            </h3>
        </div>

        <div class="authors-grid">
            <?php foreach ($page->authors as $author): ?>
                <article class="author-card">
                    <?php if ($author->avatar): ?>
                        <div class="author-avatar-wrapper">
                            <img
                                    src="<?= htmlspecialchars($author->avatar) ?>"
                                    alt="<?= htmlspecialchars($author->name) ?>"
                                    class="author-avatar"
                            >
                        </div>
                    <?php else: ?>
                        <div class="author-avatar-wrapper">
                            <div class="author-avatar-placeholder">
                                <?= strtoupper(substr($author->name, 0, 2)) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="author-card-content">
                        <div class="author-card-header">
                            <h4 class="author-name"><?= htmlspecialchars($author->name) ?></h4>
                            <?php
                            $pivotRole = $author->pivot->role ?? 'primary';
                            if ($pivotRole === 'contributor'):
                                ?>
                                <span class="author-role-badge">Contributor</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($author->bio): ?>
                            <p class="author-bio">
                                <?php
                                $bio = $author->bio;
                                $truncated = strlen($bio) > 150 ? substr($bio, 0, 150) . '...' : $bio;
                                echo nl2br(htmlspecialchars($truncated));
                                ?>
                            </p>
                        <?php endif; ?>

                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/authors/<?= htmlspecialchars($author->slug) ?>"
                           class="author-profile-link">
                            <span>View Profile</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M5 12h14"/>
                                <path d="M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <style>
        .authors-section {
            margin-top: 4rem;
            padding: 3rem 0;
            border-top: 2px solid #e5e7eb;
        }

        .authors-header {
            margin-bottom: 2.5rem;
        }

        .authors-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .authors-icon {
            color: #2563eb;
            stroke-width: 2;
        }

        .authors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
        }

        .author-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 32px;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .author-card:hover {
            border-color: #2563eb;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.12);
            transform: translateY(-4px);
        }

        .author-avatar-wrapper {
            margin-bottom: 20px;
        }

        .author-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.25s ease;
        }

        .author-card:hover .author-avatar {
            border-color: #2563eb;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.2);
        }

        .author-avatar-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            border: 4px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.25s ease;
        }

        .author-card:hover .author-avatar-placeholder {
            border-color: #2563eb;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .author-card-content {
            width: 100%;
        }

        .author-card-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .author-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .author-role-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .author-bio {
            color: #6b7280;
            line-height: 1.6;
            margin: 0 0 20px;
            font-size: 0.95rem;
        }

        .author-profile-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .author-profile-link:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .author-profile-link svg {
            transition: transform 0.2s ease;
        }

        .author-profile-link:hover svg {
            transform: translateX(4px);
        }

        /* Single author layout */
        .authors-grid:has(.author-card:only-child) {
            grid-template-columns: 1fr;
            max-width: 600px;
            margin: 0 auto;
        }

        .authors-grid:has(.author-card:only-child) .author-card {
            padding: 40px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .authors-section {
                padding: 2rem 0;
            }

            .authors-title {
                font-size: 1.5rem;
            }

            .authors-grid {
                grid-template-columns: 1fr;
            }

            .author-card {
                padding: 24px;
            }

            .author-avatar,
            .author-avatar-placeholder {
                width: 80px;
                height: 80px;
                font-size: 1.5rem;
            }

            .author-name {
                font-size: 1.25rem;
            }
        }
    </style>
<?php endif; ?>