<h1>Active Websites</h1>

<style>
    body {
        font-family: "Inter", Arial, sans-serif;
        background-color: #f5f6fa;
        color: #333;
        margin: 2rem;
    }

    h1 {
        text-align: center;
        color: #222;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .tile {
        background-color: #fff;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        padding: 1.25rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .tile:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
    }

    .tile h2 {
        font-size: 1.1rem;
        margin: 0 0 0.5rem;
        color: #0073e6;
    }

    .tile p {
        font-size: 0.9rem;
        color: #555;
        flex-grow: 1;
    }

    .tile a {
        display: inline-block;
        margin-top: 0.75rem;
        text-decoration: none;
        background-color: #0073e6;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        text-align: center;
        transition: background-color 0.2s;
    }

    .tile a:hover {
        background-color: #005bb5;
    }

    .no-sites {
        text-align: center;
        margin-top: 3rem;
        color: #777;
    }
</style>

<?php if ($sites->count() === 0): ?>
    <p class="no-sites">No active websites found.</p>
<?php else: ?>
    <div class="grid">
        <?php foreach ($sites as $site): ?>
            <div class="tile">
                <h2><?= htmlspecialchars($site->name) ?></h2>
                <?php if (!empty($site->description)): ?>
                    <p><?= htmlspecialchars($site->description) ?></p>
                <?php else: ?>
                    <p>No description available.</p>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($site->slug) ?>/<?= $site->url_handle ?>" target="_blank">Visit Website</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>