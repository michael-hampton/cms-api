<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Models\Site;
use App\Repositories\OrderRepository;
use App\Requests\BulkDeleteRequest;
use App\Requests\BulkUpdateOrderStatus;
use App\Requests\BulkUpdateVoucherStatus;
use App\Requests\CreateOrderRequest;
use App\Requests\UpdateOrderItemsRequest;
use App\Requests\UpdateOrderRequest;
use App\Resources\OrderResource;
use App\Search\SearchCriteriaParser;
use App\Services\OrderService;
use Exception;

class OrderController extends Controller
{
    public function __construct(
        private OrderService    $orderService,
        private OrderRepository $orderRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->orderRepository->search($criteria);

            $formattedData = $result->getData();

            $formattedData = array_map(function ($order) {
                return [
                    ...$order,
                    'customer_name' => $order['user']?->first_name . ' ' . $order['user']?->last_name,
                    'customer_email' => $order['user']?->email
                ];
            }, $formattedData);

            $result->setData($formattedData);

            $collection = new PaginatedResourceCollection($result, OrderResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateOrderRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();
            $siteId = Site::resolveSite($siteName);

            $items = $data['items'];
            unset($data['items']);

            $order = $this->orderService->createOrder($data, $items, $siteId);

            return $this->jsonResponse(['order' => $order->toArray()], 201);

        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            if (is_numeric($id)) {
                $order = $this->orderService->getOrderById((int)$id);
            } else {
                $order = $this->orderService->getOrderByNumber($id);
            }

            if (!$order) {
                return $this->errorResponse('Order not found', 404);
            }

            return $this->jsonResponse(['order' => OrderResource::make($order)->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateOrderRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();
            $order = $this->orderService->updateOrder($id, $data);

            return $this->jsonResponse(['order' => $order->toArray()]);

        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateItems(int $id, UpdateOrderItemsRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();
            $order = $this->orderService->updateOrderItems($id, $data['items']);

            return $this->jsonResponse(['order' => $order->toArray()]);

        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $result = $this->orderService->deleteOrder($id);

            if (!$result) {
                return $this->errorResponse('Order not found', 404);
            }

            return $this->successResponse('Order deleted successfully');

        } catch (Exception $e) {
            return $this->errorResponse('Order not found', 404);
        }
    }

    public function cancel(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $reason = $request->get('reason');
            $order = $this->orderService->cancelOrder($id, $reason);

            return $this->jsonResponse(['order' => $order->toArray()]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function complete(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $order = $this->orderService->completeOrder($id);

            return $this->jsonResponse(['order' => $order->toArray()]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function refund(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $reason = $request->get('reason');
            $order = $this->orderService->refundOrder($id, $reason);

            return $this->jsonResponse(['order' => $order->toArray()]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function duplicate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $duplicatedOrder = $this->orderService->duplicateOrder($id);

            return $this->jsonResponse($duplicatedOrder->toArray(), 201);

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                return $this->errorResponse($e->getMessage(), 404);
            }

            return $this->errorResponse('Failed to duplicate order: ' . $e->getMessage(), 500);
        }
    }

    public function byStatus(Request $request, string $siteName): JsonResponse
    {
        try {
            $status = $request->get('status');

            if (!$status) {
                return $this->errorResponse('Status parameter is required', 400);
            }

            $orders = $this->orderService->getOrdersByStatus($status);

            return $this->jsonResponse(['orders' => $orders->toArray()]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function byUser(int $userId, Request $request, string $siteName): JsonResponse
    {
        try {
            $limit = $request->get('limit');
            $orders = $this->orderService->getOrdersByUser($userId, $limit);

            return $this->jsonResponse(['orders' => $orders->toArray()]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function revenue(Request $request, string $siteName): JsonResponse
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $revenue = $this->orderService->getTotalRevenue($startDate, $endDate);

            return $this->jsonResponse([
                'revenue' => $revenue,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateStatus(BulkUpdateOrderStatus $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $result = $this->orderService->bulkUpdateStatus($data['ids'], $data['status']);

            return $this->resourceResponse([
                'message' => "Bulk status update completed. Updated: " . count($result['updated']) . ", Failed: " . count($result['failed']),
                'result' => $result
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk update failed: ' . $e->getMessage()], 500);
        }
    }
}