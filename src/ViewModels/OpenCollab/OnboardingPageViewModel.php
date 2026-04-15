<?php

namespace App\ViewModels\OpenCollab;

use App\Models\Site;

/**
 * Encapsulates all display-layer decisions for the contributor onboarding page.
 *
 * The view receives an instance of this class and calls only simple accessors —
 * no filtering, searching, or step-index arithmetic belongs in the template.
 *
 * Construction contract:
 *   - $pendingSteps  is the structured array returned by ContributorOnboardingService::pendingSteps()
 *                    each entry: ['step' => string, 'reason' => string, 'meta' => array]
 *   - $site          is the Site model for the current request
 *
 * Steps are always presented in canonical order: profile → payment → contract → guidelines.
 * Steps that the site does not require are excluded entirely from the applicable set.
 */
final class OnboardingPageViewModel
{
    /** Canonical display order — do not reorder. */
    private const STEP_ORDER = ['profile', 'payment', 'contract', 'guidelines'];

    private const STEP_LABELS = [
        'profile' => 'Profile',
        'payment' => 'Payment',
        'contract' => 'Contract',
        'guidelines' => 'Guidelines',
    ];

    private const STEP_TITLES = [
        'profile' => 'Set up your profile',
        'payment' => 'Payment details',
        'contract' => 'Sign the contract',
        'guidelines' => 'Brand guidelines',
    ];

    private const STEP_ICONS = [
        'profile' => '<path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>',
        'payment' => '<path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>',
        'contract' => '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>',
        'guidelines' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>',
    ];

    /** Steps that this site requires, in canonical order. */
    private readonly array $applicableSteps;

    /** Pending step names as a flat set for O(1) lookup. */
    private readonly array $pendingStepNames;

    /** Pending steps indexed by step name for O(1) reason/meta lookup. */
    private readonly array $pendingStepMap;

    /** The step the user should action right now. */
    private readonly string $currentStepName;

    public function __construct(
        private readonly array $pendingSteps,
        private readonly Site  $site,
    )
    {
        $this->applicableSteps = $this->resolveApplicableSteps();
        $this->pendingStepNames = array_column($pendingSteps, 'step');
        $this->pendingStepMap = array_column($pendingSteps, null, 'step');
        $this->currentStepName = $pendingSteps[0]['step'] ?? '';
    }

    // ── Step list ─────────────────────────────────────────────────────────────

    /**
     * Filters the canonical step list down to what this site actually requires.
     * Profile is always included; the rest honour their respective site flags.
     *
     * @return string[]
     */
    private function resolveApplicableSteps(): array
    {
        return array_values(array_filter(
            self::STEP_ORDER,
            fn(string $step) => match ($step) {
                'payment' => (bool)($this->site->require_payment_setup ?? true),
                'contract' => (bool)($this->site->require_contracts ?? true),
                'guidelines' => (bool)($this->site->require_guidelines_ack ?? true),
                default => true, // profile is always required
            },
        ));
    }

    /**
     * Steps the site requires, in display order.
     * Each entry is a StepViewModel — the template iterates this and calls accessors.
     *
     * @return StepViewModel[]
     */
    public function steps(): array
    {
        return array_map(
            fn(string $step, int $index) => new StepViewModel(
                name: $step,
                label: self::STEP_LABELS[$step],
                oneBasedIndex: $index + 1,
                totalSteps: count($this->applicableSteps),
                isDone: !in_array($step, $this->pendingStepNames, true),
                isActive: $step === $this->currentStepName,
                icon: self::STEP_ICONS[$step],
            ),
            $this->applicableSteps,
            array_keys($this->applicableSteps),
        );
    }

    // ── Current step ─────────────────────────────────────────────────────────

    public function totalSteps(): int
    {
        return count($this->applicableSteps);
    }

    public function currentStepName(): string
    {
        return $this->currentStepName;
    }

    public function currentStepTitle(): string
    {
        return self::STEP_TITLES[$this->currentStepName] ?? '';
    }

    public function currentStepIcon(): string
    {
        return self::STEP_ICONS[$this->currentStepName] ?? '';
    }

    /**
     * 1-based position of the current step within the applicable set.
     */
    public function currentStepNumber(): int
    {
        $index = array_search($this->currentStepName, $this->applicableSteps, true);

        return $index !== false ? $index + 1 : 1;
    }

    /**
     * The reason text for the current step, sourced from the service's structured data.
     */
    public function currentStepReason(): ?string
    {
        return $this->pendingStepMap[$this->currentStepName]['reason'] ?? null;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Arbitrary meta for the current step (e.g. contract_id, required_version).
     *
     * @return array<string, mixed>
     */
    public function currentStepMeta(): array
    {
        return $this->pendingStepMap[$this->currentStepName]['meta'] ?? [];
    }
}