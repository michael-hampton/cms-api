<?php if (!empty($errors)): ?>
    <div class="error-block"
         style="color: #721c24; background: #f8d7da; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem;">
        <strong>Please fix the following:</strong>
        <ul style="margin-top: 0.5rem;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>