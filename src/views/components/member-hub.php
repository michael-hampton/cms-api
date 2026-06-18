<?php
// ── Auth ───────────────────────────────────────────────────────────────────
$isLoggedIn = \App\Framework\Authorization\MemberAuth::check();
$siteSlug = \App\Framework\Support\SiteContext::slug();
$member = $isLoggedIn ? \App\Framework\Authorization\MemberAuth::getMember() : null;

// ── Member display fields ──────────────────────────────────────────────────
$memberFirstName = $member->first_name ?? $member->email ?? 'M';
$memberLastName = $member->last_name ?? '';
$memberDisplay = $member->displayName ?? trim("$memberFirstName $memberLastName");
$memberInitial = strtoupper(substr($memberFirstName, 0, 1));
$memberAvatar = $member->avatar_url ?? null;

// [TODO-TIER] swap 'subscription_type' with the real field once confirmed
// e.g. $member->tier  or  $member->activeSubscription?->plan?->name
$memberTier = $member->subscription_type ?? null;

// ── Page / article context ─────────────────────────────────────────────────
// $page is only set by single-article controllers (not listing pages).
$page = $page ?? null;
$isArticlePage = ($page !== null);
$pageId = $page?->id ?? null;

// [TODO-SAVED] Replace false with a real lookup, e.g.:
// $isSavedInit = $isLoggedIn && $pageId
//     ? (bool) \App\Models\PageSave::where('member_id', $member->id)
//                                   ->where('page_id',  $pageId)->exists()
//     : false;
$isSavedInit = false;

// [TODO-LIKES] Replace 0 / false with real values from $page and PageLikeRepository
$likeCount = $isArticlePage ? (int)($page->likes_count ?? 0) : 0;
$isLikedInit = false;

// ── Notification unread count ──────────────────────────────────────────────
// [TODO-ROUTES] Replace 0 with real count, e.g.:
// $unreadCount = $isLoggedIn ? ($member->unread_notifications_count ?? 0) : 0;
$unreadCount = 0;
?>

<?php /* ══════════════════════════════════════════════════════════════
   BACKDROP — used by the slide-out panel open/close animation
   ══════════════════════════════════════════════════════════════ */ ?>
<div id="mh-overlay" class="mh-overlay" aria-hidden="true"></div>

<?php /* ══════════════════════════════════════════════════════════════
   SLIDE-OUT PANEL — always present in the DOM (every page)
   ══════════════════════════════════════════════════════════ */ ?>
<aside
        id="mh-panel"
        class="mh-panel"
        role="dialog"
        aria-modal="true"
        aria-label="Member Hub"
        aria-hidden="true"
        inert
