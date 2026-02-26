<?php

namespace App\Services;

use App\Models\MenuDetail;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MenuDetailService extends BaseService
{
    public function __construct(
        private MenuDetailRepositoryInterface $menuDetails
    ) {}

    public function findMenuById(int $id): array
    {
        $menu = $this->menuDetails->findById($id);

        if (!$menu) {
            return $this->errorResponse(
                message: 'MenuDetail not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($menu);
    }

    public function list(array $params): array
    {
        $filters = [
            // Add filter mappings if needed
        ];

        $usePagination = isset($params['pagesize']) || isset($params['pagenumber']);

        if ($usePagination) {
            $pageSize = (int)($params['pagesize'] ?? 15);
            $pageNumber = (int)($params['pagenumber'] ?? 1);

            /** @var LengthAwarePaginator $menus */
            $menus = $this->menuDetails->paginate(
                $filters, [], $pageSize, $pageNumber
            );

            return $this->paginatedResponse($menus);
        }

        /** @var Collection $menus */
        $menus = $this->menuDetails->getAll($filters);

        return $this->collectionResponse($menus);
    }

    public function store(array $data): array
    {
        /** @var MenuDetail|null $existing */
        $existing = $this->menuDetails->findSoftDeletedByDate($data['date']);

        if ($existing && $existing->trashed()) {
            $existing->restore();

            $existing->items = $data['items'] ?? $existing->items;
            $existing->is_allday = $data['is_allday'] ?? $existing->is_allday;
            $this->menuDetails->save($existing);

            $existing->refresh();

            return $this->successResponse(
                data: $existing,
                message: 'MenuDetail restored and updated successfully.'
            );
        }

        /** @var MenuDetail $menu */
        $menu = $this->menuDetails->create($data);

        return $this->successResponse(
            data: $menu,
            message: 'MenuDetail created successfully.',
            statusCode: 201
        );
    }

    public function update(MenuDetail $menu, array $data): array
    {
        $menu->fill($data);
        $this->menuDetails->save($menu);

        return $this->successResponse(
            data: $menu,
            message: 'MenuDetail updated successfully.'
        );
    }

    public function destroy(MenuDetail $menu): array
    {
        $this->menuDetails->delete($menu);

        return $this->successResponse(
            message: 'MenuDetail deleted successfully.',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->menuDetails->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} MenuDetails deleted successfully.",
            statusCode: 200,
            includeData: false
        );
    }
}