<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\OrderRepository;

class MemberOrdersController extends Controller
{
    public function __construct(private OrderRepository $orderRepository)
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();

        return $this->view('member/orders/index', [
            'member' => $member,
            'site' => SiteContext::get(),
        ]);
    }

    public function show(int $orderId)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $order = $this->orderRepository->getOrderById($orderId);

        if (!$order || $order->user_id !== $member->id) {
            return $this->redirect('/member/orders')->withErrors(['message' => 'Order not found']);
        }

        return $this->view('member/orders/show', [
            'member' => $member,
            'site' => SiteContext::get(),
            'order' => $order
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