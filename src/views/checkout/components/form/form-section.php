<?php
$close = $close ?? false;

if ($close): ?>
    </div><?php else:
    $title = $title ?? null;
    $id = $id ?? null;
    $style = $style ?? '';
    $class = $class ?? '';
    $headerContent = $headerContent ?? null; // New attribute for extra header HTML
    ?>
<div class="form-section<?= $class ? ' ' . htmlspecialchars($class) : '' ?>"
        <?= $id ? 'id="' . htmlspecialchars($id) . '"' : '' ?>
        <?= $style ? 'style="' . htmlspecialchars($style) . '"' : '' ?>>

    <?php if ($title || $headerContent): ?>
    <div class="section-header"
         style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <?php if ($title): ?>
            <h2 class="section-title" style="margin-bottom: 0; padding-bottom: 0; border: none;">
                <?= htmlspecialchars($title) ?>
            </h2>
        <?php endif; ?>
        <?= $headerContent ?>
    </div>
<?php endif; ?>
<?php endif; ?>