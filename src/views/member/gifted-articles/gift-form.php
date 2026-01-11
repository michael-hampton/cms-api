<?php
/**
 * @var Member $member
 * @var Site $site
 * @var Page $page
 * @var array $allowance
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Article - <?= htmlspecialchars($page->title) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 24px;
        }

        .article-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }

        .article-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        input[type="email"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        input[type="email"]:focus,
        textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .allowance-info {
            background: #e8f5e9;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .allowance-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .allowance-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .share-link-container {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .share-link {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .share-link input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }

        .btn-copy {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-copy:hover {
            background: #0056b3;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4CAF50;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Gift This Article</h1>

    <div class="article-info">
        <div class="article-title"><?= htmlspecialchars($page->title) ?></div>
        <div style="font-size: 14px; color: #666;">
            Share this article with someone special
        </div>
    </div>

    <?php if (!$allowance['can_gift']): ?>
        <div class="allowance-info allowance-error">
            <strong>Gift limit reached!</strong> You've used all <?= $allowance['annual_limit'] ?> of your annual
            article gifts.
        </div>
    <?php elseif ($allowance['remaining_gifts'] <= 2): ?>
        <div class="allowance-info allowance-warning">
            <strong>Almost there!</strong> You have <?= $allowance['remaining_gifts'] ?>
            gift<?= $allowance['remaining_gifts'] !== 1 ? 's' : '' ?> remaining this year.
        </div>
    <?php else: ?>
        <div class="allowance-info">
            You have <strong><?= $allowance['remaining_gifts'] ?></strong> gifts remaining out
            of <?= $allowance['annual_limit'] ?> this year.
        </div>
    <?php endif; ?>

    <div class="success-message" id="successMessage"></div>
    <div class="error-message" id="errorMessage"></div>

    <form id="giftForm" <?= !$allowance['can_gift'] ? 'style="display:none;"' : '' ?>>
        <div class="form-group">
            <label for="recipient_email">Recipient's Email Address *</label>
            <input
                    type="email"
                    id="recipient_email"
                    name="recipient_email"
                    required
                    placeholder="friend@example.com"
            >
            <div class="error" id="emailError">Please enter a valid email address</div>
        </div>

        <div class="form-group">
            <label for="personal_message">Personal Message (Optional)</label>
            <textarea
                    id="personal_message"
                    name="personal_message"
                    placeholder="Add a personal note to your gift..."
                    maxlength="500"
            ></textarea>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                <span id="charCount">0</span>/500 characters
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                Send Gift
            </button>
            <a href="/<?= htmlspecialchars($site->slug) ?>/<?= htmlspecialchars($page->slug) ?>"
               class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>

    <div class="loading" id="loading">
        <div class="spinner"></div>
        <p>Sending your gift...</p>
    </div>

    <div class="share-link-container" id="shareLinkContainer">
        <h3>Gift Sent Successfully! 🎁</h3>
        <p>Share this link with your recipient:</p>
        <div class="share-link">
            <input type="text" id="shareLink" readonly>
            <button type="button" class="btn-copy" onclick="copyShareLink()">Copy Link</button>
        </div>
        <p style="font-size: 14px; color: #666; margin-top: 15px;">
            An email notification will be sent to the recipient shortly.
        </p>
        <div style="margin-top: 20px;">
            <a href="/member/gifted-articles" class="btn btn-primary">View My Gifts</a>
            <a href="/<?= htmlspecialchars($site->slug) ?>/<?= htmlspecialchars($page->slug) ?>"
               class="btn btn-secondary">
                Back to Article
            </a>
        </div>
    </div>
</div>

<script>
    const form = document.getElementById('giftForm');
    const submitBtn = document.getElementById('submitBtn');
    const loading = document.getElementById('loading');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const emailError = document.getElementById('emailError');
    const shareLinkContainer = document.getElementById('shareLinkContainer');
    const shareLinkInput = document.getElementById('shareLink');
    const messageTextarea = document.getElementById('personal_message');
    const charCount = document.getElementById('charCount');

    // Character counter
    messageTextarea.addEventListener('input', function () {
        charCount.textContent = this.value.length;
    });

    // Form submission
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const email = document.getElementById('recipient_email').value.trim();
        const message = document.getElementById('personal_message').value.trim();

        // Validate email
        if (!isValidEmail(email)) {
            emailError.style.display = 'block';
            return;
        }
        emailError.style.display = 'none';

        // Hide messages
        successMessage.style.display = 'none';
        errorMessage.style.display = 'none';

        // Show loading
        form.style.display = 'none';
        loading.style.display = 'block';
        submitBtn.disabled = true;

        try {
            const response = await fetch('/<?= htmlspecialchars($site->slug) ?>/gift-article/<?= htmlspecialchars($page->slug) ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    recipient_email: email,
                    personal_message: message
                })
            });

            const data = await response.json();

            if (data.data && data.data.success) {
                // Show share link
                shareLinkInput.value = data.data.share_link;
                loading.style.display = 'none';
                shareLinkContainer.style.display = 'block';
            } else {
                throw new Error(data.data?.message || 'Failed to send gift');
            }
        } catch (error) {
            loading.style.display = 'none';
            form.style.display = 'block';
            submitBtn.disabled = false;
            errorMessage.textContent = error.message || 'An error occurred. Please try again.';
            errorMessage.style.display = 'block';
        }
    });

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function copyShareLink() {
        shareLinkInput.select();
        document.execCommand('copy');

        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => {
            btn.textContent = originalText;
        }, 2000);
    }
</script>
</body>
</html>