<?php

use App\Framework\Support\SiteContext;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --success-color: #10b981;
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
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
        }

        .form-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: " *";
            color: #ef4444;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .form-help {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .contact-info {
            background: var(--bg-light);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .contact-info h3 {
            margin-bottom: 1rem;
        }

        .contact-info p {
            color: var(--text-secondary);
            line-height: 1.6;
        }
    </style>
</head>
<body>

@include('member._header')

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Contact Support</h1>
        <p class="page-subtitle">We're here to help. Fill out the form below and we'll get back to you as soon as
            possible.</p>
    </div>

    <div class="form-card">
        <div id="alertContainer"></div>

        <form id="supportForm">
            <div class="form-group">
                <label class="form-label required" for="reason">What do you need help with?</label>
                <select id="reason" name="reason" class="form-control" required>
                    <option value="">Select a reason...</option>
                    <?php foreach ($contactReasons as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="subscriptionGroup" style="display: none;">
                <label class="form-label" for="subscription_id">Related Subscription</label>
                <select id="subscription_id" name="subscription_id" class="form-control">
                    <option value="">Select subscription (optional)...</option>
                    <?php foreach ($activeSubscriptions as $sub): ?>
                        <option value="<?= $sub->id ?>">
                            <?= htmlspecialchars($sub->plan_name) ?> -
                            <?= $sub->isPrint() ? '📦 Print' : '💻 Digital' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">Select the subscription your query relates to</div>
            </div>

            <div class="form-group" id="brandGroup" style="display: none;">
                <label class="form-label" for="brand">Brand</label>
                <select id="brand" name="brand" class="form-control">
                    <option value="">Select brand (optional)...</option>
                    <option value="kiplinger">Kiplinger</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label required" for="message">Message</label>
                <textarea id="message" name="message" class="form-control" required
                          placeholder="Please provide as much detail as possible..."></textarea>
                <div class="form-help">The more details you provide, the faster we can help you</div>
            </div>

            <div class="form-group">
                <label class="form-label required" for="contact_name">Your Name</label>
                <input type="text" id="contact_name" name="contact_name" class="form-control"
                       value="<?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label required" for="contact_email">Email Address</label>
                <input type="email" id="contact_email" name="contact_email" class="form-control"
                       value="<?= htmlspecialchars($member->email) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="contact_phone">Phone Number (Optional)</label>
                <input type="tel" id="contact_phone" name="contact_phone" class="form-control"
                       placeholder="+1 (555) 123-4567">
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                Submit Request
            </button>
        </form>

        <div class="contact-info">
            <h3>Need Immediate Help?</h3>
            <p>
                For urgent matters, you can also reach us at:<br>
                <strong>Email:</strong> support@<?= htmlspecialchars($site->domain ?? 'example.com') ?><br>
                <strong>Phone:</strong> 1-800-XXX-XXXX (Mon-Fri, 9am-5pm EST)
            </p>
        </div>
    </div>
</div>

<script>
    const SITE_SLUG = '<?= SiteContext::slug() ?>';

    // Show/hide fields based on reason
    document.getElementById('reason').addEventListener('change', function () {
        const subscriptionGroup = document.getElementById('subscriptionGroup');
        const brandGroup = document.getElementById('brandGroup');
        const subscriptionRelatedReasons = ['delivery_issue', 'billing_question', 'subscription_change'];

        if (subscriptionRelatedReasons.includes(this.value)) {
            subscriptionGroup.style.display = 'block';
            brandGroup.style.display = 'block';
        } else {
            subscriptionGroup.style.display = 'none';
            brandGroup.style.display = 'none';
        }
    });

    document.getElementById('supportForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(`/${SITE_SLUG}/member/support/submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showAlert('success', `${result.message} Your ticket number is #${result.ticket_id}`);
                this.reset();
                setTimeout(() => {
                    window.location.href = `/${SITE_SLUG}/member/dashboard`;
                }, 3000);
            } else {
                showAlert('error', result.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Request';
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('error', 'An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Request';
        }
    });

    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        alertContainer.innerHTML = `
            <div class="alert alert-${type} show">
                ${escapeHtml(message)}
            </div>
        `;

        setTimeout(() => {
            alertContainer.querySelector('.alert')?.classList.remove('show');
        }, 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

</body>
</html>