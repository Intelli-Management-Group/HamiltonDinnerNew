<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

abstract class BaseService
{
    protected function response(int $statusCode, array $payload): array
    {
        return [
            'statusCode' => $statusCode,
            'payload' => $payload,
        ];
    }

    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $extra = [],
        bool $includeData = true
    ): array {
        $payload = ['success' => true];

        if ($includeData) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if (!empty($extra)) {
            $payload = array_merge($payload, $extra);
        }

        return $this->response($statusCode, $payload);
    }

    protected function errorResponse(
        string $message,
        int $statusCode = 404,
        mixed $data = null,
        array $extra = [],
        bool $includeData = true
    ): array {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($includeData) {
            $payload['data'] = $data;
        }

        if (!empty($extra)) {
            $payload = array_merge($payload, $extra);
        }

        return $this->response($statusCode, $payload);
    }

    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        int $statusCode = 200,
        ?string $message = null,
        string $paginationKey = 'pagination',
        ?array $paginationData = null,
        array $extra = []
    ): array {
        $pagination = $paginationData ?? [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];

        $payload = [
            'success' => true,
            'data' => $paginator->items(),
            $paginationKey => $pagination,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if (!empty($extra)) {
            $payload = array_merge($payload, $extra);
        }

        return $this->response($statusCode, $payload);
    }

    protected function collectionResponse(
        Collection $collection,
        int $statusCode = 200,
        ?string $message = null,
        bool $includeCount = true,
        string $countKey = 'count',
        array $extra = []
    ): array {
        $payload = [
            'success' => true,
            'data' => $collection,
        ];

        if ($includeCount) {
            $payload[$countKey] = $collection->count();
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if (!empty($extra)) {
            $payload = array_merge($payload, $extra);
        }

        return $this->response($statusCode, $payload);
    }
}
