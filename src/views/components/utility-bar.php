<?php
/**
 * Utility Bar Partial
 *
 * Renders in two contexts:
 *   'sticky'  — fixed bottom bar on article pages, included from main-content.php
 *   'ribbon'  — inline action ribbon on page cards, included from page-card.php
 *
 * @var object|array $page - The page object (required)
 * @var string $context - 'sticky' | 'ribbon' (default: 'sticky')
 * @var bool $showToolbar - Guard flag carried over from page-card.php (default: true)
 *
 * Assumptions about MemberAuth (adjust to match your actual helper):
 *   MemberAuth::check()           → bool, is the current user logged in
 *   MemberAuth::currentMember()   → object|null, member with ->first_name, ->tier, ->points
 *   MemberAuth::unreadCount()     → int, unread hub notifications
 *
 * Existing JS hooks preserved from page-card.php:
 *   toggleShareDropdown(btn)
 *   openCommentModal(url, pageId)
 *   openNewsletterModal()
 *
 * New JS hooks (defined in utility-bar.js):
 *   utilityBar.toggleSave(pageId, btn)
 *   utilityBar.toggleLike(pageId, btn)
 *   utilityBar.openHub(tab)
 *   utilityBar.showGuestSavePrompt()
 */

use App\Framework\Authorization\MemberAuth;

$context = $context ?? 'sticky';
$showToolbar = $showToolbar ?? true;

if (!$showToolbar) return;

// ── Auth state ──────────────────────────────────────────────────────────────
$isLoggedIn = class_exists('MemberAuth') && MemberAuth::check();
$member = $isLoggedIn ? MemberAuth::getMember() : null;
$unread = $isLoggedIn ? (int)$member->getUnreadCount() : 0;

// ── Page identifiers ────────────────────────────────────────────────────────
$pageId = $page->id ?? null;
$pageUrl = $page->getUrlAttribute ?? ($page->url ?? '#');
$pageTitle = $page->title ?? '';
$siteSlug = class_exists('\App\Framework\Support\SiteContext')
        ? \App\Framework\Support\SiteContext::slug()
        : '';

$fullUrl = '/' . $siteSlug . htmlspecialchars($pageUrl);

// ── CSS class for context ───────────────────────────────────────────────────
$barClass = $context === 'sticky' ? 'utility-bar utility-bar--sticky' : 'utility-bar utility-bar--ribbon';
?>

    @js('utility-bar.js')
    @css('utility-bar.css')

<?php if ($context === 'sticky'): ?>
    <!-- Read-progress bar sits outside the bar element so it can span full width -->
    <div class="utility-bar-progress" id="utilityBarProgress" role="progressbar" aria-valuemin="0" aria-valuemax="100"
         aria-valuenow="0">
        <div class="utility-bar-progress__fill"></div>
    </div>
