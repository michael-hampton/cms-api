<?php

namespace App\Controllers;

use App\Framework\Container;
use App\Framework\Http\Exceptions\HttpException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\RedirectResponse;
use App\Framework\Http\Response;
use App\Framework\Session\Session;
use App\Framework\View\ViewRenderer;
use App\Search\PaginatedResult;

abstract class Controller
{
    protected $viewRenderer;

    protected string $viewPath = __DIR__ . '/../views/';

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
            'success' => false,
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
            'success' => true,
            'timestamp' => date('c')
        ];

        return JsonResponse::json($data);
    }

    // Non-API methods return Response objects
    protected function redirectResponse(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /**
     * Checks if a view file exists.
     *
     * @param string $viewName  View name like 'users/show' or 'home'
     * @param string $extension Optional file extension (default: 'php')
     */
    protected function viewExists(string $viewName, string $extension = 'php'): bool
    {
        // Normalize view name to path (e.g. 'users/show' → 'users/show.php')
        $path = rtrim($this->viewPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $viewName)
            . '.' . ltrim($extension, '.');

        return file_exists($path) && is_readable($path);
    }

    /**
     * Create a redirect response
     */
    protected function redirect(string $url): RedirectResponse
    {
        return new RedirectResponse($url);
    }

    /**
     * Redirect back to the previous URL
     */
    protected function back(): RedirectResponse
    {
        $previousUrl = Session::previousUrl() ?? '/';

        return new RedirectResponse($previousUrl);
    }

    /**
     * Return a 404 Not Found response.
     *
     * @param string|null $message Optional error message
     * @param bool $json Whether to return a JSON response (default: false)
     */
    protected function notFound(string $message = 'Resource not found', bool $json = false): Response|JsonResponse
    {
        if ($json) {
            return $this->errorResponse($message, 404);
        }

        // Check if a 404 view exists
        $viewName = 'errors/404';
        if ($this->viewExists($viewName)) {
            return $this->view($viewName, ['message' => $message]);
        }

        // Fallback plain HTML if no view exists
        $html = "<h1>404 Not Found</h1><p>{$message}</p>";
        return Response::html($html, 404);
    }

    protected function abort(
        int    $statusCode,
        string $message = '',
        array  $headers = []
    ): never
    {
        throw new HttpException($statusCode, $message, $headers);
    }
}