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

        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            pointer-events: all;
            animation: slideIn 0.3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
            padding: 0;
            line-height: 1;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="toast-container" id="toastContainer"></div>

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
                            <?php foreach (($countries ?? ['GB' => 'United Kingdom']) as $code => $name): ?>
                                <option value="<?= htmlspecialchars((string) $code) ?>" <?= (string) $code === 'GB' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $name) ?>
                                </option>
                            <?php endforeach; ?>
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
    class AddressStore {
        constructor() {
            this.state = {
                addresses: [],
                loading: false,
                error: null,
                deletingIds: new Set(),
                defaultingIds: new Set(),
                saving: false,
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };

            this.listeners.forEach(listener => listener(this.state));
        }

        setFlag(key, id, enabled) {
            const ids = new Set(this.state[key]);

            if (enabled) {
                ids.add(id);
            } else {
                ids.delete(id);
            }

            this.setState({[key]: ids});
        }
    }

    /**
     * Address Card Component
     * Represents a single address item in the list
     */
    class AddressCard {
        constructor(address, manager) {
            this.address = address;
            this.manager = manager;
        }

        render() {
            const addr = this.address;
            const isDefault = !!parseInt(addr.is_default);
            const deleting = this.manager.store.state.deletingIds.has(addr.id);
            const defaulting = this.manager.store.state.defaultingIds.has(addr.id);

            return UI.el('div', {
                className: `address-card ${isDefault ? 'default' : ''}`,
                'data-address-id': addr.id
            }, [
                // Header: Label and Type Badge
                UI.el('div', {className: 'address-header'}, [
                    UI.el('div', {}, [
                        UI.el('div', {className: 'address-label'}, [addr.label || 'Address']),
                        UI.el('span', {className: 'type-badge'}, [addr.type])
                    ]),
                    isDefault ? UI.el('span', {className: 'default-badge'}, ['Default']) : null
                ]),

                // Body: Address Details
                UI.el('div', {className: 'address-details'}, [
                    UI.el('div', {}, [addr.address_line_1]),
                    addr.address_line_2 ? UI.el('div', {}, [addr.address_line_2]) : null,
                    UI.el('div', {}, [`${addr.city}${addr.state ? ', ' + addr.state : ''} ${addr.postcode}`]),
                    UI.el('div', {}, [addr.country])
                ]),

                // Actions: Edit, Delete, Set Default
                UI.el('div', {className: 'address-actions'}, [
                    !isDefault ? UI.el('button', {
                        className: 'btn btn-secondary btn-sm',
                        disabled: defaulting,
                        onclick: () => this.manager.setDefault(addr.id)
                    }, [defaulting ? 'Updating…' : 'Set as Default']) : null,

                    UI.el('button', {
                        className: 'btn btn-secondary btn-sm',
                        onclick: () => this.manager.modal.open(addr)
                    }, ['Edit']),

                    UI.el('button', {
                        className: 'btn btn-danger btn-sm',
                        disabled: deleting,
                        onclick: () => this.manager.deleteAddress(addr.id)
                    }, [deleting ? 'Deleting…' : 'Delete'])
                ])
            ]);
        }
    }

    /**
     * Address Modal Component
     * Handles the logic for the Add/Edit form
     */
    class AddressModal {
        constructor(manager) {
            this.manager = manager;
            this.el = document.getElementById('address-modal');
            this.form = document.getElementById('address-form');
            this.title = document.getElementById('modal-title');
            this.editingId = null;

            this.form.onsubmit = (e) => this.handleSubmit(e);
        }

        open(address = null) {
            this.clearErrors();
            this.form.reset();

            if (address) {
                this.editingId = address.id;
                UI.text(this.title, 'Edit Address');
                this.fillForm(address);
            } else {
                this.editingId = null;
                UI.text(this.title, 'Add New Address');
                document.getElementById('address-id').value = '';
            }

            this.el.classList.add('show');
        }

        close() {
            this.el.classList.remove('show');
        }

        fillForm(data) {
            document.getElementById('address-id').value = data.id;
            document.getElementById('label').value = data.label || '';
            document.getElementById('type').value = data.type;
            document.getElementById('address_line_1').value = data.address_line_1;
            document.getElementById('address_line_2').value = data.address_line_2 || '';
            document.getElementById('city').value = data.city;
            document.getElementById('state').value = data.state || '';
            document.getElementById('postcode').value = data.postcode;
            document.getElementById('country').value = data.country;
            document.getElementById('is_default').checked = !!parseInt(data.is_default);
        }

        async handleSubmit(e) {
            e.preventDefault();
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());

            // Ensure boolean/numeric logic matches backend expectations
            data.is_default = formData.get('is_default') ? 1 : 0;
            data.member_id = MEMBER_ID;

            this.clearErrors();
            this.manager.store.setState({saving: true});

            try {
                const endpoint = this.editingId
                    ? `${API_BASE}/member/addresses/${this.editingId}`
                    : `${API_BASE}/member/addresses`;

                const method = this.editingId ? 'PUT' : 'POST';

                const result = await api(endpoint, {
                    method,
                    body: JSON.stringify(data)
                });

                if (result.success) {
                    UI.toast(this.editingId ? 'Address updated' : 'Address added', 'success');
                    this.close();
                    await this.manager.loadAddresses();
                }
            } catch (error) {
                // Note: api() wrapper in app-core handles the .errors object if present
                if (error.errors) {
                    this.displayErrors(error.errors);
                } else {
                    UI.toast(error.message || 'An unexpected error occurred', 'error');
                }
            } finally {
                this.manager.store.setState({saving: false});
            }
        }

        clearErrors() {
            document.querySelectorAll('.error-text').forEach(el => UI.text(el, ''));
        }

        displayErrors(errors) {
            Object.keys(errors).forEach(key => {
                const errorEl = document.getElementById(`error-${key}`);
                if (errorEl) UI.text(errorEl, errors[key]);
            });
        }
    }

    /**
     * Main Address Manager
     * Orchestrates loading and the high-level view state
     */
    class AddressManager {
        constructor() {
            this.container = document.getElementById('addresses-container');
            this.store = new AddressStore();
            this.modal = new AddressModal(this);
            this.store.subscribe(state => this.render(state));
            this.init();
        }

        init() {
            this.loadAddresses();

            // Global function hooks for HTML inline onclicks
            window.openAddModal = () => this.modal.open();
            window.closeModal = () => this.modal.close();

            // Close modal on background click
            this.modal.el.addEventListener('click', (e) => {
                if (e.target.id === 'address-modal') this.modal.close();
            });
        }

        async loadAddresses() {
            this.store.setState({loading: true, error: null});

            try {
                const response = await api(`${API_BASE}/member/addresses/search?member_id=${MEMBER_ID}`);
                this.store.setState({
                    addresses: response.items || [],
                    loading: false,
                });
            } catch (_) {
                this.store.setState({
                    loading: false,
                    error: 'Failed to load addresses',
                });
                UI.toast('Failed to load addresses', 'error');
            }
        }

        render(state) {
            if (state.loading) {
                return;
            }

            if (state.error) {
                UI.render(this.container, UI.emptyState({
                    icon: '⚠️',
                    title: 'Unable to load addresses',
                    body: state.error,
                    action: UI.el('button', {
                        className: 'btn btn-primary',
                        onclick: () => this.loadAddresses()
                    }, ['Retry'])
                }));
                return;
            }

            if (!state.addresses || state.addresses.length === 0) {
                UI.render(this.container, UI.emptyState({
                    icon: '📍',
                    title: 'No Addresses Found',
                    body: 'You haven\'t saved any addresses yet.',
                    action: UI.el('button', {
                        className: 'btn btn-primary',
                        onclick: () => this.modal.open()
                    }, ['Add Your First Address'])
                }));
                return;
            }

            const grid = UI.el('div', {className: 'addresses-grid'});
            state.addresses.forEach(addr => {
                const card = new AddressCard(addr, this);
                grid.appendChild(card.render());
            });

            UI.render(this.container, grid);
        }

        async deleteAddress(id) {
            if (!confirm('Are you sure you want to delete this address?')) return;

            try {
                this.store.setFlag('deletingIds', id, true);
                await api(`${API_BASE}/member/addresses/${id}`, {method: 'DELETE'});
                UI.toast('Address deleted successfully', 'success');
                await this.loadAddresses();
            } catch (error) {
                UI.toast(error.message || 'Delete failed', 'error');
            } finally {
                this.store.setFlag('deletingIds', id, false);
            }
        }

        async setDefault(id) {
            try {
                this.store.setFlag('defaultingIds', id, true);
                await api(`${API_BASE}/member/addresses/${id}/set-default`, {
                    method: 'POST',
                    body: JSON.stringify({member_id: MEMBER_ID})
                });
                UI.toast('Default address updated', 'success');
                await this.loadAddresses();
            } catch (error) {
                UI.toast('Could not update default address', 'error');
            } finally {
                this.store.setFlag('defaultingIds', id, false);
            }
        }
    }

    // Ensure the class constants from PHP are available
    const SITE = '<?= $site->slug ?>';
    const API_BASE = '/api/' + SITE;
    const MEMBER_ID = <?= (int)$member->id ?>;

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        window.addressApp = new AddressManager();
    });
</script>
</body>
</html>
