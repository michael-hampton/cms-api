@css('open-collab.css')

<div class="container">
    <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="margin: 0; font-size: 1.875rem;">Contributor Dashboard</h1>
            <p class="text-muted">Overview of your performance and content.</p>
        </div>
        <a href="" class="btn btn-primary">
            + Create New Article
        </a>
    </header>

    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-label">Total Earnings</span>
            <span class="stat-value">£<?= number_format($earnings['total'] / 100, 2) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Active Articles</span>
            <span class="stat-value"><?= $articles->where('status', 'published')->count() ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Drafts</span>
            <span class="stat-value"><?= $articles->where('status', 'draft')->count() ?></span>
        </div>
    </div>

    <div class="dashboard-grid">
        <section class="content-card">
            <div class="card-header">
                <h3 style="margin: 0;">Your Articles</h3>
            </div>

            <div class="article-list">
                <?php if (empty($articles)): ?>
                    <div style="padding: 3rem; text-align: center;" class="text-muted">
                        No articles found. Start writing your first piece!
                    </div>
                <?php endif; ?>

                <?php foreach ($articles as $article): ?>
                    <div class="table-row">
                        <div style="flex: 1;">
                            <a href="" style="text-decoration: none; color: #0f172a; font-weight: 600;">
                                <?= $article->title ?: 'Untitled Draft' ?>
                            </a>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                Last updated <?= \Carbon\Carbon::parse($article->updated_at)->diffForHumans() ?>
                            </div>
                        </div>

                        <div style="margin-right: 2rem;">
                            <span class="badge badge-<?= $article->status ?>">
                                <?= $article->status ?>
                            </span>
                        </div>

                        <div style="text-align: right; min-width: 80px;">
                            <?php if ($article->is_paid): ?>
                                <span class="text-success"
                                      style="font-weight: bold;">£<?= number_format($article->price, 2) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Free</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <aside>
            <div class="content-card" style="padding: 1.5rem;">
                <h3 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1.1rem;">Recent Activity</h3>
                <div class="activity-feed">
                    <?php if (empty($activity)): ?>
                        <p class="text-muted">No recent activity.</p>
                    <?php endif; ?>
                    <?php foreach ($activity as $event): ?>
                        <div class="activity-item">
                            <div style="font-size: 0.9rem; font-weight: 500;"><?= $event->description ?></div>
                            <div class="text-muted"
                                 style="font-size: 0.75rem;"><?= \Carbon\Carbon::parse($event->created_at)->diffForHumans() ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #f1f5f9;">

                <div class="payout-summary">
                    <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">Quick Links</h4>
                    <ul style="list-style: none; padding: 0; font-size: 0.875rem;">
                        <li style="margin-bottom: 0.5rem;"><a href="#" class="text-primary"
                                                              style="text-decoration: none;">View Public Profile</a>
                        </li>
                        <li><a href="#" class="text-primary" style="text-decoration: none;">Payment Settings</a></li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</div>