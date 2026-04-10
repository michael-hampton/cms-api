@extends('layouts.app')
@css('open-collab.css')

<div class="container" style="max-width: 600px;">
    <div class="card">
        <div style="margin-bottom: 2rem; text-align: center;">
            <h1 style="margin-bottom: 0.5rem;">Contributor Onboarding</h1>
            <div style="display: flex; gap: 8px;">
                @foreach(['profile', 'payment', 'contract', 'guidelines'] as $s)
                <div style="flex: 1; height: 4px; background: {{ $currentStep === $s ? 'var(--primary)' : (in_array($s, $pendingSteps) ? '#e2e8f0' : 'var(--success)') }}; border-radius: 2px;"></div>
                @endforeach
            </div>
        </div>

        <?php foreach (['profile', 'payment', 'contract', 'guidelines'] as $stepName): ?>
            <div class="onboarding-step-wrapper <?= $currentStep === $stepName ? 'active' : '' ?>"
                 data-step="<?= $stepName ?>">
                <?= $this->partial('open-collab/onboarding/steps/' . $stepName, ['contract' => $contract]) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // 1. Define the Global Object
    window.Onboarding = {
        async apiPost(endpoint, payload) {
            console.log(`Submitting to ${endpoint}...`, payload); // Debugging line

            try {
                const response = await fetch(`/api/{{ $site }}/open-collab/onboarding/${endpoint}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    // Force reload to let PHP PageController move to the next step
                    window.location.reload();
                } else {
                    // Handle Laravel Validation Errors (422)
                    if (result.errors) {
                        const errorMsg = Object.values(result.errors).flat().join('\n');
                        alert(errorMsg);
                    } else {
                        alert(result.message || "Error processing step");
                    }
                }
            } catch (e) {
                console.error("Fetch error:", e);
                alert("Network error. Please try again.");
            }
        },

        submitProfile(form) {
            this.apiPost('profile', {
                bio: form.querySelector('[name="bio"]').value
            });
        },

        submitPayment(form) {
            this.apiPost('payment', {
                payment_method_type: form.querySelector('[name="payment_method_type"]').value,
                payment_details: form.querySelector('[name="payment_details"]').value,
                stripe_token: 'abc' //todo needs stripe setting up
            });
        },

        submitContract(form) {
            this.apiPost('contract', {
                contract_id: form.querySelector('[name="contract_id"]').value,
                agreed: form.querySelector('[name="acknowledge"]').checked
            });
        },

        submitGuidelines(form) {
            this.apiPost('guidelines', {
                version: form.querySelector('[name="version"]').value,
                agreed: true, //todo needs to be gdpr compliant checkbox?
            });
        }
    };

    // 2. Optional: Debugging check to see if we found the active form
    document.addEventListener('DOMContentLoaded', () => {
        const activeStep = document.querySelector('.onboarding-step-wrapper.active');
        if (!activeStep) {
            console.warn("No active onboarding step found in the DOM.");
        }
    });
</script>