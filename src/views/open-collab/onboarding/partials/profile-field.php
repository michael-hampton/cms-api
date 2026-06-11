<?php
/** @var \App\ViewModels\OpenCollab\ProfileFieldViewModel $field */

$fieldId = htmlspecialchars($field->key);
$errorId = htmlspecialchars($field->errorId());
$mode = $mode ?? 'onboarding';
$currentUser = $currentUser ?? null;
?>

<?php if ($field->renderType === 'image'): ?>
    <?php
    $photoUrl = $field->stringValue;
    $initial = strtoupper(substr($currentUser->name ?? 'U', 0, 1));
    $size = $mode === 'settings' ? '80px' : '64px';
    $initialSize = $mode === 'settings' ? '1.5rem' : '1.3rem';
    ?>

    <div class="oc-form-group" style="<?= $mode === 'settings' ? 'margin-bottom:24px;' : '' ?>">
        <label class="oc-label"><?= htmlspecialchars($mode === 'settings' ? 'Profile picture' : 'Profile photo') ?></label>

        <div style="display:flex;align-items:center;gap:<?= $mode === 'settings' ? '20px' : '14px' ?>;">
            <div id="avatar-preview-wrap" style="position:relative;flex-shrink:0;">
                <div
                    id="avatar-preview"
                    style="width:<?= $size ?>;height:<?= $size ?>;border-radius:50%;background:var(--slate-pale);border:<?= $mode === 'settings' ? '2px' : '1px' ?> solid var(--border);overflow:hidden;display:grid;place-items:center;cursor:pointer;"
                    onclick="document.getElementById('avatar-file-input').click()"
                    title="Click to change photo">

                    <?php if ($photoUrl): ?>
                        <img
                            id="avatar-img"
                            src="<?= htmlspecialchars($photoUrl) ?>"
                            alt="Your avatar"
                            style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <span
                            id="avatar-initials"
                            style="font-family:var(--font-display);font-size:<?= $initialSize ?>;color:var(--slate);user-select:none;">
                            <?= htmlspecialchars($initial) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div
                    onclick="document.getElementById('avatar-file-input').click()"
                    style="position:absolute;bottom:0;right:0;width:24px;height:24px;background:var(--navy);border-radius:50%;display:grid;place-items:center;cursor:pointer;border:2px solid #fff;"
                    title="Change photo">
                    <svg viewBox="0 0 20 20" fill="#fff" width="11">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                    </svg>
                </div>
            </div>

            <div style="flex:1;">
                <?php if ($mode === 'settings'): ?>
                    <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp"
                           style="display:none;" onchange="avatarManager.onFileSelected(this)">
                    <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                            onclick="document.getElementById('avatar-file-input').click()">
                        Choose photo
                    </button>
                    <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                            id="avatar-remove-btn"
                            style="margin-left:8px;color:var(--red);<?= $photoUrl ? '' : 'display:none;' ?>"
                            onclick="avatarManager.remove()">
                        Remove
                    </button>
                    <div class="oc-help" style="margin-top:6px;">
                        JPG, PNG or WebP · Max 2 MB · Square images work best
                    </div>
                    <div id="avatar-error" style="font-size:.75rem;color:var(--red);margin-top:4px;display:none;"></div>
                <?php else: ?>
                    <label class="oc-btn oc-btn--ghost oc-btn--sm" for="avatar-file-input">
                        Choose photo
                    </label>
                    <input
                        type="file"
                        id="avatar-file-input"
                        name="<?= htmlspecialchars($field->key) ?>"
                        accept="image/jpeg,image/png,image/webp"
                        style="display:none;">
                    <button
                        type="button"
                        id="avatar-remove-btn"
                        class="oc-btn oc-btn--ghost oc-btn--sm"
                        style="<?= $photoUrl ? '' : 'display:none;' ?>">
                        Remove
                    </button>
                    <input
                        type="hidden"
                        name="<?= htmlspecialchars($field->existingInputName()) ?>"
                        value="<?= htmlspecialchars($photoUrl) ?>">
                    <div class="oc-help">JPG, PNG or WebP · Max 2 MB</div>
                    <div class="oc-error-msg" id="avatar-error"></div>
                <?php endif; ?>

                <div id="avatar-progress-wrap" style="display:none;margin-top:8px;">
                    <div style="height:4px;background:var(--slate-pale);border-radius:99px;overflow:hidden;width:180px;">
                        <div id="avatar-progress-bar"
                             style="height:100%;width:0%;background:var(--navy);border-radius:99px;transition:width .2s ease;"></div>
                    </div>
                    <div style="font-size:.72rem;color:var(--slate);margin-top:3px;"
                         id="avatar-progress-label">Uploading…
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($field->key === 'expertise'): ?>
    <div class="oc-form-group" style="<?= $mode === 'settings' ? 'margin-bottom:0;' : '' ?>">
        <?php if ($mode !== 'settings'): ?>
            <label class="oc-label" for="expertise-input"><?= htmlspecialchars($field->name) ?></label>
        <?php endif; ?>

        <div class="oc-help" style="<?= $mode === 'settings' ? 'margin-bottom:16px;' : '' ?>">
            Add up to 8 topics that describe your writing focus<?= $mode === 'settings' ? '. These help editors match you with relevant briefs.' : ' to help editors match you with briefs.' ?>
        </div>

        <div id="expertise-tags"
             class="oc-tags"
             style="display:flex;flex-wrap:wrap;gap:<?= $mode === 'settings' ? '8px' : '6px' ?>;<?= $mode === 'settings' ? 'min-height:40px;margin-bottom:16px;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--radius);background:#fff;cursor:text;' : 'margin-top:10px;' ?>"
             onclick="document.getElementById('expertise-input').focus()">
        </div>

        <div style="position:relative;margin-top:<?= $mode === 'settings' ? '0' : '10px' ?>;">
            <div style="display:flex;gap:<?= $mode === 'settings' ? '10px' : '8px' ?>;align-items:flex-start;">
                <input
                    class="oc-input"
                    id="expertise-input"
                    type="text"
                    placeholder="<?= htmlspecialchars($field->placeholder ?: 'e.g. Technology, Climate, Finance...') ?>"
                    maxlength="40"
                    autocomplete="off"
                    style="flex:1;">

                <button
                    type="button"
                    class="oc-btn oc-btn--ghost<?= $mode === 'settings' ? ' oc-btn--sm' : '' ?>"
                    id="add-expertise-btn"
                    style="<?= $mode === 'settings' ? 'flex-shrink:0;margin-top:1px;' : '' ?>">
                    Add
                </button>
            </div>

            <div
                id="expertise-suggestions"
                style="display:none;position:absolute;left:0;right:<?= $mode === 'settings' ? '0' : '72px' ?>;top:calc(100% + 4px);background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-md);z-index:50;overflow:hidden;">
            </div>
        </div>

        <input
            type="hidden"
            id="expertise"
            name="<?= htmlspecialchars($field->key) ?>"
            value="<?= htmlspecialchars(json_encode($field->selectedValues)) ?>">

        <div class="oc-help" style="margin-top:<?= $mode === 'settings' ? '6px' : '4px' ?>;" id="expertise-hint">
            Press <kbd<?= $mode === 'settings' ? ' style="font-size:.7rem;padding:1px 5px;border:1px solid var(--border);border-radius:4px;background:var(--slate-pale);"' : '' ?>>Enter</kbd>
            or comma to add · Click tags to remove it
        </div>

        <div class="oc-error-msg" id="expertise-error"></div>
    </div>

