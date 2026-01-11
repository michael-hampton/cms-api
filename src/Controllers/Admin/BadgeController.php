<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Badge;
use App\Repositories\Members\BadgeRepository;

class BadgeController extends Controller
{
    public function __construct(
        private BadgeRepository $badgeRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $siteId = SiteContext::getId();
        $badges = $this->badgeRepository->where('site_id', $siteId)
            ->orderBy('category')
            ->orderBy('tier')
            ->orderBy('name')
            ->get();

        return $this->view('admin/badges/index', [
            'badges' => $badges
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['site_id'] = SiteContext::getId();

        // Parse criteria JSON
        if (isset($data['criteria'])) {
            $data['criteria'] = json_decode($data['criteria'], true);
        }

        $badge = Badge::create($data);

        return $this->redirect('/admin/badges')
            ->with('success', 'Badge created successfully');
    }

    public function create()
    {
        return $this->view('admin/badges/create');
    }

    public function edit(int $id)
    {
        $badge = $this->badgeRepository->find($id);

        if (!$badge || $badge->site_id !== SiteContext::getId()) {
            return $this->redirect('/admin/badges')
                ->withErrors(['message' => 'Badge not found']);
        }

        return $this->view('admin/badges/edit', [
            'badge' => $badge
        ]);
    }

    public function update(int $id, Request $request)
    {
        $badge = $this->badgeRepository->find($id);

        if (!$badge || $badge->site_id !== SiteContext::getId()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Badge not found'], 404);
        }

        $data = $request->all();

        // Parse criteria JSON
        if (isset($data['criteria'])) {
            $data['criteria'] = json_decode($data['criteria'], true);
        }

        $this->badgeRepository->update($id, $data);

        return $this->redirect('/admin/badges')
            ->with('success', 'Badge updated successfully');
    }

    public function destroy(int $id)
    {
        $badge = $this->badgeRepository->find($id);

        if (!$badge || $badge->site_id !== SiteContext::getId()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Badge not found'], 404);
        }

        $this->badgeRepository->delete($id);

        return $this->jsonResponse(['success' => true, 'message' => 'Badge deleted successfully']);
    }
}