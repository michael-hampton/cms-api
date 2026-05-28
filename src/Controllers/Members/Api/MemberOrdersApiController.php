<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Billing\OrderRepository;

class MemberOrdersApiController extends Controller
{
    public function __construct(private OrderRepository $orderRepository)
    {
        parent::__construct();
    }

    public function index()
    {
        $member = MemberAuth::getMember();
        $orders = $this->orderRepository->getByUser($member->id)->map(function ($order) {
            $data = $order->toArray();
            $data['created_at'] = $order->created_at?->format('Y-m-d H:i:s');
            $data['can_be_cancelled'] = $order->canBeCancelled();

            return $data;
        });

        return $this->resourceResponse([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function show(int $id)
    {
        $order = $this->orderRepository->getOrderById($id);

        return $this->resourceResponse([
            'success' => true,
            'data' => array_merge(
                $order->toArray(),
                [
                    'billing_address' => $order->billingAddress?->toArray(),
                    'shipping_address' => $order->shippingAddress?->toArray(),
                    'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                    'can_be_cancelled' => $order->canBeCancelled()
                ]
            ),
        ]);
    }

    public function cancel(Request $request, int $orderId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $order = $this->orderRepository->find($orderId);

        if (!$order || $order->user_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (!$order->canBeCancelled()) {
            return $this->jsonResponse(['success' => false, 'message' => 'This order cannot be cancelled'], 400);
        }

        // Update order status to cancelled
        $this->orderRepository->update($orderId, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }
}
