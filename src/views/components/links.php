<?php if ($page->social && $page->social->enable_sharing): ?>
    <div class="social-sharing">
        <h4>Share this page:</h4>
        <div class="social-buttons">
            <?php
            $platforms = $page->social->platforms ?? [];
            $shareText = urlencode($page->social->share_text ?? $page->title);
            $currentUrl = urlencode("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
            ?>

            <?php if (in_array('facebook', $platforms)): ?>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $currentUrl ?>"
                   target="_blank" class="social-btn facebook">Facebook</a>
            <?php endif; ?>

            <?php if (in_array('twitter', $platforms)): ?>
                <a href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&url=<?= $currentUrl ?>"
                   target="_blank" class="social-btn twitter">Twitter</a>
            <?php endif; ?>

            <?php if (in_array('linkedin', $platforms)): ?>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $currentUrl ?>"
                   target="_blank" class="social-btn linkedin">LinkedIn</a>
            <?php endif; ?>

            <?php if (in_array('email', $platforms)): ?>
                <a href="mailto:?subject=<?= $shareText ?>&body=<?= $currentUrl ?>"
                   class="social-btn email">Email</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>