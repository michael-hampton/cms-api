<?php
// views/admin/subscription-plans/index.php
/**
 * @var \App\Models\Site $site
 * @var array $plansWithStats
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscription Plans - <?= htmlspecialchars($site->name) ?></title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 32px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Subscription Plans</h1>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/admin/subscription-plans/create"
           class="btn btn-primary">
            + Create Plan
        </a>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <?php unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <?php if (count($plansWithStats) > 0): ?>
            <table>
                <thead>
                <tr>
                    <th>Plan Name</th>
                    <th>Price</th>
                    <th>Billing Period</th>
                    <th>Subscribers</th>
                    <th>Revenue</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($plansWithStats as $item): ?>
                    <?php $plan = $item['plan']; ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($plan->name) ?></strong>
                            <?php if ($plan->is_featured): ?>
                                <span class="badge badge-warning">Featured</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($plan->currency) ?>
                            <?= number_format($plan->price, 2) ?>
                        </td>
                        <td><?= ucfirst($plan->billing_period) ?></td>
                        <td><?= $item['subscriber_count'] ?></td>
                        <td>
                            <?= htmlspecialchars($plan->currency) ?>
                            <?= number_format($item['revenue'], 2) ?>
                        </td>
                        <td>
                        <span class="badge <?= $plan->is_active ? 'badge-success' : 'badge-danger' ?>">
                            <?= $plan->is_active ? 'Active' : 'Inactive' ?>
                        </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="<?= \App\Framework\Support\SiteContext::slug() ?>/admin/subscription-plans/<?= $plan->id ?>/edit"
                                   class="btn btn-sm btn-primary">
                                    Edit
                                </a>
                                <form method="POST"
                                      action="<?= \App\Framework\Support\SiteContext::slug() ?>/admin/subscription-plans/<?= $plan->id ?>/toggle-active"
                                      style="display: inline;">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <?= $plan->is_active ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No subscription plans yet.</p>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/admin/subscription-plans/create"
                   class="btn btn-primary" style="margin-top: 20px;">
                    Create Your First Plan
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>