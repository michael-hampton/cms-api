<style>
    /** {*/
    /*    margin: 0;*/
    /*    padding: 0;*/
    /*    box-sizing: border-box;*/
    /*}*/

    /*body {*/
    /*    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;*/
    /*    background: #f5f7fa;*/
    /*    color: #2c3e50;*/
    /*    line-height: 1.6;*/
    /*}*/

    /*.container {*/
    /*    max-width: 800px;*/
    /*    margin: 0 auto;*/
    /*    padding: 20px;*/
    /*}*/

    /*.header {*/
    /*    background: white;*/
    /*    padding: 30px;*/
    /*    border-radius: 12px;*/
    /*    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);*/
    /*    margin-bottom: 30px;*/
    /*}*/

    /*.header h1 {*/
    /*    font-size: 32px;*/
    /*    color: #2c3e50;*/
    /*    margin-bottom: 10px;*/
    /*}*/

    /*.header p {*/
    /*    color: #7f8c8d;*/
    /*    font-size: 16px;*/
    /*}*/

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

    .preference-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border: 2px solid #ecf0f1;
        border-radius: 8px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }

    .preference-item:hover {
        border-color: #3498db;
        background: #f8f9fa;
    }

    .preference-info h3 {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .preference-info p {
        font-size: 14px;
        color: #7f8c8d;
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

    /*.btn {*/
    /*    display: inline-block;*/
    /*    padding: 14px 28px;*/
    /*    border: none;*/
    /*    border-radius: 8px;*/
    /*    font-size: 16px;*/
    /*    font-weight: 600;*/
    /*    cursor: pointer;*/
    /*    text-decoration: none;*/
    /*    transition: all 0.3s ease;*/
    /*}*/

    /*.btn-primary {*/
    /*    background: #3498db;*/
    /*    color: white;*/
    /*}*/

    /*.btn-primary:hover {*/
    /*    background: #2980b9;*/
    /*    transform: translateY(-2px);*/
    /*    box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);*/
    /*}*/

    /*.btn-secondary {*/
    /*    background: #95a5a6;*/
    /*    color: white;*/
    /*}*/

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

    .info-box {
        background: #e8f4f8;
        border-left: 4px solid #3498db;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .info-box strong {
        display: block;
        margin-bottom: 5px;
        color: #2c3e50;
    }

    .info-box p {
        font-size: 14px;
        color: #5a6c7d;
        margin: 0;
    }

    @media (max-width: 768px) {
        .preference-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
    }
</style>

<div class="container">
    <div class="header">
        <h1>Communication Preferences</h1>
        <p>Manage how <?= htmlspecialchars($site->name) ?> communicates with you</p>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <?php unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Important</strong>
        <p>These preferences control marketing and promotional emails. You will always receive important transactional
            emails such as order confirmations, payment receipts, and account security notifications.</p>
    </div>

    <form method="POST"
          action="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/profile/communication-preferences"
          id="preferencesForm">
        <div class="card">
            <div class="section-title">Email Preferences</div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Marketing Emails</h3>
                    <p>Receive updates, news, and promotional content from <?= htmlspecialchars($site->name) ?></p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="marketing_emails"
                           value="1" <?= ($preferences['marketing_emails'] ?? true) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Special Offers</h3>
                    <p>Get notified about exclusive deals and limited-time offers</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="special_offers"
                           value="1" <?= ($preferences['special_offers'] ?? true) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Product Updates</h3>
                    <p>Stay informed about new features and product improvements</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="product_updates"
                           value="1" <?= ($preferences['product_updates'] ?? true) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Newsletter</h3>
                    <p>Receive our regular newsletter with curated content</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="newsletter"
                           value="1" <?= ($preferences['newsletter'] ?? true) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Third-Party Communications</h3>
                    <p>Allow carefully selected partners to send you relevant offers</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="third_party_communications"
                           value="1" <?= ($preferences['third_party_communications'] ?? false) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                Save Preferences
            </button>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="btn btn-secondary">
                Back to Dashboard
            </a>
        </div>
    </form>
</div>

<script>
    document.getElementById('preferencesForm').addEventListener('submit', function (e) {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Saving...';
    });
</script>