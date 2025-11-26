<?php
// views/admin/subscription-plans/create.php
/**
 * @var \App\Models\Site $site
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Subscription Plan - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 15px;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .features-list {
            margin-top: 10px;
        }

        .feature-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .feature-item input {
            flex: 1;
        }

        .btn-add-feature {
            background: #27ae60;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>

</head>
<body>
<div class="container">
    <div class="header">
        <h1>Create Subscription Plan</h1>
        <p>Define a new subscription plan for members</p>
    </div>

    <div class="form-card">
        <form method="POST" action="/<?= \App\Framework\Support\SiteContext::slug() ?>/admin/subscription-plans">
            <div class="form-group">
                <label class="form-label">Plan Name *</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Slug (URL-friendly name)</label>
                <input type="text" name="slug" class="form-control"
                       placeholder="Leave blank to auto-generate">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Price *</label>
                <input type="number" name="price" class="form-control"
                       step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label class="form-label">Currency *</label>
                <select name="currency" class="form-control" required>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="GBP">GBP</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Billing Period *</label>
                <select name="billing_period" class="form-control" required>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                    <option value="lifetime">Lifetime</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Trial Days</label>
                <input type="number" name="trial_days" class="form-control"
                       min="0" value="0">
            </div>

            <div class="form-group">
                <label class="form-label">Features</label>
                <div id="featuresList" class="features-list">
                    <div class="feature-item">
                        <input type="text" name="features[]" class="form-control"
                               placeholder="Feature description">
                        <button type="button" class="btn-add-feature" onclick="addFeature()">+</button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" value="1" checked id="is_active">
                    <label for="is_active">Active</label>
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_featured" value="1" id="is_featured">
                    <label for="is_featured">Featured</label>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Create Plan</button>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/admin/subscription-plans"
                   class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
    function addFeature() {
        const list = document.getElementById('featuresList');
        const item = document.createElement('div');
        item.className = 'feature-item';
        item.innerHTML = `
        <input type="text" name="features[]" class="form-control" placeholder="Feature description">
        <button type="button" class="btn-add-feature" onclick="this.parentElement.remove()">-</button>
    `;
        list.appendChild(item);
    }
</script>
</body>
</html>