<?php elseif ($field->key === 'writing_samples'): ?>
    <?php if ($mode === 'settings'): ?>
        <div id="sample-links-list" style="display:flex;flex-direction:column;gap:14px;margin-bottom:16px;"></div>
    <?php else: ?>
        <div class="oc-form-group">
            <label class="oc-label">
                <?= htmlspecialchars($field->name) ?>
                <?php if (!$field->required): ?>
                    <span style="float:right;font-size:.75rem;color:var(--slate);font-weight:500;">Optional</span>
                <?php endif; ?>
            </label>
            <?php if ($field->description): ?>
                <div class="oc-help"><?= htmlspecialchars($field->description) ?></div>
            <?php endif; ?>
            <div id="samples-list" style="margin-top:10px;">
                <?php foreach ($field->sampleLinks() as $index => $sample): ?>
                    <?php $row = $index + 1; ?>
                    <div class="sample-row" style="margin-bottom:10px;">
                        <div style="display:flex;gap:8px;">
                            <input
                                class="oc-input"
                                type="url"
                                id="sample-url-<?= $row ?>"
                                name="<?= htmlspecialchars($field->key) ?>[url][]"
                                value="<?= htmlspecialchars((string) ($sample['url'] ?? '')) ?>"
                                placeholder="https://example.com/my-article"
                                style="flex:1;">
                            <button
                                type="button"
                                class="oc-btn oc-btn--ghost oc-btn--sm clear-sample-btn"
                                data-row="<?= $row ?>">
                                ×
                            </button>
                        </div>
                        <input
                            class="oc-input"
                            type="text"
                            id="sample-title-<?= $row ?>"
                            name="<?= htmlspecialchars($field->key) ?>[title][]"
                            value="<?= htmlspecialchars((string) ($sample['title'] ?? '')) ?>"
                            placeholder="Article title (optional)"
                            style="margin-top:8px;">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="oc-error-msg" id="samples-error"></div>
        </div>
    <?php endif; ?>

