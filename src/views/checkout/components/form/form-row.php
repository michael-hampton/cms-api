<?php
/**
 * Form row — wraps children in a two-column responsive grid.
 * Usage: call @include with 'content' containing the inner HTML.
 *
 * @var string|null $content Inner HTML (form controls).
 * @var int|null $cols Number of columns (1 or 2). Defaults to 2.
 */
$cols = $cols ?? 2;
$content = $content ?? '';
?>
<div class="form-row" style="grid-template-columns: <?= $cols === 1 ? '1fr' : '1fr 1fr' ?>;">
    <?= $content ?>
</div>