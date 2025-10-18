<?php if ($page->authors && $page->authors->count() > 0): ?>
    <section class="authors-section" style="margin-top: 3rem; padding: 2rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
        <h3 style="margin: 0 0 1.5rem; font-size: 1.75rem;">
            <?= $page->authors->count() > 1 ? 'Authors' : 'Author' ?>
        </h3>

        <?php foreach ($page->authors as $count => $author): ?>
            <div style="display: flex; gap: 2rem; align-items: start; margin-bottom: 2rem; padding-bottom: 2rem; <?= $page->authors->count() === ($count + 1) ? 'border-bottom: 1px solid #dee2e6;' : '' ?>">
                <?php if ($author->avatar): ?>
                    <div style="flex-shrink: 0;">
                        <img
                            src="<?= htmlspecialchars($author->avatar) ?>"
                            alt="<?= htmlspecialchars($author->name) ?>"
                            style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                        >
                    </div>
                <?php endif; ?>

                <div style="flex: 1;">
                    <h4 style="margin: 0 0 0.5rem; font-size: 1.5rem;">
                        <?= htmlspecialchars($author->name) ?>
                        <?php
                        $pivotRole = $author->pivot->role ?? 'primary';
                        if ($pivotRole === 'contributor'):
                            ?>
                            <span style="font-size: 0.875rem; color: #6c757d; font-weight: normal;">(Contributor)</span>
                        <?php endif; ?>
                    </h4>

                    <?php if ($author->bio): ?>
                        <p style="color: #666; line-height: 1.6; margin: 0.5rem 0 1rem;">
                            <?php
                            $bio = $author->bio;
                            $truncated = strlen($bio) > 200 ? substr($bio, 0, 200) . '...' : $bio;
                            echo htmlspecialchars($truncated);
                            ?>
                        </p>
                    <?php endif; ?>


                    <a href="/authors/<?= htmlspecialchars($author->slug) ?>"
                       style="display: inline-block; padding: 0.5rem 1.5rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.2s;"
                       onmouseover="this.style.background='#0056b3'"
                       onmouseout="this.style.background='#007bff'"
                    >
                        View Full Profile →
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>