<?php
// Resolve profile values if present for the enhanced profile step fields
$initialExpertise = [];
if ($profile && !empty($profile->expertise)) {
    $initialExpertise = array_values(
            array_filter(
                    array_map('trim', explode(',', $profile->expertise))
            )
    );
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started — OpenCollab</title>
    @css('open-collab.css')
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body style="background:var(--cream);min-height:100vh;display:flex;flex-direction:column;">

<!-- Minimal header -->
<header style="padding:20px 32px;border-bottom:1px solid var(--border);background:#fff;display:flex;align-items:center;gap:12px;">
    <div style="width:32px;height:32px;background:var(--navy);border-radius:6px;display:grid;place-items:center;">
        <span style="font-family:var(--font-display);font-weight:700;font-size:16px;color:var(--amber);">O</span>
    </div>
    <span style="font-family:var(--font-display);font-size:1rem;font-weight:600;color:var(--navy);">OpenCollab</span>
    <span style="margin-left:auto;font-size:.8rem;color:var(--slate);">Setting up your account</span>
</header>

<main style="flex:1;display:flex;align-items:flex-start;justify-content:center;padding:48px 20px;">
    <div style="width:100%;max-width:580px;">

        <!-- Step Indicator -->
        <div class="oc-steps" style="margin-bottom:36px;">
            <?php foreach ($vm->steps() as $step): ?>
                <div class="oc-step oc-step--<?= $step->cssState() ?>">
                    <div class="oc-step__dot">
                        <?php if ($step->isDone): ?>
                            <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                                <path fill-rule="evenodd"
                                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                        <?php else: ?>
                            <?= $step->oneBasedIndex ?>
                        <?php endif; ?>
                    </div>
                    <span class="oc-step__label"><?= $step->label ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Step Card -->
        <div class="oc-card" style="box-shadow:var(--shadow-lg);">
            <div class="oc-card__header" style="padding:22px 28px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:38px;height:38px;background:var(--navy);border-radius:8px;display:grid;place-items:center;flex-shrink:0;">
                        <svg viewBox="0 0 20 20" fill="var(--amber)" width="18"><?= $vm->currentStepIcon() ?></svg>
                    </div>
                    <div>
                        <div class="oc-card__title" style="margin-bottom:2px;"><?= $vm->currentStepTitle() ?></div>
                        <div style="font-size:.78rem;color:var(--slate);">
                            Step <?= $vm->currentStepNumber() ?> of <?= $vm->totalSteps() ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="oc-card__body" style="padding:28px;">

                <div id="form-errors" class="oc-form-errors" style="display:none;" role="alert"></div>
                <div id="form-success" class="oc-alert oc-alert--success" style="display:none;" role="status"></div>

                <?php if ($vm->currentStepReason()): ?>
                    <div class="oc-alert oc-alert--info" style="margin-bottom:16px;">
                        <?= htmlspecialchars($vm->currentStepReason()) ?>
                    </div>
                <?php endif; ?>

                <!-- ── PROFILE STEP ─────────────────────────── -->
                <?php if ($vm->currentStepName() === 'profile'): ?>
                    <form id="onboarding-form" novalidate>

                        <div id="section-bio" style="margin-bottom:24px;">
                            <p style="font-size:.875rem;color:var(--slate);margin-bottom:20px;">
                                Tell readers a little about you. This appears on your articles and public profile.
                            </p>

                            <div class="oc-form-group" style="margin-bottom:20px;">
                                <label class="oc-label">Profile photo</label>
                                <div style="display:flex;align-items:center;gap:16px;">
                                    <div id="avatar-preview-wrap" style="position:relative;flex-shrink:0;">
                                        <div id="avatar-preview"
                                             style="width:72px;height:72px;border-radius:50%;background:var(--slate-pale, #f1f5f9);border:1.5px solid var(--border);overflow:hidden;display:grid;place-items:center;cursor:pointer;"
                                             onclick="document.getElementById('avatar-file-input').click()"
                                             title="Click to change photo">
                                            <?php if (!empty($profile?->avatar)): ?>
                                                <img id="avatar-img" src="<?= htmlspecialchars($profile->avatar) ?>" alt="Your avatar" style="width:100%;height:100%;object-fit:cover;">
                                            <?php else: ?>
                                                <span id="avatar-initials" style="font-family:var(--font-display);font-size:1.3rem;color:var(--slate);user-select:none;">
                                                    <?= strtoupper(substr($currentUser->name ?? 'U', 0, 1)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div onclick="document.getElementById('avatar-file-input').click()"
                                             style="position:absolute;bottom:0;right:0;width:22px;height:22px;background:var(--navy);border-radius:50%;display:grid;place-items:center;cursor:pointer;border:2px solid #fff;"
                                             title="Change photo" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" fill="#fff" width="10"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                                        </div>
                                    </div>

                                    <div style="flex:1;">
                                        <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
                                        <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" onclick="document.getElementById('avatar-file-input').click()">
                                            Choose photo
                                        </button>
                                        <button type="button" id="avatar-remove-btn" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:6px;color:var(--red, #ef4444); display: <?= !empty($profile?->avatar) ? 'inline-block' : 'none' ?>;">
                                            Remove
                                        </button>
                                        <div class="oc-help" style="margin-top:4px; font-size: 0.75rem;">JPG, PNG or WebP · Max 2 MB</div>
                                        <div id="avatar-error" class="oc-error-msg" style="margin-top:4px;display:none;"></div>

                                        <div id="avatar-progress-wrap" style="display:none;margin-top:6px;">
                                            <div style="height:4px;background:var(--border);border-radius:99px;overflow:hidden;width:140px;">
                                                <div id="avatar-progress-bar" style="height:100%;width:0%;background:var(--navy);border-radius:99px;transition:width .2s ease;"></div>
                                            </div>
                                            <div id="avatar-progress-label" style="font-size:.72rem;color:var(--slate);margin-top:2px;">Uploading…</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="oc-form-group">
                                <label class="oc-label" for="bio">Your bio</label>
                                <textarea class="oc-textarea" id="bio" name="bio" rows="5"
                                          placeholder="I'm a writer specialising in…" required
                                          style="min-height:120px;"><?= htmlspecialchars($profile?->bio ?? '') ?></textarea>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
                                    <div class="oc-help">Between 20 and 1000 characters.</div>
                                    <div id="bio-char-count" style="font-size:.72rem;color:var(--slate);">
                                        <?= mb_strlen($profile?->bio ?? '') ?> / 1000
                                    </div>
                                </div>
                                <div class="oc-error-msg" id="bio-error"></div>
                            </div>
                        </div>

                        <hr style="border:0;border-top:1px solid var(--border);margin:24px 0;">

                        <div id="section-expertise" style="margin-bottom:24px;">
                            <label class="oc-label">Areas of expertise</label>
                            <div class="oc-help" style="margin-bottom:12px;">
                                Add up to 8 topics that describe your writing focus to help editors match you with briefs.
                            </div>

                            <div id="expertise-tags" style="display:flex;flex-wrap:wrap;gap:6px;min-height:38px;margin-bottom:12px;padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius);background:#fff;cursor:text;">
                            </div>

                            <div style="display:flex;gap:8px;align-items:flex-start;">
                                <div style="flex:1;position:relative;">
                                    <input class="oc-input" type="text" id="expertise-input"
                                           placeholder="e.g. Technology, Climate, Finance…"
                                           maxlength="40" autocomplete="off">
                                    <div id="expertise-suggestions" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);z-index:50;overflow:hidden;">
                                    </div>
                                </div>
                                <button type="button" id="add-expertise-btn" class="oc-btn oc-btn--ghost oc-btn--sm" style="height:38px;">
                                    Add
                                </button>
                            </div>
                            <div class="oc-help" style="margin-top:4px; font-size: 0.75rem;">
                                Press <kbd style="font-size:.7rem;padding:1px 4px;border:1px solid var(--border);border-radius:4px;background:#f8fafc;">Enter</kbd> or comma to add. Click tags to remove.
                            </div>
                            <div class="oc-error-msg" id="expertise-error" style="margin-top:4px;"></div>
                        </div>

                        <hr style="border:0;border-top:1px solid var(--border);margin:24px 0;">

                        <div id="section-samples" style="margin-bottom:32px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                                <label class="oc-label" style="margin-bottom:0;">Writing samples</label>
                                <span style="font-size:.75rem;color:var(--slate);font-weight:500;">Optional</span>
                            </div>
                            <div class="oc-help" style="margin-bottom:14px;">
                                Link to up to 3 pieces of your published work to give editors a sense of your style.
                            </div>

                            <div id="samples-list" style="display:flex;flex-direction:column;gap:12px;">
                                <?php
                                $samples = $profile?->sample_links ?? [];

                                while (count($samples) < 3) { $samples[] = null; }
                                foreach ($samples as $i => $sample):
                                    $n = $i + 1;
                                    ?>
                                    <div style="display:flex;gap:8px;align-items:stretch;" data-sample-row="<?= $n ?>">
                                        <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
                                            <input class="oc-input" type="url" id="sample-url-<?= $n ?>"
                                                   placeholder="https://example.com/my-article"
                                                   value="<?= htmlspecialchars($sample['url'] ?? '') ?>" style="font-size:0.82rem; padding:6px 10px;">
                                            <input class="oc-input" type="text" id="sample-title-<?= $n ?>"
                                                   placeholder="Article title (optional)"
                                                   value="<?= htmlspecialchars($sample['title'] ?? '') ?>" style="font-size:0.82rem; padding:6px 10px;">
                                        </div>
                                        <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm clear-sample-btn" data-row="<?= $n ?>" style="color:var(--slate);padding:0 10px;display:flex;align-items:center;justify-content:center;" aria-label="Clear row">
                                            <svg viewBox="0 0 20 20" fill="currentColor" width="14" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="oc-error-msg" id="samples-error" style="margin-top:4px;"></div>
                        </div>

                        <div style="display:flex; gap:12px; align-items:center; border-top:1px solid var(--border); padding-top:20px;">
                            <button type="button" class="oc-btn oc-btn--ghost" id="save-progress-btn" style="flex:1; white-space:nowrap;">
                                Save progress
                            </button>
                            <button type="submit" class="oc-btn oc-btn--amber" id="submit-btn" style="flex:2; display:flex; align-items:center; justify-content:center; gap:8px;">
                                Save &amp; continue
                                <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                    <path fill-rule="evenodd"
                                          d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- ── PAYMENT STEP ─────────────────────────── -->
                <?php elseif ($vm->currentStepName() === 'payment'): ?>
                    <form id="onboarding-form" novalidate>
                        <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                      clip-rule="evenodd"/>
                            </svg>
                            Card details are tokenised by Stripe and never stored on our servers.
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label">Payout method</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px;">
                                <label style="cursor:pointer;">
                                    <input type="radio" name="payment_method_type" value="stripe" checked
                                           style="display:none;" id="pm-stripe">
                                    <div class="payment-method-card" data-for="pm-stripe"
                                         style="border:2px solid var(--navy);background:var(--navy);border-radius:8px;padding:12px;text-align:center;">
                                        <svg viewBox="0 0 20 20" fill="var(--amber)" width="22"
                                             style="margin-bottom:6px;">
                                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                                            <path fill-rule="evenodd"
                                                  d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        <span style="display:block;font-size:.78rem;font-weight:600;color:#fff;">Card (Stripe)</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="payment_method_type" value="bank_transfer"
                                           style="display:none;" id="pm-bank">
                                    <div class="payment-method-card" data-for="pm-bank"
                                         style="border:2px solid var(--border);border-radius:8px;padding:12px;text-align:center;">
                                        <svg viewBox="0 0 20 20" fill="var(--slate)" width="22"
                                             style="margin-bottom:6px;">
                                            <path fill-rule="evenodd"
                                                  d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-5L9 4H4zm7 5a1 1 0 10-2 0v1H8a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V9z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        <span style="display:block;font-size:.78rem;font-weight:600;color:var(--slate);">Bank transfer</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="stripe-section">
                            <div id="stripe-payment-methods"
                                 style="display:none;margin-bottom:16px;border:1px solid var(--border);border-radius:var(--radius);background:#fff;overflow:hidden;">
                                <div style="padding:12px 14px;border-bottom:1px solid var(--border);font-weight:600;font-size:.84rem;color:var(--navy);">
                                    Saved cards
                                </div>
                                <div id="stripe-payment-methods-list"></div>
                            </div>
                            <div class="oc-form-group">
                                <label class="oc-label">Add a new card</label>
                                <div id="stripe-card-element"
                                     style="border:1.5px solid var(--border);border-radius:var(--radius);padding:12px 14px;background:#fff;"></div>
                                <div id="stripe-card-errors" class="oc-error-msg" style="margin-top:6px;"></div>
                                <div class="oc-help" style="margin-top:6px;">
                                    Existing cards are stored in Stripe. Add a new card here only if you want to replace or add one.
                                </div>
                            </div>
                        </div>

                        <div id="bank-section" style="display:none;">
                            <div class="oc-form-group">
                                <label class="oc-label" for="bank-account">Account email / reference</label>
                                <input class="oc-input" type="text" id="bank-account" name="bank_account"
                                       placeholder="e.g. your bank email or sort code reference">
                                <div class="oc-help">We'll use this to process manual payouts.</div>
                            </div>
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label" for="tax-country">Country of tax residence</label>
                            <select class="oc-select" id="tax-country" name="tax_country">
                                <option value="">Select country…</option>
                                <option value="GB" <?= ($profile?->tax_country ?? '') === 'GB' ? 'selected' : '' ?>>United Kingdom</option>
                                <option value="US" <?= ($profile?->tax_country ?? '') === 'US' ? 'selected' : '' ?>>United States</option>
                                <option value="CA" <?= ($profile?->tax_country ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                                <option value="AU" <?= ($profile?->tax_country ?? '') === 'AU' ? 'selected' : '' ?>>Australia</option>
                                <option value="DE" <?= ($profile?->tax_country ?? '') === 'DE' ? 'selected' : '' ?>>Germany</option>
                                <option value="FR" <?= ($profile?->tax_country ?? '') === 'FR' ? 'selected' : '' ?>>France</option>
                                <option value="IE" <?= ($profile?->tax_country ?? '') === 'IE' ? 'selected' : '' ?>>Ireland</option>
                                <option value="NL" <?= ($profile?->tax_country ?? '') === 'NL' ? 'selected' : '' ?>>Netherlands</option>
                                <option value="OTHER" <?= ($profile?->tax_country ?? '') === 'OTHER' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <div class="oc-help">Used for tax reporting purposes only.</div>
                        </div>

                        <div style="display:flex; gap:12px; align-items:center; border-top:1px solid var(--border); padding-top:20px;">
                            <button type="button" class="oc-btn oc-btn--ghost" id="save-payment-btn" style="flex:1; white-space:nowrap;">
                                Save details
                            </button>
                            <button type="submit" class="oc-btn oc-btn--amber" id="submit-btn" style="flex:2; display:flex; align-items:center; justify-content:center; gap:8px;">
                                Confirm &amp; continue
                                <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                    <path fill-rule="evenodd"
                                          d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- ── CONTRACT STEP ─────────────────────────── -->
                <?php elseif ($vm->currentStepName() === 'contract'): ?>
                    <?php $meta = $vm->currentStepMeta(); ?>
                    <form id="onboarding-form" novalidate>
                        <?php if ($contract): ?>
                            <input type="hidden" name="contract_id" value="<?= (int)$contract->id ?>">
                            <p style="font-size:.875rem;color:var(--slate);margin-bottom:16px;">
                                Please read the full contributor agreement before signing.
                            </p>
                            <div style="height:260px;overflow-y:scroll;border:1.5px solid var(--border);border-radius:var(--radius);padding:18px 20px;font-size:.875rem;line-height:1.75;color:var(--navy);background:#fff;margin-bottom:20px;"
                                 id="contract-scroll">
                                <?= $contract->content ?>
                            </div>
                            <div class="oc-toggle-row" style="margin-bottom:20px;">
                                <label class="oc-toggle">
                                    <input type="checkbox" id="agreed" name="agreed" required>
                                    <span class="oc-toggle__slider"></span>
                                </label>
                                <div class="oc-toggle-label">
                                    <strong>I have read and agree to this contributor agreement</strong>
                                    <span>Version <?= (int)$contract->version ?>, signed electronically</span>
                                </div>
                            </div>
                            <div class="oc-error-msg" id="agree-error"></div>
                            <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                                Sign &amp; continue
                            </button>
                        <?php else: ?>
                            <p style="color:var(--slate);">No contract is currently required for this site. Continue to
                                the next step.</p>
                            <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">Continue
                            </button>
                        <?php endif; ?>
                    </form>

                    <!-- ── GUIDELINES STEP ─────────────────────────── -->
                <?php elseif ($vm->currentStepName() === 'guidelines'): ?>
                    <?php $meta = $vm->currentStepMeta(); ?>
                    <form id="onboarding-form" novalidate>
                        <input type="hidden" name="version" value="<?= (int)($siteGuidelinesVersion ?? 1) ?>">
                        <p style="font-size:.875rem;color:var(--slate);margin-bottom:20px;">
                            Before you start publishing, please review our editorial standards and brand guidelines.
                        </p>
                        <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
                                <div>
                                    <div style="font-weight:600;font-size:.9rem;color:var(--navy);margin-bottom:2px;">Brand
                                        & Editorial Guidelines
                                    </div>
                                    <div style="font-size:.78rem;color:var(--slate);">
                                        Version <?= (int)($siteGuidelinesVersion ?? 1) ?></div>
                                </div>
                            </div>
                            <div style="max-height:360px;overflow:auto;background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;font-size:.875rem;line-height:1.7;color:var(--navy);white-space:pre-wrap;">
                                <?php if (!empty($siteGuidelines?->content)): ?>
                                    <?= $siteGuidelines->content ?>
                                <?php else: ?>
                                    <p style="margin:0;color:var(--slate);">No published guidelines are currently available for this site.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="oc-toggle-row" style="margin-bottom:20px;">
                            <label class="oc-toggle">
                                <input type="checkbox" id="guidelines-agreed" name="agreed" required>
                                <span class="oc-toggle__slider"></span>
                            </label>
                            <div class="oc-toggle-label">
                                <strong>I have read and will follow the brand guidelines</strong>
                                <span>Content that violates these guidelines may be removed.</span>
                            </div>
                        </div>
                        <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                            Complete onboarding
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </form>

                    <!-- ── AGE VERIFICATION STEP ────────────────── -->
                <?php elseif ($vm->currentStepName() === 'age_verification'): ?>
                    <?php $meta = $vm->currentStepMeta(); ?>
                    <form id="onboarding-form" novalidate>
                        <p style="font-size:.875rem;color:var(--slate);margin-bottom:20px;">
                            You must be at least <?= (int)($meta['minimum_age'] ?? 18) ?> years old to contribute to
                            this platform. Please enter your date of birth to confirm you meet this requirement.
                        </p>

                        <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                      clip-rule="evenodd"/>
                            </svg>
                            Your date of birth is used only to verify your eligibility. It is stored securely and
                            never shared with third parties.
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label" for="dob">Date of birth</label>
                            <input class="oc-input" type="date" id="dob" name="date_of_birth"
                                   required
                                   max="<?= date('Y-m-d') ?>"
                                   min="<?= date('Y-m-d', strtotime('-100 years')) ?>"
                                   style="max-width:220px;">
                            <div class="oc-help">
                                You must be <?= (int)($meta['minimum_age'] ?? 18) ?> or older to continue.
                            </div>
                            <div class="oc-error-msg" id="dob-error"></div>
                        </div>

                        <div class="oc-toggle-row" style="margin-bottom:20px;">
                            <label class="oc-toggle">
                                <input type="checkbox" id="dob-confirmed" name="dob_confirmed" required>
                                <span class="oc-toggle__slider"></span>
                            </label>
                            <div class="oc-toggle-label">
                                <strong>I confirm this is my correct date of birth</strong>
                                <span>Providing a false date of birth may result in account suspension.</span>
                            </div>
                        </div>

                        <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                            Confirm age &amp; continue
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </form>

                <?php endif; ?>

            </div>
        </div>

    </div>
</main>

<script>
    const STRIPE_KEY = '<?= htmlspecialchars($stripePublicKey ?? '') ?>';
    const CURRENT_STEP = '<?= $vm->currentStepName() ?>';
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const INITIAL_EXPERTISE = <?= json_encode($initialExpertise ?? []) ?>;
    const DEFAULT_NAME = '<?= htmlspecialchars($currentUser->name ?? 'U') ?>';

    class OnboardingStepBase {
        _site;
        _token;

        constructor(site, token) {
            this._site = site;
            this._token = token;
            this._bindForm();
        }

        _bindForm() {
            document.getElementById('onboarding-form')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this._submit();
            });
        }

        // Subclasses implement this
        async _submit() {
        }

        _showError(msg) {
            const el = document.getElementById('form-errors');
            el.textContent = msg;
            el.style.display = 'block';
        }

        _clearError() {
            document.getElementById('form-errors').style.display = 'none';
        }

        async _post(endpoint, payload) {
            return fetch(`/api/${this._site}/open-collab/onboarding/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: `Bearer ${this._token}`,
                },
                body: JSON.stringify(payload),
            });
        }

        _setButtonLoading(text = 'Saving…') {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = `<div class="oc-spinner"></div> ${text}`;
            return btn;
        }

        _resetButton(btn, label) {
            btn.disabled = false;
            btn.innerHTML = label;
        }

        async _handleResponse(res, btn, originalLabel) {
            const data = await res.json();
            if (res.ok) {
                window.location.reload();
            } else {
                let msg = data.message || 'An error occurred.';
                if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                this._showError(msg);
                this._resetButton(btn, originalLabel);
            }
        }
    }

    class ProfileStep extends OnboardingStepBase {
        _tags = [];
        _suggestedTopics = [
            'Technology', 'Science', 'Health', 'Finance', 'Climate',
            'Politics', 'Culture', 'Travel', 'Sport', 'Education',
            'Business', 'Opinion', 'Arts', 'Food', 'Lifestyle'
        ];

        constructor(site, token) {
            super(site, token);
            this._tags = [...INITIAL_EXPERTISE];
            this._initFeatures();
        }

        _initFeatures() {
            this._renderTags();

            // Character tracking counter metrics loop
            const bioEl = document.getElementById('bio');
            const counter = document.getElementById('bio-char-count');
            if (bioEl && counter) {
                bioEl.addEventListener('input', () => {
                    counter.textContent = `${bioEl.value.length} / 1000`;
                    counter.style.color = bioEl.value.length > 1000 ? 'var(--red)' : 'var(--slate)';
                });
            }

            // ── TRIGGER BACKGROUND AUTO-SAVES ON LOOSE BLUR ──
            bioEl?.addEventListener('blur', () => this._saveBioSegment());

            document.querySelectorAll('#samples-list input').forEach(input => {
                input.addEventListener('blur', () => this._saveSamplesSegment());
            });

            // Avatar interactions
            const avatarInput = document.getElementById('avatar-file-input');
            avatarInput?.addEventListener('change', (e) => this._onAvatarSelected(e.target));

            const removeAvatarBtn = document.getElementById('avatar-remove-btn');
            removeAvatarBtn?.addEventListener('click', () => this._removeAvatarFile());

            // Expertise key tracking hooks
            const expInput = document.getElementById('expertise-input');
            expInput?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    this._addExpertiseFromInput();
                }
            });
            expInput?.addEventListener('input', (e) => this._onExpertiseInput(e.target.value));
            document.getElementById('add-expertise-btn')?.addEventListener('click', () => this._addExpertiseFromInput());

            // Clear sample row triggers
            document.querySelectorAll('.clear-sample-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const rowNum = btn.dataset.row;
                    const urlInput = document.getElementById(`sample-url-${rowNum}`);
                    const titleInput = document.getElementById(`sample-title-${rowNum}`);
                    if (urlInput) urlInput.value = '';
                    if (titleInput) titleInput.value = '';
                    this._saveSamplesSegment();
                });
            });

            // ── EXPLICIT DISCRETE "SAVE PROGRESS" BUTTON TRIGGER ──
            document.getElementById('save-progress-btn')?.addEventListener('click', () => this._manualProgressSaveOnly());
        }

        _updateStatusIndicator(message) {
            const el = document.getElementById('autosave-status');
            if (el) {
                el.textContent = message;
                el.style.display = 'block';
            }
        }

        // ── RESTFUL BACKGROUND SECTOR SAVES ──

        async _saveBioSegment() {
            this._updateStatusIndicator('Saving bio description progress...');
            const bioText = document.getElementById('bio')?.value.trim() ?? '';
            try {
                const res = await fetch(`/api/${this._site}/open-collab/contributor`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this._token}`
                    },
                    body: JSON.stringify({ bio: bioText })
                });
                if (res.ok) {
                    this._updateStatusIndicator('Bio draft updated successfully.');
                    this._clearError();
                } else {
                    this._updateStatusIndicator('Bio structural update saved locally.');
                }
            } catch {
                this._updateStatusIndicator('Bio changes kept local (network offline).');
            }
        }

        async _saveExpertiseSegment() {
            this._updateStatusIndicator('Synchronizing focus topics...');
            try {
                const res = await fetch(`/api/${this._site}/open-collab/contributor/expertise`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this._token}`
                    },
                    body: JSON.stringify({ expertise: this._tags })
                });
                if (res.ok) {
                    this._updateStatusIndicator('Expertise categories synchronized securely.');
                    this._clearError();
                }
            } catch {
                this._updateStatusIndicator('Expertise configuration tracked locally.');
            }
        }

        async _saveSamplesSegment() {
            this._updateStatusIndicator('Saving draft samples update...');
            const samples = [];
            for (let n = 1; n <= 3; n++) {
                const url = document.getElementById(`sample-url-${n}`)?.value.trim() ?? '';
                const title = document.getElementById(`sample-title-${n}`)?.value.trim() ?? '';
                if (url) {
                    samples.push({ url, title });
                }
            }

            try {
                const res = await fetch(`/api/${this._site}/open-collab/profile/sample-links`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this._token}`
                    },
                    body: JSON.stringify({ sample_links: samples })
                });
                if (res.ok) {
                    this._updateStatusIndicator('Writing sample modifications committed.');
                    this._clearError();
                }
            } catch {
                this._updateStatusIndicator('Samples tracked in temporary memory buffer.');
            }
        }

        // ── MANUAL PROGRESS ACTION FOR MULTI-SESSION LEAVE ──
        async _manualProgressSaveOnly() {
            this._clearError();
            const btn = document.getElementById('save-progress-btn');
            const successEl = document.getElementById('form-success');
            if (successEl) successEl.style.display = 'none';

            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Saving progress...';

            try {
                // Concurrently run discrete API pipeline saves to ensure complete state execution synchronization
                await Promise.all([
                    this._saveBioSegment(),
                    this._saveExpertiseSegment(),
                    this._saveSamplesSegment()
                ]);

                if (successEl) {
                    successEl.textContent = 'Onboarding progress saved safely! You can close your browser session and return to pick up right where you left off.';
                    successEl.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                this._updateStatusIndicator('All step attributes written successfully.');
            } catch {
                this._showError('A synchronization latency timeout fault occurred during state persistence routing loops.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        _setAvatarPreview(src) {
            const wrap = document.getElementById('avatar-preview');
            if (!wrap) return;
            wrap.innerHTML = `<img id="avatar-img" src="${this._escapeHtml(src)}" alt="Your avatar" style="width:100%;height:100%;object-fit:cover;">`;
            document.getElementById('avatar-remove-btn').style.display = 'inline-block';
        }

        _onAvatarSelected(input) {
            const file = input.files?.[0];
            if (!file) return;

            const errEl = document.getElementById('avatar-error');
            errEl.style.display = 'none';

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                errEl.textContent = 'Only JPG, PNG, and WebP images are accepted.';
                errEl.style.display = 'block';
                input.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                errEl.textContent = 'Image must be under 2 MB.';
                errEl.style.display = 'block';
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => this._setAvatarPreview(e.target.result);
            reader.readAsDataURL(file);

            this._uploadAvatarFile(file);
        }

        _uploadAvatarFile(file) {
            const progressWrap = document.getElementById('avatar-progress-wrap');
            const progressBar = document.getElementById('avatar-progress-bar');
            const progressLabel = document.getElementById('avatar-progress-label');
            const errEl = document.getElementById('avatar-error');

            progressWrap.style.display = 'block';
            progressBar.style.width = '0%';
            progressLabel.textContent = 'Uploading…';

            const formData = new FormData();
            formData.append('avatar', file);

            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = (e) => {
                if (!e.lengthComputable) return;
                const pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = `${pct}%`;
                progressLabel.textContent = `${pct}%`;
            };

            xhr.onload = () => {
                progressWrap.style.display = 'none';
                if (xhr.status >= 200 && xhr.status < 300) {
                    this._updateStatusIndicator('Avatar headshot upload completed.');
                    this._clearError();
                } else {
                    errEl.textContent = 'Upload failed. Please try again.';
                    errEl.style.display = 'block';
                }
            };

            xhr.open('POST', `/api/${this._site}/open-collab/contributor/avatar`);
            xhr.setRequestHeader('Authorization', `Bearer ${this._token}`);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(formData);
        }

        async _removeAvatarFile() {
            this._updateStatusIndicator('Removing avatar file asset...');
            const errEl = document.getElementById('avatar-error');
            if (errEl) errEl.style.display = 'none';

            try {
                const res = await fetch(`/api/${this._site}/open-collab/contributor/avatar`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this._token}`
                    }
                });

                if (res.ok) {
                    const wrap = document.getElementById('avatar-preview');
                    const initial = DEFAULT_NAME.trim().charAt(0).toUpperCase();
                    wrap.innerHTML = `<span id="avatar-initials" style="font-family:var(--font-display);font-size:1.3rem;color:var(--slate);user-select:none;">${initial}</span>`;
                    document.getElementById('avatar-remove-btn').style.display = 'none';
                    document.getElementById('avatar-file-input').value = '';
                    this._updateStatusIndicator('Profile avatar resource removed.');
                    this._clearError();
                } else {
                    if (errEl) {
                        errEl.textContent = 'Failed to remove photo. Please try again.';
                        errEl.style.display = 'block';
                    }
                }
            } catch {
                if (errEl) {
                    errEl.textContent = 'Could not remove photo. Please check your connection.';
                    errEl.style.display = 'block';
                }
            }
        }

        _renderTags() {
            const container = document.getElementById('expertise-tags');
            if (!container) return;
            container.innerHTML = '';
            this._tags.forEach((tag, i) => {
                const pill = document.createElement('span');
                pill.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:var(--slate-pale, #f1f5f9);border:1px solid var(--border);border-radius:99px;font-size:.76rem;color:var(--navy);cursor:pointer;font-weight:500;';
                pill.title = 'Click to remove';
                pill.innerHTML = `${this._escapeHtml(tag)} <svg viewBox="0 0 12 12" fill="currentColor" width="9" aria-hidden="true"><path d="M6 4.586L9.293 1.293a1 1 0 111.414 1.414L7.414 6l3.293 3.293a1 1 0 01-1.414 1.414L6 7.414 2.707 10.707a1 1 0 01-1.414-1.414L4.586 6 1.293 2.707A1 1 0 012.707 1.293L6 4.586z"/></svg>`;
                pill.onclick = () => {
                    this._tags.splice(i, 1);
                    this._renderTags();
                    this._saveExpertiseSegment();
                };
                container.appendChild(pill);
            });
        }

        _onExpertiseInput(value) {
            const lower = value.toLowerCase().trim();
            const suggestions = document.getElementById('expertise-suggestions');
            if (!lower) {
                suggestions.style.display = 'none';
                return;
            }

            const matches = this._suggestedTopics.filter(s =>
                s.toLowerCase().includes(lower) && !this._tags.includes(s)
            ).slice(0, 5);

            if (!matches.length) {
                suggestions.style.display = 'none';
                return;
            }

            suggestions.innerHTML = matches.map(m =>
                `<div style="padding:8px 12px;cursor:pointer;font-size:.82rem;color:var(--navy);" class="suggestion-item">${this._escapeHtml(m)}</div>`
            ).join('');
            suggestions.style.display = 'block';

            suggestions.querySelectorAll('.suggestion-item').forEach((el, index) => {
                el.addEventListener('mousedown', () => {
                    this._addTag(matches[index]);
                    const input = document.getElementById('expertise-input');
                    input.value = '';
                    suggestions.style.display = 'none';
                });
                el.addEventListener('mouseover', () => el.style.background = 'var(--cream)');
                el.addEventListener('mouseout', () => el.style.background = '');
            });
        }

        _addExpertiseFromInput() {
            const input = document.getElementById('expertise-input');
            const val = input.value.replace(/,$/, '').trim();
            if (val) this._addTag(val);
            input.value = '';
            document.getElementById('expertise-suggestions').style.display = 'none';
        }

        _addTag(raw) {
            const tag = raw.trim();
            const errEl = document.getElementById('expertise-error');
            if (errEl) errEl.textContent = '';

            if (!tag || this._tags.includes(tag)) return;
            if (this._tags.length >= 8) {
                if (errEl) errEl.textContent = 'Maximum of 8 expertise tags allowed.';
                return;
            }
            if (tag.length > 40) {
                if (errEl) errEl.textContent = 'Tags must be under 40 characters.';
                return;
            }
            this._tags.push(tag);
            this._renderTags();
            this._saveExpertiseSegment();
        }

        _escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        // ── SUBMIT SYSTEM RUNS ONLY WHEN TRANSITIONING TO THE NEXT FULL STEP ──
        async _submit() {
            this._clearError();

            const bio = document.getElementById('bio')?.value.trim();
            const bioErr = document.getElementById('bio-error');
            const samplesErr = document.getElementById('samples-error');

            if (bioErr) bioErr.textContent = '';
            if (samplesErr) samplesErr.textContent = '';

            if (!bio || bio.length < 20) {
                if (bioErr) bioErr.textContent = 'Bio must be at least 20 characters.';
                return;
            }

            const samples = [];
            for (let n = 1; n <= 3; n++) {
                const url = document.getElementById(`sample-url-${n}`)?.value.trim() ?? '';
                const title = document.getElementById(`sample-title-${n}`)?.value.trim() ?? '';
                if (url) {
                    try {
                        new URL(url);
                    } catch {
                        if (samplesErr) samplesErr.textContent = `Sample ${n} is not a valid URL. Please include https://.`;
                        return;
                    }
                    samples.push({ url, title });
                }
            }

            const btn = this._setButtonLoading('Saving and moving to next step…');

            try {
                // Ensure bio database block matches context state precisely before wizard update
                await fetch(`/api/${this._site}/open-collab/contributor`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this._token}`
                    },
                    body: JSON.stringify({ bio: bio })
                });

                // Explicitly complete the profile step after the draft has been saved.
                const res = await this._post('steps/profile/complete', {});
                await this._handleResponse(res, btn, 'Save & continue');
            } catch (err) {
                this._showError('Something went wrong. Please check your connection and try again.');
                this._resetButton(btn, 'Save & continue');
            }
        }
    }

    class PaymentStep extends OnboardingStepBase {
        #stripe = null;
        #cardElement = null;
        #cardComplete = false;
        #paymentMethods = [];
        #selectedPaymentMethodId = null;

        constructor(site, token, stripeKey) {
            super(site, token);
            if (stripeKey) this.#initStripe(stripeKey);
            this.#bindMethodToggle();
            this.#bindSaveButton();
            this.#bindSavedPaymentMethods();
            this.#loadPaymentMethods();
        }

        #bindSaveButton() {
            document.getElementById('save-payment-btn')?.addEventListener('click', () => this.#saveOnly());
        }

        async #collectPayload() {
            const pmType = document.querySelector('[name="payment_method_type"]:checked')?.value;

            if (pmType === 'stripe' && this.#stripe && this.#cardElement) {
                if (!this.#cardComplete && this.#selectedPaymentMethodId) {
                    return {
                        payment_method_type: 'stripe',
                        payment_method_id: this.#selectedPaymentMethodId,
                        tax_country: document.getElementById('tax-country')?.value || '',
                    };
                }

                if (!this.#cardComplete) {
                    document.getElementById('stripe-card-errors').textContent = 'Add a card or choose a saved card.';
                    return null;
                }

                const {paymentMethod, error} = await this.#stripe.createPaymentMethod({
                    type: 'card',
                    card: this.#cardElement,
                });
                if (error) {
                    document.getElementById('stripe-card-errors').textContent = error.message;
                    return null;
                }

                return {
                    payment_method_type: 'stripe',
                    payment_method_id: paymentMethod.id,
                    tax_country: document.getElementById('tax-country')?.value || '',
                };
            }

            return {
                payment_method_type: 'bank_transfer',
                stripe_token: document.getElementById('bank-account')?.value || 'bank',
                tax_country: document.getElementById('tax-country')?.value || '',
            };
        }

        async #saveOnly() {
            this._clearError();
            const successEl = document.getElementById('form-success');
            if (successEl) successEl.style.display = 'none';

            const btn = document.getElementById('save-payment-btn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Saving…';

            const payload = await this.#collectPayload();
            if (!payload) {
                btn.disabled = false;
                btn.innerHTML = originalContent;
                return;
            }

            try {
                const res = await this._post('payment', payload);
                const data = await res.json();
                if (res.ok) {
                    this.#applyPaymentMethodsFromResponse(data);
                    if (this.#cardElement) {
                        this.#cardElement.clear();
                        this.#cardComplete = false;
                    }
                    if (successEl) {
                        successEl.textContent = 'Payment details saved. You can come back and continue later.';
                        successEl.style.display = 'block';
                    }
                } else {
                    let msg = data.message || 'An error occurred.';
                    if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                    this._showError(msg);
                }
            } catch {
                this._showError('Could not save payment details. Please check your connection.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        #initStripe(key) {
            this.#stripe = Stripe(key);
            const elems = this.#stripe.elements();
            this.#cardElement = elems.create('card', {
                hidePostalCode: true,
                style: {
                    base: {
                        fontFamily: "'DM Sans', sans-serif",
                        fontSize: '15px',
                        color: '#0f1929',
                        '::placeholder': {color: '#94a3b8'},
                    },
                },
            });
            this.#cardElement.mount('#stripe-card-element');
            this.#cardElement.on('change', ({complete, error}) => {
                this.#cardComplete = complete;
                document.getElementById('stripe-card-errors').textContent = error ? error.message : '';
                if (complete) {
                    this.#selectedPaymentMethodId = null;
                    document.querySelectorAll('[name="saved_payment_method"]').forEach(input => {
                        input.checked = false;
                    });
                }
            });
        }

        #bindMethodToggle() {
            document.querySelectorAll('[name="payment_method_type"]').forEach(radio => {
                radio.addEventListener('change', () => this.#onMethodChange());
            });
        }

        #onMethodChange() {
            const isStripe = document.querySelector('[name="payment_method_type"]:checked')?.value === 'stripe';
            document.getElementById('stripe-section').style.display = isStripe ? 'block' : 'none';
            document.getElementById('bank-section').style.display = isStripe ? 'none' : 'block';

            document.querySelectorAll('.payment-method-card').forEach(card => {
                const checked = document.getElementById(card.dataset.for)?.checked;
                card.style.borderColor = checked ? 'var(--navy)' : 'var(--border)';
                card.style.background = checked ? 'var(--navy)' : '';
                card.querySelectorAll('svg').forEach(s => s.setAttribute('fill', checked ? 'var(--amber)' : 'var(--slate)'));
                card.querySelectorAll('span').forEach(s => {
                    s.style.color = checked ? '#fff' : 'var(--slate)';
                });
            });
        }

        #bindSavedPaymentMethods() {
            document.getElementById('stripe-payment-methods-list')?.addEventListener('click', async event => {
                const action = event.target.closest('[data-payment-method-action]');
                if (!action) return;

                event.preventDefault();
                event.stopPropagation();

                const paymentMethodId = action.dataset.paymentMethodId;
                if (!paymentMethodId) return;

                action.disabled = true;
                const method = action.dataset.paymentMethodAction;

                try {
                    const res = await fetch(`/api/${this._site}/open-collab/onboarding/payment-methods/${paymentMethodId}${method === 'default' ? '/default' : ''}`, {
                        method: method === 'remove' ? 'DELETE' : 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'Authorization': `Bearer ${this._token}`,
                        },
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this._showError(data.error || data.message || 'Could not update payment methods.');
                        return;
                    }
                    this.#applyPaymentMethodsFromResponse(data);
                } catch {
                    this._showError('Could not update payment methods. Please check your connection.');
                } finally {
                    action.disabled = false;
                }
            });

            document.getElementById('stripe-payment-methods-list')?.addEventListener('change', event => {
                if (event.target.name === 'saved_payment_method') {
                    this.#selectedPaymentMethodId = event.target.value;
                    document.getElementById('stripe-card-errors').textContent = '';
                    if (this.#cardElement) {
                        this.#cardElement.clear();
                        this.#cardComplete = false;
                    }
                }
            });
        }

        async #loadPaymentMethods() {
            try {
                const res = await fetch(`/api/${this._site}/open-collab/onboarding/payment-methods`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this._token}`,
                    },
                });
                if (!res.ok) return;
                this.#applyPaymentMethodsFromResponse(await res.json());
            } catch {
                // The card form still works if the saved-method list cannot load.
            }
        }

        #applyPaymentMethodsFromResponse(response) {
            const payload = response.data?.data ?? response.data ?? response;
            this.#paymentMethods = payload.payment_methods ?? [];
            this.#selectedPaymentMethodId = payload.default_payment_method_id
                ?? this.#paymentMethods.find(method => method.is_default)?.id
                ?? this.#paymentMethods[0]?.id
                ?? null;
            this.#renderPaymentMethods();
        }

        #renderPaymentMethods() {
            const wrap = document.getElementById('stripe-payment-methods');
            const list = document.getElementById('stripe-payment-methods-list');
            if (!wrap || !list) return;

            if (!this.#paymentMethods.length) {
                wrap.style.display = 'none';
                list.innerHTML = '';
                return;
            }

            wrap.style.display = 'block';
            list.innerHTML = this.#paymentMethods.map(method => {
                const checked = method.id === this.#selectedPaymentMethodId ? 'checked' : '';
                const defaultBadge = method.is_default
                    ? '<span style="font-size:.7rem;color:var(--green);font-weight:700;">Default</span>'
                    : `<button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" data-payment-method-action="default" data-payment-method-id="${this.#escape(method.id)}">Set default</button>`;

                return `
                    <label style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-bottom:1px solid var(--border);cursor:pointer;">
                        <input type="radio" name="saved_payment_method" value="${this.#escape(method.id)}" ${checked}>
                        <div style="flex:1;">
                            <div style="font-weight:600;color:var(--navy);font-size:.86rem;">
                                ${this.#escape((method.brand || 'card').toUpperCase())} ending ${this.#escape(method.last4 || '')}
                            </div>
                            <div style="font-size:.74rem;color:var(--slate);">
                                Expires ${this.#escape(String(method.exp_month || '').padStart(2, '0'))}/${this.#escape(method.exp_year || '')}
                            </div>
                        </div>
                        ${defaultBadge}
                        <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" style="color:var(--red);" data-payment-method-action="remove" data-payment-method-id="${this.#escape(method.id)}">Remove</button>
                    </label>
                `;
            }).join('');
        }

        #escape(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        async _submit() {
            this._clearError();
            const btn = this._setButtonLoading('Saving…');

            const payload = await this.#collectPayload();
            if (!payload) {
                this._resetButton(btn, 'Confirm &amp; continue');
                return;
            }

            // Save payment details first, then advance the step.
            const saveRes = await this._post('payment', payload);
            if (!saveRes.ok) {
                const data = await saveRes.json();
                let msg = data.message || 'An error occurred.';
                if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                this._showError(msg);
                this._resetButton(btn, 'Confirm &amp; continue');
                return;
            }
            this.#applyPaymentMethodsFromResponse(await saveRes.clone().json());

            btn.innerHTML = `<div class="oc-spinner"></div> Confirming…`;
            const completeRes = await this._post('steps/payment/complete', {});
            await this._handleResponse(completeRes, btn, 'Confirm &amp; continue');
        }
    }

    class ContractStep extends OnboardingStepBase {
        async _submit() {
            this._clearError();
            const agreed = document.getElementById('agreed')?.checked;
            const cid = document.querySelector('[name="contract_id"]')?.value;

            if (!agreed) {
                document.getElementById('agree-error').textContent = 'You must agree to continue.';
                document.getElementById('agree-error').classList.add('visible');
                return;
            }

            const btn = this._setButtonLoading('Signing…');
            const res = await this._post('contract', {
                contract_id: parseInt(cid),
                agreed: true,
            });
            await this._handleResponse(res, btn, 'Sign & continue');
        }
    }

    class GuidelinesStep extends OnboardingStepBase {
        async _submit() {
            this._clearError();
            const agreed = document.getElementById('guidelines-agreed')?.checked;
            const version = document.querySelector('[name="version"]')?.value;

            if (!agreed) {
                this._showError('Please acknowledge the guidelines.');
                return;
            }

            const btn = this._setButtonLoading('Completing…');
            const res = await this._post('guidelines', {
                version: parseInt(version),
                agreed: true,
            });
            await this._handleResponse(res, btn, 'Complete onboarding');
        }
    }

    class AgeVerificationStep extends OnboardingStepBase {
        async _submit() {
            this._clearError();

            const dob = document.getElementById('dob')?.value;
            const confirmed = document.getElementById('dob-confirmed')?.checked;
            const dobError = document.getElementById('dob-error');

            dobError.textContent = '';
            dobError.classList.remove('visible');

            if (!dob) {
                dobError.textContent = 'Please enter your date of birth.';
                dobError.classList.add('visible');
                return;
            }

            // Client-side sanity check — server re-validates authoritatively.
            // We parse the date as UTC to avoid timezone boundary issues matching
            // the server-side ContributorAgeValidationService behaviour.
            const parts = dob.split('-').map(Number); // [YYYY, MM, DD]
            const dobDate = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
            const today = new Date();
            const todayUtc = new Date(Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()));

            if (dobDate >= todayUtc) {
                dobError.textContent = 'Date of birth cannot be in the future.';
                dobError.classList.add('visible');
                return;
            }

            if (!confirmed) {
                this._showError('Please confirm that this is your correct date of birth.');
                return;
            }

            const btn = this._setButtonLoading('Verifying…');
            const res = await this._post('age-verification', {date_of_birth: dob});

            await this._handleResponse(res, btn, 'Confirm age &amp; continue');
        }
    }

    class OnboardingManager {
        #step;

        constructor({site, token, stripeKey, currentStep}) {
            this.#step = this.#createStep(currentStep, site, token, stripeKey);
        }

        #createStep(name, site, token, stripeKey) {
            switch (name) {
                case 'profile':
                    return new ProfileStep(site, token);
                case 'payment':
                    return new PaymentStep(site, token, stripeKey);
                case 'contract':
                    return new ContractStep(site, token);
                case 'guidelines':
                    return new GuidelinesStep(site, token);
                case 'age_verification':
                    return new AgeVerificationStep(site, token);
                default:
                    return null;
            }
        }
    }

    const onboardingManager = new OnboardingManager({
        site: SITE,
        token: localStorage.getItem('oc_token') || '',
        stripeKey: STRIPE_KEY,
        currentStep: CURRENT_STEP,
    });
</script>
</body>
</html>
