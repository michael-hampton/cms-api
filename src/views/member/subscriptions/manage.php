<?php
/**
 * @var \App\Models\Site $site
 * @var \App\Models\MemberSubscriptionPreference $preference
 * @var \App\Models\Member $member
 * @var \App\Framework\Support\Collection $categories
 * @var array $contentTypes
 * @var string $token
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscription - <?= htmlspecialchars($site->name) ?></title>
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .header p {
            color: #7f8c8d;
            font-size: 16px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .form-description {
            display: block;
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #3498db;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .radio-option:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .radio-option input {
            margin-right: 12px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .radio-option.selected {
            border-color: #3498db;
            background: #ebf5fb;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-option:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .checkbox-option input {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-option.selected {
            border-color: #3498db;
            background: #ebf5fb;
        }

        .btn {
            display: inline-block;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .member-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .member-info strong {
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Manage Your Subscription</h1>
        <p>Update your email preferences for <?= htmlspecialchars($site->name) ?></p>
    </div>

    <div class="alert alert-info">
        <strong>Quick Access</strong><br>
        You're managing your preferences without logging in. Changes will be saved immediately.
    </div>

    <div class="card">
        <div class="member-info">
            <strong>Email:</strong> <?= htmlspecialchars($member->email) ?><br>
            <strong>Member Since:</strong> <?= $member->created_at->format('M j, Y') ?>
        </div>
    </div>

    <form method="POST" action="/member/subscriptions/manage/<?= htmlspecialchars($token) ?>" id="preferencesForm">
        <!-- Email Notifications Toggle -->
        <div class="card">
            <div class="section-title">Email Notifications</div>

            <div class="form-group">
                <label class="form-label">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div>Receive email notifications</div>
                            <span class="form-description">
                                    Turn off to stop receiving all promotional emails
                                </span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox"
                                   name="email_notifications"
                                   value="1"
                                    <?= $preference->email_notifications ? 'checked' : '' ?>
                                   onchange="toggleDependentFields(this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </label>
            </div>
        </div>

        <!-- Frequency Selection -->
        <div class="card" id="frequencyCard">
            <div class="section-title">Email Frequency</div>

            <div class="form-group">
                <label class="form-label">How often would you like to receive emails?</label>
                <div class="radio-group">
                    <label class="radio-option <?= $preference->newsletter_frequency === 'daily' ? 'selected' : '' ?>">
                        <input type="radio"
                               name="newsletter_frequency"
                               value="daily"
                                <?= $preference->newsletter_frequency === 'daily' ? 'checked' : '' ?>
                               onchange="updateRadioSelection(this)">
                        <div>
                            <strong>Daily</strong>
                            <div style="font-size: 14px; color: #7f8c8d;">Get updates every day</div>
                        </div>
                    </label>

                    <label class="radio-option <?= $preference->newsletter_frequency === 'weekly' ? 'selected' : '' ?>">
                        <input type="radio"
                               name="newsletter_frequency"
                               value="weekly"
                                <?= $preference->newsletter_frequency === 'weekly' ? 'checked' : '' ?>
                               onchange="updateRadioSelection(this)">
                        <div>
                            <strong>Weekly</strong>
                            <div style="font-size: 14px; color: #7f8c8d;">Weekly digest of updates</div>
                        </div>
                    </label>

                    <label class="radio-option <?= $preference->newsletter_frequency === 'monthly' ? 'selected' : '' ?>">
                        <input type="radio"
                               name="newsletter_frequency"
                               value="monthly"
                                <?= $preference->newsletter_frequency === 'monthly' ? 'checked' : '' ?>
                               onchange="updateRadioSelection(this)">
                        <div>
                            <strong>Monthly</strong>
                            <div style="font-size: 14px; color: #7f8c8d;">Monthly summary</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Content Type Preferences -->
        <div class="card" id="contentTypesCard">
            <div class="section-title">Content Types</div>

            <div class="form-group">
                <label class="form-label">What type of content would you like to receive?</label>
                <span class="form-description">Leave all unchecked to receive all types</span>

                <div class="checkbox-group">
                    <?php
                    $selectedTypes = $preference->content_types ?? [];
                    foreach ($contentTypes as $key => $label):
                        ?>
                        <label class="checkbox-option <?= in_array($key, $selectedTypes) ? 'selected' : '' ?>">
                            <input type="checkbox"
                                   name="content_types[]"
                                   value="<?= htmlspecialchars($key) ?>"
                                    <?= in_array($key, $selectedTypes) ? 'checked' : '' ?>
                                   onchange="updateCheckboxSelection(this)">
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Category Preferences -->
        <div class="card" id="categoriesCard">
            <div class="section-title">Category Preferences</div>

            <div class="form-group">
                <label class="form-label">Which categories interest you?</label>
                <span class="form-description">Leave all unchecked to receive content from all categories</span>

                <div class="checkbox-group">
                    <?php
                    $selectedCategories = $preference->category_preferences ?? [];
                    foreach ($categories as $category):
                        ?>
                        <label class="checkbox-option <?= in_array($category->id, $selectedCategories) ? 'selected' : '' ?>">
                            <input type="checkbox"
                                   name="category_preferences[]"
                                   value="<?= $category->id ?>"
                                    <?= in_array($category->id, $selectedCategories) ? 'checked' : '' ?>
                                   onchange="updateCheckboxSelection(this)">
                            <?= htmlspecialchars($category->name) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                Save Preferences
            </button>
            <button type="button" class="btn btn-danger" onclick="confirmUnsubscribe()">
                Unsubscribe from All
            </button>
        </div>
    </form>

    <div class="card" style="margin-top: 30px; text-align: center; font-size: 14px; color: #7f8c8d;">
        <p>
            Want full access to your account?
            <a href="/member/login" style="color: #3498db; text-decoration: none;">Log in here</a>
        </p>
    </div>
</div>

<script>
    function toggleDependentFields(enabled) {
        const cards = ['frequencyCard', 'contentTypesCard', 'categoriesCard'];
        cards.forEach(cardId => {
            const card = document.getElementById(cardId);
            if (card) {
                card.style.opacity = enabled ? '1' : '0.5';
                card.style.pointerEvents = enabled ? 'auto' : 'none';
            }
        });
    }

    function updateRadioSelection(radio) {
        const options = radio.closest('.radio-group').querySelectorAll('.radio-option');
        options.forEach(opt => opt.classList.remove('selected'));
        radio.closest('.radio-option').classList.add('selected');
    }

    function updateCheckboxSelection(checkbox) {
        const option = checkbox.closest('.checkbox-option');
        if (checkbox.checked) {
            option.classList.add('selected');
        } else {
            option.classList.remove('selected');
        }
    }

    function confirmUnsubscribe() {
        if (confirm('Are you sure you want to unsubscribe from all emails? You can always come back and resubscribe.')) {
            window.location.href = '/member/subscriptions/unsubscribe/<?= htmlspecialchars($token) ?>';
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function () {
        const emailNotifications = document.querySelector('input[name="email_notifications"]');
        if (emailNotifications) {
            toggleDependentFields(emailNotifications.checked);
        }
    });
</script>
</body>
</html>