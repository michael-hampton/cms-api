@section('logic')
<?php
/**
 * Template: open-collab/onboarding/dashboard.php
 *
 * Variables injected by controller:
 *   $onboardingStatus   array  — {isComplete, completedCount, totalSteps, completedSteps[], pendingSteps[]}
 *   $site               Site
 *   $currentUser        User
 */

$pageTitle = 'Onboarding';
$activeNav = 'onboarding';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/contributor/dashboard'], ['label' => 'Onboarding']];
$pageClass = '';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')
<div style="max-width:700px;">

    <!-- Header -->
    <div style="margin-bottom:28px;animation:fadeSlideIn .3s ease;">
        <h1 style="font-family:var(--font-display);font-size:1.75rem;color:var(--navy);margin-bottom:6px;">
            Get set up as a contributor
        </h1>
        <p style="font-size:.9rem;color:var(--slate);line-height:1.6;">
            Complete the steps below before you can publish content or request payouts.
        </p>
    </div>

    <!-- Progress bar card -->
    <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .35s ease;" id="onboarding-progress-card">
        <div class="oc-card__body" style="padding:20px 24px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div>
                    <span style="font-weight:600;font-size:.95rem;color:var(--navy);" id="progress-label">
                        Loading…
                    </span>
                    <span style="font-size:.8rem;color:var(--slate);margin-left:8px;" id="progress-pct"></span>
                </div>
                <span id="progress-badge" style="display:none;" class="oc-badge oc-badge--published">
                    ✓ All done
                </span>
            </div>
            <div style="height:8px;background:var(--slate-pale);border-radius:99px;overflow:hidden;">
                <div id="progress-bar"
                     style="height:100%;width:0%;background:linear-gradient(90deg,var(--green),#34d399);border-radius:99px;transition:width .6s cubic-bezier(.4,0,.2,1);"></div>
            </div>
        </div>
    </div>

    <!-- Step checklist -->
    <div id="steps-container" style="display:flex;flex-direction:column;gap:12px;animation:fadeSlideIn .4s ease;">
        <!-- Injected by OnboardingDashboard JS class -->
    </div>

    <!-- Completion state -->
    <div id="completion-state"
         style="display:none;text-align:center;padding:48px 24px;animation:fadeSlideIn .3s ease;">
        <div style="width:60px;height:60px;background:#dcfce7;border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;">
            <svg viewBox="0 0 20 20" fill="#16a34a" width="28">
                <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clip-rule="evenodd"/>
            </svg>
        </div>
        <h2 style="font-family:var(--font-display);font-size:1.35rem;color:var(--navy);margin-bottom:8px;">
            You're ready to create content
        </h2>
        <p style="font-size:.9rem;color:var(--slate);line-height:1.6;max-width:380px;margin:0 auto 20px;">
            All onboarding requirements are complete. Head to your dashboard to start submitting content.
        </p>
        <a href="/contributor/dashboard" class="oc-btn oc-btn--primary">Go to dashboard</a>
    </div>

    <!-- Error state -->
    <div id="onboarding-error" style="display:none;">
        <div class="oc-alert oc-alert--error">
            Could not load your onboarding status. Please refresh the page.
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = localStorage.getItem('oc_token') || '';

    // ── Step definitions ────────────────────────────────────────────────────
    // Provides static metadata for each known step. Backend drives which
    // steps are applicable; this is presentation-only.
    const STEP_META = {
        profile: {
            title: 'Complete your profile',
            description: 'Add a bio so readers know who you are.',
            action: {label: 'Complete profile', href: '/contributor/settings#profile'},
            icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>`,
        },
        payment: {
            title: 'Set up payouts',
            description: 'Connect Stripe to receive your earnings.',
            action: {label: 'Set up payouts', href: '/contributor/settings#stripe-connect'},
            icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>`,
        },
        contract: {
            title: 'Sign contributor agreement',
            description: 'Review and sign the platform contributor contract.',
            action: {label: 'Review contract', href: '/contributor/onboarding/contract'},
            icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>`,
        },
        guidelines: {
            title: 'Acknowledge brand guidelines',
            description: 'Confirm you have read the editorial standards.',
            action: {label: 'Read guidelines', href: '/contributor/onboarding/guidelines'},
            icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>`,
        },
        age_verification: {
            title: 'Verify your age',
            description: 'Confirm you meet the minimum contributor age requirement.',
            action: {label: 'Verify age', href: '/contributor/settings#profile'},
            icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
        },
    };

    // ── StatusBadge ─────────────────────────────────────────────────────────
    class StatusBadge {
        static render(status) {
            const configs = {
                completed: {label: '✓ Complete', bg: '#dcfce7', color: '#15803d', border: '#bbf7d0'},
                pending: {label: 'Pending', bg: '#fef9c3', color: '#854d0e', border: '#fde68a'},
                in_progress: {label: 'In progress', bg: '#dbeafe', color: '#1e40af', border: '#bfdbfe'},
                locked: {label: 'Locked', bg: '#f1f5f9', color: '#64748b', border: '#e2e8f0'},
            };
            const c = configs[status] || configs.pending;
            return `<span style="
                display:inline-flex;align-items:center;gap:4px;
                padding:3px 10px;border-radius:99px;font-size:.75rem;font-weight:600;
                background:${c.bg};color:${c.color};border:1px solid ${c.border};
                white-space:nowrap;
            ">${c.label}</span>`;
        }
    }

    // ── StepCard ─────────────────────────────────────────────────────────────
    class StepCard {
        /**
         * @param {string} stepKey
         * @param {'pending'|'completed'|'in_progress'|'locked'} status
         * @param {string|null} reason   — backend reason string for pending steps
         * @param {number} index         — for staggered animation delay
         */
        static render(stepKey, status, reason, index) {
            const meta = STEP_META[stepKey] || {
                title: stepKey.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
                description: reason || '',
                action: null,
                icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><circle cx="10" cy="10" r="8"/></svg>`,
            };

            const isComplete = status === 'completed';
            const delay = (index * 0.05).toFixed(2);

            const actionBtn = (!isComplete && meta.action)
                ? `<a href="${meta.action.href}" class="oc-btn oc-btn--primary oc-btn--sm" style="white-space:nowrap;">
                       ${meta.action.label}
                   </a>`
                : '';

            return `
            <div class="oc-card" style="
                animation:fadeSlideIn .4s ease ${delay}s both;
                opacity:${isComplete ? '.7' : '1'};
                border-left:3px solid ${isComplete ? 'var(--green)' : 'var(--amber)'};
            ">
                <div class="oc-card__body" style="
                    display:flex;align-items:flex-start;gap:14px;padding:16px 20px;
                ">
                    <!-- Icon -->
            <div style="
                        width:36px;height:36px;border-radius:8px;flex-shrink:0;
                        background:${isComplete ? '#dcfce7' : 'var(--slate-pale)'};
                        color:${isComplete ? '#15803d' : 'var(--slate)'};
                        display:grid;place-items:center;margin-top:1px;
                    ">${meta.icon}</div>

                <!-- Content -->
            <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px;">
            <span style="font-weight:600;font-size:.9rem;color:var(--navy);">
            ${meta.title}
            </span>
            ${StatusBadge.render(status)}
            </div>
            <div style="font-size:.8rem;color:var(--slate);line-height:1.5;">
            ${reason || meta.description}
            </div>
            </div>

                <!-- Action -->
            <div style="flex-shrink:0;margin-top:2px;">
            ${actionBtn}
            </div>
            </div>
            </div>`
        ;
                }
            }

            // ── OnboardingDashboard ──────────────────────────────────────────────────
            class OnboardingDashboard {
                #site;
                #token;
                #sseSource = null;
                #state     = null;

                constructor(site, token) {
                    this.#site  = site;
                    this.#token = token;
                }

                async init() {
                    await this.#load();
                    this.#connectSse();
                }

                // ── Private ─────────────────────────────────────────────────────────

                async #load() {
                    try {
                        const res = await fetch(
            `/api/${this.#site}/open-collab/onboarding-status`
        , {
                            headers: { Authorization:
            `Bearer ${this.#token}`
        , Accept: 'application/json' },
                        });

                        if (!res.ok) throw new Error('Non-OK response');

                        const data = await res.json();
                        this.#state = this.#normalise(data);
                        this.#render();
                    } catch {
                        document.getElementById('onboarding-error').style.display = 'block';
                        document.getElementById('onboarding-progress-card').style.display = 'none';
                    }
                }

                /**
                 * Normalises the API response into the shape the dashboard expects.
                 * Handles both the legacy {pending_steps:[]} shape and the richer
                 * {isComplete, completedCount, totalSteps, completedSteps[], pendingSteps[]} shape.
                 */
                #normalise(data) {
                    // Legacy shape: just pending_steps array
                    if (Array.isArray(data.pending_steps) && data.completedSteps === undefined) {
                        const pending = data.pending_steps;
                        return {
                            isComplete:     pending.length === 0,
                            completedCount: 0,
                            totalSteps:     pending.length,
                            completedSteps: [],
                            pendingSteps:   pending,
                        };
                    }
                    return data;
                }

                #render() {
                    const s = this.#state;
                    this.#renderProgress(s);
                    this.#renderSteps(s);

                    if (s.isComplete) {
                        document.getElementById('steps-container').style.display = 'none';
                        document.getElementById('completion-state').style.display = 'block';
                    }
                }

                #renderProgress(s) {
                    const pct   = s.totalSteps > 0 ? Math.round((s.completedCount / s.totalSteps) * 100) : 0;
                    const label = document.getElementById('progress-label');
                    const pctEl = document.getElementById('progress-pct');
                    const bar   = document.getElementById('progress-bar');
                    const badge = document.getElementById('progress-badge');

                    label.textContent =
            `${s.completedCount} of ${s.totalSteps} steps complete`
        ;
                    pctEl.textContent =
            `${pct}%`
        ;
                    // Defer so CSS transition fires after paint
                    requestAnimationFrame(() => { bar.style.width =
            `${pct}%`
        ; });

                    if (s.isComplete) {
                        badge.style.display = 'inline-flex';
                        bar.style.width     = '100%';
                    }
                }

                #renderSteps(s) {
                    const container = document.getElementById('steps-container');
                    container.innerHTML = '';

                    const allSteps = [
                        ...s.completedSteps.map(key => ({ key, status: 'completed', reason: null })),
                        ...s.pendingSteps.map(p  => ({ key: p.step, status: 'pending', reason: p.reason })),
                    ];

                    if (allSteps.length === 0) return;

                    allSteps.forEach(({ key, status, reason }, i) => {
                        container.insertAdjacentHTML('beforeend', StepCard.render(key, status, reason, i));
                    });
                }

                // ── SSE ─────────────────────────────────────────────────────────────

                #connectSse() {
                    const url =
            `/api/${this.#site}/open-collab/events/stream?token=${encodeURIComponent(this.#token)}`
        ;

                    try {
                        this.#sseSource = new EventSource(url);
                    } catch {
                        return; // SSE not available — silent fallback
                    }

                    const refreshEvents = [
                        'contract.signed',
                        'guidelines.acknowledged',
                        'payment.enabled',
                        'profile.updated',
                        'age.verified',
                    ];

                    for (const event of refreshEvents) {
                        this.#sseSource.addEventListener(event, () => this.#load());
                    }

                    this.#sseSource.addEventListener('error', () => {
                        // Reconnect after 5s — browser auto-reconnects but we guard
                        // against rapid loops by checking readyState.
                        if (this.#sseSource.readyState === EventSource.CLOSED) {
                            setTimeout(() => this.#connectSse(), 5000);
                        }
                    });
                }
            }

            // ── Boot ─────────────────────────────────────────────────────────────────
            const onboardingDashboard = new OnboardingDashboard(SITE, TOKEN);
            onboardingDashboard.init();
</script>
@endsection