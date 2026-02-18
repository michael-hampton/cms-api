# You've received a gift article! 🎁

Hello,

Someone thought you'd enjoy reading this article.

@panel(📖 <?= htmlspecialchars($articleTitle) ?>)

<?php if (!empty($personalMessage)): ?>
    ## Personal Message

    <?= htmlspecialchars($personalMessage) ?>

    @divider
<?php endif; ?>

@button(Read Article, <?= $shareLink ?>)

@subcopy(This article was shared with you via <?= htmlspecialchars($siteName) ?>. The link above is unique to you.)