<?php elseif ($field->key === 'bio'): ?>
    <div class="oc-form-group">
        <label class="oc-label" for="bio"><?= htmlspecialchars($mode === 'settings' ? $field->name : 'Your bio') ?></label>
        <textarea class="oc-textarea" id="bio" name="<?= htmlspecialchars($field->key) ?>"
                  rows="<?= $mode === 'settings' ? '4' : '5' ?>"
                  placeholder="<?= htmlspecialchars($field->placeholder ?: "I'm a writer specialising in...") ?>"
                  <?= $field->required ? 'required' : '' ?>
                  <?= $mode === 'settings' ? '' : 'style="min-height:120px;"' ?>><?= htmlspecialchars($field->stringValue) ?></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
            <div class="oc-help"><?= $mode === 'settings' ? 'Visible to readers on your published articles.' : 'Between 20 and 1000 characters.' ?></div>
            <div id="bio-char-count" style="font-size:.72rem;color:var(--slate);">
                <?= mb_strlen($field->stringValue) ?> / 1000
            </div>
        </div>
        <div class="oc-error-msg" id="bio-error"></div>
    </div>

<?php elseif ($field->key === 'display_name'): ?>
    <div class="oc-form-group">
        <label class="oc-label" for="display-name"><?= htmlspecialchars($field->name) ?></label>
        <input class="oc-input" type="text" id="display-name" name="<?= htmlspecialchars($field->key) ?>"
               value="<?= htmlspecialchars($field->stringValue ?: ($currentUser->name ?? '')) ?>"
               placeholder="<?= htmlspecialchars($field->placeholder) ?>"
            <?= $field->required ? 'required' : '' ?>>
        <?php if ($field->description): ?>
            <div class="oc-help"><?= htmlspecialchars($field->description) ?></div>
        <?php endif; ?>
        <div class="oc-error-msg" id="<?= $errorId ?>"></div>
    </div>

<?php else: ?>
<div class="oc-form-group" style="margin-bottom:20px;">
    <label class="oc-label" for="<?= $fieldId ?>">
        <?= htmlspecialchars($field->name) ?>

        <?php if (!$field->required): ?>
            <span style="font-size:.75rem;color:var(--slate);font-weight:500;">Optional</span>
        <?php endif; ?>
    </label>

    <?php if ($field->description): ?>
        <div class="oc-help" style="margin-bottom:8px;">
            <?= htmlspecialchars($field->description) ?>
        </div>
    <?php endif; ?>

    <?php if ($field->renderType === 'textarea'): ?>
        <textarea
                class="oc-textarea"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>"
                rows="4"
                placeholder="<?= htmlspecialchars($field->placeholder) ?>"
            <?= $field->required ? 'required' : '' ?>><?= htmlspecialchars($field->stringValue) ?></textarea>

    <?php elseif ($field->renderType === 'multi_select'): ?>
        <select
                class="oc-input"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>[]"
                multiple
            <?= $field->required ? 'required' : '' ?>>

            <?php foreach ($field->options as $option): ?>
                <option
                        value="<?= htmlspecialchars($option['value']) ?>"
                    <?= $field->isSelected($option['value']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($option['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($field->renderType === 'select'): ?>
        <select
                class="oc-select"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>"
                <?= $field->required ? 'required' : '' ?>>

            <option value="">Select country…</option>

            <?php foreach ($field->options as $option): ?>
                <option
                        value="<?= htmlspecialchars($option['value']) ?>"
                        <?= $field->stringValue === $option['value'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($option['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php else: ?>
        <input
                class="oc-input"
                type="<?= htmlspecialchars($field->inputType()) ?>"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>"
                value="<?= htmlspecialchars($field->stringValue) ?>"
                placeholder="<?= htmlspecialchars($field->placeholder) ?>"
            <?= $field->required ? 'required' : '' ?>>
    <?php endif; ?>

    <div class="oc-error-msg" id="<?= $errorId ?>"></div>
</div>
<?php endif; ?>
