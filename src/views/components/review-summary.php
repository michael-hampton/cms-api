<?php
// components/review-summary.php
$verdict = trim((string) ($verdict ?? ''));
$pros = is_array($pros ?? null) ? $pros : [];
$cons = is_array($cons ?? null) ? $cons : [];
if ($verdict === '' && !$pros && !$cons) return;
?>
<div class="public-content-review-summary">
    <?php if ($verdict !== ''): ?>
        <p class="public-content-review-summary__verdict"><?= htmlspecialchars($verdict, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="public-content-review-summary__columns">
        <?php if ($pros): ?>
            <div class="public-content-review-summary__pros">
                <h3>Pros</h3>
                <ul>
                    <?php foreach ($pros as $pro): ?>
                        <li><?= htmlspecialchars((string) $pro, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($cons): ?>
            <div class="public-content-review-summary__cons">
                <h3>Cons</h3>
                <ul>
                    <?php foreach ($cons as $con): ?>
                        <li><?= htmlspecialchars((string) $con, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>