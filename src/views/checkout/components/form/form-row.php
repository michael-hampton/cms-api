<?php
/**
 * @var bool $close If true, renders only the closing tag.
 * @var int $cols
 */
$close = $close ?? false;

if ($close): ?>
    </div><?php else:
    $cols = $cols ?? 2;
    $class = $class ?? '';
    $style = $style ?? '';
    $gridStyle = 'grid-template-columns: ' . ($cols === 1 ? '1fr' : '1fr 1fr') . ';';
    if ($style) $gridStyle .= ' ' . $style;
    ?>
<div class="form-row<?= $class ? ' ' . htmlspecialchars($class) : '' ?>"
     style="<?= htmlspecialchars($gridStyle) ?>">
<?php endif; ?>