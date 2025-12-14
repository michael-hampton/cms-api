<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Newsletters - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .newsletters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .newsletter-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            position: relative;
        }

        .newsletter-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .newsletter-card.subscribed {
            border: 2px solid var(--success-color);
            background: linear-gradient(to bottom, #f0fdf4 0%, white 100%);
        }

        .newsletter-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .newsletter-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .newsletter-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.subscribed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.unsubscribed {
            background: #f3f4f6;
            color: #6b7280;
        }

        .newsletter-content {
            margin: 1rem 0;
        }

        .newsletter-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .newsletter-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .newsletter-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .newsletter-checkbox {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .floating-action-bar {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 1rem 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            align-items: center;
            gap: 1.5rem;
            z-index: 100;
        }

        .floating-action-bar.show {
            display: flex;
        }

        .selected-count {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Newsletter Modal */
        .newsletter-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .newsletter-modal-overlay.show {
            display: flex;
        }

        .newsletter-modal {
            background: white;
            border-radius: 1rem;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .newsletter-modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .newsletter-modal-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .newsletter-modal-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .newsletter-modal-body {
            padding: 2rem;
        }

        .newsletter-modal-grid {
            display: grid;
            gap: 1rem;
        }

        .newsletter-modal-item {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 1rem;
        }

        .newsletter-modal-item:hover {
            border-color: var(--primary-color);
            background: #f8f9ff;
        }

        .newsletter-modal-item.selected {
            border-color: var(--primary-color);
            background: linear-gradient(to right, #eff6ff 0%, #f8f9ff 100%);
        }

        .newsletter-modal-item.already-subscribed {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f9fafb;
        }

        .newsletter-modal-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--primary-color);
            flex-shrink: 0;
            margin-top: 0.25rem;
        }

        .newsletter-modal-content {
            flex: 1;
        }

        .newsletter-modal-item-title {
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .newsletter-modal-item-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .newsletter-modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
            position: sticky;
            bottom: 0;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .close-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: var(--text-secondary);
            font-size: 1.5rem;
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .newsletters-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .btn-group {
                width: 100%;
                flex-direction: column;
            }

            .floating-action-bar {
                left: 1rem;
                right: 1rem;
                transform: none;
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Newsletters</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                Manage your newsletter subscriptions
            </p>
        </div>
        <div class="btn-group">
            <button onclick="openNewsletterModal()" class="btn btn-primary">
                📧 Subscribe to Newsletters
            </button>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="btn btn-secondary">
                ← Back to Dashboard
            </a>
        </div>
    </div>

    <div id="alert-container"></div>

    <h2 class="section-title">
        <span>📬</span>
        All Available Newsletters
    </h2>

    <?php if ($availableNewsletters->isEmpty()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📧</div>
            <h3>No Newsletters Available</h3>
            <p>Check back later for newsletter options</p>
        </div>
    <?php else: ?>
        <div class="newsletters-grid">
            <?php foreach ($availableNewsletters as $newsletter): ?>
                <?php
                $isSubscribed = false;
                foreach ($subscriptions as $sub) {
                    if ($sub->newsletter_id === $newsletter->id) {
                        $isSubscribed = true;
                        break;
                    }
                }
                ?>
                <div class="newsletter-card <?= $isSubscribed ? 'subscribed' : '' ?>"
                     data-newsletter-id="<?= $newsletter->id ?>">

                    <?php if (!$isSubscribed): ?>
                        <input type="checkbox"
                               class="newsletter-checkbox"
                               data-newsletter-id="<?= $newsletter->id ?>"
                               onchange="updateSelection()">
                    <?php endif; ?>

                    <div class="newsletter-header">
                        <div class="newsletter-icon">📧</div>
                        <div class="newsletter-status">
                            <span class="status-badge <?= $isSubscribed ? 'subscribed' : 'unsubscribed' ?>">
                                <?= $isSubscribed ? '✓ Subscribed' : 'Not Subscribed' ?>
                            </span>
                        </div>
                    </div>

                    <div class="newsletter-content">
                        <h3 class="newsletter-title"><?= htmlspecialchars($newsletter->title) ?></h3>
                        <?php if ($newsletter->content): ?>
                            <p class="newsletter-description"><?= htmlspecialchars($newsletter->content) ?></p>
                        <?php endif; ?>

                        <div class="newsletter-meta">
                            <div class="meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <?= ucfirst($newsletter->interval) ?>
                            </div>
                            <?php if ($newsletter->active): ?>
                                <div class="meta-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Active
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($isSubscribed): ?>
                        <button onclick="quickUnsubscribe(<?= $newsletter->id ?>)"
                                class="btn btn-danger btn-sm" style="width: 100%;">
                            Unsubscribe
                        </button>
                    <?php else: ?>
                        <button onclick="quickSubscribe(<?= $newsletter->id ?>)"
                                class="btn btn-primary btn-sm" style="width: 100%;">
                            Subscribe
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Floating Action Bar -->
<div id="floatingActionBar" class="floating-action-bar">
    <span class="selected-count">
        <span id="selectedCount">0</span> newsletter(s) selected
    </span>
    <div class="btn-group">
        <button onclick="subscribeSelected()" class="btn btn-primary">
            Subscribe to Selected
        </button>
        <button onclick="clearSelection()" class="btn btn-secondary">
            Clear
        </button>
    </div>
</div>

<!-- Newsletter Modal -->
<div id="newsletterModal" class="newsletter-modal-overlay">
    <div class="newsletter-modal">
        <div class="newsletter-modal-header">
            <button onclick="closeNewsletterModal()" class="close-btn">×</button>
            <h2 class="newsletter-modal-title">Subscribe to Newsletters</h2>
            <p class="newsletter-modal-subtitle">
                Select the newsletters you'd like to receive
            </p>
        </div>

        <div class="newsletter-modal-body">
            <div class="newsletter-modal-grid" id="modalNewsletterGrid">
                <?php foreach ($availableNewsletters as $newsletter): ?>
                    <?php
                    $isSubscribed = false;
                    $subscriberId = null;
                    foreach ($subscriptions as $sub) {
                        if ($sub->newsletter_id === $newsletter->id) {
                            $isSubscribed = true;
                            $subscriberId = $sub->id;
                            break;
                        }
                    }
                    ?>
                    <div class="newsletter-modal-item <?= $isSubscribed ? 'already-subscribed' : '' ?>"
                         data-newsletter-id="<?= $newsletter->id ?>"
                         onclick="<?= !$isSubscribed ? 'toggleModalCheckbox(this)' : '' ?>">
                        <input type="checkbox"
                               class="newsletter-modal-checkbox"
                               data-newsletter-id="<?= $newsletter->id ?>"
                                <?= $isSubscribed ? 'disabled checked' : '' ?>
                               onclick="event.stopPropagation()">
                        <div class="newsletter-modal-content">
                            <div class="newsletter-modal-item-title">
                                <?= htmlspecialchars($newsletter->title) ?>
                                <?= $isSubscribed ? '<span style="color: var(--success-color); font-size: 0.875rem;">(Already subscribed)</span>' : '' ?>
                            </div>
                            <div class="newsletter-modal-item-description">
                                <?= htmlspecialchars($newsletter->content ?: 'Stay updated with our latest content') ?>
                                <br>
                                <strong>Frequency:</strong> <?= ucfirst($newsletter->interval) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="newsletter-modal-footer">
            <div class="select-all-container">
                <input type="checkbox" id="selectAllCheckbox" onchange="selectAllNewsletters(this.checked)">
                <label for="selectAllCheckbox" style="cursor: pointer; user-select: none;">
                    Select All Available
                </label>
            </div>
            <div class="btn-group">
                <button onclick="closeNewsletterModal()" class="btn btn-secondary">
                    Cancel
                </button>
                <button onclick="subscribeModalSelected()" class="btn btn-primary">
                    Subscribe to Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    let selectedNewsletters = new Set();

    function updateSelection() {
        selectedNewsletters.clear();
        document.querySelectorAll('.newsletter-checkbox:checked').forEach(checkbox => {
            selectedNewsletters.add(parseInt(checkbox.dataset.newsletterId));
        });

        const floatingBar = document.getElementById('floatingActionBar');
        const count = selectedNewsletters.size;

        document.getElementById('selectedCount').textContent = count;

        if (count > 0) {
            floatingBar.classList.add('show');
        } else {
            floatingBar.classList.remove('show');
        }
    }

    function clearSelection() {
        document.querySelectorAll('.newsletter-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelection();
    }

    async function subscribeSelected() {
        if (selectedNewsletters.size === 0) {
            showAlert('Please select at least one newsletter', 'error');
            return;
        }

        const newsletterIds = Array.from(selectedNewsletters);

        try {
            const response = await fetch(`/${SITE}/member/newsletters/bulk-subscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({newsletter_ids: newsletterIds})
            });

            const data = await response.json();

            if (data.success) {
                showAlert(`Successfully subscribed to ${newsletterIds.length} newsletter(s)`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to subscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to subscribe', 'error');
        }
    }

    async function quickSubscribe(newsletterId) {
        try {
            const response = await fetch(`/${SITE}/member/newsletter/signup`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({newsletter_id: newsletterId})
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Successfully subscribed!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to subscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to subscribe', 'error');
        }
    }

    async function quickUnsubscribe(newsletterId) {
        if (!confirm('Are you sure you want to unsubscribe from this newsletter?')) {
            return;
        }

        // Find subscriber ID
        const subscriberId = findSubscriberId(newsletterId);
        if (!subscriberId) {
            showAlert('Subscription not found', 'error');
            return;
        }

        try {
            const response = await fetch(`/${SITE}/member/newsletters/unsubscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ subscriber_id: subscriberId })
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Successfully unsubscribed', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to unsubscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to unsubscribe', 'error');
        }
    }

    function findSubscriberId(newsletterId) {
        // This will need to be populated from PHP data
        const subscriptions = <?= json_encode($subscriptions->map(function ($sub) {
            return ['newsletter_id' => $sub->newsletter_id, 'id' => $sub->id];
        })->toArray()) ?>;

        const sub = subscriptions.find(s => s.newsletter_id === newsletterId);
        return sub ? sub.id : null;
    }

    function openNewsletterModal() {
        document.getElementById('newsletterModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeNewsletterModal() {
        document.getElementById('newsletterModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        // Reset checkboxes
        document.querySelectorAll('.newsletter-modal-checkbox:not([disabled])').forEach(cb => {
            cb.checked = false;
            cb.closest('.newsletter-modal-item').classList.remove('selected');
        });
        document.getElementById('selectAllCheckbox').checked = false;
    }

    function toggleModalCheckbox(item) {
        const checkbox = item.querySelector('.newsletter-modal-checkbox');
        if (!checkbox.disabled) {
            checkbox.checked = !checkbox.checked;
            item.classList.toggle('selected', checkbox.checked);
        }
    }

    function selectAllNewsletters(checked) {
        document.querySelectorAll('.newsletter-modal-checkbox:not([disabled])').forEach(checkbox => {
            checkbox.checked = checked;
            checkbox.closest('.newsletter-modal-item').classList.toggle('selected', checked);
        });
    }

    async function subscribeModalSelected() {
        const selected = Array.from(document.querySelectorAll('.newsletter-modal-checkbox:checked:not([disabled])'))
            .map(cb => parseInt(cb.dataset.newsletterId));

        if (selected.length === 0) {
            showAlert('Please select at least one newsletter', 'error');
            return;
        }

        try {
            const response = await fetch(`/${SITE}/member/newsletters/bulk-subscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({newsletter_ids: selected})
            });

            const data = await response.json();

            if (data.success) {
                showAlert(`Successfully subscribed to ${selected.length} newsletter(s)`, 'success');
                closeNewsletterModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to subscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to subscribe', 'error');
        }
    }

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        alertContainer.innerHTML = `
            <div class="alert ${alertClass}">
                <span>${type === 'success' ? '✓' : '✕'}</span>
                ${escapeHtml(message)}
            </div>
        `;

        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Close modal on outside click
    document.getElementById('newsletterModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeNewsletterModal();
        }
    });
</script>

</body>
</html>