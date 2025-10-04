<?php
// views/estate/404.php
$title = '404 - Page Not Found';
$description = 'The page you are looking for could not be found.';
?>

<div class="container" style="padding: 4rem 2rem; text-align: center;">
    <div style="max-width: 600px; margin: 0 auto;">
        <h1 style="font-size: 4rem; font-weight: 800; color: var(--primary); margin-bottom: 1rem;">404</h1>
        <h2 style="font-size: 2rem; margin-bottom: 1.5rem;">Page Not Found</h2>
        <p style="font-size: 1.125rem; color: var(--text-light); margin-bottom: 2rem;"><?= htmlspecialchars($message ?? 'The page you are looking for could not be found.') ?></p>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="/" class="btn btn-primary">Go Home</a>
            <a href="/properties" class="btn btn-outline">Browse Properties</a>
        </div>
    </div>
</div>