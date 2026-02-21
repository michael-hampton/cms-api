<?php
/**
 * Utility Bar Partial — v2
 *
 * RIBBON context only. Sits inline at the bottom of each .page-card.
 * Light theme. No dark backgrounds.
 *
 * Share dropdown is hidden until user clicks Share.
 * Like button makes optimistic API call and toggles state.
 * Hub pill / Join button opens member hub panel via memberHub:open event.
 *
 * @var object $page – Page object (required)
 * @var bool $showToolbar – Guard flag (default true)
 */

use App\Framework\Authorization\MemberAuth;

$context = $context ?? 'ribbon';
$showToolbar = $showToolbar ?? true;
if (!$showToolbar) return;

// ── Auth state ──────────────────────────────────────────────────────────────
$isLoggedIn = class_exists('MemberAuth') && MemberAuth::check();
$member = $isLoggedIn ? MemberAuth::getMember() : null;
$unread = $isLoggedIn && method_exists($member, 'getUnreadCount') ? (int)$member->getUnreadCount() : 0;

// ── Page identifiers ────────────────────────────────────────────────────────
$pageId = $page->id ?? null;
$pageUrl = method_exists($page, 'getUrlAttribute') ? $page->getUrlAttribute() : ($page->url ?? '#');
$pageTitle = htmlspecialchars($page->title ?? '', ENT_QUOTES);
$siteSlug = class_exists('\App\Framework\Support\SiteContext')
        ? \App\Framework\Support\SiteContext::slug()
        : '';
$fullUrl = '/' . $siteSlug . htmlspecialchars($pageUrl);

// ── Like / save state seeded from PHP ──────────────────────────────────────
// [TODO-SAVED] Replace false with real lookup:
// $isSaved = $isLoggedIn && $pageId
//   ? (bool)\App\Repositories\Members\PageSaveRepository::existsForMember($member->id, $pageId)
//   : false;
$isSaved = false;

// [TODO-LIKES] Replace 0 / false with real values:
// $likeCount = (int)($page->likes_count ?? 0);
// $isLiked   = $isLoggedIn ? PageLikeRepository::isLikedBy($pageId, $member->id, $siteId) : false;
$likeCount = (int)($page->likes_count ?? 0);
$isLiked = false;