>

    <?php /* ── Header ── */ ?>
    <div class="mh-header">

        <div class="mh-identity">
            <?php if ($isLoggedIn): ?>
                <div class="mh-avatar">
                    <?php if ($memberAvatar): ?>
                        <img src="<?= htmlspecialchars($memberAvatar) ?>"
                             alt="<?= htmlspecialchars($memberDisplay) ?>">
                    <?php else: ?>
                        <?= $memberInitial ?>
                    <?php endif; ?>
                </div>
                <div class="mh-identity-text">
                    <span class="mh-name"><?= htmlspecialchars($memberDisplay) ?></span>
                    <?php if ($memberTier): ?>
                        <div class="mh-meta">
                        <span class="mh-tier-badge">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <?= htmlspecialchars(ucfirst($memberTier)) ?>
                        </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mh-identity-guest">
                    <span class="mh-name">Your Hub</span>
                    <span class="mh-sub">Sign in to unlock all features</span>
                </div>
            <?php endif; ?>

            <button class="mh-close" id="mh-close" aria-label="Close hub">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="mh-tabs" role="tablist" id="mh-tabs">
            <?php if ($isLoggedIn): ?>
                <button class="mh-tab mh-tab--active" role="tab"
                        data-tab="feed" aria-selected="true" aria-controls="mh-pane-feed">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Feed
                    <span class="mh-tab-badge" id="mh-feed-badge"
                          style="<?= $unreadCount > 0 ? '' : 'display:none' ?>">
                        <?= $unreadCount > 0 ? (int)$unreadCount : '' ?>
                    </span>
                </button>
                <button class="mh-tab" role="tab"
                        data-tab="saved" aria-selected="false" aria-controls="mh-pane-saved">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                    Saved
                    <span class="mh-tab-badge" id="mh-saved-badge" style="display:none"></span>
                </button>
            <?php endif; ?>

            <button class="mh-tab <?= !$isLoggedIn ? 'mh-tab--active' : '' ?>"
                    role="tab" data-tab="community"
                    aria-selected="<?= !$isLoggedIn ? 'true' : 'false' ?>"
                    aria-controls="mh-pane-community">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Community
            </button>

            <button class="mh-tab" role="tab"
                    data-tab="deals" aria-selected="false" aria-controls="mh-pane-deals">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                Deals
            </button>

            <button class="mh-tab" role="tab"
                    data-tab="subscriptions" aria-selected="false" aria-controls="mh-pane-subscriptions">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                Subs
            </button>

            <button class="mh-tab" role="tab"
                    data-tab="badges" aria-selected="false" aria-controls="mh-pane-badges">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="12" cy="8" r="6"/>
                    <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>
                </svg>
                Badges
            </button>

            <button class="mh-tab" role="tab"
                    data-tab="notifications" aria-selected="false" aria-controls="mh-pane-notifications">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                Alerts
                <span class="mh-tab-badge" id="mh-notif-badge" style="display:none"></span>
            </button>
        </div>
    </div>

    <?php /* ── Scrollable body ── */ ?>
    <div class="mh-body">

        <?php /* ─── FEED (logged-in only) ─── */ ?>
        <?php if ($isLoggedIn): ?>
            <div id="mh-pane-feed" class="mh-pane mh-pane--active"
                 role="tabpanel">
                <div class="mh-pane-loading" id="mh-feed-loading">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="mh-skeleton-row">
                            <div class="mh-skel mh-skel--circle"></div>
                            <div class="mh-skel-lines">
                                <div class="mh-skel mh-skel--line" style="width:80%"></div>
                                <div class="mh-skel mh-skel--line" style="width:50%"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div id="mh-feed-content" style="display:none">
                    <div class="mh-pane-header">
                        <span class="mh-section-label">Activity</span>
                        <button class="mh-text-btn" id="mh-mark-read" style="display:none">
                            Mark all read
                        </button>
                    </div>
                    <div id="mh-feed-list"></div>
                    <div class="mh-live-block">
                        <div class="mh-section-label" style="margin-bottom:10px">Live right now</div>
                        <div id="mh-live-list"></div>
                        <button class="mh-text-btn" id="mh-see-community"
                                style="width:100%;text-align:center;padding:8px 0;margin-top:6px">
                            All community →
                        </button>
                    </div>
                </div>
            </div>

            <?php /* ─── SAVED (logged-in only) ─── */ ?>
            <div id="mh-pane-saved" class="mh-pane" role="tabpanel">
                <div class="mh-pane-loading" id="mh-saved-loading">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="mh-skeleton-row">
                            <div class="mh-skel mh-skel--thumb"></div>
                            <div class="mh-skel-lines">
                                <div class="mh-skel mh-skel--line" style="width:90%"></div>
                                <div class="mh-skel mh-skel--line" style="width:60%"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div id="mh-saved-content" style="display:none">
                    <div class="mh-pane-header">
                        <span class="mh-section-label" id="mh-saved-count">Saved</span>
                    </div>
                    <div id="mh-saved-list"></div>
                    <div id="mh-saved-empty" class="mh-empty-state" style="display:none">
                        <div class="mh-empty-icon">🔖</div>
                        <div class="mh-empty-title">Nothing saved yet</div>
                        <div class="mh-empty-sub">
                            Tap the bookmark on any article to save it here
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ─── COMMUNITY ─── */ ?>
        <div id="mh-pane-community"
             class="mh-pane <?= !$isLoggedIn ? 'mh-pane--active' : '' ?>"
             role="tabpanel">
            <?php if (!$isLoggedIn): ?>
                <div class="mh-gate-block">
                    <div class="mh-gate-title">Join to participate</div>
                    <div class="mh-gate-body">
                        Vote in polls, make predictions, and challenge friends.
                    </div>
                    <a href="/<?= $siteSlug ?>/member/register"
                       class="mh-btn-primary">Create free account</a>
                </div>
            <?php endif; ?>
            <div class="mh-pane-loading" id="mh-community-loading">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="mh-skel mh-skel--card" style="margin-bottom:10px"></div>
                <?php endfor; ?>
            </div>
            <div id="mh-community-content" style="display:none">
                <div class="mh-section-label" style="padding:0 15px 11px">
                    Live &amp; active
                </div>
                <div id="mh-community-list"></div>
                <div class="mh-leaderboard">
                    <div class="mh-leaderboard-header">
                        <span class="mh-section-label">🏅 This Week</span>
                        <span class="mh-text-btn">View all</span>
                    </div>
                    <div id="mh-leaderboard-rows"></div>
                </div>
            </div>
        </div>

        <?php /* ─── DEALS ─── */ ?>
        <div id="mh-pane-deals" class="mh-pane" role="tabpanel">
            <div class="mh-pane-loading" id="mh-deals-loading">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="mh-skeleton-row" style="padding:11px 15px">
                        <div class="mh-skel mh-skel--thumb"></div>
                        <div class="mh-skel-lines">
                            <div class="mh-skel mh-skel--line" style="width:75%"></div>
                            <div class="mh-skel mh-skel--line" style="width:45%"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <div id="mh-deals-content" style="display:none">
                <div class="mh-pane-header">
                    <span class="mh-section-label">
                        <?= $isLoggedIn ? 'Recommended for you' : "Today's best deals" ?>
                    </span>
                </div>
                <div id="mh-deals-list"></div>
                <?php if (!$isLoggedIn): ?>
                    <div class="mh-gate-block" style="margin-top:14px">
                        <div class="mh-gate-title">Get personalised deals</div>
                        <div class="mh-gate-body">
                            Sign up to see deals matched to what you read.
                        </div>
                        <div style="display:flex;gap:8px">
                            <a href="/<?= $siteSlug ?>/member/register"
                               class="mh-btn-primary"
                               style="flex:1;text-align:center">Sign up free</a>
                            <button class="mh-btn-outline" style="flex:1">Browse deals</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mh-alert-row">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                             stroke="#f59e0b" stroke-width="2" stroke-linecap="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <div style="flex:1">
                            <div class="mh-alert-title">Price drop alerts</div>
                            <div class="mh-alert-sub">Notify me when saved items drop in price</div>
                        </div>
                        <div class="mh-toggle" id="mh-price-toggle"
                             role="switch" aria-checked="true" tabindex="0">
                            <div class="mh-toggle-knob"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isLoggedIn): ?>

            <?php /* ─── SUBSCRIPTIONS ─── */ ?>
            <div id="mh-pane-subscriptions" class="mh-pane" role="tabpanel">
                <div class="mh-pane-loading" id="mh-subscriptions-loading">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="mh-skeleton-row">
                            <div class="mh-skel mh-skel--thumb"></div>
                            <div class="mh-skel-lines">
                                <div class="mh-skel mh-skel--line" style="width:70%"></div>
                                <div class="mh-skel mh-skel--line" style="width:40%"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div id="mh-subscriptions-content" style="display:none">
                    <div class="mh-pane-header">
                        <span class="mh-section-label">Active Subscriptions</span>
                        <a href="/<?= $siteSlug ?>/member/subscriptions" class="mh-text-btn">Manage →</a>
                    </div>
                    <div id="mh-subscriptions-list"></div>
                    <div id="mh-subscriptions-empty" class="mh-empty-state" style="display:none">
                        <div class="mh-empty-icon">📋</div>
                        <div class="mh-empty-title">No active subscriptions</div>
                        <div class="mh-empty-sub">Upgrade to unlock premium content</div>
                        <a href="/<?= $siteSlug ?>/member/subscriptions" class="mh-btn-primary"
                           style="margin-top:12px;display:inline-block">View Plans</a>
                    </div>
                </div>
            </div>

            <?php /* ─── BADGES / ACTIVITY ─── */ ?>
            <div id="mh-pane-badges" class="mh-pane" role="tabpanel">
                <div class="mh-pane-loading" id="mh-badges-loading">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="mh-skeleton-row">
                            <div class="mh-skel mh-skel--circle"></div>
                            <div class="mh-skel-lines">
                                <div class="mh-skel mh-skel--line" style="width:60%"></div>
                                <div class="mh-skel mh-skel--line" style="width:80%"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div id="mh-badges-content" style="display:none">
                    <div class="mh-pane-header">
                        <span class="mh-section-label">Your Badges</span>
                        <a href="/<?= $siteSlug ?>/member/activity/badges" class="mh-text-btn">All →</a>
                    </div>
                    <div id="mh-badges-earned" style="padding:0 15px;display:flex;flex-wrap:wrap;gap:8px"></div>
                    <div class="mh-pane-header" style="margin-top:14px">
                        <span class="mh-section-label">Recent Activity</span>
                    </div>
                    <div id="mh-badges-activity"></div>
                    <div id="mh-badges-empty" class="mh-empty-state" style="display:none">
                        <div class="mh-empty-icon">🏆</div>
                        <div class="mh-empty-title">No badges yet</div>
                        <div class="mh-empty-sub">Read articles, comment and engage to earn your first badge</div>
                    </div>
                </div>
            </div>

            <?php /* ─── NOTIFICATIONS ─── */ ?>
            <div id="mh-pane-notifications" class="mh-pane" role="tabpanel">
                <div class="mh-pane-loading" id="mh-notifications-loading">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="mh-skeleton-row">
                            <div class="mh-skel mh-skel--circle"></div>
                            <div class="mh-skel-lines">
                                <div class="mh-skel mh-skel--line" style="width:85%"></div>
                                <div class="mh-skel mh-skel--line" style="width:50%"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div id="mh-notifications-content" style="display:none">
                    <div class="mh-pane-header">
                        <span class="mh-section-label">Notifications</span>
                        <button class="mh-text-btn" id="mh-mark-notifs-read" style="display:none">Clear all</button>
                    </div>
                    <div id="mh-notifications-list"></div>
                    <div id="mh-notifications-empty" class="mh-empty-state" style="display:none">
                        <div class="mh-empty-icon">🔔</div>
                        <div class="mh-empty-title">All caught up</div>
                        <div class="mh-empty-sub">No new notifications</div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <?php if ($isLoggedIn): ?>
        <div class="mh-footer">
            <a href="/<?= $siteSlug ?>/member/dashboard" class="mh-footer-link">
                <span>Go to full dashboard</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>
    <?php endif; ?>

