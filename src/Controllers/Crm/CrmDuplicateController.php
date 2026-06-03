<?php

declare(strict_types=1);

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Exceptions\Members\MergeConflictException;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\CrmMemberRepository;
use App\Services\Members\MemberDuplicateDetectionService;
use App\Services\Members\MemberMergeService;

/**
 * Routes (add to your routes file):
 *
 *   GET    crm/members/{memberId}/duplicates
 *   GET    crm/members/{memberId}/duplicates/{duplicateMemberId}/compare
 *   POST   crm/members/{memberId}/duplicates/{duplicateMemberId}/merge
 *   GET    crm/members/{memberId}/duplicates/{duplicateMemberId}/conflicts
 */
class CrmDuplicateController extends Controller
{
    public function __construct(
        private readonly CrmMemberRepository           $crmMemberRepository,
        private readonly MemberDuplicateDetectionService $duplicateDetectionService,
        private readonly MemberMergeService            $mergeService,
    ) {
        parent::__construct();
    }

    // ─── GET crm/members/{memberId}/duplicates ────────────────────────────────

    /**
     * Returns all possible duplicates for a member.
     * Used by the CRM banner and the scan-for-duplicates action.
     */
    public function index(int $memberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->errorResponse('Member not found.', 404);
        }

        $matches = $this->duplicateDetectionService->detectForMember($member);

        return $this->resourceResponse([
            'duplicates' => $matches->map(fn($m) => $m->toArray())->values()->all(),
        ]);
    }

    // ─── GET crm/members/{memberId}/duplicates/{duplicateMemberId}/compare ────

    /**
     * Returns side-by-side comparison data for the member and one possible
     * duplicate. Used by the comparison modal (Ticket 3).
     */
    public function compare(int $memberId, int $duplicateMemberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $siteId = SiteContext::getId();

        $member    = $this->crmMemberRepository->findForSite($memberId, $siteId);
        $duplicate = $this->crmMemberRepository->findForSite($duplicateMemberId, $siteId);

        if (!$member || !$duplicate) {
            return $this->errorResponse('Member not found.', 404);
        }

        $fields = $this->buildComparisonFields($member, $duplicate);

        return $this->resourceResponse([
            'current_member'   => $this->memberSummary($member),
            'duplicate_member' => $this->memberSummary($duplicate),
            'comparison'       => $fields,
        ]);
    }

    // ─── GET crm/members/{memberId}/duplicates/{duplicateMemberId}/conflicts ──

    /**
     * Returns the conflict list for a proposed merge without committing it.
     * Used by the merge flow to surface warnings before confirmation.
     */
    public function conflicts(int $memberId, int $duplicateMemberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $siteId = SiteContext::getId();

        $primary   = $this->crmMemberRepository->findForSite($memberId, $siteId);
        $secondary = $this->crmMemberRepository->findForSite($duplicateMemberId, $siteId);

        if (!$primary || !$secondary) {
            return $this->errorResponse('Member not found.', 404);
        }

        $conflicts = $this->mergeService->detectConflicts($primary, $secondary);

        return $this->resourceResponse([
            'can_merge' => empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    // ─── POST crm/members/{memberId}/duplicates/{duplicateMemberId}/merge ─────

    /**
     * Executes the merge. The caller must specify which member is primary.
     *
     * Body: { "primary_member_id": int, "reason": string? }
     *
     * primary_member_id must be either memberId or duplicateMemberId —
     * this forces the caller to make an explicit choice.
     */
    public function merge(Request $request, int $memberId, int $duplicateMemberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $siteId = SiteContext::getId();

        $memberA = $this->crmMemberRepository->findForSite($memberId, $siteId);
        $memberB = $this->crmMemberRepository->findForSite($duplicateMemberId, $siteId);

        if (!$memberA || !$memberB) {
            return $this->errorResponse('Member not found.', 404);
        }

        $primaryMemberId = (int) $request->input('primary_member_id');

        if (!in_array($primaryMemberId, [$memberId, $duplicateMemberId], true)) {
            return $this->errorResponse(
                'primary_member_id must be one of the two members involved in the merge.',
                422
            );
        }

        $secondaryMemberId = $primaryMemberId === $memberId ? $duplicateMemberId : $memberId;
        $reason            = $request->input('reason') ?: null;

        try {
            $this->mergeService->merge(
                primaryMemberId:   $primaryMemberId,
                secondaryMemberId: $secondaryMemberId,
                adminId:           (int) Auth::id(),
                options:           ['reason' => $reason],
            );
        } catch (MergeConflictException $e) {
            return $this->resourceResponse([
                'success'   => false,
                'message'   => 'Merge blocked due to conflicts.',
                'conflicts' => $e->getConflicts(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Merge failed. Please try again.', 500);
        }

        return $this->resourceResponse([
            'success'            => true,
            'message'            => 'Members merged successfully.',
            'primary_member_id'  => $primaryMemberId,
            'merged_member_id'   => $secondaryMemberId,
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function memberSummary(mixed $member): array
    {
        return [
            'id'                  => $member->id,
            'first_name'          => $member->first_name,
            'last_name'           => $member->last_name,
            'email'               => $member->email,
            'phone'               => $member->phone,
            'stripe_customer_id'  => $member->stripe_customer_id,
            'is_active'           => $member->is_active,
            'email_verified_at'   => $member->email_verified_at?->format('Y-m-d H:i:s'),
            'created_at'          => $member->created_at?->format('Y-m-d H:i:s'),
            'subscription_count'  => \App\Models\Subscription::where('member_id', $member->id)->count(),
            'order_count'         => \App\Models\Order::where('user_id', $member->id)->count(),
            'payment_count'       => \App\Models\Payment::where('member_id', $member->id)->count(),
            'last_activity_at'    => \App\Models\Payment::where('member_id', $member->id)
                ->orderByDesc('created_at')
                ->value('created_at'),
        ];
    }

    /**
     * Build field-level comparison. Each entry describes whether the two
     * members match on that field, and what each value is.
     */
    private function buildComparisonFields(mixed $member, mixed $duplicate): array
    {
        $fields = [
            'email'              => [$member->email,              $duplicate->email],
            'phone'              => [$member->phone,              $duplicate->phone],
            'stripe_customer_id' => [$member->stripe_customer_id, $duplicate->stripe_customer_id],
            'first_name'         => [$member->first_name,         $duplicate->first_name],
            'last_name'          => [$member->last_name,          $duplicate->last_name],
        ];

        $comparison = [];

        foreach ($fields as $field => [$current, $dup]) {
            $comparison[$field] = [
                'matches'   => $this->valuesMatch($current, $dup),
                'current'   => $current,
                'duplicate' => $dup,
            ];
        }

        return $comparison;
    }

    private function valuesMatch(mixed $a, mixed $b): bool
    {
        if (empty($a) && empty($b)) {
            return true;
        }

        return strtolower(trim((string) $a)) === strtolower(trim((string) $b));
    }
}