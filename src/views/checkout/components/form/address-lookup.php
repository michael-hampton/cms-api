<?php
/**
 * Address Lookup component.
 *
 * Renders a postcode/address search input that calls the address lookup API,
 * displays a dropdown of suggestions, and populates the manual-entry fields
 * (address, city, county, postal_code, country) on selection.
 *
 * Designed to slot directly into billing-form.php in place of bare inputs.
 * Replace the existing bare address / city / postal_code / country inputs in
 * billing-form with a single:
 *
 * @include('checkout/components/form/address-lookup', [
 *       'member'           => $member,
 *       'requiresShipping' => $requiresShipping,
 *   ])
 *
 * Country follows the same <select> + handleCountryChange() pattern already
 * used in billing-form so US-specific UI toggling keeps working unchanged.
 *
 * @var object|null $member Pre-fills fields from member address data.
 * @var bool $requiresShipping Controls `required` on address fields.
 *
 * DEFENSIVE DEFAULTS
 */
$member = $member ?? null;
$requiresShipping = $requiresShipping ?? false;
$required = $requiresShipping;

$prefill = [
        'address' => $member->address ?? '',
        'city' => $member->city ?? '',
        'county' => $member->county ?? '',
        'postal_code' => $member->postal_code ?? '',
        'country' => $member->country ?? 'GB',
];

// Full country list — matches billing-form so handleCountryChange() keeps working
$countries = [
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'AU' => 'Australia',
        'CA' => 'Canada',
        'IE' => 'Ireland',
        'NZ' => 'New Zealand',
        'ZA' => 'South Africa',
        'DE' => 'Germany',
        'FR' => 'France',
        'ES' => 'Spain',
        'IT' => 'Italy',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'PT' => 'Portugal',
        'AT' => 'Austria',
        'CH' => 'Switzerland',
];

$hasExistingAddress = !empty($prefill['address']) || !empty($prefill['postal_code']);
?>

