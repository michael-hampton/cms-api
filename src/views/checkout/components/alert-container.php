<?php
/**
 * Alert container.
 * The JS showAlert() function in the layout writes into this element.
 *
 * @var string|null $id DOM id — defaults to 'alert-container'
 */
$containerId = $id ?? 'alert-container';
?>
<div id="<?= htmlspecialchars($containerId) ?>"></div>