<?php endif; ?>

    <div class="<?= $barClass ?>" id="utilityBar" data-page-id="<?= (int)$pageId ?>">

        <!-- ── Save ─────────────────────────────────────────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--save"
                id="utilityBarSaveBtn"
                data-page-id="<?= (int)$pageId ?>"
                aria-label="Save article"
                aria-pressed="false"
                onclick="utilityBar.handleSave(this)"
        >
        <span class="utility-bar__icon">
            <!-- outline (default) -->
            <svg class="icon-save-outline" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
            <!-- filled (saved state) -->
            <svg class="icon-save-filled" viewBox="0 0 24 24" width="22" height="22" fill="currentColor"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
            <?php if (!$isLoggedIn): ?>
                <span class="utility-bar__guest-dot" aria-hidden="true"></span>
            <?php endif; ?>
        </span>
            <span class="utility-bar__label">Save</span>
        </button>

        <!-- ── Like ─────────────────────────────────────────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--like"
                id="utilityBarLikeBtn"
                data-page-id="<?= (int)$pageId ?>"
                aria-label="Like article"
                aria-pressed="false"
                onclick="utilityBar.toggleLike(this)"
        >
        <span class="utility-bar__icon">
            <svg class="icon-like-outline" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <svg class="icon-like-filled" viewBox="0 0 24 24" width="22" height="22" fill="currentColor"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
        </span>
            <span class="utility-bar__label utility-bar__label--like-count">Like</span>
        </button>

        <!-- ── Share (existing dropdown, preserved exactly) ─────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--share action-btn"
                aria-label="Share article"
                onclick="toggleShareDropdown(this)"
        >
        <span class="utility-bar__icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"
                 aria-hidden="true">
                <circle cx="18" cy="5" r="3"/>
                <circle cx="6" cy="12" r="3"/>
                <circle cx="18" cy="19" r="3"/>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
            </svg>
        </span>
            <span class="utility-bar__label">Share</span>

            <!-- Share dropdown — identical markup to page-card.php -->
            <div class="share-dropdown" onclick="event.stopPropagation()">
                <div class="share-option" onclick="shareToFacebook('<?= $fullUrl ?>')">
                    <div class="share-option-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="#1877f2">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </div>
                    <span class="share-option-text">Facebook</span>
                </div>
                <div class="share-option"
                     onclick="shareToTwitter('<?= $fullUrl ?>', '<?= htmlspecialchars($pageTitle) ?>')">
                    <div class="share-option-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="#1da1f2">
                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                        </svg>
                    </div>
                    <span class="share-option-text">Twitter</span>
                </div>
                <div class="share-option" onclick="shareToLinkedIn('<?= $fullUrl ?>')">
                    <div class="share-option-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="#0077b5">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </div>
                    <span class="share-option-text">LinkedIn</span>
                </div>
                <div class="share-option"
                     onclick="shareToWhatsApp('<?= $fullUrl ?>', '<?= htmlspecialchars($pageTitle) ?>')">
                    <div class="share-option-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="#25d366">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <span class="share-option-text">WhatsApp</span>
                </div>
                <div class="share-option"
                     onclick="shareToReddit('<?= $fullUrl ?>', '<?= htmlspecialchars($pageTitle) ?>')">
                    <div class="share-option-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="#ff4500">
                            <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                        </svg>
                    </div>
                    <span class="share-option-text">Reddit</span>
                </div>
                <div class="share-option" onclick="copyLink('<?= $fullUrl ?>')">
                    <div class="share-option-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none"
                             stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                    </div>
                    <span class="share-option-text">Copy Link</span>
                </div>
            </div>
        </button>

        <!-- ── Comment (existing handler preserved) ──────────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--comment action-btn"
                aria-label="Comment on article"
                onclick="openCommentModal('<?= $fullUrl ?>', '<?= (int)$pageId ?>')"
        >
        <span class="utility-bar__icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"
                 aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </span>
            <span class="utility-bar__label">Comment</span>
        </button>

        <!-- ── Newsletter (existing handler preserved) ───────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--newsletter action-btn"
                aria-label="Newsletter sign-up"
                onclick="openNewsletterModal()"
        >
        <span class="utility-bar__icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"
                 aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </span>
            <span class="utility-bar__label">Newsletter</span>
        </button>

        <!-- ── Divider ────────────────────────────────────────────────────────── -->
        <div class="utility-bar__divider" aria-hidden="true"></div>

        <!-- ── My Hub / Join pill ────────────────────────────────────────────── -->
        <?php if ($isLoggedIn && $member): ?>
            <button
                    class="utility-bar__btn utility-bar__hub-pill utility-bar__hub-pill--member"
                    aria-label="Open member hub"
                    aria-haspopup="dialog"
                    onclick="utilityBar.openHub()"
            >
            <span class="utility-bar__avatar" aria-hidden="true">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($member->first_name, 0, 1))) ?>
            </span>
                <span class="utility-bar__hub-label">My Hub</span>
                <?php if ($unread > 0): ?>
                    <span class="utility-bar__badge" aria-label="<?= $unread ?> unread notifications">
                    <?= $unread ?>
                </span>
                <?php endif; ?>
            </button>
        <?php else: ?>
            <button
                    class="utility-bar__btn utility-bar__hub-pill utility-bar__hub-pill--guest"
                    aria-label="Join or sign in"
                    onclick="utilityBar.openHub()"
            >
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
                     aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="utility-bar__hub-label">Join / Sign in</span>
            </button>
        <?php endif; ?>

    </div>

    <!-- ── Guest Save Prompt ──────────────────────────────────────────────────────
         Rendered once per page; JS positions it above the bar when triggered.
         Only output when user is a guest — logged-in save goes straight to API.
    ──────────────────────────────────────────────────────────────────────────── -->
<?php if (!$isLoggedIn): ?>
    <div class="guest-save-prompt" id="guestSavePrompt" role="dialog" aria-modal="true" aria-labelledby="guestSaveTitle"
         hidden>
        <div class="guest-save-prompt__inner">
            <div class="guest-save-prompt__header">
                <div>
                    <p class="guest-save-prompt__title" id="guestSaveTitle">🔖 Save this article</p>
                    <p class="guest-save-prompt__subtitle">Free account · sync across all your devices</p>
                </div>
                <button class="guest-save-prompt__close" aria-label="Close" onclick="utilityBar.hideGuestSavePrompt()">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form class="guest-save-prompt__form" onsubmit="utilityBar.submitGuestSave(event, <?= (int)$pageId ?>)">
                <input
                        type="email"
                        name="email"
                        id="guestSaveEmail"
                        class="guest-save-prompt__input"
                        placeholder="your@email.com"
                        autocomplete="email"
                        required
                >
                <label class="guest-save-prompt__consent">
                    <input type="checkbox" name="newsletter_consent" value="1">
                    <span>Also send me the weekly newsletter — best stories &amp; deals</span>
                </label>
                <button type="submit" class="guest-save-prompt__submit">
                    Save &amp; create free account
                </button>
                <p class="guest-save-prompt__signin">
                    Already a member?
                    <a href="/<?= $siteSlug ?>/login?redirect=<?= urlencode($fullUrl) ?>">Sign in</a>
                </p>
            </form>
            <!-- Success state, hidden until JS swaps it in -->
            <div class="guest-save-prompt__success" hidden>
                <p class="guest-save-prompt__success-emoji">🎉</p>
                <p class="guest-save-prompt__success-title">Article saved!</p>
                <p class="guest-save-prompt__success-body">Check your email to verify &amp; access your reading list</p>
            </div>
        </div>
    </div>
<?php endif; ?>