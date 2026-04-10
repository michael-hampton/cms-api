<div class="container">
    <div class="header-row"
         style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>Your Articles</h1>
        <a href="" class="btn btn-primary">+ Create New Article</a>
    </div>

    <div class="content-card">
        <table class="article-table" style="width: 100%; border-collapse: collapse;">
            <thead>
            <tr style="text-align: left; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <th style="padding: 1rem;">Title</th>
                <th style="padding: 1rem;">Status</th>
                <th style="padding: 1rem;">Type</th>
                <th style="padding: 1rem;">Earnings</th>
                <th style="padding: 1rem; text-align: right;">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($articles)): ?>
                <tr>
                    <td colspan="5" style="padding: 3rem; text-align: center;" class="text-muted">
                        You haven't written any articles yet.
                    </td>
                </tr>
            <?php endif; ?>
            <?php
            foreach ($articles as $article): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 1rem;">
                        <div style="font-weight: 600;"><?= $article->title ?: 'Untitled' ?></div>
                        <small class="text-muted">Last updated <?= $article->updated_at->format('d
                            M Y') ?></small>
                    </td>
                    <td style="padding: 1rem;">
                            <span class="badge badge-<?= $article->status ?>">
                                <?= $article->status ?>
                            </span>
                    </td>
                    <td style="padding: 1rem;">
                        <?= $article->is_paid ? '💰 Paid' : '🆓 Free' ?>
                    </td>
                    <td style="padding: 1rem;">
                        <?= $article->is_paid ? '£' . number_format($article->price / 100, 2) : '-' ?>
                    </td>
                    <td style="padding: 1rem; text-align: right;">
                        <a href="/<?= $site ?>/open-collab/articles/edit/<?= $article->id ?>" class="btn btn-sm"
                           style="background: #f1f5f9; margin-right: 5px;">Edit</a>

                        <button onclick="deleteArticle(<?= $article->id ?>)" class="btn btn-sm btn-danger"
                                style="background: #fee2e2; color: #b91c1c; border: none;">
                            Delete
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    async function deleteArticle(id) {
        if (!confirm('Are you sure you want to delete this article? This action cannot be undone.')) return;

        const token = localStorage.getItem('api_token');
        const res = await fetch(`/api/<?= $site ?>/open-collab/pages/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`,
                'X-CSRF-TOKEN': '<?= csrf_token() ?>'
            }
        });

        if (res.ok) {
            window.location.reload();
        } else {
            alert('Failed to delete article.');
        }
    }
</script>