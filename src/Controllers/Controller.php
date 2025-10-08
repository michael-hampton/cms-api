<?php

namespace App\Controllers;

use App\Framework\Container;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Response;
use App\Framework\View\ViewRenderer;
use App\Search\PaginatedResult;

abstract class Controller
{
    protected $viewRenderer;

    public function __construct()
    {
        $this->viewRenderer = Container::getInstance()->resolve(ViewRenderer::class);
    }

    // View methods return Response objects
    protected function view(string $template, array $data = []): Response
    {
        $html = $this->viewRenderer->render($template, $data);

        return Response::html($html);
    }

    // API methods return arrays (router will convert to JSON Response)
    protected function jsonResponse(array $data, int $statusCode = 200): JsonResponse
    {

        $data = [
            'data' => $this->serializeModels($data),
            'status' => $statusCode,
            'success' => $data['success'] ?? true,
            'timestamp' => date('c')
        ];

        return JsonResponse::json($data, $statusCode);
    }

    protected function resourceResponse(array $data, int $statusCode = 200): JsonResponse
    {
        return JsonResponse::json(array_merge(['success' => true], $data), $statusCode);
    }

    protected function searchResponse(PaginatedResult $result, int $statusCode = 200): JsonResponse
    {
        $payload = [
            'items' => $this->serializeModels($result->getData()),
            'pagination' => [
                'total' => $result->getTotal(),
                'per_page' => $result->getPerPage(),
                'current_page' => $result->getPage(),
                'total_pages' => $result->getTotalPages(),
                'has_more' => $result->hasMore()
            ],
            'status' => $statusCode,
            'success' => true,
            'timestamp' => date('c')
        ];

        return JsonResponse::json($payload, $statusCode);
    }

    protected function serializeModels(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->serializeModels($value);
            } elseif ($value instanceof \App\Models\Model) {
                $data[$key] = $value->toArray();
            } elseif ($value instanceof \App\Framework\Support\Collection) {
                // If using a collection class, convert it to array as well
                $data[$key] = $value->map(fn($item) => $item instanceof \App\Models\Model ? $item->toArray() : $item)->all();
            }
        }

        return $data;
    }

    protected function errorResponse(string $message, int $statusCode = 400, array $errors = []): JsonResponse
    {
        $data = [
            'error' => $message,
            'errors' => $errors,
            'status' => $statusCode,
            'timestamp' => date('c')
        ];

        return JsonResponse::json($data, $statusCode);
    }

    protected function successResponse(string $message = 'Success', array $data = []): JsonResponse
    {
        $data = [
            'message' => $message,
            'data' => $data,
            'status' => 200,
            'timestamp' => date('c')
        ];

        return JsonResponse::json($data);
    }

    // Non-API methods return Response objects
    protected function redirectResponse(string $url): Response
    {
        return Response::redirect($url);
    }
}