</aside>

<?php /* ══════════════════════════════════════════════════════════════
   GUEST SAVE PROMPT
   Shown when a guest taps any bookmark (utility bar or card).
   JS updates #mgp-page-id before showing it so the right page ID
   is always submitted, regardless of which bookmark triggered it.
   ══════════════════════════════════════════════════════════ */ ?>
<div id="mh-guest-prompt" class="mh-guest-prompt"
     aria-hidden="true" style="display:none">
    <div class="mgp-header">
        <div>
            <div class="mgp-title">🔖 Save this article</div>
            <div class="mgp-sub">Free account · syncs across all your devices</div>
        </div>
        <button class="mh-close mgp-close" id="mgp-close" aria-label="Close">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
    <form id="mgp-form" novalidate>
        <?php /* JS overwrites this value when triggered from a card */ ?>
        <input type="hidden" name="page_id" id="mgp-page-id"
               value="<?= htmlspecialchars((string)($pageId ?? '')) ?>">
        <input type="email" name="email" id="mgp-email"
               placeholder="your@email.com" required autocomplete="email">
        <label class="mgp-checkbox">
            <input type="checkbox" name="newsletter_consent" id="mgp-consent">
            <span>Send me the weekly newsletter — best stories &amp; deals</span>
        </label>
        <button type="submit" class="mh-btn-primary" style="width:100%">
            Save &amp; create free account
        </button>
        <div class="mgp-signin-row">
            <span>Already a member? </span>
            <a href="/<?= $siteSlug ?>/member/login" class="mh-link">Sign in</a>
        </div>
    </form>
    <div id="mgp-success" style="display:none;text-align:center;padding:12px 0">
        <div style="font-size:28px;margin-bottom:8px">🎉</div>
        <div class="mgp-title">Article saved!</div>
        <div class="mgp-sub" style="margin-top:4px">
            Check your email to verify &amp; access your reading list
        </div>
    </div>
