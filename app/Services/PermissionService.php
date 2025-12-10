<?php

namespace App\Services;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PermissionService
{
    public function __construct(
        private PermissionRepositoryInterface $permissionRepository
    )
    {}
    
    public function list(array $params): array
    {
        $filters = [
            'search' => $params['search'] ?? null,
        ];

        $use_pagination = isset($params['pagesize']) || isset($params['pagenumber']);

        if ($use_pagination) {
            $pageSize = (int)($params['pagesize'] ?? 15);
            $pageNumber = (int)($params['pagenumber'] ?? 1);

            /** @var LengthAwarePaginator $permissions */
            $permissions = $this->permissionRepository->paginateWithRoles(
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload' => [
                    'success' => true,
                    'data' => $permissions->items(),
                    'pagination' => [
                        'total' => $permissions->total(),
                        'per_page' => $permissions->perPage(),
                        'current_page' => $permissions->currentPage(),
                        'last_page' => $permissions->lastPage(),
                        'from' => $permissions->firstItem(),
                        'to' => $permissions->lastItem()
                    ]
                ]
            ];
        }

        /** @var Collection $permissions */
        $permissions = $this->permissionRepository->getAllWithRoles($filters);
        
        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data' => $permissions,
                'count' => $permissions->count()
            ]
        ];
    }
}