<?php if ($messages = \App\Framework\Session\Session::getFlash('success')): ?>
    <div class="alert alert-success"
         style="padding: 1rem; background: #d4edda; border: 1px solid #c3e6cb; margin-bottom: 1rem;">
        <?= htmlspecialchars($messages) ?>
    </div>
<?php endif; ?>

<?php if ($errors = \App\Framework\Session\Session::getFlash('error')): ?>
    <div class="alert alert-danger"
         style="padding: 1rem; background: #f8d7da; border: 1px solid #f5c6cb; margin-bottom: 1rem;">
        <?= htmlspecialchars($errors) ?>
    </div>
<?php endif; ?>