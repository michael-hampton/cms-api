<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Addresses - <?= htmlspecialchars($site->name) ?></title>
    <style>
        /* ... All your CSS remains exactly the same ... */
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            margin-top: 2rem;
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
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
            transition: all 0.3s;
        }

        .address-card.default {
            border-color: var(--primary-color);
            background: #eff6ff;
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
        }

        .default-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
        }

        .type-badge {
            display: inline-block;
            background: var(--bg-light);
            color: var(--text-secondary);
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
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
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .error-text {
            color: var(--danger-color);
            font-size: 0.875rem;
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
        <div style="text-align: center; padding: 3rem;">
            <p style="color: var(--text-secondary);">Loading addresses...</p>
        </div>
    </div>
</main>

<div id="address-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header"
             style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between;">
            <h2 class="modal-title" id="modal-title">Add Address</h2>
            <button onclick="closeModal()" style="background:none; border:none; cursor:pointer;">✕</button>
        </div>
        <form id="address-form" onsubmit="handleSubmit(event)">
            <div class="modal-body" style="padding: 1.5rem;">
                <input type="hidden" id="address-id" name="id">
                <div class="form-group">
                    <label class="form-label">Label (e.g., Home, Work) *</label>
                    <input type="text" id="label" name="label" class="form-input" required>
                    <div class="error-text" id="error-label"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address Type *</label>
                    <select id="type" name="type" class="form-select" required>
                        <option value="both">Shipping & Billing</option>
                        <option value="shipping">Shipping Only</option>
                        <option value="billing">Billing Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Address Line 1 *</label>
                    <input type="text" id="address_line_1" name="address_line_1" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Address Line 2</label>
                    <input type="text" id="address_line_2" name="address_line_2" class="form-input">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <input type="text" id="city" name="city" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" id="state" name="state" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Postcode *</label>
                        <input type="text" id="postcode" name="postcode" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country *</label>
                        <select id="country" name="country" class="form-select" required>
                            <option value="US">United States</option>
                            <option value="GB">United Kingdom</option>
                            <option value="CA">Canada</option>
                            <option value="AU">Australia</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" id="is_default" name="is_default" value="1">
                        <span>Set as default address</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer"
                 style="padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submit-btn">Save Address</button>
            </div>
        </form>
    </div>
</div>

<script>
    const SITE = '<?= $site->slug ?? 'default' ?>';
    const API_BASE = '/api/' + SITE;
    const MEMBER_ID = <?= $member->id ?>;

    let editingAddressId = null;

    // Load addresses as soon as the script runs
    document.addEventListener('DOMContentLoaded', loadAddresses);

    async function loadAddresses() {
        try {
            const response = await fetch(`${API_BASE}/member/addresses/search?member_id=${MEMBER_ID}`);
            const data = await response.json();

            // Ensure we handle both the 'items' key and the direct 'success' check
            if (data.success) {
                renderAddresses(data.items || []);
            } else {
                showAlert(data.message || 'Failed to load addresses', 'error');
            }
        } catch (error) {
            console.error('Error loading addresses:', error);
            showAlert('Failed to load addresses', 'error');
        }
    }

    function renderAddresses(addresses) {
        const container = document.getElementById('addresses-container');

        if (!addresses || addresses.length === 0) {
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

        const gridHtml = addresses.map(address => {
            const isDefault = address.is_default;
            return `
                <div class="address-card ${isDefault ? 'default' : ''}" data-address-id="${address.id}">
                    <div class="address-header">
                        <div>
                            <div class="address-label">
                                ${escapeHtml(address.label || 'Address')}
                            </div>
                            <span class="type-badge">${escapeHtml(address.type)}</span>
                        </div>
                        ${isDefault ? '<span class="default-badge">Default</span>' : ''}
                    </div>

                    <div class="address-details">
                        ${escapeHtml(address.address_line_1)}<br>
                        ${address.address_line_2 ? escapeHtml(address.address_line_2) + '<br>' : ''}
                        ${escapeHtml(address.city)}${address.state ? ', ' + escapeHtml(address.state) : ''} ${escapeHtml(address.postcode)}<br>
                        ${escapeHtml(address.country)}
                    </div>

                    <div class="address-actions">
                        ${!isDefault ? `
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
            `;
        }).join('');

        container.innerHTML = `<div class="addresses-grid" id="addresses-grid">${gridHtml}</div>`;
    }

    /* Modal & Form Logic */
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
            const response = await fetch(`${API_BASE}/member/addresses/search?member_id=${MEMBER_ID}`);
            const data = await response.json();
            const address = data.items.find(a => a.id === id);

            if (address) {
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
            }
        } catch (error) {
            showAlert('Failed to load address details', 'error');
        }
    }

    function closeModal() {
        document.getElementById('address-modal').classList.remove('show');
    }

    async function handleSubmit(e) {
        e.preventDefault();

        // 1. Get all form data into a clean object
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());

        // 2. Format specific data types
        data.is_default = formData.get('is_default') === '1';
        data.member_id = MEMBER_ID;

        // 3. Logic for the ID:
        // If we ARE editing, we keep the ID from the hidden input.
        // If we ARE NOT editing (creating), we remove it so the API doesn't see an empty "id": ""
        if (!editingAddressId) {
            delete data.id;
        }

        clearErrors();

        try {
            const url = editingAddressId
                ? `${API_BASE}/member/addresses/${editingAddressId}`
                : `${API_BASE}/member/addresses`;

            const method = editingAddressId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok) {
                showAlert(editingAddressId ? 'Address updated' : 'Address added', 'success');
                closeModal();
                await loadAddresses(); // Refresh the list
            } else {
                if (result.errors) {
                    displayErrors(result.errors);
                } else {
                    showAlert(result.message || 'Error saving address', 'error');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('System error occurred', 'error');
        }
    }

    async function deleteAddress(id) {
        if (!confirm('Are you sure?')) return;
        try {
            const response = await fetch(`${API_BASE}/member/addresses/${id}`, {method: 'DELETE'});
            if (response.ok) {
                showAlert('Deleted', 'success');
                loadAddresses();
            }
        } catch (error) {
            showAlert('Delete failed', 'error');
        }
    }

    async function setDefault(id) {
        try {
            const response = await fetch(`${API_BASE}/member/addresses/${id}/set-default`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({member_id: MEMBER_ID})
            });
            if (response.ok) {
                loadAddresses();
            }
        } catch (error) {
            showAlert('Update failed', 'error');
        }
    }

    /* Helpers */
    function showAlert(message, type = 'success') {
        const container = document.getElementById('alert-container');
        container.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
        setTimeout(() => container.innerHTML = '', 5000);
    }

    function clearErrors() {
        document.querySelectorAll('.error-text').forEach(el => el.textContent = '');
    }

    function displayErrors(errors) {
        Object.keys(errors).forEach(f => {
            if (document.getElementById(`error-${f}`)) document.getElementById(`error-${f}`).textContent = errors[f];
        });
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // UI Event Listeners
    document.getElementById('address-modal').addEventListener('click', (e) => {
        if (e.target.id === 'address-modal') closeModal();
    });
</script>
</body>
</html>