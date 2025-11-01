<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\MemberRepository;

class MemberController extends Controller
{
    public function __construct(private readonly MemberRepository $memberRepository)
    {
    }

    public function search(Request $request, string $siteName)
    {
        try {
            $site = SiteContext::get();

            $search = $request->get('search', '');
            $perPage = min($request->get('per_page', 10), 50);

            $members = $this->memberRepository->searchMembers($search, $perPage);

            return $this->resourceResponse([
                'success' => true,
                'items' => $members,
                'total' => $members->count()
            ]);

        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to search members'
            ], 500);
        }
    }
}