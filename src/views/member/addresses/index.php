<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Addresses - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --border-color: #e5e7eb;
            --bg-light: #f9fafb;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-icon {
            padding: 0.5rem;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-secondary);
            border-radius: 0.25rem;
            transition: all 0.3s;
        }

        .btn-icon:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .addresses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .address-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            position: relative;
            transition: all 0.3s;
        }

        .address-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .address-card.default {
            border-color: var(--primary-color);
            background: #eff6ff;
        }

        .address-card.editing {
            border-color: var(--primary-color);
        }

        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .address-label {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--text-primary);
        }

        .default-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .type-badge {
            display: inline-block;
            background: var(--bg-light);
            color: var(--text-secondary);
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        .address-details {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }

        .address-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 0.75rem;
            border: 2px dashed var(--border-color);
        }

        .empty-state svg {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Modal Styles */
        .modal-overlay {
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

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 0.75rem;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-label .required {
            color: var(--danger-color);
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-checkbox input {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
        }

        .error-text {
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .close-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: var(--text-secondary);
            border-radius: 0.25rem;
        }

        .close-btn:hover {
            background: var(--bg-light);
        }

        @media (max-width: 768px) {
            .addresses-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: start;
                gap: 1rem;
            }

            .address-actions {
                flex-direction: column;
            }

            .address-actions .btn {
                width: 100%;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <h1 class="page-title">My Addresses</h1>
        <button onclick="openAddModal()" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add New Address
        </button>
    </div>

    <div id="alert-container"></div>

    <div id="addresses-container">
        <?php if ($addresses->isEmpty()): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <h3>No Addresses Yet</h3>
                <p>Add your first address to speed up checkout</p>
                <button onclick="openAddModal()" class="btn btn-primary">Add Address</button>
            </div>
        <?php else: ?>
            <div class="addresses-grid" id="addresses-grid">
                <?php foreach ($addresses as $address): ?>
                    <div class="address-card <?= $address->is_default ? 'default' : '' ?>" data-address-id="<?= $address->id ?>">
                        <div class="address-header">
                            <div>
                                <div class="address-label">
                                    <?= htmlspecialchars($address->label ?: 'Address') ?>
                                </div>
                                <span class="type-badge"><?= htmlspecialchars($address->type) ?></span>
                            </div>
                            <?php if ($address->is_default): ?>
                                <span class="default-badge">Default</span>
                            <?php endif; ?>
                        </div>

                        <div class="address-details">
                            <?= htmlspecialchars($address->address_line_1) ?><br>
                            <?php if ($address->address_line_2): ?>
                                <?= htmlspecialchars($address->address_line_2) ?><br>
                            <?php endif; ?>
                            <?= htmlspecialchars($address->city) ?><?= $address->state ? ', ' . htmlspecialchars($address->state) : '' ?> <?= htmlspecialchars($address->postcode) ?><br>
                            <?= htmlspecialchars($address->country) ?>
                        </div>

                        <div class="address-actions">
                            <?php if (!$address->is_default): ?>
                                <button onclick="setDefault(<?= $address->id ?>)" class="btn btn-secondary btn-sm">
                                    Set as Default
                                </button>
                            <?php endif; ?>
                            <button onclick="editAddress(<?= $address->id ?>)" class="btn btn-secondary btn-sm">
                                Edit
                            </button>
                            <button onclick="deleteAddress(<?= $address->id ?>)" class="btn btn-danger btn-sm">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Address Modal -->
<div id="address-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-title">Add Address</h2>
            <button onclick="closeModal()" class="close-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <form id="address-form" onsubmit="handleSubmit(event)">
            <div class="modal-body">
                <input type="hidden" id="address-id" name="id">

                <div class="form-group">
                    <label class="form-label">
                        Label (e.g., Home, Work) <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="label"
                        name="label"
                        class="form-input"
                        placeholder="Home"
                        required>
                    <div class="error-text" id="error-label"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Address Type <span class="required">*</span>
                    </label>
                    <select id="type" name="type" class="form-select" required>
                        <option value="both">Shipping & Billing</option>
                        <option value="shipping">Shipping Only</option>
                        <option value="billing">Billing Only</option>
                    </select>
                    <div class="error-text" id="error-type"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Address Line 1 <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="address_line_1"
                        name="address_line_1"
                        class="form-input"
                        placeholder="123 Main Street"
                        required>
                    <div class="error-text" id="error-address_line_1"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Address Line 2</label>
                    <input
                        type="text"
                        id="address_line_2"
                        name="address_line_2"
                        class="form-input"
                        placeholder="Apartment, suite, unit, etc. (optional)">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            City <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            id="city"
                            name="city"
                            class="form-input"
                            required>
                        <div class="error-text" id="error-city"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">State / Province</label>
                        <input
                            type="text"
                            id="state"
                            name="state"
                            class="form-input">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Postcode / ZIP <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            id="postcode"
                            name="postcode"
                            class="form-input"
                            required>
                        <div class="error-text" id="error-postcode"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Country <span class="required">*</span>
                        </label>
                        <select id="country" name="country" class="form-select" required>
                            <option value="">Select Country</option>
                            <option value="US">United States</option>
                            <option value="GB">United Kingdom</option>
                            <option value="CA">Canada</option>
                            <option value="AU">Australia</option>
                            <option value="DE">Germany</option>
                            <option value="FR">France</option>
                        </select>
                        <div class="error-text" id="error-country"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" id="is_default" name="is_default" value="1">
                        <span>Set as default address</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    Save Address
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$site = \App\Framework\Support\SiteContext::get();
?>

<script>
    const SITE = '<?= $site->slug ?? 'default' ?>';
    const API_BASE = '/api/' + SITE;
    const MEMBER_ID = <?= $member->id ?>;

    let editingAddressId = null;

    function openAddModal() {
        editingAddressId = null;
        document.getElementById('modal-title').textContent = 'Add Address';
        document.getElementById('address-form').reset();
        document.getElementById('address-id').value = '';
        clearErrors();
        document.getElementById('address-modal').classList.add('show');
    }

    async function editAddress(id) {
        editingAddressId = id;
        document.getElementById('modal-title').textContent = 'Edit Address';

        try {
            const response = await fetch(`/${SITE}/member/addresses/search?member_id=${MEMBER_ID}`);
            const data = await response.json();

            const address = data.items.find(a => a.id === id);
            if (!address) {
                showAlert('Address not found', 'error');
                return;
            }

            document.getElementById('address-id').value = address.id;
            document.getElementById('label').value = address.label || '';
            document.getElementById('type').value = address.type;
            document.getElementById('address_line_1').value = address.address_line_1;
            document.getElementById('address_line_2').value = address.address_line_2 || '';
            document.getElementById('city').value = address.city;
            document.getElementById('state').value = address.state || '';
            document.getElementById('postcode').value = address.postcode;
            document.getElementById('country').value = address.country;
            document.getElementById('is_default').checked = address.is_default;

            clearErrors();
            document.getElementById('address-modal').classList.add('show');
        } catch (error) {
            console.error('Error loading address:', error);
            showAlert('Failed to load address', 'error');
        }
    }

    function closeModal() {
        document.getElementById('address-modal').classList.remove('show');
        editingAddressId = null;
    }

    async function handleSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const data = {};

        formData.forEach((value, key) => {
            if (key === 'is_default') {
                data[key] = value === '1';
            } else if (key !== 'id') {
                data[key] = value;
            }
        });

        data.member_id = MEMBER_ID;

        clearErrors();

        try {
            const url = editingAddressId
                ? `/${SITE}/member/addresses/${editingAddressId}`
                : `/${SITE}/member/addresses`;

            const method = editingAddressId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok) {
                showAlert(editingAddressId ? 'Address updated successfully' : 'Address added successfully', 'success');
                closeModal();
                await loadAddresses();
            } else {
                if (result.errors) {
                    displayErrors(result.errors);
                } else {
                    showAlert(result.message || 'Failed to save address', 'error');
                }
            }
        } catch (error) {
            console.error('Error saving address:', error);
            showAlert('Failed to save address', 'error');
        }
    }

    async function deleteAddress(id) {
        if (!confirm('Are you sure you want to delete this address?')) {
            return;
        }

        try {
            const response = await fetch(`/${SITE}/member/addresses/${id}`, {
                method: 'DELETE'
            });

            if (response.ok) {
                showAlert('Address deleted successfully', 'success');
                await loadAddresses();
            } else {
                showAlert('Failed to delete address', 'error');
            }
        } catch (error) {
            console.error('Error deleting address:', error);
            showAlert('Failed to delete address', 'error');
        }
    }

    async function setDefault(id) {
        try {
            const response = await fetch(`/${SITE}/member/addresses/${id}/set-default`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ member_id: MEMBER_ID })
            });

            if (response.ok) {
                showAlert('Default address updated', 'success');
                await loadAddresses();
            } else {
                showAlert('Failed to update default address', 'error');
            }
        } catch (error) {
            console.error('Error setting default:', error);
            showAlert('Failed to update default address', 'error');
        }
    }

    async function loadAddresses() {
        try {
            const response = await fetch(`/${SITE}/member/addresses/search?member_id=${MEMBER_ID}`);
            const data = await response.json();

            if (!data.success || !data.items) {
                throw new Error('Invalid response');
            }

            renderAddresses(data.items);
        } catch (error) {
            console.error('Error loading addresses:', error);
            showAlert('Failed to load addresses', 'error');
        }
    }

    function renderAddresses(addresses) {
        const container = document.getElementById('addresses-container');

        if (addresses.length === 0) {
            container.innerHTML = `
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <h3>No Addresses Yet</h3>
                        <p>Add your first address to speed up checkout</p>
                        <button onclick="openAddModal()" class="btn btn-primary">Add Address</button>
                    </div>
                `;
            return;
        }

        const html = addresses.map(address => `
                <div class="address-card ${address.is_default ? 'default' : ''}" data-address-id="${address.id}">
                    <div class="address-header">
                        <div>
                            <div class="address-label">
                                ${escapeHtml(address.label || 'Address')}
                            </div>
                            <span class="type-badge">${escapeHtml(address.type)}</span>
                        </div>
                        ${address.is_default ? '<span class="default-badge">Default</span>' : ''}
                    </div>

                    <div class="address-details">
                        ${escapeHtml(address.address_line_1)}<br>
                        ${address.address_line_2 ? escapeHtml(address.address_line_2) + '<br>' : ''}
                        ${escapeHtml(address.city)}${address.state ? ', ' + escapeHtml(address.state) : ''} ${escapeHtml(address.postcode)}<br>
                        ${escapeHtml(address.country)}
                    </div>

                    <div class="address-actions">
                        ${!address.is_default ? `
                            <button onclick="setDefault(${address.id})" class="btn btn-secondary btn-sm">
                                Set as Default
                            </button>
                        ` : ''}
                        <button onclick="editAddress(${address.id})" class="btn btn-secondary btn-sm">
                            Edit
                        </button>
                        <button onclick="deleteAddress(${address.id})" class="btn btn-danger btn-sm">
                            Delete
                        </button>
                    </div>
                </div>
            `).join('');

        container.innerHTML = `<div class="addresses-grid" id="addresses-grid">${html}</div>`;
    }

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${escapeHtml(message)}
                </div>
            `;

        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearErrors() {
        document.querySelectorAll('.error-text').forEach(el => el.textContent = '');
    }

    function displayErrors(errors) {
        Object.keys(errors).forEach(field => {
            const errorEl = document.getElementById(`error-${field}`);
            if (errorEl) {
                errorEl.textContent = errors[field];
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Close modal when clicking overlay
    document.getElementById('address-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('address-modal').classList.contains('show')) {
            closeModal();
        }
    });
</script>
</body>
</html>