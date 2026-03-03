<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Exceptions\CompetitionAlreadyEnteredException;
use App\Exceptions\CompetitionEntryNotUnlockedException;
use App\Exceptions\CompetitionNotAvailableException;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\Quiz\CompetitionRepository;
use App\Services\Quiz\CompetitionService;

class CompetitionController extends Controller
{
    public function __construct(
        private readonly CompetitionService    $competitionService,
        private readonly CompetitionRepository $competitionRepository,
    )
    {
        parent::__construct();
    }

    // -------------------------------------------------------------------------
    // GET /competitions
    // -------------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $siteId = SiteContext::getId();
        $member = MemberAuth::getMember();

        $competitions = $this->competitionService->getCompetitionsForSite($siteId, $member);
        $featured = $this->competitionService->getFeatured($siteId, $member);

        return $this->view('competitions.index', [
            'competitions' => $competitions,
            'featured' => $featured,
            'member' => $member,
            'hasActive' => collect($competitions)->contains('status', 'active'),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /competitions/{slug}
    // -------------------------------------------------------------------------

    public function show(Request $request, string $slug): Response
    {
        $siteId = SiteContext::getId();
        $member = MemberAuth::getMember();
        $competition = $this->competitionService->getCompetition($siteId, $slug, $member);

        if (!$competition) {
            return $this->notFound();
        }

        return $this->view('competitions.show', [
            'competition' => $competition,
            'member' => $member,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /competitions/{id}/enter
    // -------------------------------------------------------------------------

    public function enter(Request $request, int $id): JsonResponse
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'unauthenticated', 'redirect' => '/join'], 401);
        }

        try {
            $result = $this->competitionService->enter(
                $id,
                $member,
                referredByMemberId: $request->input('referred_by_member_id'),
            );

            return $this->jsonResponse([
                'success' => true,
                'entry_count' => $result['entry_count'],
                'message' => "You're entered into the draw! Good luck.",
            ]);
        } catch (CompetitionAlreadyEnteredException) {
            return $this->jsonResponse(['error' => 'already_entered', 'message' => 'You have already entered this competition.'], 409);
        } catch (CompetitionEntryNotUnlockedException) {
            return $this->jsonResponse(['error' => 'not_unlocked', 'message' => "You haven't unlocked entry to this competition yet."], 403);
        } catch (CompetitionNotAvailableException) {
            return $this->jsonResponse(['error' => 'not_available', 'message' => 'This competition is not currently available.'], 404);
        }
    }

    // -------------------------------------------------------------------------
    // POST /competitions/{id}/notify
    // -------------------------------------------------------------------------

    public function notify(Request $request, int $id): JsonResponse
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'unauthenticated', 'redirect' => '/join'], 401);
        }

        try {
            $this->competitionService->requestNotification($id, $member);

            return $this->jsonResponse([
                'success' => true,
                'message' => "We'll notify you when this competition opens.",
            ]);
        } catch (CompetitionNotAvailableException) {
            return $this->jsonResponse(['error' => 'not_found', 'message' => 'Competition not found.'], 404);
        }
    }

    // -------------------------------------------------------------------------
    // GET /competitions/{id}/progress
    // -------------------------------------------------------------------------

    public function progress(Request $request, int $id): JsonResponse
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['error' => 'unauthenticated'], 401);
        }

        $competition = $this->competitionRepository->find($id);

        if (!$competition) {
            return $this->jsonResponse(['error' => 'not_found'], 404);
        }

        $progress = $this->competitionService->getEntryProgress($competition, $member);

        return $this->jsonResponse(['progress' => $progress]);
    }
}