<style>
    /* ── Address lookup wrapper ──────────────────────────────── */
    .address-lookup {
        margin-bottom: 0;
    }

    /* ── Search row ─────────────────────────────────────────── */
    .address-lookup__search-row {
        display: flex;
        gap: 8px;
        align-items: flex-end;
        margin-bottom: 12px;
    }

    .address-lookup__search-row .form-group {
        flex: 1;
        margin-bottom: 0;
    }

    .address-lookup__find-btn {
        flex-shrink: 0;
        padding: 0.73rem 1rem; /* matches .form-input height */
        background: var(--primary-color, #2563eb);
        color: #fff;
        border: none;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        transition: background 0.2s, opacity 0.2s;
        line-height: 1.5;
    }

    .address-lookup__find-btn:hover {
        background: #1d4ed8;
    }

    .address-lookup__find-btn:disabled {
        opacity: .6;
        cursor: not-allowed;
    }

    /* ── Results dropdown ───────────────────────────────────── */
    .address-lookup__results {
        position: relative;
        z-index: 50;
        margin-top: -8px;
        margin-bottom: 12px;
        background: #fff;
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 0.5rem;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .1);
        overflow: hidden;
        display: none;
    }

    .address-lookup__result-item {
        padding: 10px 14px;
        font-size: 0.875rem;
        cursor: pointer;
        color: var(--text-primary, #1e293b);
        border-bottom: 1px solid var(--border-color, #f1f5f9);
        transition: background 0.1s;
    }

    .address-lookup__result-item:last-child {
        border-bottom: none;
    }

    .address-lookup__result-item:hover {
        background: #f8fafc;
    }

    .address-lookup__no-results {
        padding: 12px 14px;
        font-size: 0.875rem;
        color: var(--text-secondary, #64748b);
        text-align: center;
    }

    /* Loading spinner */
    .address-lookup__spinner {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 14px;
    }

    .address-lookup__spinner::after {
        content: '';
        width: 18px;
        height: 18px;
        border: 2px solid var(--border-color, #e2e8f0);
        border-top-color: var(--primary-color, #2563eb);
        border-radius: 50%;
        animation: al-spin 0.7s linear infinite;
    }

    @keyframes al-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ── Manual entry ───────────────────────────────────────── */
    .address-lookup__manual .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    /* ── Toggle link ────────────────────────────────────────── */
    .address-lookup__toggle {
        background: none;
        border: none;
        color: var(--primary-color, #2563eb);
        font-size: 0.8125rem;
        cursor: pointer;
        padding: 0;
        margin-top: 8px;
        text-decoration: underline;
        text-underline-offset: 2px;
        display: inline-block;
    }

    .address-lookup__toggle:hover {
        color: #1d4ed8;
    }

    /* ── Responsive ─────────────────────────────────────────── */
    @media (max-width: 600px) {
        .address-lookup__manual .form-row {
            grid-template-columns: 1fr;
        }

        .address-lookup__search-row {
            flex-wrap: wrap;
        }

        .address-lookup__find-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="address-lookup" id="address-lookup">

    {{-- ── Postcode search row ────────────────────────────── --}}
    <div class="address-lookup__search-row">
        @include('checkout/components/form/input', [
        'name' => 'postcode_search',
        'id' => 'postcode-search',
        'label' => 'Find your address',
        'type' => 'text',
        'placeholder' => 'Enter postcode, e.g. SW1A 1AA',
        'value' => $prefill['postal_code'],
        'errorId' => 'error-postcode-search',
        'attrs' => [
        'autocomplete' => 'postal-code',
        'aria-label' => 'Postcode for address lookup',
        ],
        ])

        <button type="button"
                id="address-find-btn"
                class="address-lookup__find-btn"
                onclick="addressLookup.find()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Find address
        </button>
    </div>

    {{-- ── Results dropdown ───────────────────────────────── --}}
    <div id="address-results"
         class="address-lookup__results"
         role="listbox"
         aria-label="Address suggestions"></div>

    {{-- ── Manual entry fields ────────────────────────────── --}}
    <div id="address-manual-entry"
         class="address-lookup__manual"
         style="<?= $hasExistingAddress ? '' : 'display:none;' ?>">

        @include('checkout/components/form/input', [
        'name' => 'address',
        'id' => 'address',
        'label' => 'Address line 1',
        'type' => 'text',
        'value' => $prefill['address'],
        'required' => $required,
        'placeholder' => 'Street address',
        'attrs' => ['autocomplete' => 'address-line1'],
        ])

        @include('checkout/components/form/input', [
        'name' => 'city',
        'id' => 'city',
        'label' => 'City',
        'type' => 'text',
        'value' => $prefill['city'],
        'required' => $required,
        'placeholder' => 'City / Town',
        'attrs' => ['autocomplete' => 'address-level2'],
        ])

        <div class="form-row">
            @include('checkout/components/form/input', [
            'name' => 'county',
            'id' => 'county',
            'label' => 'County / State',
            'type' => 'text',
            'value' => $prefill['county'],
            'placeholder' => 'County',
            'attrs' => ['autocomplete' => 'address-level1'],
            ])

            @include('checkout/components/form/input', [
            'name' => 'postal_code',
            'id' => 'postal_code',
            'label' => 'Postcode / ZIP',
            'type' => 'text',
            'value' => $prefill['postal_code'],
            'required' => $required,
            'placeholder' => 'Postcode',
            'attrs' => ['autocomplete' => 'postal-code'],
            ])
        </div>

        {{-- Country — uses the same select + handleCountryChange() as billing-form --}}
        <div class="form-group">
            <label class="form-label" for="country">
                Country
                <?php if ($required): ?><span class="required">*</span><?php endif; ?>
            </label>
            <select name="country"
                    id="country"
                    class="form-select"
                    onchange="handleCountryChange(this.value)"
                    <?= $required ? 'required' : '' ?>>
                <?php foreach ($countries as $code => $name): ?>
                    <option value="<?= htmlspecialchars($code) ?>"
                            <?= $prefill['country'] === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-error" id="error-country"></span>
        </div>

    </div>

    <button type="button"
            id="address-lookup-toggle"
            class="address-lookup__toggle"
            onclick="addressLookup.toggleManual()">
        <?= $hasExistingAddress ? 'Search for a different address' : 'Enter address manually' ?>
    </button>

</div>

<script>
    /**
     * addressLookup — postcode search + manual-entry toggle.
     *
     * API contract (GET {API_BASE}/address-lookup?postcode=SW1A1AA):
     *   200: { success: true,  addresses: [{ address, city, county, postal_code, country }] }
     *   422: { success: false, message: string }
     *
     * Reads the global `API_BASE` already declared on every checkout/subscription page.
     */
    const addressLookup = (() => {
        const resultsEl = document.getElementById('address-results');
        const manualEl = document.getElementById('address-manual-entry');
        const toggleBtn = document.getElementById('address-lookup-toggle');
        const postcodeInput = document.getElementById('postcode-search');
        const findBtn = document.getElementById('address-find-btn');
        const errorEl = document.getElementById('error-postcode-search');

        // Maps API response keys to form input ids
        const fieldMap = {
            address: 'address',
            city: 'city',
            county: 'county',
            postal_code: 'postal_code',
            country: 'country',
        };

        let manualVisible = <?= $hasExistingAddress ? 'true' : 'false' ?>;
        let _addresses = [];

        /* ── Helpers ──────────────────────────────────────────────── */
        const showResults = html => {
            resultsEl.innerHTML = html;
            resultsEl.style.display = 'block';
        };
        const hideResults = () => {
            resultsEl.style.display = 'none';
            resultsEl.innerHTML = '';
        };

        function showManual() {
            manualEl.style.display = 'block';
            toggleBtn.textContent = 'Search for a different address';
            manualVisible = true;
        }

        function hideManual() {
            manualEl.style.display = 'none';
            toggleBtn.textContent = 'Enter address manually';
            manualVisible = false;
        }

        /* ── Populate form fields from a selected address ─────────── */
        function populate(addr) {
            Object.entries(fieldMap).forEach(([key, inputId]) => {
                const el = document.getElementById(inputId);
                if (el && addr[key] !== undefined) el.value = addr[key];
            });

            // Trigger country-dependent UI (US state field, etc.)
            const countryEl = document.getElementById('country');
            if (countryEl && typeof window.handleCountryChange === 'function') {
                window.handleCountryChange(countryEl.value);
            }

            showManual();
            hideResults();
        }

        /* ── Lookup ───────────────────────────────────────────────── */
        async function find() {
            const postcode = postcodeInput?.value.trim();
            if (errorEl) errorEl.textContent = '';

            if (!postcode) {
                if (errorEl) errorEl.textContent = 'Please enter a postcode to search.';
                return;
            }

            findBtn.disabled = true;
            showResults('<div class="address-lookup__spinner"></div>');

            try {
                const base = (typeof API_BASE !== 'undefined') ? API_BASE : '';
                const res = await fetch(`${base}/address-lookup?postcode=${encodeURIComponent(postcode)}`, {
                    headers: {'Accept': 'application/json'}
                });
                const data = await res.json();

                if (!data.success || !data.addresses?.length) {
                    showResults(`<div class="address-lookup__no-results">${data.message || 'No addresses found. Please enter manually.'}</div>`);
                    setTimeout(() => {
                        hideResults();
                        showManual();
                    }, 1800);
                    return;
                }

                _addresses = data.addresses;

                const items = _addresses.map((addr, i) => {
                    const label = [addr.address, addr.city, addr.postal_code].filter(Boolean).join(', ');
                    return `<div class="address-lookup__result-item"
                             role="option" tabindex="0"
                             onclick="addressLookup.select(${i})"
                             onkeydown="if(event.key==='Enter')addressLookup.select(${i})">
                            ${label}
                        </div>`;
                }).join('');

                showResults(items);
            } catch (err) {
                console.error('Address lookup error:', err);
                showResults(`<div class="address-lookup__no-results">Lookup unavailable. Please enter your address manually.</div>`);
                setTimeout(() => {
                    hideResults();
                    showManual();
                }, 1800);
            } finally {
                findBtn.disabled = false;
            }
        }

        /* ── Public API ───────────────────────────────────────────── */
        function select(index) {
            if (_addresses[index]) populate(_addresses[index]);
        }

        function toggleManual() {
            manualVisible ? hideManual() : showManual();
        }

        /* ── Wiring ───────────────────────────────────────────────── */
        // Close dropdown when clicking outside the component
        document.addEventListener('click', e => {
            if (!document.getElementById('address-lookup')?.contains(e.target)) hideResults();
        });

        // Enter key on postcode input triggers search
        postcodeInput?.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                find();
            }
        });

        return {find, select, toggleManual};
    })();
</script>