</div>


<?php /* ══════════════════════════════════════════════════════════════
   JS CONFIG — must appear before member-hub.js loads
   ══════════════════════════════════════════════════════════ */ ?>
<script>
    window.MH = {
        isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
        isArticlePage: <?= $isArticlePage ? 'true' : 'false' ?>,
        siteSlug: <?= json_encode($siteSlug) ?>,
        pageId: <?= json_encode($pageId) ?>,
        isSaved: <?= $isSavedInit ? 'true' : 'false' ?>,
        isLiked: <?= $isLikedInit ? 'true' : 'false' ?>,
        likeCount: <?= (int)$likeCount ?>,
        unread: <?= (int)$unreadCount ?>,

        // [TODO-ROUTES] Replace stub paths with your real routes.
        // Key names are used verbatim in member-hub.js — do not rename them.
        routes: {
            // GET  → [{id, page_id, title, url, category, read_time,
            //          saved_at_label, image_url}]
            savesIndex: '/<?= $siteSlug ?>/member/hub/saved',
            saveToggle: '/<?= $siteSlug ?>/member/hub/saved/toggle',
            // POST → body: {page_id}  → 201 with saved item
            saveCreate: '/<?= $siteSlug ?>/member/saves',
            // DELETE → 204
            saveDelete: '/<?= $siteSlug ?>/member/hub/saved/{id}',

            // GET  → [{id, type, user, action, target, time, read}]
            feedIndex: '/<?= $siteSlug ?>/member/hub/feed',
            // POST → marks all read → {success: true}
            feedReadAll: '/<?= $siteSlug ?>/member/feed/read-all',

            // GET  → [{id, title, sale_price, original_price, discount,
            //          merchant, sponsored, tag, image_url}]
            dealsIndex: '/<?= $siteSlug ?>/member/hub/deals',

            // GET  → [{id, type, title, votes?, entries?}]
            communityIndex: '/<?= $siteSlug ?>/member/hub/community',
            pollVote: '/<?= $siteSlug ?>/member/hub/polls/vote',
            // GET  → [{name, points}]
            leaderboard: '/<?= $siteSlug ?>/community/leaderboard',

            // POST / DELETE → {success, likes_count}
            likePage: '/<?= $siteSlug ?>/pages/{id}/like',

            // POST → {page_id, email, newsletter_consent}
            // Wire to NewsletterController@signup or a dedicated endpoint
            guestSave: '/<?= $siteSlug ?>/member/guest-save',
            subscriptionsIndex: '/<?= $siteSlug ?>/member/subscriptions/data',
            badgesIndex: '/<?= $siteSlug ?>/member/badges/data',
            notificationsIndex: '/<?= $siteSlug ?>/member/notifications',
            notificationsMarkRead: '/<?= $siteSlug ?>/member/notifications/mark-read',
        },

        csrf: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };
</script>