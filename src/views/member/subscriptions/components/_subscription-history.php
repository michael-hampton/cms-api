<div class="card">
    <h2>
        <span class="icon">📜</span>
        Subscription History
    </h2>

    <?php if ($subscriptionHistory->count() > 0): ?>
        <table class="history-table">
            <thead>
            <tr>
                <th>Plan</th>
                <th>Type</th>
                <th>Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Price</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($subscriptionHistory as $sub): ?>
                <tr>
                    <td style="font-weight: 600;">
                        <?= htmlspecialchars($sub->plan_name) ?>
                        <?php if ($sub->delivery_type): ?>
                            <span style="font-size: 12px; color: #64748b; display: block; margin-top: 4px;">
                                <?= $sub->isDigital() ? '💻 Digital' : '📦 Print' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge"
                              style="background: <?= $sub->type === 'paid' ? '#e0e7ff' : '#f3f4f6' ?>; color: <?= $sub->type === 'paid' ? '#3730a3' : '#374151' ?>;">
                            <?= ucfirst(htmlspecialchars($sub->type ?? 'standard')) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $sub->status === 'active' ? 'success' : 'warning' ?>">
                            <?= ucfirst(htmlspecialchars($sub->status)) ?>
                        </span>
                    </td>
                    <td><?= $sub->start_date->format('M d, Y') ?></td>
                    <td>
                        <?= $sub->end_date ? $sub->end_date->format('M d, Y') : 'N/A' ?>
                    </td>
                    <td style="font-weight: 600;">
                        <?= htmlspecialchars($sub->currency) ?>
                        <?= number_format($sub->price, 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <p>No subscription history</p>
        </div>
    <?php endif; ?>
</div>