<?php
/**
 * Shared address form partial.
 *
 * Expected variables in scope:
 *   $member       — Member model
 *   $addressTypes — AddressType[]  (all enum cases)
 *   $address      — Address model  (null on create)
 *   $submitLabel  — string         ('Create Address' | 'Save Changes')
 *   $formAction   — string         (POST endpoint)
 *   $cancelUrl    — string
 */

$v = fn(string $field, string $default = '') => htmlspecialchars(isset($address) ? ($address->{$field} ?? $default) : $default);
?>

<div id="form-alert" style="display:none;margin-bottom:16px"></div>

<div class="card">
    <div class="card-header">
        <?= htmlspecialchars($submitLabel) ?>
        &mdash;
        <span style="font-weight:400;color:var(--color-muted)">
            <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>
        </span>
    </div>

    <div class="card-body">

        <!-- Type -->
        <div class="form-group">
            <label for="type">Address Type</label>
            <div class="type-options">
                <?php foreach ($addressTypes as $type): ?>
                    <label class="radio-card" id="radio-<?= $type->value ?>">
                        <input
                                type="radio"
                                name="type"
                                value="<?= $type->value ?>"
                                <?= $v('type', 'both') === $type->value ? 'checked' : '' ?>
                        >
                        <span class="radio-label"><?= htmlspecialchars($type->label()) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="field-error" id="err-type"></div>
        </div>

        <!-- Label -->
        <div class="form-group">
            <label for="label">Label <span class="optional">(optional)</span></label>
            <input
                    type="text"
                    id="label"
                    name="label"
                    value="<?= $v('label') ?>"
                    placeholder="e.g. Home, Office"
                    maxlength="100"
            >
            <div class="field-error" id="err-label"></div>
        </div>

        <!-- Address lines -->
        <div class="form-group">
            <label for="address_line_1">Address Line 1</label>
            <input
                    type="text"
                    id="address_line_1"
                    name="address_line_1"
                    value="<?= $v('address_line_1') ?>"
                    placeholder="Street address"
                    maxlength="255"
            >
            <div class="field-error" id="err-address_line_1"></div>
        </div>

        <div class="form-group">
            <label for="address_line_2">Address Line 2 <span class="optional">(optional)</span></label>
            <input
                    type="text"
                    id="address_line_2"
                    name="address_line_2"
                    value="<?= $v('address_line_2') ?>"
                    placeholder="Apartment, suite, unit, etc."
                    maxlength="255"
            >
        </div>

        <!-- City / State row -->
        <div class="form-row">
            <div class="form-group">
                <label for="city">City</label>
                <input
                        type="text"
                        id="city"
                        name="city"
                        value="<?= $v('city') ?>"
                        maxlength="100"
                >
                <div class="field-error" id="err-city"></div>
            </div>
            <div class="form-group">
                <label for="state">State / County <span class="optional">(optional)</span></label>
                <input
                        type="text"
                        id="state"
                        name="state"
                        value="<?= $v('state') ?>"
                        maxlength="100"
                >
            </div>
        </div>

        <!-- Postcode / Country row -->
        <div class="form-row">
            <div class="form-group">
                <label for="postcode">Postcode <span class="optional">(optional)</span></label>
                <input
                        type="text"
                        id="postcode"
                        name="postcode"
                        value="<?= $v('postcode') ?>"
                        maxlength="20"
                >
            </div>
            <div class="form-group">
                <label for="country">Country</label>
                <input
                        type="text"
                        id="country"
                        name="country"
                        value="<?= $v('country', 'GB') ?>"
                        maxlength="100"
                >
                <div class="field-error" id="err-country"></div>
            </div>
        </div>

    </div><!-- .card-body -->

    <div class="card-footer">
        <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn btn-ghost">Cancel</a>
        <button class="btn btn-primary" id="submit-btn" type="button">
            <?= htmlspecialchars($submitLabel) ?>
        </button>
    </div>
</div>

<script>
    (function () {
        'use strict';

        var submitBtn = document.getElementById('submit-btn');
        var formAlert = document.getElementById('form-alert');
        var formAction = <?= json_encode($formAction) ?>;
        var cancelUrl = <?= json_encode($cancelUrl) ?>;

        // Highlight selected radio card
        document.querySelectorAll('.radio-card input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', updateRadioCards);
        });

        function updateRadioCards() {
            document.querySelectorAll('.radio-card').forEach(function (card) {
                var radio = card.querySelector('input[type="radio"]');
                card.classList.toggle('selected', radio.checked);
            });
        }

        updateRadioCards();

        function showToast(msg, isError) {
            var toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = 'toast' + (isError ? ' toast-error' : '');
            void toast.offsetWidth;
            toast.classList.add('show');
            setTimeout(function () {
                toast.classList.remove('show');
            }, 3000);
        }

        function clearErrors() {
            document.querySelectorAll('.field-error').forEach(function (el) {
                el.textContent = '';
            });
            document.querySelectorAll('.has-error').forEach(function (el) {
                el.classList.remove('has-error');
            });
            formAlert.style.display = 'none';
        }

        function showFieldError(field, message) {
            var errEl = document.getElementById('err-' + field);
            var input = document.getElementById(field);
            if (errEl) errEl.textContent = message;
            if (input) input.classList.add('has-error');
        }

        function showAlert(message, type) {
            formAlert.className = 'alert alert-' + type;
            formAlert.textContent = message;
            formAlert.style.display = 'block';
            formAlert.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }

        function val(id) {
            var el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }

        submitBtn.addEventListener('click', function () {
            clearErrors();

            var selectedType = document.querySelector('input[name="type"]:checked');

            var payload = {
                type: selectedType ? selectedType.value : '',
                label: val('label'),
                address_line_1: val('address_line_1'),
                address_line_2: val('address_line_2'),
                city: val('city'),
                state: val('state'),
                postcode: val('postcode'),
                country: val('country'),
            };

            // Client-side required fields
            var required = {type: 'Address type', address_line_1: 'Address line 1', city: 'City', country: 'Country'};
            var hasError = false;

            Object.entries(required).forEach(function (pair) {
                if (!payload[pair[0]]) {
                    showFieldError(pair[0], pair[1] + ' is required.');
                    hasError = true;
                }
            });

            if (hasError) return;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving\u2026';

            fetch(formAction, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(payload),
            })
                .then(function (r) {
                    return r.json().then(function (d) {
                        return {status: r.status, data: d};
                    });
                })
                .then(function (res) {
                    if (res.data.success) {
                        showToast(res.data.message || 'Saved.');
                        setTimeout(function () {
                            window.location.href = cancelUrl;
                        }, 700);
                    } else if (res.status === 422) {
                        showAlert(res.data.message || 'Please correct the errors below.', 'error');
                        if (res.data.errors) {
                            Object.entries(res.data.errors).forEach(function (pair) {
                                showFieldError(pair[0], Array.isArray(pair[1]) ? pair[1][0] : pair[1]);
                            });
                        }
                    } else {
                        showAlert(res.data.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(function () {
                    showAlert('Something went wrong. Please try again.', 'error');
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = <?= json_encode($submitLabel) ?>;
                });
        });
    })();
</script>