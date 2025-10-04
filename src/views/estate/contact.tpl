@include('estate/header')

<?php
// views/estate/contact.php
$title = $page->title ?? 'Contact Us - Premier Properties';
$description = $page->meta_description ?? 'Get in touch with Premier Properties';
?>

<main class="mt-20">
    <div class="container" style="padding: 2rem;">
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin: 0; padding-left: 1.5rem;">
                <?php foreach ($errors as $field => $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($page && $page->blocks): ?>
        <?php foreach ($page->blocks as $block): ?>
        <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Contact Form -->
    </div>
</main>

@include('estate/footer');