// Unique suffix so multiple bars on a listing page don't share IDs
$uid = 'ub-' . ($pageId ?? uniqid());
?>

    @js('utility-bar.js')
    @css('utility-bar.css')

    <div class="utility-bar utility-bar--ribbon"
         id="<?= $uid ?>"
         data-page-id="<?= (int)$pageId ?>">

        <!-- ── Save ────────────────────────────────────────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--save<?= $isSaved ? ' is-saved' : '' ?>"
                data-page-id="<?= (int)$pageId ?>"
                data-saved="<?= $isSaved ? '1' : '0' ?>"
                aria-label="<?= $isSaved ? 'Unsave article' : 'Save article' ?>"
                aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                data-action="save"
        >
        <span class="utility-bar__icon">
            <svg class="icon-save-outline" viewBox="0 0 24 24" width="20" height="20"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
            <svg class="icon-save-filled" viewBox="0 0 24 24" width="20" height="20"
                 fill="currentColor" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
            <?php if (!$isLoggedIn): ?>
                <span class="utility-bar__guest-dot" aria-hidden="true"></span>
            <?php endif; ?>
        </span>
            <span class="utility-bar__label"><?= $isSaved ? 'Saved' : 'Save' ?></span>
        </button>

        <!-- ── Like ────────────────────────────────────────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--like<?= $isLiked ? ' is-liked' : '' ?>"
                data-page-id="<?= (int)$pageId ?>"
                data-liked="<?= $isLiked ? '1' : '0' ?>"
                data-like-count="<?= $likeCount ?>"
                aria-label="<?= $isLiked ? 'Unlike article' : 'Like article' ?>"
                aria-pressed="<?= $isLiked ? 'true' : 'false' ?>"
                data-action="like"
        >
        <span class="utility-bar__icon">
            <svg class="icon-like-outline" viewBox="0 0 24 24" width="20" height="20"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <svg class="icon-like-filled" viewBox="0 0 24 24" width="20" height="20"
                 fill="currentColor" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
        </span>
            <span class="utility-bar__label utility-bar__label--like-count">
            <?= $likeCount > 0 ? $likeCount : 'Like' ?>
        </span>
        </button>

        <!-- ── Share ────────────────────────────────────────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--share"
                aria-label="Share article"
                aria-expanded="false"
                data-action="share"
        >
        <span class="utility-bar__icon">
            <svg viewBox="0 0 24 24" width="20" height="20"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="18" cy="5" r="3"/>
                <circle cx="6" cy="12" r="3"/>
                <circle cx="18" cy="19" r="3"/>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
            </svg>
        </span>
            <span class="utility-bar__label">Share</span>

            <!-- Hidden until .is-open added by JS -->
            <div class="utility-bar__share-dropdown" role="menu" onclick="event.stopPropagation()">
                <div class="utility-bar__share-option" role="menuitem" tabindex="-1"
                     data-share="facebook">
                <span class="utility-bar__share-option-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="#1877f2">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </span>
                    <span>Facebook</span>
                </div>
                <div class="utility-bar__share-option" role="menuitem" tabindex="-1"
                     data-share="twitter">
                <span class="utility-bar__share-option-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="#1da1f2">
                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                </span>
                    <span>Twitter</span>
                </div>
                <div class="utility-bar__share-option" role="menuitem" tabindex="-1"
                     data-share="linkedin">
                <span class="utility-bar__share-option-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="#0077b5">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                </span>
                    <span>LinkedIn</span>
                </div>
                <div class="utility-bar__share-option" role="menuitem" tabindex="-1"
                     data-share="whatsapp">
                <span class="utility-bar__share-option-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="#25d366">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </span>
                    <span>WhatsApp</span>
                </div>
                <div class="utility-bar__share-option" role="menuitem" tabindex="-1"
                     data-share="copy">
                <span class="utility-bar__share-option-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                    </svg>
                </span>
                    <span class="ub-copy-label">Copy link</span>
                </div>
            </div>
        </button>

        <!-- ── Comment ──────────────────────────────────────────────────────── -->
        <button
                class="utility-bar__btn utility-bar__btn--comment"
                aria-label="Comment on article"
                data-action="comment"
                data-url="<?= htmlspecialchars($fullUrl) ?>"
                data-page-id="<?= (int)$pageId ?>"
        >
        <span class="utility-bar__icon">
            <svg viewBox="0 0 24 24" width="20" height="20"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </span>
            <span class="utility-bar__label">Comment</span>
        </button>

        <button
                class="utility-bar__btn utility-bar__btn--newsletter action-btn"
                aria-label="Newsletter sign-up"
                onclick="openNewsletterModal()"
        >
    <span class="utility-bar__icon">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"
             aria-hidden="true">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
        </svg>
    </span>
            <span class="utility-bar__label">Newsletter</span>
        </button>


        <!-- ── Divider ──────────────────────────────────────────────────────── -->
        <div class="utility-bar__divider" aria-hidden="true"></div>

        <!-- ── Hub pill / Join button ────────────────────────────────────────── -->
        <?php if ($isLoggedIn && $member): ?>
            <button
                    class="utility-bar__btn utility-bar__hub-pill utility-bar__hub-pill--member"
                    aria-label="Open member hub"
                    aria-haspopup="dialog"
                    data-action="open-hub"
            >
            <span class="utility-bar__avatar" aria-hidden="true">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($member->first_name ?? 'M', 0, 1))) ?>
            </span>
                <span class="utility-bar__hub-label">My Hub</span>
                <?php if ($unread > 0): ?>
                    <span class="utility-bar__badge"
                          aria-label="<?= $unread ?> unread notifications">
                    <?= $unread ?>
                </span>
                <?php endif; ?>
            </button>
        <?php else: ?>
            <button
                    class="utility-bar__btn utility-bar__hub-pill utility-bar__hub-pill--guest"
                    aria-label="Join or sign in"
                    data-action="open-hub"
            >
                <svg viewBox="0 0 24 24" width="14" height="14"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="utility-bar__hub-label">Join / Sign in</span>
            </button>
        <?php endif; ?>

    </div>

    <!-- ── Guest Save Prompt (once per page for guest users) ──────────────────
         Rendered conditionally; multiple bars on a listing page each trigger
         this shared prompt. JS shows/hides it as needed.
    ─────────────────────────────────────────────────────────────────────────── -->
<?php if (!$isLoggedIn && !defined('UB_GUEST_PROMPT_RENDERED')): ?>
    <?php define('UB_GUEST_PROMPT_RENDERED', true); ?>
    <div class="guest-save-prompt" id="guestSavePrompt"
         role="dialog" aria-modal="true"
         aria-labelledby="guestSaveTitle" hidden>
        <div class="guest-save-prompt__inner">
            <div class="guest-save-prompt__header">
                <div>
                    <p class="guest-save-prompt__title" id="guestSaveTitle">🔖 Save this article</p>
                    <p class="guest-save-prompt__subtitle">Free account · sync across all devices</p>
                </div>
                <button class="guest-save-prompt__close"
                        aria-label="Close"
                        id="guestSaveClose">
                    <svg viewBox="0 0 24 24" width="14" height="14"
                         fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form class="guest-save-prompt__form" id="guestSaveForm"
                  onsubmit="utilityBar.submitGuestSave(event, parseInt(document.getElementById('guestSavePageId').value))">
                <input type="hidden" id="guestSavePageId" value="<?= (int)$pageId ?>">
                <input type="email" name="email" id="guestSaveEmail"
                       class="guest-save-prompt__input"
                       placeholder="your@email.com"
                       autocomplete="email" required>
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
            <div class="guest-save-prompt__success" id="guestSaveSuccess" hidden>
                <p class="guest-save-prompt__success-emoji">🎉</p>
                <p class="guest-save-prompt__success-title">Article saved!</p>
                <p class="guest-save-prompt__success-body">Check your email to verify &amp; access your reading list</p>
            </div>
        </div>
    </div>
<?php endif; ?>