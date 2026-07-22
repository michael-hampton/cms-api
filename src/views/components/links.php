<?php
/**
 * Social share island driven by page_social via PageSocialShareState DTO.
 *
 * Expected: $socialShare (PageSocialShareState|null)
 */

use App\DTO\PublicContent\Social\PageSocialShareState;

$socialShare = $socialShare ?? null;

if (
    !$socialShare instanceof PageSocialShareState
    || !$socialShare->enableSharing
    || $socialShare->platforms === []
) {
    return;
}

$shareText = rawurlencode($socialShare->shareText);
$shareUrl = rawurlencode($socialShare->shareUrl);
$platforms = $socialShare->platforms;
?>
<div class="social-sharing" data-component="social-links">
    <h4>Share this page:</h4>
    <div class="social-buttons">
        <?php if (in_array('facebook', $platforms, true)): ?>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>"
               target="_blank" rel="noopener noreferrer" class="social-btn facebook">Facebook</a>
        <?php endif; ?>

        <?php if (in_array('twitter', $platforms, true)): ?>
            <a href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&url=<?= $shareUrl ?>"
               target="_blank" rel="noopener noreferrer" class="social-btn twitter">Twitter</a>
        <?php endif; ?>

        <?php if (in_array('linkedin', $platforms, true)): ?>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>"
               target="_blank" rel="noopener noreferrer" class="social-btn linkedin">LinkedIn</a>
        <?php endif; ?>

        <?php if (in_array('email', $platforms, true)): ?>
            <a href="mailto:?subject=<?= $shareText ?>&body=<?= $shareUrl ?>"
               class="social-btn email">Email</a>
        <?php endif; ?>
    </div>
</div>
