<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Models\ProductBadge;
use App\Repositories\Product\ProductBadgeRepository;

class ProductBadgeController extends Controller
{
    public function __construct(
        private ProductBadgeRepository $productBadgeRepository
    )
    {
        parent::__construct();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'badge_type' => 'required|string',
            'label' => 'required|string',
            'color' => 'required|string',
            'icon' => 'nullable|string',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'sort_order' => 'integer',
            'is_active' => 'boolean'
        ]);

        $badge = ProductBadge::create($data);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Product badge added successfully',
            'badge' => $badge
        ]);
    }

    public function update(int $id, Request $request)
    {
        $badge = $this->productBadgeRepository->find($id);

        if (!$badge) {
            return $this->jsonResponse(['success' => false, 'message' => 'Badge not found'], 404);
        }

        $data = $request->validate([
            'badge_type' => 'required|string',
            'label' => 'required|string',
            'color' => 'required|string',
            'icon' => 'nullable|string',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'sort_order' => 'integer',
            'is_active' => 'boolean'
        ]);

        $this->productBadgeRepository->update($id, $data);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Product badge updated successfully'
        ]);
    }

    public function destroy(int $id)
    {
        $badge = $this->productBadgeRepository->find($id);

        if (!$badge) {
            return $this->jsonResponse(['success' => false, 'message' => 'Badge not found'], 404);
        }

        $this->productBadgeRepository->delete($id);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Product badge deleted successfully'
        ]);
    }
}