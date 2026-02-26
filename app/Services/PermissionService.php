<?php

namespace App\Services;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PermissionService extends BaseService
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
            $permissions = $this->permissionRepository->paginate(
                $filters, [], $pageSize, $pageNumber
            );

            return $this->paginatedResponse($permissions);
        }

        /** @var Collection $permissions */
        $permissions = $this->permissionRepository->getAll($filters);

        return $this->collectionResponse($permissions);
    }
}
