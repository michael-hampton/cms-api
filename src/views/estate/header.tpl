<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Premier Properties') ?></title>
    <meta name="description" content="<?= htmlspecialchars($description ?? 'Premier Properties - Luxury Real Estate in London') ?>">

    <?php

use App\Framework\Support\SiteContext;

$cssFile = asset(SiteContext::css(), 'css');

?>

    <link rel="stylesheet" href="<?= $cssFile ?>">
</head>
<body>
<header class="header">
    <?php echo (new \App\Services\MenuRenderer())->render($menu, ['layout' => 'vertical', 'logo' => true, 'title' => $title ?? $page->title]) ?>
</header>
